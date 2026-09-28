<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../lib/auth_helpers.php';

$database = new Database();
$db = $database->getConnection();

$input = json_input();
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
$currentPassword = $input['current_password'] ?? '';
$newPassword = $input['new_password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

// Validate inputs
if ($email === '' || $currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    respond(400, [
        'success' => false,
        'message' => 'Email, current password, new password, and confirmation are required.',
    ]);
}

// Check if new password matches confirmation
if ($newPassword !== $confirmPassword) {
    respond(400, [
        'success' => false,
        'message' => 'New password and confirmation do not match.',
    ]);
}

// Check if new password is at least 6 characters
if (!valid_password($newPassword)) {
    respond(400, [
        'success' => false,
        'message' => 'New password must be at least 8 characters long.',
    ]);
}

// Fetch user from database
$stmt = $db->prepare('SELECT password_hash FROM Users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    respond(404, [
        'success' => false,
        'message' => 'User not found.',
    ]);
}

// Verify current password
if (!verify_password($currentPassword, $user['password_hash'])) {
    respond(401, [
        'success' => false,
        'message' => 'Current password is incorrect.',
    ]);
}

// Update password
$newHash = hash_password($newPassword);
$update = $db->prepare('UPDATE Users SET password_hash = :ph WHERE email = :email');
$update->execute([
    ':ph' => $newHash,
    ':email' => $email,
]);

respond(200, [
    'success' => true,
    'message' => 'Password changed successfully.',
]);
?>
