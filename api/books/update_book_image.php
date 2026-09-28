<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Update Book Cover Image
 * Uploads a new cover image for an existing book
 */

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get ISBN from request
$isbn = isset($_POST['isbn']) ? trim($_POST['isbn']) : '';

if ($isbn === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ISBN is required.'
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

// Check if book exists
$stmt = $db->prepare('SELECT isbn FROM Books WHERE isbn = :isbn');
$stmt->execute([':isbn' => $isbn]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Book not found.'
    ]);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadsDir = __DIR__ . '/../../uploads/books';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = md5($isbn . time()) . '.' . $fileExtension;
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
$imagePath = 'uploads/books/' . $filename;

$updateStmt = $db->prepare('UPDATE Books SET pic_path = :image WHERE isbn = :isbn');
$updateStmt->execute([
    ':image' => $imagePath,
    ':isbn' => $isbn
]);

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Book cover updated successfully.',
    'image_url' => 'http://localhost:8000/serve_image.php?path=' . urlencode($imagePath)
]);
?>
