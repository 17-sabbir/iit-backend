<?php
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $search = trim($_GET['search'] ?? '');
    
    if ($search === '') {
        // Return all courses if no search term
        $stmt = $db->prepare('SELECT course_id, course_name, semester FROM Courses ORDER BY course_id');
        $stmt->execute();
    } else {
        // Search by course_id, course_name, or semester
        $stmt = $db->prepare('SELECT course_id, course_name, semester FROM Courses 
            WHERE course_id LIKE :search 
            OR course_name LIKE :search 
            OR semester LIKE :search 
            ORDER BY course_id');
        $searchParam = '%' . $search . '%';
        $stmt->execute([':search' => $searchParam]);
    }
    
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => count($courses),
        'courses' => $courses,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to search courses: ' . $e->getMessage(),
    ]);
}
