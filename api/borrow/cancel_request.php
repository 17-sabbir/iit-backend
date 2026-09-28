<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$payload = json_decode(file_get_contents('php://input'));

// Support both naming conventions: user_email/email and request_id/transaction_id
$userEmail = isset($payload->user_email) ? strtolower(trim($payload->user_email)) : 
             (isset($payload->email) ? strtolower(trim($payload->email)) : null);
$requestId = isset($payload->request_id) ? intval($payload->request_id) : 
             (isset($payload->transaction_id) ? intval($payload->transaction_id) : null);

if (empty($userEmail) || empty($requestId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'user_email (or email) and request_id (or transaction_id) are required',
    ]);
    exit;
}

try {
    // Verify the request belongs to the user and is in Pending status
    $checkStmt = $db->prepare('
        SELECT request_id, status 
        FROM Transaction_Requests 
        WHERE request_id = :request_id AND requester_email = :email
    ');
    $checkStmt->execute([
        ':request_id' => $requestId,
        ':email' => $userEmail,
    ]);
    
    $request = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Request not found or does not belong to you',
        ]);
        exit;
    }
    
    if ($request['status'] !== 'Pending') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Only pending requests can be cancelled',
        ]);
        exit;
    }
    
    // Update the request status to Cancelled
    $updateStmt = $db->prepare('
        UPDATE Transaction_Requests 
        SET status = "Cancelled" 
        WHERE request_id = :request_id
    ');
    $updateStmt->execute([':request_id' => $requestId]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Request cancelled successfully',
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to cancel request: ' . $e->getMessage(),
    ]);
}
?>
