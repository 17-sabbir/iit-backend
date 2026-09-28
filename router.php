<?php
/**
 * Simple router for the PHP development server
 * Maps requests to api/ subdirectories
 */

// ----- Global CORS handling -----
// Allow development frontends (Vite/React) to call the PHP APIs.
// If credentials/cookies are used, the origin must be explicit (not '*').
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:8000',
];

// Optionally allow a custom LAN host during mobile/web testing
// e.g. http://32.0.2.182:5173 or http://32.0.2.182:8000
if ($origin) {
    $host = parse_url($origin, PHP_URL_HOST);
    $scheme = parse_url($origin, PHP_URL_SCHEME) ?: 'http';
    $port = parse_url($origin, PHP_URL_PORT);
    if ($host) {
        $candidate = $scheme . '://' . $host . ($port ? ':' . $port : '');
        // Allow any origin that matches the same host but typical dev ports
        if (!in_array($candidate, $allowedOrigins, true) && preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host)) {
            if ($port) {
                $allowedOrigins[] = $candidate;
            } else {
                // Add common dev ports for LAN IPs
                $allowedOrigins[] = $scheme . '://' . $host . ':5173';
                $allowedOrigins[] = $scheme . '://' . $host . ':8000';
            }
        }
    }
}

if ($origin && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// Handle preflight requests early
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Get the requested path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove leading slash
$path = ltrim($path, '/');

// Handle the root request
if ($path === '' || $path === '/') {
    echo json_encode(['message' => 'API Server Running']);
    exit;
}

// Map requests to api/ directory
// If request already starts with 'api/', map directly. Otherwise, prefix 'api/'.
if (strpos($path, 'api/') === 0) {
    $file = __DIR__ . '/' . $path;
} else {
    $file = __DIR__ . '/api/' . $path;
}

// Check if file exists
if (is_file($file)) {
    // Change to the api directory so relative includes work correctly
    chdir(dirname($file));
    // Ensure our CORS headers win even if the included file sets its own
    ob_start();
    include $file;
    $output = ob_get_clean();
    if ($origin && in_array($origin, $allowedOrigins, true)) {
        // Remove any wildcard ACAO header and re-assert the explicit origin
        if (function_exists('header_remove')) {
            @header_remove('Access-Control-Allow-Origin');
        }
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    }
    echo $output;
    exit;
}

// Check if this is a serve_image.php request at the root level
if ($path === 'serve_image.php' || strpos($path, 'serve_image.php') === 0) {
    include __DIR__ . '/serve_image.php';
    exit;
}

// 404 - not found
http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => 'Endpoint not found: ' . $path
]);
exit;
