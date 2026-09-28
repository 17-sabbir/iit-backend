<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../../config/database.php';

$isbn = $_GET['isbn'] ?? null;

if (!$isbn) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ISBN parameter is required'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get the PDF URL or file path from Digital_Resources or Books table
    $stmt = $db->prepare('
        SELECT file_path FROM Digital_Resources 
        WHERE isbn = :isbn AND resource_type = "PDF"
        LIMIT 1
    ');
    $stmt->execute([':isbn' => $isbn]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource || !$resource['file_path']) {
        // Try getting from Books table pdf_url
        $stmt = $db->prepare('SELECT pdf_url FROM Books WHERE isbn = :isbn');
        $stmt->execute([':isbn' => $isbn]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$book || !$book['pdf_url']) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'No PDF found for this book'
            ]);
            exit;
        }
        $filePath = $book['pdf_url'];
    } else {
        $filePath = $resource['file_path'];
    }

    // If it's a URL, redirect to it
    if (preg_match('/^https?:\/\//', $filePath)) {
        header('Location: ' . $filePath);
        exit;
    }

    // If it's a local path, serve it. We resolve against backend root so both
    // absolute ("/uploads/pdfs/...") and relative stored paths work.
    $fullPath = null;
    $baseDir = realpath(__DIR__ . '/../../');
    $normalized = ltrim($filePath, '/');

    if (file_exists($filePath)) {
        $fullPath = $filePath;
    } elseif ($baseDir && file_exists($baseDir . '/' . $normalized)) {
        $fullPath = $baseDir . '/' . $normalized;
    } elseif ($baseDir && file_exists($baseDir . '/' . $filePath)) {
        $fullPath = $baseDir . '/' . $filePath;
    }

    if (!$fullPath || !file_exists($fullPath)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'PDF file not found on server: ' . ($fullPath ?: $filePath)
        ]);
        exit;
    }

    // Serve the file
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($fullPath));
    header('Content-Disposition: inline; filename="book_' . $isbn . '.pdf"');
    header('Cache-Control: public, max-age=3600');
    readfile($fullPath);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error downloading PDF: ' . $e->getMessage()
    ]);
}
