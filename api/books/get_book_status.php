<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get ISBN from query parameter
$isbn = isset($_GET['isbn']) ? $_GET['isbn'] : '';

if (empty($isbn)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ISBN is required',
    ]);
    exit;
}

try {
    // Get book from Books table
    $bookStmt = $db->prepare('SELECT isbn FROM Books WHERE isbn = :isbn');
    $bookStmt->execute([':isbn' => $isbn]);
    $book = $bookStmt->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Book not found',
        ]);
        exit;
    }

    // Count copies by status from Book_Copies
    $copiesStmt = $db->prepare('
        SELECT status, COUNT(*) as count
        FROM Book_Copies
        WHERE isbn = :isbn
        GROUP BY status
    ');
    $copiesStmt->execute([':isbn' => $isbn]);
    $copiesData = $copiesStmt->fetchAll(PDO::FETCH_ASSOC);

    $copies = [
        'available' => 0,
        'borrowed' => 0,
        'reserved' => 0,
        'lost' => 0,
        'discarded' => 0,
    ];
    
    foreach ($copiesData as $row) {
        $status = strtolower($row['status']);
        if (isset($copies[$status])) {
            $copies[$status] = (int)$row['count'];
        }
    }

    // Count active reservations
    $reserveStmt = $db->prepare('
        SELECT COUNT(*) as reserved_count
        FROM Reservations
        WHERE isbn = :isbn 
          AND status = "Active"
    ');
    $reserveStmt->execute([':isbn' => $isbn]);
    $reserveData = $reserveStmt->fetch(PDO::FETCH_ASSOC);

    $copiesTotal = array_sum($copies);
    $copiesAvailable = $copies['available'];
    $borrowedCount = $copies['borrowed'];
    $reservedCount = (int)$reserveData['reserved_count'];

    // Determine availability
    $available = $copiesAvailable > 0;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'isbn' => $isbn,
        'copies_total' => $copiesTotal,
        'copies_available' => $copiesAvailable,
        'borrowed_count' => $borrowedCount,
        'reserved_count' => $reservedCount,
        'available' => $available,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch book status: ' . $e->getMessage(),
    ]);
}
?>
