<?php
/**
 * Get Notifications for a User
 * Retrieves notifications for the authenticated user
 */

require_once '../../config/database.php';
require_once '../lib/notification_helpers.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get user email from request
$input = json_decode(file_get_contents('php://input'), true) ?? $_GET;
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
$limit = isset($input['limit']) ? (int)$input['limit'] : 50;

if ($email === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email is required.'
    ]);
    exit;
}

try {
    // Fetch paginated notifications plus the total count
    $notifications = getUserNotifications($db, $email, $limit);

    $countStmt = $db->prepare('SELECT COUNT(*) AS total_count FROM Notifications WHERE user_email = :email');
    $countStmt->execute([':email' => $email]);
    $totalCount = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total_count'] ?? 0);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($notifications),
        'total_count' => $totalCount,
        'notifications' => $notifications
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch notifications: ' . $e->getMessage()
    ]);
}
?>
