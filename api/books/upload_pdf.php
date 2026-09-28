<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    if (!isset($_FILES['pdf'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No PDF file provided']);
        exit;
    }

    $file = $_FILES['pdf'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File upload error: ' . $file['error']]);
        exit;
    }

    // Check MIME type
    $mimeType = mime_content_type($file['tmp_name']);
    if ($mimeType !== 'application/pdf') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File must be a PDF']);
        exit;
    }

    // Check file size (max 50MB)
    $maxSize = 50 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File too large (max 50MB)']);
        exit;
    }

    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../uploads/pdfs';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $filename = "pdf_{$timestamp}_{$random}.pdf";
    $filepath = $uploadDir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }

    // Return the path relative to the web root (will be served via download_pdf.php)
    $path = '/uploads/pdfs/' . $filename;
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'url' => $path,
        'path' => $path
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
