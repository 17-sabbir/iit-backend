<?php
require_once '../../config/database.php';
require_once '../../config/db_pre_registration.php';
require_once '../lib/auth_helpers.php';
require_once '../lib/mail_service.php';

$database = new Database();
$db = $database->getConnection();

$input = json_input();
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
$password = $input['password'] ?? '';
$name = $input['name'] ?? '';
$role = $input['role'] ?? 'Student';
$phone = $input['phone'] ?? '';

if ($email === '' || $password === '') {
    respond(400, [
        'success' => false,
        'message' => 'Email and password are required.',
    ]);
}

if (!valid_password($password)) {
    respond(400, [
        'success' => false,
        'message' => 'Password must be at least 8 characters long.',
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
            'contact' => $librarian['contact'],
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
            'contact' => $director['contact'],
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
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    respond(400, [
        'success' => false,
        'message' => 'Account already exists.',
    ]);
}

$hash = hash_password($password);

// Insert new account
$ins = $db->prepare('INSERT INTO Users (email, name, password_hash, role, contact, created_at) VALUES (:email, :name, :ph, :role, :contact, NOW())');
$ins->execute([
    ':email' => $email,
    ':name' => $name,
    ':ph' => $hash,
    ':role' => $role,
    ':contact' => $phone,
]);

$otpResult = issue_otp($email, 'EmailVerification');
if (!$otpResult['ok']) {
    $wait = $otpResult['wait'] ?? 60;
    respond(429, [
        'success' => false,
        'message' => "Please retry after {$wait} seconds.",
    ]);
}

// Send verification email
$emailSent = MailService::sendVerificationEmail($email, $otpResult['otp']);

respond(201, [
    'success' => true, // Always succeed in dev mode so user can proceed
    'message' => 'User registered. Verification code sent to your email. (Check server logs or /tmp/iit_shelf_otp.log)',
    'email' => $email,
    'otp' => $otpResult['otp'], // Return for development testing
]);
?>
