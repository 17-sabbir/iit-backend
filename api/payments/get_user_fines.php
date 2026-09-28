<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');
include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$userEmail = $_GET['user_email'] ?? null;

if (!$userEmail) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'user_email is required',
    ]);
    exit;
}

try {
    // Get outstanding fines for the user (already created fines)
    $query = 'SELECT 
        fine_id,
        amount,
        description,
        paid,
        payment_date
    FROM Fines 
    WHERE user_email = :email AND paid = FALSE
    ORDER BY fine_id DESC';

    $stmt = $db->prepare($query);
    $stmt->execute([':email' => $userEmail]);

    $fines = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fines[] = [
            'fine_id' => (int)$row['fine_id'],
            'amount' => (float)$row['amount'],
            'description' => $row['description'],
            'paid' => (bool)$row['paid'],
            'payment_date' => $row['payment_date'],
        ];
    }

    // Calculate total outstanding fines from existing records
    $totalOutstanding = 0;
    foreach ($fines as $fine) {
        $totalOutstanding += $fine['amount'];
    }
    
    // Calculate pending fines for currently overdue books (not yet returned)
    $overdueQuery = 'SELECT 
        at.transaction_id,
        at.due_date,
        b.title,
        DATEDIFF(CURDATE(), at.due_date) as days_overdue
    FROM Approved_Transactions at
    JOIN Transaction_Requests tr ON at.request_id = tr.request_id
    JOIN Book_Copies bc ON at.copy_id = bc.copy_id
    JOIN Books b ON bc.isbn = b.isbn
    WHERE tr.requester_email = :email 
    AND (at.status = "Borrowed" OR at.status = "Overdue")
    AND at.return_date IS NULL
    AND at.due_date < CURDATE()';
    
    $overdueStmt = $db->prepare($overdueQuery);
    $overdueStmt->execute([':email' => $userEmail]);
    
    $pendingFines = [];
    while ($row = $overdueStmt->fetch(PDO::FETCH_ASSOC)) {
        $daysOverdue = (int)$row['days_overdue'];
        $fineAmount = $daysOverdue * 10; // 10 TK per day
        
        $pendingFines[] = [
            'transaction_id' => (int)$row['transaction_id'],
            'book_title' => $row['title'],
            'days_overdue' => $daysOverdue,
            'amount' => (float)$fineAmount,
            'description' => "Overdue fine for '{$row['title']}' ({$daysOverdue} days)",
        ];
        
        $totalOutstanding += $fineAmount;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'total_outstanding' => $totalOutstanding,
        'fines_count' => count($fines),
        'fines' => $fines,
        'pending_fines_count' => count($pendingFines),
        'pending_fines' => $pendingFines,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}
?>
