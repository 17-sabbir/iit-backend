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

$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$courseId = isset($_GET['course_id']) ? $_GET['course_id'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$availability = isset($_GET['availability']) ? $_GET['availability'] : '';
$bookType = isset($_GET['book_type']) ? $_GET['book_type'] : '';

// Base query to fetch books
$query = "SELECT DISTINCT b.*
          FROM Books b 
          WHERE 1=1 AND b.title NOT LIKE '[DELETED]%'";

// Apply course filter if specified
if (!empty($courseId)) {
    $query .= " AND EXISTS (SELECT 1 FROM Book_Courses bc WHERE bc.isbn = b.isbn AND bc.course_id = :course_id)";
}

// Apply semester filter if specified
if (!empty($semester)) {
    $query .= " AND EXISTS (
        SELECT 1 FROM Book_Courses bc 
        JOIN Courses c ON bc.course_id = c.course_id 
        WHERE bc.isbn = b.isbn AND c.semester = :semester
    )";
}

if (!empty($search)) {
    $query .= " AND (b.title LIKE :search OR b.author LIKE :search OR b.isbn LIKE :search)";
}

if (!empty($category)) {
    $query .= " AND b.category = :category";
}

// Book type filter: Physical = has copies, Digital = has PDF
if (!empty($bookType)) {
    if ($bookType === 'Digital') {
        $query .= " AND EXISTS (SELECT 1 FROM Digital_Resources dr WHERE dr.isbn = b.isbn AND dr.resource_type = 'PDF')";
    } elseif ($bookType === 'Physical') {
        $query .= " AND EXISTS (SELECT 1 FROM Book_Copies WHERE Book_Copies.isbn = b.isbn)";
    }
}

$query .= " ORDER BY b.title ASC";

$stmt = $db->prepare($query);

if (!empty($search)) {
    $search_param = "%{$search}%";
    $stmt->bindParam(":search", $search_param);
}

if (!empty($category)) {
    $stmt->bindParam(":category", $category);
}

if (!empty($courseId)) {
    $stmt->bindParam(":course_id", $courseId);
}

if (!empty($semester)) {
    $stmt->bindParam(":semester", $semester);
}

$stmt->execute();

$books = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Fetch all courses for this book
    $coursesStmt = $db->prepare('
        SELECT bc.course_id, c.course_name, c.semester
        FROM Book_Courses bc
        JOIN Courses c ON bc.course_id = c.course_id
        WHERE bc.isbn = :isbn
        ORDER BY c.course_id
    ');
    $coursesStmt->execute([':isbn' => $row['isbn']]);
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract course_ids and semesters for backward compatibility
    $courseIds = array_map(function($c) { return $c['course_id']; }, $courses);
    $semesters = array_unique(array_map(function($c) { return $c['semester']; }, $courses));
    
    // Count available copies
    $copiesStmt = $db->prepare('SELECT COUNT(*) as cnt FROM Book_Copies WHERE isbn = :isbn AND status = "Available"');
    $copiesStmt->execute([':isbn' => $row['isbn']]);
    $copiesAvailable = (int)$copiesStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    // Count total copies
    $totalCopiesStmt = $db->prepare('SELECT COUNT(*) as cnt FROM Book_Copies WHERE isbn = :isbn');
    $totalCopiesStmt->execute([':isbn' => $row['isbn']]);
    $totalCopies = (int)$totalCopiesStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    // Apply availability filter
    if (!empty($availability)) {
        if ($availability === 'Available' && $copiesAvailable === 0) {
            continue; // Skip books with no available copies
        } elseif ($availability === 'Not Available' && $copiesAvailable > 0) {
            continue; // Skip books with available copies
        }
    }
    
    // Check for PDF
    $pdfStmt = $db->prepare('SELECT file_path FROM Digital_Resources WHERE isbn = :isbn AND resource_type = "PDF"');
    $pdfStmt->execute([':isbn' => $row['isbn']]);
    $pdfRow = $pdfStmt->fetch(PDO::FETCH_ASSOC);
    
    // Always serve PDFs through download endpoint so paths stay consistent
    $pdfUrl = null;
    if ($pdfRow && !empty($pdfRow['file_path'])) {
        $pdfUrl = 'http://localhost:8000/api/books/download_pdf.php?isbn=' . urlencode($row['isbn']);
    }
    
    $books[] = [
        'isbn' => $row['isbn'],
        'title' => $row['title'],
        'author' => $row['author'],
        'category' => $row['category'],
        'publisher' => $row['publisher'],
        'publication_year' => $row['publication_year'],
        'edition' => $row['edition'],
        'description' => $row['description'],
        'pic_path' => $row['pic_path'],
        'copies_available' => $copiesAvailable,
        'copies_total' => $totalCopies,
        'course_id' => !empty($courseIds) ? $courseIds[0] : null, // First course for backward compatibility
        'course_ids' => $courseIds, // Array of all course IDs
        'courses' => $courses, // Full course details with names and semesters
        'semesters' => array_values($semesters), // Unique semesters
        'pdf_url' => $pdfUrl,
    ];
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'count' => count($books),
    'books' => $books,
]);
?>
