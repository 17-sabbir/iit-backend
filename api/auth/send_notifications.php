<?php
/**
 * Manual trigger for sending notifications (for testing or cron jobs)
 * Can be called to immediately send due date and fine reminders
 */

require_once '../../config/database.php';
require_once '../lib/notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true) ?? $_GET;
$type = $input['type'] ?? 'all'; // 'due_dates', 'fines', or 'all'

try {
    $results = [
        'due_date_reminders' => 0,
        'fine_reminders' => 0
    ];
    
    if ($type === 'due_dates' || $type === 'all') {
        $results['due_date_reminders'] = sendDueDateReminders($db);
    }
    
    if ($type === 'fines' || $type === 'all') {
        $results['fine_reminders'] = sendFineReminders($db);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Notifications sent successfully',
        'results' => $results
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send notifications: ' . $e->getMessage()
    ]);
}
?>
