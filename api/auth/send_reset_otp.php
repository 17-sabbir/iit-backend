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
require_once '../lib/mail_service.php';

$database = new Database();
$db = $database->getConnection();

$input = json_input();
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';

if ($email === '') {
    respond(400, [
        'success' => false,
        'message' => 'Email is required.',
    ]);
}

$stmt = $db->prepare('SELECT email FROM Users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Don't reveal if email exists
    respond(200, [
        'success' => true,
        'message' => 'If an account exists, a reset code has been sent.',
    ]);
}

$otpResult = issue_otp($email, 'PasswordReset');
if (!$otpResult['ok']) {
    $wait = $otpResult['wait'] ?? 60;
    respond(429, [
        'success' => false,
        'message' => "Please retry after {$wait} seconds.",
    ]);
}

// Send password reset email
$emailSent = MailService::sendPasswordResetEmail($email, $otpResult['otp']);

respond(200, [
    'success' => true, // Always succeed in dev mode
    'message' => 'Password reset code sent to your email. (Check server logs or /tmp/iit_shelf_otp.log)',
    'otp' => $otpResult['otp'], // Return for development testing
]);
?>
