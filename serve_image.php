<?php
/**
 * Serve profile images
 * Usage: /serve_image.php?path=uploads/profiles/filename.jpg
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$path = isset($_GET['path']) ? $_GET['path'] : '';

if (empty($path)) {
    http_response_code(400);
    die('Path required');
}

// Prevent directory traversal attacks
$path = str_replace(['..', '\\', "\0"], '', $path);

// Only allow uploads directory
if (strpos($path, 'uploads/') !== 0) {
    http_response_code(403);
    die('Forbidden');
}

$file = __DIR__ . '/' . $path;

// Check if file exists
if (!file_exists($file) || !is_file($file)) {
    http_response_code(404);
    die('Not found');
}

// Determine MIME type
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
];

$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Send headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=86400');

// Send file
readfile($file);
exit;
