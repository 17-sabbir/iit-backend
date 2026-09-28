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
 * Get User Transactions
 * Retrieves borrowed, returned, and reserved books for a user
 */

require_once '../../config/database.php';
require_once '../lib/request_cleanup.php';

$database = new Database();
$db = $database->getConnection();

// Clean up expired borrow requests (older than 24 hours)
cleanupExpiredBorrowRequests($db);

// Get user email from request
$input = json_decode(file_get_contents('php://input'), true) ?? $_GET;
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
$status = isset($input['status']) ? $input['status'] : 'all'; // all, borrowed, returned, reserved

if ($email === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email is required.'
    ]);
    exit;
}

try {
    $transactions = [];
    
    // Get all transaction IDs that have pending return requests
    $pendingReturnIds = [];
    $pendingReturnStmt = $db->prepare("
        SELECT DISTINCT n.message
        FROM Notifications n
        WHERE n.user_email = :email AND n.type = 'ReturnRequestPending'
    ");
    $pendingReturnStmt->execute([':email' => $email]);
    
    while ($notif = $pendingReturnStmt->fetch(PDO::FETCH_ASSOC)) {
        if (preg_match('/Transaction\s+#(\d+)/', $notif['message'], $matches)) {
            $pendingReturnIds[] = (int)$matches[1];
        }
    }
    
    // Get borrowed and returned books from Approved_Transactions
    if ($status === 'all' || $status === 'borrowed' || $status === 'returned') {
        $statusFilter = '';
        if ($status === 'borrowed') {
            // Include both Borrowed and Overdue books when fetching borrowed books
            $statusFilter = " AND (at.status = 'Borrowed' OR at.status = 'Overdue')";
        } elseif ($status === 'returned') {
            $statusFilter = " AND at.status = 'Returned'";
        }
        
        $stmt = $db->prepare("
            SELECT 
                at.transaction_id,
                at.copy_id,
                at.issue_date,
                at.due_date,
                at.return_date,
                at.status,
                b.isbn,
                b.title,
                b.author,
                b.pic_path,
                bc.status as copy_status,
                f.fine_id,
                f.amount as fine_amount,
                f.paid as fine_paid,
                DATEDIFF(at.due_date, NOW()) as days_remaining
            FROM Approved_Transactions at
            JOIN Transaction_Requests tr ON at.request_id = tr.request_id
            JOIN Book_Copies bc ON at.copy_id = bc.copy_id
            JOIN Books b ON bc.isbn = b.isbn
            LEFT JOIN Fines f ON at.transaction_id = f.transaction_id
            WHERE tr.requester_email = :email $statusFilter
            ORDER BY at.issue_date DESC
        ");
        $stmt->execute([':email' => $email]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Skip books with pending return requests (they'll appear in pending section instead)
            if (in_array($row['transaction_id'], $pendingReturnIds)) {
                continue;
            }
            
            // Check if book is overdue (borrowed and past due date)
            $isOverdue = false;
            $daysOverdue = 0;
            $actualStatus = $row['status'];
            $daysRemaining = (int)$row['days_remaining'];
            
            if ($row['status'] === 'Borrowed' && $row['return_date'] === null) {
                $now = new DateTime();
                $dueDate = new DateTime($row['due_date']);
                
                if ($now > $dueDate) {
                    $isOverdue = true;
                    $daysOverdue = $now->diff($dueDate)->days;
                    $actualStatus = 'Overdue';
                }
            }
            
            $transactions[] = [
                'type' => strtolower($actualStatus),
                'transaction_id' => $row['transaction_id'],
                'copy_id' => $row['copy_id'],
                'isbn' => $row['isbn'],
                'title' => $row['title'],
                'author' => $row['author'],
                'pic_path' => $row['pic_path'],
                'cover' => $row['pic_path'],
                'issue_date' => $row['issue_date'],
                'due_date' => $row['due_date'],
                'return_date' => $row['return_date'],
                'status' => $actualStatus,
                'is_overdue' => $isOverdue,
                'days_overdue' => $daysOverdue,
                'days_remaining' => $daysRemaining,
                'fine_id' => $row['fine_id'],
                'fine_amount' => $row['fine_amount'] ? (float)$row['fine_amount'] : 0,
                'fine_paid' => $row['fine_paid']
            ];
        }
    }
    
    // Get reserved books from Reservations
    if ($status === 'all' || $status === 'reserved') {
        $stmt = $db->prepare("
            SELECT 
                r.reservation_id,
                r.isbn,
                r.created_at as reservation_date,
                r.expires_at as expiry_date,
                r.status as reservation_status,
                b.title,
                b.author,
                b.pic_path
            FROM Reservations r
            JOIN Books b ON r.isbn = b.isbn
            WHERE r.user_email = :email AND r.status = 'Active'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([':email' => $email]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = [
                'type' => 'reserved',
                'reservation_id' => $row['reservation_id'],
                'isbn' => $row['isbn'],
                'title' => $row['title'],
                'author' => $row['author'],
                'pic_path' => $row['pic_path'],
                'reservation_date' => $row['reservation_date'],
                'expiry_date' => $row['expiry_date'],
                'status' => $row['reservation_status']
            ];
        }
    }
    
    // Get pending borrow requests from Transaction_Requests
    if ($status === 'all' || $status === 'pending') {
        $stmt = $db->prepare("
            SELECT 
                tr.request_id,
                tr.isbn,
                tr.request_date,
                b.title,
                b.author,
                b.pic_path,
                TIMESTAMPDIFF(HOUR, tr.request_date, NOW()) as hours_old,
                TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(tr.request_date, INTERVAL 24 HOUR)) as minutes_until_expiry
            FROM Transaction_Requests tr
            JOIN Books b ON tr.isbn = b.isbn
            WHERE tr.requester_email = :email AND tr.status = 'Pending'
            ORDER BY tr.request_date DESC
        ");
        $stmt->execute([':email' => $email]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $minutesRemaining = (int)$row['minutes_until_expiry'];
            $hoursRemaining = floor($minutesRemaining / 60);
            $minsRemaining = $minutesRemaining % 60;
            
            $transactions[] = [
                'type' => 'pending',
                'request_id' => $row['request_id'],
                'isbn' => $row['isbn'],
                'title' => $row['title'],
                'author' => $row['author'],
                'pic_path' => $row['pic_path'],
                'request_date' => $row['request_date'],
                'hours_old' => (int)$row['hours_old'],
                'expires_in_hours' => max(0, $hoursRemaining),
                'expires_in_minutes' => max(0, $minsRemaining),
                'is_expired' => $minutesRemaining <= 0,
                'status' => 'Pending'
            ];
        }
        
        // Get pending return requests from Notifications
        $returnNotifStmt = $db->prepare("
            SELECT n.user_email, n.message, n.sent_at
            FROM Notifications n
            WHERE n.user_email = :email AND n.type = 'ReturnRequestPending'
            ORDER BY n.sent_at DESC
        ");
        $returnNotifStmt->execute([':email' => $email]);
        
        while ($notif = $returnNotifStmt->fetch(PDO::FETCH_ASSOC)) {
            // Extract transaction_id from message: "Return request for Transaction #123 is pending..."
            if (preg_match('/Transaction\s+#(\d+)/', $notif['message'], $matches)) {
                $transactionId = (int)$matches[1];
                
                // Get book details for this transaction
                $txStmt = $db->prepare("
                    SELECT 
                        at.transaction_id,
                        at.copy_id,
                        at.issue_date,
                        at.due_date,
                        b.isbn,
                        b.title,
                        b.author,
                        b.pic_path
                    FROM Approved_Transactions at
                    JOIN Transaction_Requests tr ON at.request_id = tr.request_id
                    JOIN Book_Copies bc ON at.copy_id = bc.copy_id
                    JOIN Books b ON bc.isbn = b.isbn
                    WHERE at.transaction_id = :tid AND at.status = 'Borrowed'
                ");
                $txStmt->execute([':tid' => $transactionId]);
                $txRow = $txStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($txRow) {
                    $transactions[] = [
                        'type' => 'pending_return',
                        'transaction_id' => $txRow['transaction_id'],
                        'isbn' => $txRow['isbn'],
                        'title' => $txRow['title'],
                        'author' => $txRow['author'],
                        'pic_path' => $txRow['pic_path'],
                        'copy_id' => $txRow['copy_id'],
                        'issue_date' => $txRow['issue_date'],
                        'due_date' => $txRow['due_date'],
                        'request_date' => $notif['sent_at'],
                        'status' => 'Pending Return'
                    ];
                }
            }
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($transactions),
        'transactions' => $transactions
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch transactions: ' . $e->getMessage()
    ]);
}
?>
