<?php
/**
 * Update Library Settings
 * Allows librarians to update library contact information
 */

header('Content-Type: application/json');
include_once '../../config/db_pre_registration.php';

$database = new PreRegistrationDatabase();
$db = $database->connect();

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['settings']) || !is_array($input['settings'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Settings data is required',
    ]);
    exit;
}

try {
    $db->beginTransaction();
    
    // Prepare update statement
    $stmt = $db->prepare("
        INSERT INTO Library_Settings (setting_key, setting_value)
        VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE setting_value = :value
    ");
    
    $updatedCount = 0;
    foreach ($input['settings'] as $key => $value) {
        // Only allow specific keys to be updated
        if (!in_array($key, ['library_email', 'library_phone', 'library_hours', 'library_location'])) {
            continue;
        }
        
        $stmt->execute([
            ':key' => $key,
            ':value' => $value,
        ]);
        $updatedCount++;
    }
    
    $db->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Successfully updated $updatedCount settings",
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update library settings: ' . $e->getMessage(),
    ]);
}
?>
