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
include_once '../lib/notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'));

$transactionId = $data->transaction_id ?? null;
$copyId = $data->copy_id ?? null;
$damageFine = isset($data->damage_fine) ? floatval($data->damage_fine) : 0;
$bookCondition = isset($data->book_condition)
    ? strtolower(trim($data->book_condition))
    : '';

if (empty($transactionId) && empty($copyId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'transaction_id or copy_id is required',
    ]);
    exit;
}

try {
    $db->beginTransaction();

    // Locate active transaction
    if (!empty($transactionId)) {
        $find = $db->prepare('SELECT at.transaction_id, at.copy_id, tr.requester_email, at.issue_date, at.due_date 
            FROM Approved_Transactions at 
            JOIN Transaction_Requests tr ON at.request_id = tr.request_id
            WHERE at.transaction_id = :tid AND at.status = "Borrowed"');
        $find->execute([':tid' => $transactionId]);
    } else {
        $find = $db->prepare('SELECT at.transaction_id, at.copy_id, tr.requester_email, at.issue_date, at.due_date 
            FROM Approved_Transactions at 
            JOIN Transaction_Requests tr ON at.request_id = tr.request_id
            WHERE at.copy_id = :cid AND at.status = "Borrowed" ORDER BY at.issue_date DESC LIMIT 1');
        $find->execute([':cid' => $copyId]);
    }

    $txn = $find->fetch(PDO::FETCH_ASSOC);
    if (!$txn) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Active borrow transaction not found',
        ]);
        exit;
    }

    // Overdue calculation
    $due = new DateTime($txn['due_date']);
    $now = new DateTime();
    $fineAmount = 0;
    $daysOverdue = 0;
    if ($now > $due) {
        $daysOverdue = $now->diff($due)->days;
        $fineAmount = $daysOverdue * 10; // 10 BDT per day
    }

    // Close transaction
    $close = $db->prepare('UPDATE Approved_Transactions SET status = "Returned", return_date = NOW() WHERE transaction_id = :tid');
    $close->execute([':tid' => $txn['transaction_id']]);

    // Update copy status/condition based on submitted condition
    $statusForCopy = 'Available';
    if ($bookCondition === 'lost') {
        $statusForCopy = 'Lost';
    } elseif ($bookCondition === 'discarded') {
        $statusForCopy = 'Discarded';
    }

    $copyUpdate = $db->prepare('UPDATE Book_Copies SET status = :status, condition_note = :note WHERE copy_id = :cid');
    $copyUpdate->execute([
        ':status' => $statusForCopy,
        ':note' => $bookCondition ? ucfirst($bookCondition) : null,
        ':cid' => $txn['copy_id'],
    ]);

    // No Return_Requests table used; processing proceeds without updating auxiliary tables.

    // Get ISBN and book title for notifications and reservation handling
    $bookStmt = $db->prepare('SELECT bc.isbn, b.title FROM Book_Copies bc JOIN Books b ON bc.isbn = b.isbn WHERE bc.copy_id = :cid');
    $bookStmt->execute([':cid' => $txn['copy_id']]);
    $bookRow = $bookStmt->fetch(PDO::FETCH_ASSOC);

    // When a copy becomes available, activate the 12-hour window for queue #1 (if any)
    if ($bookRow && isset($bookRow['isbn'])) {
        ensureReservationWindow($db, $bookRow['isbn']);
    }

    // Notify user that their return request has been approved/processed
    if ($bookRow && isset($bookRow['title'])) {
        try {
            notifyReturnRequestApproved($db, $txn['requester_email'], $bookRow['title']);
        } catch (Exception $e) {
            // Non-blocking: log and continue
            error_log('Return approval notification failed: ' . $e->getMessage());
        }
    }

    // Record fine if overdue
    $fineId = null;
    if ($fineAmount > 0) {
        $fineInsert = $db->prepare('INSERT INTO Fines (
            transaction_id, user_email, amount, description, paid
        ) VALUES (
            :transaction_id, :user_email, :amount, :description, 0
        )');
        $fineInsert->execute([
            ':transaction_id' => $txn['transaction_id'],
            ':user_email' => $txn['requester_email'],
            ':amount' => $fineAmount,
            ':description' => "Late return: {$daysOverdue} days overdue",
        ]);
        $fineId = $db->lastInsertId();
    }

    // Record damage/lost fine if provided
    $damageFineId = null;
    if ($damageFine > 0) {
        $label = in_array($bookCondition, ['damaged', 'discarded', 'lost'])
            ? ucfirst($bookCondition)
            : 'Damaged';
        $fineInsert = $db->prepare('INSERT INTO Fines (
            transaction_id, user_email, amount, description, paid
        ) VALUES (
            :transaction_id, :user_email, :amount, :description, 0
        )');
        $fineInsert->execute([
            ':transaction_id' => $txn['transaction_id'],
            ':user_email' => $txn['requester_email'],
            ':amount' => $damageFine,
            ':description' => "$label fine",
        ]);
        $damageFineId = $db->lastInsertId();
    }

    $db->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Book returned successfully',
        'fine' => $fineAmount,
        'fine_id' => $fineId,
        'damage_fine' => $damageFine,
        'damage_fine_id' => $damageFineId,
    ]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to return book: ' . $e->getMessage(),
    ]);
}
?>
