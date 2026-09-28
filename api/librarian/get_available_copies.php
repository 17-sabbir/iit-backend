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

$database = new Database();
$db = $database->getConnection();

$isbn = isset($_GET['isbn']) ? trim($_GET['isbn']) : '';

if ($isbn === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'isbn is required']);
    exit;
}

try {
    $stmt = $db->prepare('SELECT copy_id, shelf_id, compartment_no, subcompartment_no, condition_note
        FROM Book_Copies
        WHERE isbn = :isbn AND status = "Available"
        ORDER BY copy_id');
    $stmt->execute([':isbn' => $isbn]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => count($rows),
        'copies' => $rows,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
