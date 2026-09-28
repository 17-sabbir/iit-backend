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
require_once '../../config/db_auth_temp.php';
require_once '../lib/auth_helpers.php';
require_once '../lib/otp_attempt_tracker.php';

$database = new Database();
$db = $database->getConnection();

$preRegDatabase = new PreRegistrationDatabase();
$preDb = $preRegDatabase->connect();

$input = json_input();
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
$otp = $input['otp'] ?? '';

if ($email === '' || $otp === '') {
    respond(400, [
        'success' => false,
        'message' => 'Email and OTP are required.',
    ]);
}

// Check if account is locked due to failed attempts
$lockout = OtpAttemptTracker::isLockedOut($email, 'EmailVerification');
if ($lockout['locked']) {
    OtpAttemptTracker::logAttempt($email, 'EmailVerification', false, 'Account locked - too many attempts');
    respond(429, [
        'success' => false,
        'message' => $lockout['message'],
        'retry_after' => $lockout['retry_after'],
    ]);
}

$validate = validate_otp($email, 'EmailVerification', $otp);
if (!$validate['ok']) {
    $remaining = OtpAttemptTracker::getRemainingAttempts($email, 'EmailVerification');
    OtpAttemptTracker::logAttempt($email, 'EmailVerification', false, $validate['message'] ?? 'Invalid OTP');
    respond(400, [
        'success' => false,
        'message' => $validate['message'] ?? 'Invalid OTP.',
        'remaining_attempts' => $remaining,
    ]);
}

// OTP is valid - now check if user already exists (in case of duplicate requests)
$stmt = $db->prepare('SELECT email FROM Users WHERE email = :email');
$stmt->execute([':email' => $email]);
$existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existingUser) {
    // Fetch pre-registration data
    $userInfo = null;
    $preRegRole = null;
    
    // Check Student table
    $stmt = $preDb->prepare("SELECT email, roll, full_name, contact, session FROM PreReg_Students WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        $preRegRole = 'Student';
        $userInfo = $student;
    } else {
        // Check Teacher table
        $stmt = $preDb->prepare("SELECT email, designation, full_name, contact FROM PreReg_Teachers WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($teacher) {
            $preRegRole = 'Teacher';
            $userInfo = $teacher;
        } else {
            // Check Librarian table
            $stmt = $preDb->prepare("SELECT email, full_name, contact FROM PreReg_Librarians WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $librarian = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($librarian) {
                $preRegRole = 'Librarian';
                $userInfo = $librarian;
            } else {
                // Check Director table
                $stmt = $preDb->prepare("SELECT email, full_name, contact FROM PreReg_Directors WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $director = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($director) {
                    $preRegRole = 'Director';
                    $userInfo = $director;
                }
            }
        }
    }
    
    if (!$preRegRole || !$userInfo) {
        respond(403, [
            'success' => false,
            'message' => 'Pre-registration data not found. Please contact administrator.',
        ]);
    }
    
    // Create user account with placeholder password
    $placeholderHash = hash_password(bin2hex(random_bytes(16)));
    
    $ins = $db->prepare('INSERT INTO Users (email, name, password_hash, role, contact, created_at) VALUES (:email, :name, :ph, :role, :contact, NOW())');
    $ins->execute([
        ':email' => $email,
        ':name' => $userInfo['full_name'],
        ':ph' => $placeholderHash,
        ':role' => $preRegRole,
        ':contact' => $userInfo['contact'] ?? '',
    ]);
    
    // If student, also create Students entry
    if ($preRegRole === 'Student' && isset($userInfo['roll'], $userInfo['session'])) {
        $stuIns = $db->prepare('INSERT INTO Students (email, roll, session) VALUES (:email, :roll, :session)');
        $stuIns->execute([
            ':email' => $email,
            ':roll' => $userInfo['roll'],
            ':session' => $userInfo['session'],
        ]);
    }
    
    // If teacher, create Teachers entry
    if ($preRegRole === 'Teacher' && isset($userInfo['designation'])) {
        $tchIns = $db->prepare('INSERT INTO Teachers (email, designation) VALUES (:email, :designation)');
        $tchIns->execute([
            ':email' => $email,
            ':designation' => $userInfo['designation'],
        ]);
    }
}

// Delete OTP record after successful verification
$authTempDb = new AuthTempDatabase();
$authDb = $authTempDb->connect();
$authDb->prepare('DELETE FROM Temp_User_Verification WHERE email = :email AND purpose = :purpose')
    ->execute([':email' => $email, ':purpose' => 'EmailVerification']);

OtpAttemptTracker::logAttempt($email, 'EmailVerification', true, 'Email verified');

respond(200, [
    'success' => true,
    'message' => 'Email verified successfully.',
]);
?>
