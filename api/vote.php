<?php
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);

header('Content-Type: application/json');

include_once __DIR__ . '/logger.php';

$conn = null;

include_once __DIR__ . '/../../db_config.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    $errorMsg = "vote.php: DB connection failed or not established. Path checked: " . realpath(__DIR__ . '/../../db_config.php') . (isset($conn) && $conn->connect_error ? " Error: " . $conn->connect_error : " Conn variable not set or include failed.");
    custom_log($errorMsg, 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: Could not connect to database.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['influencer_id']) || !isset($input['guess']) || !is_numeric($input['influencer_id'])) {
    custom_log("vote.php: Invalid input received. Data: " . json_encode($input), 'WARN');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input: missing influencer_id or guess, or id is not numeric.']);
    $conn->close();
    exit;
}

$influencer_id = intval($input['influencer_id']);
$guess = $input['guess'];

if ($guess !== 'gay' && $guess !== 'straight') {
     custom_log("vote.php: Invalid guess value '{$guess}' for influencer ID {$influencer_id}.", 'WARN');
     http_response_code(400);
     echo json_encode(['error' => 'Invalid guess value. Must be "gay" or "straight".']);
     $conn->close();
     exit;
}


$column_to_update = ($guess === 'gay') ? 'votes_gay' : 'votes_straight';
custom_log("vote.php: Processing vote '{$guess}' for influencer ID {$influencer_id}.", 'INFO');

$conn->begin_transaction();

try {
    $stmt_update = $conn->prepare("UPDATE influencers SET `$column_to_update` = `$column_to_update` + 1 WHERE id = ?");
    if (!$stmt_update) {
        throw new Exception("Prepare failed (update): " . $conn->error);
    }
    $stmt_update->bind_param("i", $influencer_id);

    if (!$stmt_update->execute()) {
         throw new Exception("Execute failed (update): " . $stmt_update->error);
    }

    custom_log("vote.php: Vote successfully recorded for influencer ID {$influencer_id}.", 'INFO');

    $stmt_select = $conn->prepare("SELECT votes_gay, votes_straight FROM influencers WHERE id = ?");
     if (!$stmt_select) {
        throw new Exception("Prepare failed (select): " . $conn->error);
    }
    $stmt_select->bind_param("i", $influencer_id);

    if (!$stmt_select->execute()) {
         throw new Exception("Execute failed (select): " . $stmt_select->error);
    }
    $result = $stmt_select->get_result();

    if ($result->num_rows > 0) {
        $stats = $result->fetch_assoc();
        $conn->commit();
        custom_log("vote.php: Successfully retrieved stats for influencer ID {$influencer_id}: " . json_encode($stats), 'INFO');
        echo json_encode($stats);
    } else {
         throw new Exception("Influencer with ID {$influencer_id} not found after trying to update stats.");
    }

} catch (Exception $e) {
    $conn->rollback();
    custom_log("Voting error for influencer ID {$influencer_id}: " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Could not process vote or retrieve stats.']);
} finally {
     if (isset($stmt_select) && $stmt_select instanceof mysqli_stmt) { $stmt_select->close(); }
     if (isset($stmt_update) && $stmt_update instanceof mysqli_stmt) { $stmt_update->close(); }
}


if ($conn) {
    $conn->close();
}
custom_log("--- vote.php END ---", 'DEBUG');

?>