<?php
/**
 * Update User Profile
 * Updates user profile information (name, phone, role)
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../lib/auth_helpers.php';

$database = new Database();
$db = $database->getConnection();

$input = json_input();

// Validate required fields
$email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
$name = isset($input['name']) ? trim($input['name']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : '';
$role = isset($input['role']) ? trim($input['role']) : '';

if ($email === '') {
    respond(400, [
        'success' => false,
        'message' => 'Email is required.',
    ]);
}

// Check if user exists
$stmt = $db->prepare('SELECT email FROM Users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    respond(404, [
        'success' => false,
        'message' => 'User not found.',
    ]);
}

// Build update query
$updateFields = [];
$params = [':email' => $email];

if ($name !== '') {
    $updateFields[] = 'name = :name';
    $params[':name'] = $name;
}

if ($phone !== '') {
    $updateFields[] = 'contact = :contact';
    $params[':contact'] = $phone;
}

if ($role !== '') {
    // Validate role
    $validRoles = ['Student', 'Teacher', 'Librarian', 'Director'];
    if (!in_array($role, $validRoles)) {
        respond(400, [
            'success' => false,
            'message' => 'Invalid role. Must be Student, Teacher, Librarian, or Director.',
        ]);
    }
    $updateFields[] = 'role = :role';
    $params[':role'] = $role;
}

if (empty($updateFields)) {
    respond(400, [
        'success' => false,
        'message' => 'No fields to update.',
    ]);
}

try {
    $query = 'UPDATE Users SET ' . implode(', ', $updateFields) . ' WHERE email = :email';
    $stmt = $db->prepare($query);
    $stmt->execute($params);

    respond(200, [
        'success' => true,
        'message' => 'Profile updated successfully.',
        'user' => [
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'role' => $role,
        ],
    ]);
} catch (PDOException $e) {
    respond(500, [
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}
?>
