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

try {
    // Get categories
    $sql = "SELECT DISTINCT category 
            FROM Books 
            WHERE category IS NOT NULL 
            AND category != '' 
            AND title NOT LIKE '[DELETED]%'
            ORDER BY category";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    $categories = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $row['category'];
    }
    
    // Get semesters
    $semesterSql = "SELECT DISTINCT semester 
                    FROM Courses 
                    WHERE semester IS NOT NULL 
                    ORDER BY semester";
    
    $semesterStmt = $db->prepare($semesterSql);
    $semesterStmt->execute();
    
    $semesters = [];
    while ($row = $semesterStmt->fetch(PDO::FETCH_ASSOC)) {
        $semesters[] = $row['semester'];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'semesters' => $semesters
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_categories.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'categories' => [],
        'semesters' => []
    ]);
}

