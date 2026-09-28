<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$requestId = intval($input['request_id'] ?? 0);
$librarian = $input['declined_by'] ?? 'System';
$reason = trim($input['reason'] ?? '');

if ($requestId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'request_id is required']);
    exit;
}

try {
    // Load request
    $stmt = $db->prepare('SELECT requester_identifier, title FROM Requests WHERE request_id = :rid AND status = "Pending"');
    $stmt->execute([':rid' => $requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pending request not found or already processed']);
        exit;
    }

    // Optionally append reason to description
    if ($reason !== '') {
        $updDesc = $db->prepare('UPDATE Requests SET description = CONCAT(COALESCE(description, ""), "\nDecline Reason: ", :reason) WHERE request_id = :rid');
        $updDesc->execute([':reason' => $reason, ':rid' => $requestId]);
    }

    // Mark as Rejected
    $upd = $db->prepare('UPDATE Requests SET status = "Rejected", approved_by = :by, approved_at = NOW() WHERE request_id = :rid AND status = "Pending"');
    $upd->execute([':by' => $librarian, ':rid' => $requestId]);

    // Optional: notify user (System type)
    createNotification($db, $req['requester_identifier'], "Your addition request for '" . $req['title'] . "' was declined.", 'System');

    echo json_encode(['success' => true, 'message' => 'Book addition request declined']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
