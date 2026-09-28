<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$transactionId = $input['transaction_id'] ?? null;

if (!$transactionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'transaction_id is required']);
    exit;
}

try {
    // Fetch requester and book title from transaction
    $infoStmt = $db->prepare('SELECT tr.requester_email, b.title 
        FROM Approved_Transactions at 
        JOIN Transaction_Requests tr ON at.request_id = tr.request_id 
        JOIN Book_Copies bc ON at.copy_id = bc.copy_id 
        JOIN Books b ON bc.isbn = b.isbn 
        WHERE at.transaction_id = :tid');
    $infoStmt->execute([':tid' => $transactionId]);
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }

    $email = $info['requester_email'];
    $bookTitle = $info['title'] ?: 'Book';

    // Notify user that their return request was approved
    notifyReturnRequestApproved($db, $email, $bookTitle);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Return request approved']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error approving return request: ' . $e->getMessage()]);
}
