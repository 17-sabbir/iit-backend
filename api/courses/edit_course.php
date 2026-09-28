<?php
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $data = json_decode(file_get_contents('php://input')) ?: new stdClass();
    $courseId = trim($data->course_id ?? '');
    $courseName = trim($data->course_name ?? '');
    $semester = isset($data->semester) ? trim($data->semester) : null;

    if ($courseId === '' || $courseName === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'course_id and course_name are required',
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

    // Update course
    $stmt = $db->prepare('UPDATE Courses SET course_name = :course_name, semester = :semester WHERE course_id = :course_id');
    $stmt->execute([
        ':course_id' => $courseId,
        ':course_name' => $courseName,
        ':semester' => $semester,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Course updated successfully',
        'course_id' => $courseId,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update course: ' . $e->getMessage(),
    ]);
}
