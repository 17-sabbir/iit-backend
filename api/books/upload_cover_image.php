<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Check if file was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No image file uploaded or upload error occurred'
    ]);
    exit;
}

$file = $_FILES['image'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);

if (!in_array($mime_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.'
    ]);
    exit;
}

// Validate file size (max 5MB)
$max_size = 5 * 1024 * 1024; // 5MB in bytes
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'File size exceeds maximum limit of 5MB'
    ]);
    exit;
}

// Create uploads directory if it doesn't exist
$upload_dir = '../../uploads/covers/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$original_name = pathinfo($file['name'], PATHINFO_FILENAME);
$sanitized_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $original_name);
$unique_name = $sanitized_name . '_' . time() . '_' . uniqid() . '.' . $file_extension;
$upload_path = $upload_dir . $unique_name;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Return relative path for storage in database
    $relative_path = 'uploads/covers/' . $unique_name;
    
    echo json_encode([
        'success' => true,
        'message' => 'Cover image uploaded successfully',
        'path' => $relative_path
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save uploaded file'
    ]);
}
