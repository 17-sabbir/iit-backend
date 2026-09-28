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

$payload = json_decode(file_get_contents('php://input'));

$isbn = $payload->isbn ?? null;

if (empty($isbn)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ISBN is required',
    ]);
    exit;
}

try {
    $db->beginTransaction();

    // Soft delete: mark image path and title so rowcount is >0 even if pic_path is NULL
    $stmt = $db->prepare('UPDATE Books SET pic_path = CONCAT("[DELETED]", COALESCE(pic_path, "")), title = CONCAT("[DELETED] ", title) WHERE isbn = :isbn');
    $stmt->bindParam(':isbn', $isbn);
    $stmt->execute();

    // Mark all copies as discarded
    $copyStmt = $db->prepare('UPDATE Book_Copies SET status = "Discarded" WHERE isbn = :isbn');
    $copyStmt->bindParam(':isbn', $isbn);
    $copyStmt->execute();

    if ($stmt->rowCount() > 0) {
        $db->commit();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Book removed successfully',
        ]);
    } else {
        $db->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Book not found',
        ]);
    }
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to delete book: ' . $e->getMessage(),
    ]);
}
?>
