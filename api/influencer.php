<?php
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);

header('Content-Type: application/json');

include_once __DIR__ . '/logger.php';

custom_log("--- influencer.php START ---", 'DEBUG');

$excludedIds = [];
$conn = null;

include_once __DIR__ . '/../../db_config.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    $errorMsg = "influencer.php: DB connection failed. Path checked: " . realpath(__DIR__ . '/../../db_config.php') . (isset($conn) && $conn->connect_error ? " Error: " . $conn->connect_error : " Conn variable not set or include failed.");
    custom_log($errorMsg, 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: Could not connect to database.']);
    exit;
} else {
    custom_log("influencer.php: DB Connection successful.", 'INFO');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = file_get_contents('php://input');
    custom_log("influencer.php: Received Payload: " . $payload, 'DEBUG');
    $input = json_decode($payload, true);
    if (isset($input['exclude_ids']) && is_array($input['exclude_ids'])) {
        $excludedIds = array_filter($input['exclude_ids'], 'is_numeric');
        $excludedIds = array_map('intval', $excludedIds);
        custom_log("influencer.php: Parsed exclude_ids: " . print_r($excludedIds, true), 'DEBUG');
    } else {
        custom_log("influencer.php: exclude_ids not found or not an array in payload.", 'DEBUG');
    }
} else {
    custom_log("influencer.php: Request method was not POST.", 'WARN');
}


$sql = "SELECT id, name, image_url FROM influencers WHERE is_active = TRUE";
$params = [];
$types = "";

if (!empty($excludedIds)) {
    $placeholders = implode(',', array_fill(0, count($excludedIds), '?'));
    $sql .= " AND id NOT IN ($placeholders)";
    $params = $excludedIds;
    $types = str_repeat('i', count($excludedIds));
    custom_log("influencer.php: Adding NOT IN clause. Params count: " . count($params), 'DEBUG');
} else {
    custom_log("influencer.php: exclude_ids is empty, no NOT IN clause added.", 'DEBUG');
}

$sql .= " ORDER BY RAND() LIMIT 1";

custom_log("influencer.php: Final SQL Query: " . $sql, 'DEBUG');

$stmt = null;
try {
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        custom_log("influencer.php: Prepare failed: (" . $conn->errno . ") " . $conn->error . " SQL: " . $sql, 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare statement failed.']);
        $conn->close();
        exit;
    }

    if (!empty($params)) {
         if (!$stmt->bind_param($types, ...$params)) {
             custom_log("influencer.php: Bind Param failed: (" . $stmt->errno . ") " . $stmt->error, 'ERROR');
             http_response_code(500);
             echo json_encode(['error' => 'Database bind param failed.']);
             $stmt->close();
             $conn->close();
             exit;
         }
    }

    if (!$stmt->execute()) {
         custom_log("influencer.php: Execute failed: (" . $stmt->errno . ") " . $stmt->error, 'ERROR');
         http_response_code(500);
         echo json_encode(['error' => 'Database execute statement failed.']);
         $stmt->close();
         $conn->close();
         exit;
    }

    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $influencer = $result->fetch_assoc();
        custom_log("influencer.php: Influencer FOUND: " . print_r($influencer, true), 'INFO');
        echo json_encode($influencer);
    } else {
        custom_log("influencer.php: Influencer NOT found with current filters (excluded IDs: " . implode(',', $excludedIds) . ").", 'INFO');

        $count_all_active_sql = "SELECT COUNT(*) as total FROM influencers WHERE is_active = TRUE";
        $count_result = $conn->query($count_all_active_sql);
        $total_active = ($count_result && $count_result->num_rows > 0) ? $count_result->fetch_assoc()['total'] : 0;

        if ($total_active > 0 && empty($result->num_rows)) {
             http_response_code(404);
             echo json_encode(['error' => 'No available influencers found.']);
             custom_log("influencer.php: Responded with 404 - No *available* influencers found (all active were excluded).", 'WARN');
        } else {
             http_response_code(404);
             echo json_encode(['error' => 'No active influencers found or database empty.']);
             custom_log("influencer.php: Responded with 404 - No *active* influencers found in DB or DB empty. Total active: {$total_active}", 'WARN');
        }
    }

} catch (Exception $e) {
    custom_log("influencer.php: General Error: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
} finally {
    if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
}


if ($conn) {
    $conn->close();
}
custom_log("--- influencer.php END ---", 'DEBUG');
?>