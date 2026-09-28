<?php
/**
 * Get Library Settings
 * Retrieves library contact information and settings
 */

header('Content-Type: application/json');
include_once '../../config/db_pre_registration.php';

$database = new PreRegistrationDatabase();
$db = $database->connect();

try {
    // Fetch all library settings
    $stmt = $db->prepare("
        SELECT setting_key, setting_value
        FROM Library_Settings
        ORDER BY setting_id
    ");
    $stmt->execute();
    
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'settings' => $settings,
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch library settings: ' . $e->getMessage(),
    ]);
}
?>
