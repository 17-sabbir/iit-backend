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
include_once '../lib/notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'));
$requestId = $data->request_id ?? null;

if (empty($requestId)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'request_id is required']);
    exit;
}

try {
    // Load request
    $q = $db->prepare('SELECT tr.request_id, tr.isbn, tr.requester_email, b.title
                       FROM Transaction_Requests tr
                       JOIN Books b ON b.isbn = tr.isbn
                       WHERE tr.request_id = :rid AND tr.status = "Pending"');
    $q->execute([':rid'=>$requestId]);
    $req = $q->fetch(PDO::FETCH_ASSOC);
    if (!$req) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Pending request not found']);
        exit;
    }

    // Update request status to Rejected
    $updateStmt = $db->prepare('UPDATE Transaction_Requests SET status = "Rejected" WHERE request_id = :rid');
    $updateStmt->execute([':rid'=>$requestId]);

    // Notify user about rejection
    $notificationMsg = "Your borrow request for '{$req['title']}' has been rejected by the librarian.";
    $notifyStmt = $db->prepare("INSERT INTO Notifications (user_email, message, type) VALUES (?, ?, 'BorrowRequestRejected')");
    $notifyStmt->execute([$req['requester_email'], $notificationMsg]);

    http_response_code(200);
    echo json_encode(['success'=>true,'message'=>'Request rejected successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error rejecting request: '.$e->getMessage()]);
}
?>
