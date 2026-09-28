<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['request_id'])) {
        echo json_encode(['success' => false, 'message' => 'Request ID is required']);
        exit;
    }

    $requestId = (int)$data['request_id'];
    
    // Build update query dynamically based on provided fields
    $updates = [];
    $params = [':request_id' => $requestId];
    
    if (isset($data['title'])) {
        $updates[] = 'title = :title';
        $params[':title'] = $data['title'];
    }
    
    if (isset($data['author'])) {
        $updates[] = 'author = :author';
        $params[':author'] = $data['author'];
    }
    
    if (isset($data['isbn'])) {
        $updates[] = 'isbn = :isbn';
        $params[':isbn'] = $data['isbn'];
    }
    
    if (isset($data['category'])) {
        $updates[] = 'category = :category';
        $params[':category'] = $data['category'];
    }
    
    if (isset($data['publisher'])) {
        $updates[] = 'publisher = :publisher';
        $params[':publisher'] = $data['publisher'];
    }
    
    if (isset($data['publication_year'])) {
        $updates[] = 'publication_year = :publication_year';
        $params[':publication_year'] = $data['publication_year'];
    }
    
    if (isset($data['edition'])) {
        $updates[] = 'edition = :edition';
        $params[':edition'] = $data['edition'];
    }
    
    if (isset($data['course_id'])) {
        $updates[] = 'course_id = :course_id';
        $params[':course_id'] = $data['course_id'];
    }
    
    if (isset($data['pdf_path'])) {
        $updates[] = 'pdf_path = :pdf_path';
        $params[':pdf_path'] = $data['pdf_path'];
    }
    
    if (isset($data['pic_path'])) {
        $updates[] = 'pic_path = :pic_path';
        $params[':pic_path'] = $data['pic_path'];
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    $sql = "UPDATE Addition_Requests SET " . implode(', ', $updates) . " WHERE request_id = :request_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Addition request updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No changes made or request not found'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in update_addition_request.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in update_addition_request.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
