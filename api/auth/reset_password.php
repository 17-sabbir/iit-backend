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
$otp = $input['otp'] ?? '';
$newPassword = $input['new_password'] ?? '';

if ($email === '' || $otp === '' || $newPassword === '') {
    respond(400, [
        'success' => false,
        'message' => 'Email, OTP, and new password are required.',
    ]);
}

if (!valid_password($newPassword)) {
    respond(400, [
        'success' => false,
        'message' => 'Password must be at least 8 characters long.',
    ]);
}

$check = $db->prepare('SELECT email FROM Users WHERE email = :email');
$check->execute([':email' => $email]);
if (!$check->fetch(PDO::FETCH_ASSOC)) {
    respond(404, [
        'success' => false,
        'message' => 'User not found.',
    ]);
}

$valid = validate_otp($email, 'PasswordReset', $otp);
if (!$valid['ok']) {
    respond(400, [
        'success' => false,
        'message' => $valid['message'] ?? 'Invalid OTP.',
    ]);
}

$hash = hash_password($newPassword);
$db->prepare('UPDATE Users SET password_hash = :ph WHERE email = :email')
    ->execute([':ph' => $hash, ':email' => $email]);

$db->prepare('DELETE FROM Temp_User_Verification WHERE email = :email AND purpose = :purpose')
    ->execute([':email' => $email, ':purpose' => 'PasswordReset']);

respond(200, [
    'success' => true,
    'message' => 'Password reset successful.',
]);
?>
