<?php
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $stmt = $db->prepare('SELECT course_id, course_name, semester FROM Courses ORDER BY course_id');
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'courses' => $courses,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch courses: ' . $e->getMessage(),
    ]);
}
