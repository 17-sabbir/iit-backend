<?php
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $data = json_decode(file_get_contents('php://input')) ?: new stdClass();
    $courseId = trim($data->course_id ?? '');

    if ($courseId === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'course_id is required',
        ]);
        exit;
    }

    // Check if course exists
    $checkStmt = $db->prepare('SELECT course_id FROM Courses WHERE course_id = :course_id');
    $checkStmt->execute([':course_id' => $courseId]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Course not found',
        ]);
        exit;
    }

    // Delete course
    $stmt = $db->prepare('DELETE FROM Courses WHERE course_id = :course_id');
    $stmt->execute([':course_id' => $courseId]);

    echo json_encode([
        'success' => true,
        'message' => 'Course deleted successfully',
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to delete course: ' . $e->getMessage(),
    ]);
}
