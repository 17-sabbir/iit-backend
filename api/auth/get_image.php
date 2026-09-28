<?php
/**
 * Serve profile images
 * Usage: /auth/get_image.php?path=uploads/profiles/filename.jpg
 */

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

// Navigate from /api/auth/ to root, then to uploads
$file = __DIR__ . '/../../' . $path;

// Resolve real path to prevent bypass
$realPath = realpath($file);
$realDir = realpath(__DIR__ . '/../../uploads');

// Verify the file is within the uploads directory
if ($realPath === false || $realDir === false || strpos($realPath, $realDir) !== 0) {
    http_response_code(403);
    die('Forbidden');
}

// Check if file exists
if (!file_exists($realPath) || !is_file($realPath)) {
    http_response_code(404);
    die('Not found');
}

// Determine MIME type
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];

$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Send headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *');

// Send file
readfile($realPath);
exit;
