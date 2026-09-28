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
include_once '../lib/reservation_helpers.php';

$database = new Database();
$db = $database->getConnection();

// Accept email from either GET or POST
$userEmail = $_GET['email'] ?? null;
if (!$userEmail) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $userEmail = $input['email'] ?? null;
}

if (!$userEmail) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'email parameter is required',
    ]);
    exit;
}

try {
    // Clean up expired reservations and renumber queue for consistency with librarian view
    $isbnStmt = $db->prepare('SELECT DISTINCT isbn FROM Reservations WHERE status = "Active"');
    $isbnStmt->execute();
    $isbns = $isbnStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($isbns as $isbn) {
        cleanupExpiredReservationsForIsbn($db, $isbn);
        renumberReservationQueue($db, $isbn);
    }
    
    // Get active reservations for the user
    $query = "SELECT 
        r.reservation_id,
        r.isbn,
        b.title,
        b.author,
        b.category,
        b.pic_path,
        r.queue_position,
        r.status,
        r.created_at,
        r.expires_at,
        COUNT(DISTINCT bc.copy_id) as total_copies,
        SUM(CASE WHEN bc.status = 'Available' THEN 1 ELSE 0 END) as available_copies
    FROM Reservations r
    LEFT JOIN Books b ON r.isbn = b.isbn
    LEFT JOIN Book_Copies bc ON b.isbn = bc.isbn
    WHERE r.user_email = :email AND r.status IN ('Active', 'Pending')
    GROUP BY r.reservation_id
    ORDER BY r.queue_position ASC, r.created_at ASC";

    $stmt = $db->prepare($query);
    $stmt->execute([':email' => $userEmail]);

    $reservations = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Check if reservation has 12-hour window (expires_at set and not expired)
        $isReady = false;
        $hoursRemaining = 0;
        if ($row['expires_at']) {
            $expiresAt = new DateTime($row['expires_at']);
            $now = new DateTime();
            if ($expiresAt > $now) {
                $isReady = true;
                $interval = $now->diff($expiresAt);
                $hoursRemaining = ($interval->days * 24) + $interval->h;
            }
        }
        
        // Get total people in queue for this book
        $queueStmt = $db->prepare("SELECT COUNT(*) as total FROM Reservations WHERE isbn = :isbn AND status = 'Active'");
        $queueStmt->execute([':isbn' => $row['isbn']]);
        $totalInQueue = (int)$queueStmt->fetchColumn();
        
        $reservations[] = [
            'reservationId' => (int)$row['reservation_id'],
            'isbn' => $row['isbn'],
            'title' => $row['title'],
            'author' => $row['author'],
            'category' => $row['category'],
            'cover' => $row['pic_path'] ?? null,
            'queuePosition' => (int)$row['queue_position'],
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
            'expiresAt' => $row['expires_at'],
            'isReady' => $isReady,
            'hoursRemaining' => $hoursRemaining,
            'totalInQueue' => $totalInQueue,
            'totalCopies' => (int)$row['total_copies'],
            'availableCopies' => (int)$row['available_copies'] ?? 0,
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($reservations),
        'reservations' => $reservations
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching reservations: ' . $e->getMessage(),
    ]);
}
