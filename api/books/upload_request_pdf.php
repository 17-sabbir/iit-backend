<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include_once '../../config/database.php';

// Check if file was uploaded
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No PDF file uploaded or upload error occurred'
    ]);
    exit;
}

$file = $_FILES['pdf'];

// Validate file type
$allowed_types = ['application/pdf'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);

if (!in_array($mime_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Only PDF files are allowed.'
    ]);
    exit;
}

// Validate file size (max 50MB)
$max_size = 50 * 1024 * 1024; // 50MB in bytes
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'File size exceeds maximum limit of 50MB'
    ]);
    exit;
}

// Create uploads directory if it doesn't exist
$upload_dir = '../../uploads/pdfs/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = 'pdf';
$original_name = pathinfo($file['name'], PATHINFO_FILENAME);
$sanitized_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $original_name);
$unique_name = $sanitized_name . '_' . time() . '_' . uniqid() . '.' . $file_extension;
$upload_path = $upload_dir . $unique_name;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Return relative path for storage in database
    $relative_path = 'uploads/pdfs/' . $unique_name;
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'PDF uploaded successfully',
        'path' => $relative_path,
        'filename' => $unique_name,
        'size' => $file['size']
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save uploaded file'
    ]);
}
?>
