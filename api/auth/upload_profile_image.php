<?php
/**
 * Upload Profile Image
 * Uploads user profile image and saves path to database
 */

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get email from request
$email = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';

if ($email === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email is required.'
    ]);
    exit;
}

// Validate file
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No image provided or upload error.'
    ]);
    exit;
}

$file = $_FILES['image'];
$maxSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Validate file size
if ($file['size'] > $maxSize) {
    http_response_code(413);
    echo json_encode([
        'success' => false,
        'message' => 'Image size must be less than 5MB.'
    ]);
    exit;
}

// Validate file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(415);
    echo json_encode([
        'success' => false,
        'message' => 'Only JPEG, PNG, GIF, and WebP images are allowed.'
    ]);
    exit;
}

// Check if user exists
$stmt = $db->prepare('SELECT email FROM Users WHERE email = :email');
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

// Create uploads directory if it doesn't exist
$uploadsDir = __DIR__ . '/../../uploads/profiles';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = md5($email . time()) . '.' . $fileExtension;
$filepath = $uploadsDir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save image.'
    ]);
    exit;
}

// Save path to database (relative path for serving)
$imagePath = 'uploads/profiles/' . $filename;

$updateStmt = $db->prepare('UPDATE Users SET profile_image = :image WHERE email = :email');
$updateStmt->execute([
    ':image' => $imagePath,
    ':email' => $email
]);

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Profile image uploaded successfully.',
    'image_url' => 'http://localhost:8000/auth/get_image.php?path=' . urlencode($imagePath)
]);
