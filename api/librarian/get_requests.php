<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../../config/database.php';
include_once '../lib/request_cleanup.php';
include_once '../lib/reservation_helpers.php';

$database = new Database();
$db = $database->getConnection();

// Clean up expired borrow requests (older than 24 hours)
try {
    cleanupExpiredBorrowRequests($db);
} catch (Exception $e) {
    // Non-blocking: log and continue
    error_log('Borrow request cleanup failed: ' . $e->getMessage());
}

$type = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');

function likeClause($field) {
    return "($field LIKE :q)";
}

try {
    if ($type === 'borrow') {
        $sql = "SELECT tr.request_id, tr.isbn, tr.request_date, u.name, u.email, b.title,
                TIMESTAMPDIFF(HOUR, tr.request_date, NOW()) as hours_old,
                TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(tr.request_date, INTERVAL 24 HOUR)) as minutes_until_expiry
                FROM Transaction_Requests tr
                JOIN Users u ON u.email = tr.requester_email
                JOIN Books b ON b.isbn = tr.isbn
                WHERE tr.status = 'Pending'";
        if ($search !== '') {
            // Use distinct named parameters to avoid PDO HY093 errors on repeated names
            $sql .= " AND ((u.name LIKE :q1) OR (u.email LIKE :q2) OR (b.title LIKE :q3) OR (tr.isbn LIKE :q4))";
        }
        $sql .= " ORDER BY tr.request_date DESC";
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':q1', $like);
            $stmt->bindValue(':q2', $like);
            $stmt->bindValue(':q3', $like);
            $stmt->bindValue(':q4', $like);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add expiration info to each row
        foreach ($rows as &$row) {
            $minutesRemaining = (int)$row['minutes_until_expiry'];
            $row['expires_in_hours'] = max(0, floor($minutesRemaining / 60));
            $row['expires_in_minutes'] = max(0, $minutesRemaining % 60);
            $row['is_expired'] = $minutesRemaining <= 0;
        }
        
        echo json_encode(['success'=>true,'count'=>count($rows),'items'=>$rows]);
        exit;
    }

    if ($type === 'return') {
        // Derive pending return requests from Notifications (type: ReturnRequestPending)
        // Parse transaction id from message and join to Approved_Transactions for details
        $notifSql = "SELECT user_email, message, sent_at
                     FROM Notifications
                     WHERE type = 'ReturnRequestPending'
                     ORDER BY sent_at DESC";
        $notifStmt = $db->prepare($notifSql);
        $notifStmt->execute();
        $notifs = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($notifs as $n) {
            if (!preg_match('/Transaction\s+#(\d+)/', $n['message'], $m)) {
                continue;
            }
            $tid = (int)$m[1];
                 $tx = $db->prepare("SELECT at.transaction_id, at.copy_id, at.issue_date, at.due_date, tr.isbn, u.name, u.email, b.title,
                                       GREATEST(DATEDIFF(NOW(), at.due_date), 0) AS days_overdue
                                FROM Approved_Transactions at
                                JOIN Transaction_Requests tr ON tr.request_id = at.request_id
                                JOIN Users u ON u.email = tr.requester_email
                                JOIN Book_Copies bc ON bc.copy_id = at.copy_id
                                JOIN Books b ON b.isbn = bc.isbn
                                WHERE at.transaction_id = :tid AND at.status = 'Borrowed'");
            $tx->execute([':tid' => $tid]);
            $row = $tx->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;
            $row['requested_at'] = $n['sent_at'];
            // Apply search filter client-side
            if ($search !== '') {
                $like = strtolower("%$search%");
                $hay = strtolower(($row['name'] ?? '') . ' ' . ($row['email'] ?? '') . ' ' . ($row['title'] ?? '') . ' ' . ($row['isbn'] ?? ''));
                if (strpos($hay, trim($search)) === false && strpos($hay, str_replace('%','',$like)) === false) {
                    continue;
                }
            }
            $items[] = $row;
        }
        echo json_encode(['success'=>true,'count'=>count($items),'items'=>$items]);
        exit;
    }

    if ($type === 'reserve') {
        // Clean up and renumber queues before fetching
        $isbnStmt = $db->prepare('SELECT DISTINCT isbn FROM Reservations WHERE status = "Active"');
        $isbnStmt->execute();
        $isbns = $isbnStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($isbns as $isbn) {
            cleanupExpiredReservationsForIsbn($db, $isbn);
            renumberReservationQueue($db, $isbn);
        }
        
        $sql = "SELECT r.reservation_id, r.isbn, r.queue_position, r.status, r.created_at,
                       u.name, u.email, b.title
                FROM Reservations r
                JOIN Users u ON u.email = r.user_email
                JOIN Books b ON b.isbn = r.isbn
                WHERE r.status = 'Active'";
        if ($search !== '') {
            $sql .= " AND ((u.name LIKE :q1) OR (u.email LIKE :q2) OR (b.title LIKE :q3) OR (r.isbn LIKE :q4))";
        }
        $sql .= " ORDER BY r.isbn ASC, r.queue_position ASC, r.created_at ASC";
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':q1', $like);
            $stmt->bindValue(':q2', $like);
            $stmt->bindValue(':q3', $like);
            $stmt->bindValue(':q4', $like);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'count'=>count($rows),'items'=>$rows]);
        exit;
    }

    if ($type === 'addition') {
        $sql = "SELECT req.request_id, req.title AS requested_title, req.isbn, req.requester_identifier AS email,
                       COALESCE(u.name, 'Unknown') AS name, req.status, req.approved_by, req.approved_at, req.description
                FROM Requests req
                LEFT JOIN Users u ON u.email = req.requester_identifier
                WHERE req.status = 'Pending'";
        if ($search !== '') {
            $sql .= " AND ((u.name LIKE :q1) OR (req.requester_identifier LIKE :q2) OR (req.title LIKE :q3) OR (req.isbn LIKE :q4))";
        }
        $sql .= " ORDER BY req.request_id DESC";
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':q1', $like);
            $stmt->bindValue(':q2', $like);
            $stmt->bindValue(':q3', $like);
            $stmt->bindValue(':q4', $like);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'count'=>count($rows),'items'=>$rows]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid type']);
} catch (Exception $e) {
    // Avoid 500 to keep UI graceful; return empty lists as success
    error_log('get_requests error: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode(['success'=>true,'items'=>[],'count'=>0]);
}
