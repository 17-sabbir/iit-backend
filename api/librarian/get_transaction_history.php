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

$database = new Database();
$db = $database->getConnection();

$filter = $_GET['filter'] ?? 'All';
$search = trim($_GET['search'] ?? '');
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

try {
    $transactions = [];
    
    // Fetch Borrow transactions (Completed borrows)
    if ($filter === 'All' || $filter === 'Borrow') {
        $sql = "SELECT 
                    'Borrow' as type,
                    b.title as book_title,
                    u.email as user_id,
                    u.name as user_name,
                    DATE(at.issue_date) as date,
                    TIME_FORMAT(at.issue_date, '%h:%i %p') as time,
                    'Completed' as status
                FROM Approved_Transactions at
                JOIN Transaction_Requests tr ON tr.request_id = at.request_id
                JOIN Users u ON u.email = tr.requester_email
                JOIN Books b ON b.isbn = tr.isbn
                WHERE at.status IN ('Borrowed', 'Returned')";
        
        if ($search !== '') {
            $sql .= " AND (u.email LIKE :search1 OR u.name LIKE :search2 OR b.title LIKE :search3)";
        }
        if ($startDate !== '') {
            $sql .= " AND DATE(at.issue_date) >= :start1";
        }
        if ($endDate !== '') {
            $sql .= " AND DATE(at.issue_date) <= :end1";
        }
        
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':search1', $like);
            $stmt->bindValue(':search2', $like);
            $stmt->bindValue(':search3', $like);
        }
        if ($startDate !== '') {
            $stmt->bindValue(':start1', $startDate);
        }
        if ($endDate !== '') {
            $stmt->bindValue(':end1', $endDate);
        }
        $stmt->execute();
        $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Fetch Return transactions
    if ($filter === 'All' || $filter === 'Return') {
        $sql = "SELECT 
                    'Return' as type,
                    b.title as book_title,
                    u.email as user_id,
                    u.name as user_name,
                    DATE(at.return_date) as date,
                    TIME_FORMAT(at.return_date, '%h:%i %p') as time,
                    'Completed' as status
                FROM Approved_Transactions at
                JOIN Transaction_Requests tr ON tr.request_id = at.request_id
                JOIN Users u ON u.email = tr.requester_email
                JOIN Books b ON b.isbn = tr.isbn
                WHERE at.status = 'Returned' AND at.return_date IS NOT NULL";
        
        if ($search !== '') {
            $sql .= " AND (u.email LIKE :search1 OR u.name LIKE :search2 OR b.title LIKE :search3)";
        }
        if ($startDate !== '') {
            $sql .= " AND DATE(at.return_date) >= :start1";
        }
        if ($endDate !== '') {
            $sql .= " AND DATE(at.return_date) <= :end1";
        }
        
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':search1', $like);
            $stmt->bindValue(':search2', $like);
            $stmt->bindValue(':search3', $like);
        }
        if ($startDate !== '') {
            $stmt->bindValue(':start1', $startDate);
        }
        if ($endDate !== '') {
            $stmt->bindValue(':end1', $endDate);
        }
        $stmt->execute();
        $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Fetch Fine Payment transactions
    if ($filter === 'All' || $filter === 'Fine Payment') {
        $sql = "SELECT 
                    'Fine Payment' as type,
                    COALESCE(b.title, 'Various Books') as book_title,
                    f.user_email as user_id,
                    u.name as user_name,
                    DATE(p.paid_at) as date,
                    TIME_FORMAT(p.paid_at, '%h:%i %p') as time,
                    CONCAT('BDT ', FORMAT(p.amount, 2)) as amount,
                    'Paid' as status
                FROM Payments p
                JOIN fine_payment fp ON fp.payment_id = p.payment_id
                JOIN Fines f ON f.fine_id = fp.fine_id
                JOIN Users u ON u.email = f.user_email
                LEFT JOIN Approved_Transactions at ON at.transaction_id = f.transaction_id
                LEFT JOIN Transaction_Requests tr ON tr.request_id = at.request_id
                LEFT JOIN Books b ON b.isbn = tr.isbn
                WHERE p.status = 'Completed'";
        
        if ($search !== '') {
            $sql .= " AND (f.user_email LIKE :search1 OR u.name LIKE :search2 OR b.title LIKE :search3)";
        }
        if ($startDate !== '') {
            $sql .= " AND DATE(p.paid_at) >= :start1";
        }
        if ($endDate !== '') {
            $sql .= " AND DATE(p.paid_at) <= :end1";
        }
        
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':search1', $like);
            $stmt->bindValue(':search2', $like);
            $stmt->bindValue(':search3', $like);
        }
        if ($startDate !== '') {
            $stmt->bindValue(':start1', $startDate);
        }
        if ($endDate !== '') {
            $stmt->bindValue(':end1', $endDate);
        }
        $stmt->execute();
        $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Fetch Reservation transactions
    if ($filter === 'All' || $filter === 'Reservation') {
        $sql = "SELECT 
                    'Reservation' as type,
                    b.title as book_title,
                    r.user_email as user_id,
                    u.name as user_name,
                    DATE(r.created_at) as date,
                    TIME_FORMAT(r.created_at, '%h:%i %p') as time,
                    CASE 
                        WHEN r.status = 'Active' THEN 'Active'
                        WHEN r.status = 'Notified' THEN 'Notified'
                        WHEN r.status = 'Fulfilled' THEN 'Fulfilled'
                        WHEN r.status = 'Expired' THEN 'Expired'
                        ELSE r.status
                    END as status
                FROM Reservations r
                JOIN Users u ON u.email = r.user_email
                JOIN Books b ON b.isbn = r.isbn";
        
        $whereClauses = [];
        if ($search !== '') {
            $whereClauses[] = "(r.user_email LIKE :search1 OR u.name LIKE :search2 OR b.title LIKE :search3)";
        }
        if ($startDate !== '') {
            $whereClauses[] = "DATE(r.created_at) >= :start1";
        }
        if ($endDate !== '') {
            $whereClauses[] = "DATE(r.created_at) <= :end1";
        }
        
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':search1', $like);
            $stmt->bindValue(':search2', $like);
            $stmt->bindValue(':search3', $like);
        }
        if ($startDate !== '') {
            $stmt->bindValue(':start1', $startDate);
        }
        if ($endDate !== '') {
            $stmt->bindValue(':end1', $endDate);
        }
        $stmt->execute();
        $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Sort by date descending
    usort($transactions, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    echo json_encode([
        'success' => true,
        'count' => count($transactions),
        'transactions' => $transactions
    ]);
    
} catch (Exception $e) {
    error_log('Transaction history error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch transaction history: ' . $e->getMessage()
    ]);
}
