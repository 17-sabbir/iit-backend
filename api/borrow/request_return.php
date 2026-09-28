<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../../config/database.php';
include_once '../lib/enhanced_notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'));
$transactionId = $data->transaction_id ?? null;
$userEmail = $data->user_email ?? null;

if (empty($transactionId) || empty($userEmail)) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'transaction_id and user_email are required']);
  exit;
}

try {
  // Validate transaction exists and belongs to user and is borrowed
  $stmt = $db->prepare("SELECT at.transaction_id
    FROM Approved_Transactions at
    JOIN Transaction_Requests tr ON tr.request_id = at.request_id
    WHERE at.transaction_id = :tid AND tr.requester_email = :email AND at.status = 'Borrowed'");
  $stmt->execute([':tid'=>$transactionId, ':email'=>$userEmail]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    http_response_code(404);
    echo json_encode(['success'=>false,'message'=>'Active borrowed transaction not found for user']);
    exit;
  }

  // No persistent Return_Requests table usage — rely on notifications only.
  // We simply notify librarians and let them process via the Requests UI.
  
  // Get book title for notification
  $bookStmt = $db->prepare("
    SELECT b.title 
    FROM Approved_Transactions at
    JOIN Book_Copies bc ON at.copy_id = bc.copy_id
    JOIN Books b ON bc.isbn = b.isbn
    WHERE at.transaction_id = :tid
  ");
  $bookStmt->execute([':tid'=>$transactionId]);
  $book = $bookStmt->fetch(PDO::FETCH_ASSOC);
  $bookTitle = $book['title'] ?? 'Unknown Book';
  
  // Notify librarians about new return request
  notifyLibrarianReturnRequest($db, $userEmail, $bookTitle, $transactionId);

  echo json_encode(['success'=>true,'message'=>'Return request submitted. Waiting for librarian approval.']);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Error submitting return request: '.$e->getMessage()]);
}
