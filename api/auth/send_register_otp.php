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
require_once '../../config/db_pre_registration.php';
require_once '../lib/auth_helpers.php';
require_once '../lib/mail_service.php';

$database = new Database();
$db = $database->getConnection();

$input = json_input();
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
$phone = $input['phone'] ?? '';
$role = $input['role'] ?? 'Student';
$name = $input['name'] ?? '';

if ($email === '') {
    respond(400, [
        'success' => false,
        'message' => 'Email is required.',
    ]);
}

// STEP 1: Validate email exists in pre-registration database (REQUIRED)
$preRegDatabase = new PreRegistrationDatabase();
$preDb = $preRegDatabase->connect();

if (!$preDb) {
    respond(500, [
        'success' => false,
        'message' => 'Unable to validate pre-registration. Please contact administrator.',
    ]);
}

$userInfo = null;
$preRegRole = null;

// Check Student table
$stmt = $preDb->prepare("SELECT email, roll, full_name, contact, session FROM PreReg_Students WHERE email = :email");
$stmt->execute([':email' => $email]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student) {
    $preRegRole = 'Student';
    $userInfo = [
        'email' => $student['email'],
        'full_name' => $student['full_name'],
        'contact' => $student['contact'],
        'roll' => $student['roll'],
        'session' => $student['session']
    ];
}

// Check Teacher table if not student
if (!$preRegRole) {
    $stmt = $preDb->prepare("SELECT email, designation, full_name, contact FROM PreReg_Teachers WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($teacher) {
        $preRegRole = 'Teacher';
        $userInfo = [
            'email' => $teacher['email'],
            'full_name' => $teacher['full_name'],
            'contact' => $teacher['contact'],
            'designation' => $teacher['designation']
        ];
    }
}

// Check Librarian table if not student or teacher
if (!$preRegRole) {
    $stmt = $preDb->prepare("SELECT email, full_name, contact FROM PreReg_Librarians WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $librarian = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($librarian) {
        $preRegRole = 'Librarian';
        $userInfo = [
            'email' => $librarian['email'],
            'full_name' => $librarian['full_name'],
            'contact' => $librarian['contact']
        ];
    }
}

// Check Director table if not student, teacher, or librarian
if (!$preRegRole) {
    $stmt = $preDb->prepare("SELECT email, full_name, contact FROM PreReg_Directors WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $director = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($director) {
        $preRegRole = 'Director';
        $userInfo = [
            'email' => $director['email'],
            'full_name' => $director['full_name'],
            'contact' => $director['contact']
        ];
    }
}

// If email not found in ANY pre-registration table, reject registration
if (!$preRegRole || !$userInfo) {
    respond(403, [
        'success' => false,
        'message' => 'This email is not pre-registered. Only authorized users can create accounts. Please contact the administrator.',
    ]);
}

// Override role and name from pre-registration data
$role = $preRegRole;
$name = $userInfo['full_name'] ?? '';
$phone = $userInfo['contact'] ?? $phone;

// Check existing user in main database
$stmt = $db->prepare('SELECT email FROM Users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    respond(400, [
        'success' => false,
        'message' => 'Account already exists. Please sign in.',
    ]);
}

// Don't create user yet - only send OTP
// User will be created in verify_email.php after successful OTP verification

$otpResult = issue_otp($email, 'EmailVerification');
if (!$otpResult['ok']) {
    $wait = $otpResult['wait'] ?? 60;
    respond(429, [
        'success' => false,
        'message' => "Please wait ${wait}s before requesting another code.",
        'retry_after' => $wait,
    ]);
}

// Send verification email
$emailSent = MailService::sendVerificationEmail($email, $otpResult['otp']);

respond(200, [
    'success' => true, // Always succeed in dev mode so user can proceed
    'message' => 'Verification code sent to your email. (Check server logs or /tmp/iit_shelf_otp.log)',
    'email' => $email,
    'role' => $role,
    'user_info' => $userInfo,
    'otp' => $otpResult['otp'], // Return for development testing
]);
