<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$isbn = $_GET['isbn'] ?? '';

if (empty($isbn)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ISBN is required'
    ]);
    exit;
}

try {
    $stmt = $db->prepare('
        SELECT 
            bc.copy_id,
            bc.isbn,
            bc.shelf_id,
            bc.compartment_no,
            bc.subcompartment_no,
            bc.status,
            bc.condition_note,
            bc.created_at
        FROM Book_Copies bc
        WHERE bc.isbn = :isbn
        ORDER BY bc.copy_id
    ');
    
    $stmt->execute([':isbn' => $isbn]);
    $copies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'copies' => $copies,
        'count' => count($copies)
    ]);
} catch (Exception $e) {
    error_log('GET_BOOK_COPIES ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch copies: ' . $e->getMessage()
    ]);
}
?>
