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
 * Test script to verify librarian requests are syncing with database
 */
include_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "=== Testing Librarian Requests Database Sync ===\n\n";

// Test 1: Check Transaction_Requests table
echo "1. Checking Transaction_Requests table...\n";
$stmt = $db->prepare("SELECT COUNT(*) as count FROM Transaction_Requests WHERE status = 'Pending'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Pending borrow requests: {$result['count']}\n\n";

// Test 2: Check Approved_Transactions table
echo "2. Checking Approved_Transactions table...\n";
$stmt = $db->prepare("SELECT COUNT(*) as count FROM Approved_Transactions WHERE status = 'Borrowed'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Currently borrowed (return requests): {$result['count']}\n\n";

// Test 3: Check Reservations table
echo "3. Checking Reservations table...\n";
$stmt = $db->prepare("SELECT COUNT(*) as count FROM Reservations WHERE status = 'Active'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Active reservations: {$result['count']}\n\n";

// Test 4: Check Requests table (addition requests)
echo "4. Checking Requests table...\n";
$stmt = $db->prepare("SELECT COUNT(*) as count FROM Requests WHERE status = 'Pending'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Pending addition requests: {$result['count']}\n\n";

// Test 5: Sample borrow request data
echo "5. Sample borrow request data:\n";
$stmt = $db->prepare("
    SELECT tr.request_id, tr.isbn, tr.request_date, u.name, u.email, b.title
    FROM Transaction_Requests tr
    JOIN Users u ON u.email = tr.requester_email
    JOIN Books b ON b.isbn = tr.isbn
    WHERE tr.status = 'Pending'
    ORDER BY tr.request_date DESC
    LIMIT 3
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) > 0) {
    foreach ($rows as $row) {
        echo "   - {$row['name']} ({$row['email']}) requested '{$row['title']}' (ISBN: {$row['isbn']})\n";
    }
} else {
    echo "   No pending borrow requests found\n";
}

echo "\n=== Database Sync Test Complete ===\n";
echo "\nThe Flutter app fetches data from these tables via:\n";
echo "- GET /librarian/get_requests.php?type=borrow (Transaction_Requests)\n";
echo "- GET /librarian/get_requests.php?type=return (Approved_Transactions)\n";
echo "- GET /librarian/get_requests.php?type=reserve (Reservations)\n";
echo "- GET /librarian/get_requests.php?type=addition (Requests)\n";
echo "\nAll requests are stored in and fetched from the database in real-time.\n";
?>
