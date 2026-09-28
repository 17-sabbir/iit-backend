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

$payload = json_decode(file_get_contents('php://input'));

$userEmail = $payload->user_email ?? null;
$isbn = $payload->isbn ?? null;

if (empty($isbn) || empty($userEmail)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'isbn and user_email are required',
    ]);
    exit;
}

try {
    // Ensure book exists
    $bookCheck = $db->prepare('SELECT isbn FROM Books WHERE isbn = :isbn');
    $bookCheck->execute([':isbn' => $isbn]);
    if (!$bookCheck->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Book not found',
        ]);
        exit;
    }

    // Prevent duplicate active reservation
    $dup = $db->prepare('SELECT reservation_id FROM Reservations WHERE isbn = :isbn AND user_email = :user_email AND status = "Active"');
    $dup->execute([
        ':isbn' => $isbn,
        ':user_email' => $userEmail,
    ]);
    if ($dup->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You already have an active reservation for this book',
        ]);
        exit;
    }

    // Determine queue position
    $queueStmt = $db->prepare('SELECT IFNULL(MAX(queue_position),0) as pos FROM Reservations WHERE isbn = :isbn AND status = "Active"');
    $queueStmt->execute([':isbn' => $isbn]);
    $pos = (int)$queueStmt->fetch(PDO::FETCH_ASSOC)['pos'] + 1;

    $stmt = $db->prepare('INSERT INTO Reservations (
        isbn, user_email, queue_position, status, created_at
    ) VALUES (
        :isbn, :user_email, :queue_position, "Active", NOW()
    )');

    $stmt->execute([
        ':isbn' => $isbn,
        ':user_email' => $userEmail,
        ':queue_position' => $pos,
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Book reserved successfully',
        'reservation_id' => $db->lastInsertId(),
        'queue_position' => $pos,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to reserve book: ' . $e->getMessage(),
    ]);
}
?>
