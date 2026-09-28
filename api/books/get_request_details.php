<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
if ($requestId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'request_id is required']);
    exit;
}

try {
    $stmt = $db->prepare('SELECT request_id, requester_identifier AS email, isbn, title, author, publisher, publication_year, edition, pdf_path, category, pic_path, description, status, approved_by, approved_at FROM Requests WHERE request_id = :rid');
    $stmt->execute([':rid' => $requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    echo json_encode(['success' => true, 'item' => $row]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
