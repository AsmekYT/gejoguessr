<?php
header('Content-Type: application/json');

error_log("--- influencer.php START ---");

$excludedIds = [];
$conn = null;

include_once __DIR__ . '/../../db_config.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    error_log("influencer.php: DB connection failed or not established after include. Path checked: " . realpath(__DIR__ . '/../../db_config.php') . (isset($conn) && $conn->connect_error ? " Error: " . $conn->connect_error : " Conn variable not set or include failed."));
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: Could not connect to database.']);
    exit;
} else {
    error_log("influencer.php: DB Connection successful.");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = file_get_contents('php://input');
    // --- LOG: Otrzymany payload ---
    error_log("influencer.php: Received Payload: " . $payload);
    $input = json_decode($payload, true);
    if (isset($input['exclude_ids']) && is_array($input['exclude_ids'])) {
        // Poprawne użycie array_filter
        $excludedIds = array_filter($input['exclude_ids'], 'is_numeric');
        $excludedIds = array_map('intval', $excludedIds);
        // --- LOG: Sparsowane exclude_ids ---
        error_log("influencer.php: Parsed exclude_ids: " . print_r($excludedIds, true));
    } else {
        error_log("influencer.php: exclude_ids not found or not an array in payload.");
    }
} else {
    error_log("influencer.php: Request method was not POST.");
}


$sql = "SELECT id, name, image_url FROM influencers WHERE is_active = TRUE";
$params = [];
$types = "";

if (!empty($excludedIds)) {
    $placeholders = implode(',', array_fill(0, count($excludedIds), '?'));
    $sql .= " AND id NOT IN ($placeholders)";
    $params = $excludedIds;
    $types = str_repeat('i', count($excludedIds));
    error_log("influencer.php: Adding NOT IN clause. Params count: " . count($params));
} else {
    error_log("influencer.php: exclude_ids is empty, no NOT IN clause added.");
}

$sql .= " ORDER BY RAND() LIMIT 1";

error_log("influencer.php: Final SQL Query: " . $sql);


$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("influencer.php: Prepare failed: (" . $conn->errno . ") " . $conn->error . " SQL: " . $sql);
    http_response_code(500);
    echo json_encode(['error' => 'Database prepare statement failed.']);
    $conn->close();
    exit;
}

if (!empty($params)) {
     $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
     error_log("influencer.php: Execute failed: (" . $stmt->errno . ") " . $stmt->error);
     http_response_code(500);
     echo json_encode(['error' => 'Database execute statement failed.']);
     $stmt->close();
     $conn->close();
     exit;
}

$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $influencer = $result->fetch_assoc();
    error_log("influencer.php: Influencer FOUND: " . print_r($influencer, true));
    echo json_encode($influencer);
} else {
    error_log("influencer.php: Influencer NOT found with current filters.");

    $count_all_active_sql = "SELECT COUNT(*) as total FROM influencers WHERE is_active = TRUE";
    $count_result = $conn->query($count_all_active_sql);
    $total_active = ($count_result && $count_result->num_rows > 0) ? $count_result->fetch_assoc()['total'] : 0;


    $count_available_sql = "SELECT COUNT(*) as total FROM influencers WHERE is_active = TRUE";
     if (!empty($params)) {
        $placeholders_count = implode(',', array_fill(0, count($params), '?'));
        $count_available_sql .= " AND id NOT IN ($placeholders_count)";
    }
    $stmt_count_available = $conn->prepare($count_available_sql);
     $total_available = 0;
     if($stmt_count_available) {
        if (!empty($params)) {
             $stmt_count_available->bind_param($types, ...$params); //
        }
        if($stmt_count_available->execute()){
            $count_available_result = $stmt_count_available->get_result();
            $total_available = ($count_available_result && $count_available_result->num_rows > 0) ? $count_available_result->fetch_assoc()['total'] : 0;
        } else {
             error_log("influencer.php: Execute failed for count available: (" . $stmt_count_available->errno . ") " . $stmt_count_available->error);
        }
         if ($stmt_count_available) $stmt_count_available->close();
     } else {
         error_log("influencer.php: Prepare failed for count available: (" . $conn->errno . ") " . $conn->error);
     }

    error_log("influencer.php: Check counts - Total Active: {$total_active}, Total Available (after exclusion): {$total_available}");


    if ($total_active > 0 && $total_available === 0) {
         http_response_code(404);
         echo json_encode(['error' => 'No available influencers found.']);
         error_log("influencer.php: Responded with 404 - No available influencers found.");
    } else {
         http_response_code(404);
         echo json_encode(['error' => 'No active influencers found or initial database empty.']);
         error_log("influencer.php: Responded with 404 - No active influencers found or DB empty.");
    }
}

if ($stmt) $stmt->close();
$conn->close();
error_log("--- influencer.php END ---");
?>