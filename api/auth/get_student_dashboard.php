<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Get Student Dashboard Data
 * Retrieves dashboard stats including borrowed books, overdue, and pending requests
 */

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get email from request
$input = json_decode(file_get_contents('php://input'), true) ?? $_GET;
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';

if ($email === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email is required.'
    ]);
    exit;
}

try {
    // Get borrowed count from Transactions table if it exists, otherwise return 0
    $borrowed = 0;
    $overdue = 0;
    $borrowed_books = [];
    $overdue_books = [];

    try {
        // Try to get all transactions for the user
        $stmt = $db->prepare('
            SELECT * FROM Transactions 
            WHERE user_email = :email AND status IN ("Borrowed", "Overdue")
            ORDER BY borrow_date DESC
        ');
        $stmt->execute([':email' => $email]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $now = new DateTime();
        foreach ($transactions as $tx) {
            $borrowed++;
            
            $due_date = new DateTime($tx['due_date']);
            $is_overdue = $now > $due_date;
            
            if ($is_overdue) {
                $overdue++;
                $overdue_books[] = [
                    'id' => $tx['transaction_id'] ?? $tx['id'],
                    'isbn' => $tx['isbn'],
                    'title' => $tx['title'] ?? $tx['book_title'] ?? 'Unknown',
                    'due_date' => $tx['due_date'],
                    'days_overdue' => $now->diff($due_date)->days,
                    'status' => 'Overdue'
                ];
            } else {
                $borrowed_books[] = [
                    'id' => $tx['transaction_id'] ?? $tx['id'],
                    'isbn' => $tx['isbn'],
                    'title' => $tx['title'] ?? $tx['book_title'] ?? 'Unknown',
                    'due_date' => $tx['due_date'],
                    'status' => 'Borrowed'
                ];
            }
        }
    } catch (Exception $txErr) {
        // Table might not exist or different structure; just skip
        error_log('Transaction query failed: ' . $txErr->getMessage());
    }

    // Get outstanding fines for the user
    $outstanding_fines = 0;
    try {
        $finesStmt = $db->prepare('
            SELECT SUM(amount) as total_fines 
            FROM Fines 
            WHERE user_email = :email AND paid = 0
        ');
        $finesStmt->execute([':email' => $email]);
        $finesResult = $finesStmt->fetch(PDO::FETCH_ASSOC);
        $outstanding_fines = (float)($finesResult['total_fines'] ?? 0);
    } catch (Exception $fineErr) {
        error_log('Fines query failed: ' . $fineErr->getMessage());
    }

    // Get pending book requests
    $pending_requests = 0;
    try {
        $requestsStmt = $db->prepare('
            SELECT COUNT(*) as pending_requests 
            FROM BookRequests 
            WHERE requester_email = :email AND status = "Pending"
        ');
        $requestsStmt->execute([':email' => $email]);
        $requestsResult = $requestsStmt->fetch(PDO::FETCH_ASSOC);
        $pending_requests = (int)($requestsResult['pending_requests'] ?? 0);
    } catch (Exception $reqErr) {
        error_log('Requests query failed: ' . $reqErr->getMessage());
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'stats' => [
            'totalBorrowed' => $borrowed,
            'borrowLimit' => 2,
            'outstandingFines' => $outstanding_fines,
            'overdueCount' => $overdue,
            'pendingRequests' => $pending_requests,
            'readyReservations' => 0
        ],
        'borrowedBooks' => $borrowed_books,
        'overdueBooks' => $overdue_books
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch dashboard data: ' . $e->getMessage()
    ]);
}
?>
