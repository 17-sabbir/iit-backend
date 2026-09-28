<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
$newPassword = $input['new_password'] ?? '';
$name = isset($input['name']) ? trim($input['name']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : '';
$role = isset($input['role']) ? trim($input['role']) : 'Student';

if ($email === '' || $newPassword === '') {
    respond(400, [
        'success' => false,
        'message' => 'Email and new password are required.',
    ]);
}

if (!valid_password($newPassword)) {
    respond(400, [
        'success' => false,
        'message' => 'Password must be at least 8 characters long.',
    ]);
}

$stmt = $db->prepare('SELECT email FROM Users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    respond(404, [
        'success' => false,
        'message' => 'Account not found.',
    ]);
}

$hash = hash_password($newPassword);

// Get current user name from database (set from pre-registration)
$stmt = $db->prepare('SELECT name, contact, role FROM Users WHERE email = :email');
$stmt->execute([':email' => $email]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Only update password - preserve pre-registered name, contact, and role
$upd = $db->prepare('UPDATE Users SET password_hash = :ph WHERE email = :email');
$upd->execute([
    ':ph' => $hash,
    ':email' => $email,
]);

respond(200, [
    'success' => true,
    'message' => 'Password set successfully. You can now sign in.',
]);
