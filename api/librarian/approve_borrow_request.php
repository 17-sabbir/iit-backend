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
include_once '../lib/notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'));
$requestId = $data->request_id ?? null;
$selectedCopyId = isset($data->copy_id) ? trim($data->copy_id) : '';

if (empty($requestId)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'request_id is required']);
    exit;
}

if ($selectedCopyId === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'copy_id is required']);
    exit;
}

try {
    // Load request
    $q = $db->prepare('SELECT tr.request_id, tr.isbn, tr.requester_email, u.role
                       FROM Transaction_Requests tr
                       JOIN Users u ON u.email = tr.requester_email
                       WHERE tr.request_id = :rid AND tr.status = "Pending"');
    $q->execute([':rid'=>$requestId]);
    $req = $q->fetch(PDO::FETCH_ASSOC);
    if (!$req) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Pending request not found']);
        exit;
    }

    // Set loan days and borrow limit based on role
    $role = strtolower($req['role']);
    $loanDays = match($role){
        'student'=>7,
        'teacher'=>15,
        'librarian'=>30,
        'director'=>30,
        default=>14,
    };
    $borrowLimit = match($role){
        'student'=>2,
        'teacher'=>5,
        'librarian'=>10,
        'director'=>10,
        default=>2,
    };

    // Check if user has exceeded borrow limit
    $borrowedCountStmt = $db->prepare('
        SELECT COUNT(*) as count
        FROM Approved_Transactions at
        JOIN Transaction_Requests tr ON tr.request_id = at.request_id
        WHERE tr.requester_email = :email AND at.status = "Borrowed"
    ');
    $borrowedCountStmt->execute([':email'=>$req['requester_email']]);
    $borrowedCount = (int)$borrowedCountStmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($borrowedCount >= $borrowLimit) {
        http_response_code(400);
        echo json_encode([
            'success'=>false,
            'message'=>"User has reached their borrowing limit ($borrowLimit books). They currently have $borrowedCount books borrowed."
        ]);
        exit;
    }
    
    // Check if user has exceeded fine limit (200 TK)
    $fineStmt = $db->prepare('
        SELECT COALESCE(SUM(amount), 0) as total_fines
        FROM Fines
        WHERE user_email = :email AND paid = 0
    ');
    $fineStmt->execute([':email'=>$req['requester_email']]);
    $totalFines = (float)$fineStmt->fetch(PDO::FETCH_ASSOC)['total_fines'];
    
    if ($totalFines >= 200) {
        http_response_code(400);
        echo json_encode([
            'success'=>false,
            'message'=>"User has unpaid fines totaling $totalFines TK. Fines must be below 200 TK to borrow books."
        ]);
        exit;
    }

    $db->beginTransaction();

    // Validate selected copy availability for this ISBN
    $copyStmt = $db->prepare('SELECT copy_id FROM Book_Copies WHERE copy_id = :cid AND isbn = :isbn AND status = "Available" FOR UPDATE');
    $copyStmt->execute([':cid'=>$selectedCopyId, ':isbn'=>$req['isbn']]);
    $copy = $copyStmt->fetch(PDO::FETCH_ASSOC);
    if (!$copy) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Selected copy is not available for this ISBN']);
        exit;
    }

    // Approve request
    $upd = $db->prepare('UPDATE Transaction_Requests SET status = "Approved" WHERE request_id = :rid');
    $upd->execute([':rid'=>$requestId]);

    // Create approved transaction
    $txn = $db->prepare('INSERT INTO Approved_Transactions (request_id, copy_id, issue_date, due_date, status)
                         VALUES (:rid, :cid, NOW(), DATE_ADD(NOW(), INTERVAL :days DAY), "Borrowed")');
    $txn->execute([':rid'=>$requestId, ':cid'=>$copy['copy_id'], ':days'=>$loanDays]);

    // Update copy status
    $copyUpd = $db->prepare('UPDATE Book_Copies SET status = "Borrowed" WHERE copy_id = :cid');
    $copyUpd->execute([':cid'=>$copy['copy_id']]);

    $db->commit();

    // Send notification to user
    $bookStmt = $db->prepare('SELECT title FROM Books WHERE isbn = :isbn');
    $bookStmt->execute([':isbn'=>$req['isbn']]);
    $bookTitle = $bookStmt->fetchColumn() ?: 'Unknown Book';
    $dueDate = date('M d, Y', strtotime("+{$loanDays} days"));
    notifyBorrowRequestApproved($db, $req['requester_email'], $bookTitle, $req['isbn'], $dueDate);

    echo json_encode(['success'=>true,'message'=>'Request approved','copy_id'=>$copy['copy_id']]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error approving request: '.$e->getMessage()]);
}
