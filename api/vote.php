<?php
header('Content-Type: application/json');

$conn = null;

include_once __DIR__ . '/../../db_config.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    error_log("vote.php: DB connection failed or not established after include. Path checked: " . realpath(__DIR__ . '/../../db_config.php') . (isset($conn) && $conn->connect_error ? " Error: " . $conn->connect_error : " Conn variable not set or include failed."));
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: Could not connect to database.']);
    exit;
}


$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['influencer_id']) || !isset($input['guess']) || !is_numeric($input['influencer_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input: missing influencer_id or guess, or id is not numeric.']);
    $conn->close();
    exit;
}

$influencer_id = intval($input['influencer_id']);
$guess = $input['guess'];

if ($guess !== 'gay' && $guess !== 'straight') {
     http_response_code(400);
     echo json_encode(['error' => 'Invalid guess value. Must be "gay" or "straight".']);
     $conn->close();
     exit;
}


$column_to_update = ($guess === 'gay') ? 'votes_gay' : 'votes_straight';

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
    $stmt_update->close();

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
        echo json_encode($stats);
    } else {
         throw new Exception("Influencer with ID {$influencer_id} not found after trying to update stats.");
    }
    $stmt_select->close();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Voting error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not process vote or retrieve stats.']);
}

$conn->close();
?>