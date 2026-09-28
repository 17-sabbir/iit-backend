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

$database = new Database();
$db = $database->getConnection();

// Clean up expired borrow requests
cleanupExpiredBorrowRequests($db);

try {
    // 1. Total Books - count distinct ISBNs
    $totalBooksStmt = $db->query("SELECT COUNT(DISTINCT isbn) as total FROM Books WHERE title NOT LIKE '[DELETED]%'");
    $totalBooksData = $totalBooksStmt->fetch(PDO::FETCH_ASSOC);
    $totalBooks = (int)$totalBooksData['total'];

    // 2. Pending Returns - derive from notifications of pending return requests
    $pendingReturns = 0;
    try {
        $pendingNotifStmt = $db->prepare("SELECT message FROM Notifications WHERE type = 'ReturnRequestPending'");
        $pendingNotifStmt->execute();
        $tids = [];
        while ($row = $pendingNotifStmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row['message']) && preg_match('/Transaction\\s+#(\\d+)/', $row['message'], $m)) {
                $tids[$m[1]] = true;
            }
        }
        $pendingReturns = count($tids);
    } catch (Exception $e) {
        $pendingReturns = 0; // fail-safe
    }

    // 3. Pending Requests - pending borrow requests
    $pendingRequestsStmt = $db->query("
        SELECT COUNT(*) as pending 
        FROM Transaction_Requests 
        WHERE status = 'Pending'
    ");
    $pendingRequestsData = $pendingRequestsStmt->fetch(PDO::FETCH_ASSOC);
    $pendingRequests = (int)$pendingRequestsData['pending'];

    // 4. Fines Collected Today - sum of paid fines today
    $finesCollectedStmt = $db->query("
        SELECT IFNULL(SUM(amount), 0) as collected 
        FROM Fines 
        WHERE paid = 1 AND DATE(payment_date) = CURDATE()
    ");
    $finesCollectedData = $finesCollectedStmt->fetch(PDO::FETCH_ASSOC);
    $finesCollectedToday = (float)$finesCollectedData['collected'];

    // 5. Return Approvals - pending return requests awaiting librarian approval
        // 5. Return Approvals - approximate using same pending notifications count
        $returnApprovals = $pendingReturns;

    // 6. New Book Requests - pending book addition requests
    $newBookRequestsStmt = $db->query("
        SELECT COUNT(*) as pending 
        FROM Requests 
        WHERE status = 'Pending'
    ");
    $newBookRequestsData = $newBookRequestsStmt->fetch(PDO::FETCH_ASSOC);
    $newBookRequests = (int)$newBookRequestsData['pending'];

    // 7. Payment Verifications - unpaid fines awaiting verification
    $paymentVerificationsStmt = $db->query("
        SELECT COUNT(*) as pending 
        FROM Fines 
        WHERE paid = 0
    ");
    $paymentVerificationsData = $paymentVerificationsStmt->fetch(PDO::FETCH_ASSOC);
    $paymentVerifications = (int)$paymentVerificationsData['pending'];

    // 8. Recent Activity - last 5 transactions
    $recentActivityStmt = $db->query("
        SELECT 
            at.transaction_id,
            at.copy_id,
            at.issue_date,
            at.return_date,
            at.status,
            u.name,
            tr.requester_email as email,
            b.title
        FROM Approved_Transactions at
        JOIN Transaction_Requests tr ON at.request_id = tr.request_id
        JOIN Users u ON tr.requester_email = u.email
        JOIN Book_Copies bc ON at.copy_id = bc.copy_id
        JOIN Books b ON bc.isbn = b.isbn
        ORDER BY at.issue_date DESC
        LIMIT 5
    ");
    $recentActivity = [];
    while ($row = $recentActivityStmt->fetch(PDO::FETCH_ASSOC)) {
        $recentActivity[] = $row;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_books' => $totalBooks,
            'pending_returns' => $pendingReturns,
            'pending_requests' => $pendingRequests,
            'fines_collected_today' => $finesCollectedToday,
            'return_approvals' => $returnApprovals,
            'new_book_requests' => $newBookRequests,
            'payment_verifications' => $paymentVerifications,
        ],
        'recent_activity' => $recentActivity,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dashboard stats: ' . $e->getMessage()
    ]);
}
?>
