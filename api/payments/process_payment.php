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
include_once '../lib/enhanced_notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

$payload = json_decode(file_get_contents('php://input')) ?: new stdClass();

$userEmail = $payload->user_email ?? null;
$fineIds = $payload->fine_ids ?? [];
$transactionIds = $payload->transaction_ids ?? [];
$paymentMethod = $payload->payment_method ?? 'cash';

if (!$userEmail || (empty($fineIds) && empty($transactionIds))) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'user_email and at least one of fine_ids or transaction_ids are required',
    ]);
    exit;
}

try {
    $db->beginTransaction();
    
    $totalAmount = 0;
    
    // Process pending fines (overdue books not yet returned)
    // Return these books and create fines for them
    if (!empty($transactionIds)) {
        foreach ($transactionIds as $transactionId) {
            // Get transaction details
            $transactionStmt = $db->prepare('
                SELECT at.transaction_id, at.due_date, at.copy_id, tr.requester_email, b.title
                FROM Approved_Transactions at
                JOIN Transaction_Requests tr ON at.request_id = tr.request_id
                JOIN Book_Copies bc ON at.copy_id = bc.copy_id
                JOIN Books b ON bc.isbn = b.isbn
                WHERE at.transaction_id = ? AND tr.requester_email = ?
                AND (at.status = "Borrowed" OR at.status = "Overdue")
                AND at.return_date IS NULL
            ');
            $transactionStmt->execute([$transactionId, $userEmail]);
            $transaction = $transactionStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) continue;
            
            // Calculate fine
            $dueDate = new DateTime($transaction['due_date']);
            $now = new DateTime();
            
            if ($now > $dueDate) {
                $daysOverdue = $now->diff($dueDate)->days;
                $fineAmount = $daysOverdue * 10; // 10 TK per day
                $totalAmount += $fineAmount;
                
                // Create fine record (unpaid initially)
                $fineStmt = $db->prepare('
                    INSERT INTO Fines (transaction_id, user_email, amount, description, paid)
                    VALUES (?, ?, ?, ?, FALSE)
                ');
                $description = "Overdue fine for '{$transaction['title']}' ({$daysOverdue} days)";
                $fineStmt->execute([$transactionId, $userEmail, $fineAmount, $description]);
                
                $createdFineId = $db->lastInsertId();
                $fineIds[] = $createdFineId; // Add to the fineIds array for payment linking
                
                // Return the book
                $returnStmt = $db->prepare('
                    UPDATE Approved_Transactions 
                    SET return_date = CURDATE(), status = "Returned"
                    WHERE transaction_id = ?
                ');
                $returnStmt->execute([$transactionId]);
                
                // Update copy status to Available
                $updateCopyStmt = $db->prepare('
                    UPDATE Book_Copies 
                    SET status = "Available"
                    WHERE copy_id = ?
                ');
                $updateCopyStmt->execute([$transaction['copy_id']]);
            }
        }
    }

    // Calculate total amount from existing fines
    $fineStmt = $db->prepare('SELECT SUM(amount) as total FROM Fines WHERE fine_id = ? AND user_email = ?');
    
    foreach ($fineIds as $fineId) {
        $fineStmt->execute([$fineId, $userEmail]);
        $row = $fineStmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $totalAmount += (float)$row['total'];
        }
    }
    
    // Create a payment record
    $paymentStmt = $db->prepare('INSERT INTO Payments (amount, status, payment_method, paid_at) 
        VALUES (:amount, :status, :payment_method, :paid_at)');

    // Insert payment
    $paymentStmt->execute([
        ':amount' => $totalAmount,
        ':status' => 'Completed',
        ':payment_method' => $paymentMethod,
        ':paid_at' => date('Y-m-d H:i:s'),
    ]);

    $paymentId = $db->lastInsertId();

    // Link fines to payment and mark as paid
    $updateFineStmt = $db->prepare('UPDATE Fines SET paid = TRUE, payment_date = ? 
        WHERE fine_id = ? AND user_email = ?');
    
    $linkPaymentStmt = $db->prepare('INSERT INTO fine_payment (fine_id, payment_id) VALUES (?, ?)');

    foreach ($fineIds as $fineId) {
        $updateFineStmt->execute([
            date('Y-m-d H:i:s'),
            $fineId,
            $userEmail
        ]);
        $linkPaymentStmt->execute([$fineId, $paymentId]);
    }

    $db->commit();
    
    // Send payment confirmation notification
    notifyPaymentConfirmation($db, $userEmail, $totalAmount, $paymentMethod);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment successful',
        'payment_id' => (int)$paymentId,
        'amount' => $totalAmount,
    ]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Payment processing failed: ' . $e->getMessage(),
    ]);
}
?>
