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
include_once '../lib/request_cleanup.php';
include_once '../lib/reservation_helpers.php';
include_once '../lib/enhanced_notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

// Clean up expired borrow requests (older than 24 hours) before processing new request
cleanupExpiredBorrowRequests($db);

$data = json_decode(file_get_contents('php://input'));

$userEmail = $data->user_email ?? null;
$isbn = $data->isbn ?? null;

if (empty($isbn) || empty($userEmail)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'isbn and user_email are required',
    ]);
    exit;
}

try {
    // Check if user exists
    $userStmt = $db->prepare('SELECT role FROM Users WHERE email = :email');
    $userStmt->execute([':email' => $userEmail]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found',
        ]);
        exit;
    }

    // Check borrowing limit based on role
    $role = strtolower($user['role']);
    $borrowLimit = match($role) {
        'student' => 2,
        'teacher' => 5,
        'librarian' => 10,
        'director' => 10,
        default => 2,
    };

    // Count currently borrowed books
    $borrowedCountStmt = $db->prepare('
        SELECT COUNT(*) as count
        FROM Approved_Transactions at
        JOIN Transaction_Requests tr ON tr.request_id = at.request_id
        WHERE tr.requester_email = :email AND at.status = "Borrowed"
    ');
    $borrowedCountStmt->execute([':email' => $userEmail]);
    $borrowedCount = (int)$borrowedCountStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Count pending requests
    $pendingCountStmt = $db->prepare('
        SELECT COUNT(*) as count
        FROM Transaction_Requests
        WHERE requester_email = :email AND status = "Pending"
    ');
    $pendingCountStmt->execute([':email' => $userEmail]);
    $pendingCount = (int)$pendingCountStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Check if total (borrowed + pending) exceeds limit
    $totalCount = $borrowedCount + $pendingCount;
    if ($totalCount >= $borrowLimit) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "You have reached your borrowing limit ($borrowLimit books). You currently have $borrowedCount borrowed and $pendingCount pending requests.",
        ]);
        exit;
    }

    // Check if book exists
    $bookStmt = $db->prepare('SELECT title FROM Books WHERE isbn = :isbn');
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

    // Reservation priority: only queue #1 can request during its 12h window
    $topReservation = ensureReservationWindow($db, $isbn);
    $isTopHolder = false;
    if ($topReservation) {
        $holderEmail = $topReservation['user_email'];
        $expiresAt = $topReservation['expires_at'];

        if ($holderEmail !== $userEmail) {
            $expiresText = $expiresAt ? (new DateTime($expiresAt))->format('Y-m-d H:i') : 'soon';
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => "This book is reserved for queue #1 until $expiresText.",
                'reserved_for' => $holderEmail,
                'expires_at' => $expiresAt,
            ]);
            exit;
        }

        // Current requester is queue #1 holder
        $isTopHolder = true;
    }

    // Check if user already has a pending request for this book
    $checkStmt = $db->prepare('
        SELECT request_id 
        FROM Transaction_Requests 
        WHERE isbn = :isbn 
        AND requester_email = :email 
        AND status = "Pending"
    ');
    $checkStmt->execute([
        ':isbn' => $isbn,
        ':email' => $userEmail,
    ]);

    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'You already have a pending request for this book',
        ]);
        exit;
    }

    // Check if user currently has this book borrowed
    $borrowedStmt = $db->prepare('
        SELECT at.transaction_id
        FROM Approved_Transactions at
        JOIN Transaction_Requests tr ON tr.request_id = at.request_id
        WHERE tr.isbn = :isbn 
        AND tr.requester_email = :email 
        AND at.status = "Borrowed"
    ');
    $borrowedStmt->execute([
        ':isbn' => $isbn,
        ':email' => $userEmail,
    ]);

    if ($borrowedStmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'You have already borrowed this book',
        ]);
        exit;
    }

    // Create pending transaction request
    $reqStmt = $db->prepare('
        INSERT INTO Transaction_Requests (
            isbn, requester_email, request_date, status
        ) VALUES (
            :isbn, :requester_email, NOW(), "Pending"
        )
    ');
    $reqStmt->execute([
        ':isbn' => $isbn,
        ':requester_email' => $userEmail,
    ]);
    $requestId = $db->lastInsertId();

    // Get book title for notification
    $bookStmt = $db->prepare('SELECT title FROM Books WHERE isbn = :isbn');
    $bookStmt->execute([':isbn' => $isbn]);
    $book = $bookStmt->fetch(PDO::FETCH_ASSOC);
    $bookTitle = $book['title'] ?? 'Unknown Book';

    // Notify librarians about new borrow request
    notifyLibrarianBorrowRequest($db, $userEmail, $bookTitle, $requestId);

    // If requester was queue #1 reservation holder, complete their reservation and advance queue
    if ($isTopHolder) {
        completeReservationForUser($db, $isbn, $userEmail);
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Borrow request submitted successfully. Waiting for librarian approval.',
        'request_id' => $requestId,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to submit borrow request: ' . $e->getMessage(),
    ]);
}
?>
