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

    // Upsert behavior: insert or update name/semester
    $stmt = $db->prepare('INSERT INTO Courses (course_id, course_name, semester)
        VALUES (:course_id, :course_name, :semester)
        ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), semester = VALUES(semester)');

    $stmt->execute([
        ':course_id' => $courseId,
        ':course_name' => $courseName,
        ':semester' => $semester,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Course saved',
        'course_id' => $courseId,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save course: ' . $e->getMessage(),
    ]);
}
