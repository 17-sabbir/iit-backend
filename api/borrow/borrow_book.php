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
    // Get user role to determine loan period
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

    // Set loan days based on role
    $role = strtolower($user['role']);
    $loanDays = match($role) {
        'student' => 7,
        'teacher' => 15,
        'librarian' => 30,
        'director' => 30,
        default => 14,
    };

    // Check available copy
    $copyStmt = $db->prepare('SELECT copy_id FROM Book_Copies WHERE isbn = :isbn AND status = "Available" LIMIT 1');
    $copyStmt->execute([':isbn' => $isbn]);
    $copy = $copyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$copy) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No available copies for this book',
        ]);
        exit;
    }

    $copyId = $copy['copy_id'];

    $db->beginTransaction();

    // Create transaction request
    $reqStmt = $db->prepare('INSERT INTO Transaction_Requests (
        isbn, requester_email, request_date, status
    ) VALUES (
        :isbn, :requester_email, NOW(), "Approved"
    )');
    $reqStmt->execute([
        ':isbn' => $isbn,
        ':requester_email' => $userEmail,
    ]);
    $requestId = $db->lastInsertId();

    // Create approved transaction
    $txn = $db->prepare('INSERT INTO Approved_Transactions (
        request_id, copy_id, issue_date, due_date, status
    ) VALUES (
        :request_id, :copy_id, NOW(), DATE_ADD(NOW(), INTERVAL :loan_days DAY), "Borrowed"
    )');

    $txn->execute([
        ':request_id' => $requestId,
        ':copy_id' => $copyId,
        ':loan_days' => $loanDays,
    ]);

    // Update copy status
    $copyUpdate = $db->prepare('UPDATE Book_Copies SET status = "Borrowed" WHERE copy_id = :copy_id');
    $copyUpdate->execute([':copy_id' => $copyId]);

    $db->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Book borrowed successfully',
        'transaction_id' => $db->lastInsertId(),
        'copy_id' => $copyId,
    ]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to borrow book: ' . $e->getMessage(),
    ]);
}
?>
