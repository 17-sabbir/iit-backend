<?php
/**
 * Get all courses linked to a specific book by ISBN
 * Returns array of courses with course_id, course_name, and semester
 */

header('Content-Type: application/json');

include_once '../../config/database.php';

// Support both GET and POST
$isbn = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['isbn'])) {
    $isbn = trim($_GET['isbn']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput);
    if ($data && isset($data->isbn)) {
        $isbn = trim($data->isbn);
    }
}

if (empty($isbn)) {
    echo json_encode([
        'success' => false,
        'message' => 'ISBN is required'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Fetch all courses linked to this book with their details
    $stmt = $db->prepare('
        SELECT 
            bc.course_id,
            c.course_name,
            c.semester
        FROM Book_Courses bc
        INNER JOIN Courses c ON bc.course_id = c.course_id
        WHERE bc.isbn = :isbn
        ORDER BY c.course_id
    ');
    
    $stmt->execute([':isbn' => $isbn]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'courses' => $courses
    ]);
    
} catch (PDOException $e) {
    error_log('Error in get_book_courses.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
