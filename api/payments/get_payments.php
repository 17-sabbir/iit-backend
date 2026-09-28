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

$userEmail = $_GET['user_email'] ?? null;
$startDate = $_GET['startDate'] ?? date('Y-m-01');
$endDate = $_GET['endDate'] ?? date('Y-m-d');

if (!$userEmail) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'user_email is required',
    ]);
    exit;
}

try {
    // Get payment history (paid fines) within date range
    $historyQuery = 'SELECT 
        f.fine_id as id,
        f.amount,
        f.description,
        f.paid,
        f.payment_date,
        p.payment_id,
        p.status as payment_status,
        "Fine Payment" as reason,
        COALESCE(b.title, "Book") as bookTitle,
        COALESCE(b.author, "Unknown") as author,
        COALESCE(b.isbn, "") as isbn,
        COALESCE(b.isbn, "") as bookId
    FROM Fines f
    LEFT JOIN fine_payment fp ON f.fine_id = fp.fine_id
    LEFT JOIN Payments p ON fp.payment_id = p.payment_id
    LEFT JOIN Approved_Transactions at ON f.transaction_id = at.transaction_id
    LEFT JOIN Transaction_Requests tr ON at.request_id = tr.request_id
    LEFT JOIN Book_Copies bc ON at.copy_id = bc.copy_id
    LEFT JOIN Books b ON bc.isbn = b.isbn
    WHERE f.user_email = :email AND f.paid = TRUE
    AND (f.payment_date IS NULL OR DATE(f.payment_date) BETWEEN :start_date AND :end_date)
    ORDER BY f.payment_date DESC
    LIMIT 50';

    $stmt = $db->prepare($historyQuery);
    $stmt->execute([
        ':email' => $userEmail,
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ]);

    $paymentHistory = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paymentHistory[] = [
            'id' => (int)$row['id'],
            'amount' => (float)$row['amount'],
            'description' => $row['description'],
            'paid' => (bool)$row['paid'],
            'payment_date' => $row['payment_date'],
            'payment_id' => $row['payment_id'] ? (int)$row['payment_id'] : null,
            'payment_status' => $row['payment_status'] ?? 'Completed',
            'bookTitle' => $row['bookTitle'],
            'author' => $row['author'],
            'isbn' => $row['isbn'],
            'bookId' => $row['bookId'],
            'reason' => $row['reason'],
            'timeAgo' => $row['payment_date'] ? (new DateTime($row['payment_date']))->format('M d, Y') : 'N/A',
        ];
    }

    // Get outstanding (unpaid) fines
    $outstandingQuery = 'SELECT 
        f.fine_id as id,
        f.amount,
        f.description,
        f.paid,
        f.payment_date,
        COALESCE(b.title, "Book") as bookTitle,
        COALESCE(b.author, "Unknown") as author,
        COALESCE(b.isbn, "") as isbn,
        COALESCE(b.isbn, "") as bookId,
        "Fine Payment" as reason
    FROM Fines f
    LEFT JOIN Approved_Transactions at ON f.transaction_id = at.transaction_id
    LEFT JOIN Transaction_Requests tr ON at.request_id = tr.request_id
    LEFT JOIN Book_Copies bc ON at.copy_id = bc.copy_id
    LEFT JOIN Books b ON bc.isbn = b.isbn
    WHERE f.user_email = :email AND f.paid = FALSE
    ORDER BY f.fine_id DESC';

    $outStmt = $db->prepare($outstandingQuery);
    $outStmt->execute([':email' => $userEmail]);

    $outstandingFines = [];
    $totalOutstanding = 0;
    while ($row = $outStmt->fetch(PDO::FETCH_ASSOC)) {
        $amount = (float)$row['amount'];
        $totalOutstanding += $amount;
        $outstandingFines[] = [
            'id' => (int)$row['id'],
            'amount' => $amount,
            'description' => $row['description'],
            'paid' => (bool)$row['paid'],
            'payment_date' => $row['payment_date'],
            'bookTitle' => $row['bookTitle'],
            'author' => $row['author'],
            'isbn' => $row['isbn'],
            'bookId' => $row['bookId'],
            'reason' => $row['reason'],
        ];
    }

    // Calculate stats
    $totalPaidThisMonth = 0;
    foreach ($paymentHistory as $payment) {
        $totalPaidThisMonth += $payment['amount'];
    }

    $stats = [
        'totalOutstanding' => $totalOutstanding,
        'totalPaidThisMonth' => $totalPaidThisMonth,
        'totalTransactions' => count($paymentHistory),
    ];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'paymentHistory' => $paymentHistory,
        'outstandingFines' => $outstandingFines,
        'stats' => $stats,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching payment data: ' . $e->getMessage(),
    ]);
}
