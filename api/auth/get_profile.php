<?php
/**
 * Get User Profile
 * Retrieves user profile information including profile image
 */

require_once '../../config/database.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get email from request (JSON or POST)
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';

if ($email === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email is required.'
    ]);
    exit;
}

// Get user profile
$stmt = $db->prepare('
    SELECT email, name, contact, role, profile_image, created_at, last_login
    FROM Users 
    WHERE email = :email
');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'User not found.'
    ]);
    exit;
}

// Build full URL for profile image if it exists
$profileImageUrl = null;
if (!empty($user['profile_image'])) {
    // Image is stored as "uploads/profiles/filename.jpg"
    // Use /api path so Vite proxy handles CORS
    $profileImageUrl = '/api/auth/get_image.php?path=' . urlencode($user['profile_image']);
}

// Role-specific data: add designation for teachers, roll/session for students
$roleSpecific = [];
if (!empty($user['role'])) {
    $role = strtolower($user['role']);
    if ($role === 'teacher') {
        $tStmt = $db->prepare('SELECT designation FROM Teachers WHERE email = :email');
        $tStmt->execute([':email' => $email]);
        $t = $tStmt->fetch(PDO::FETCH_ASSOC);
        if ($t && isset($t['designation'])) {
            $roleSpecific['designation'] = $t['designation'];
        }
    } elseif ($role === 'student') {
        $sStmt = $db->prepare('SELECT roll, session FROM Students WHERE email = :email');
        $sStmt->execute([':email' => $email]);
        $s = $sStmt->fetch(PDO::FETCH_ASSOC);
        if ($s) {
            if (isset($s['roll'])) $roleSpecific['roll'] = $s['roll'];
            if (isset($s['session'])) $roleSpecific['session'] = $s['session'];
        }
    }
}

// Return user profile
http_response_code(200);
echo json_encode([
    'success' => true,
    'user' => [
        'email' => $user['email'],
        'name' => $user['name'] ?? 'User',
        'phone' => $user['contact'] ?? 'Not provided',  // Map contact to phone for app
        'contact' => $user['contact'] ?? '',  // Also include contact
        'role' => $user['role'] ?? '',
        'profile_image' => $profileImageUrl,
        'created_at' => $user['created_at'],
        'last_login' => $user['last_login'] ?? null,
        // include role-specific fields flat for convenience
        ...$roleSpecific,
    ]
]);
