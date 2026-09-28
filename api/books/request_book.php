<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$payload = json_decode(file_get_contents('php://input'));

$title = $payload->title ?? '';
$author = $payload->author ?? '';
$category = $payload->category ?? null;
$publisher = $payload->publisher ?? null;
$publication_year = $payload->publication_year ?? null;
$edition = $payload->edition ?? null;
$description = $payload->description ?? null;
$pic_path = $payload->pic_path ?? null;
$pdf_path = $payload->pdf_path ?? null;
$priority = $payload->priority ?? 'Medium';
$userEmail = $payload->user_email ?? null;

if ($title === '' || empty($userEmail)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'title and user_email are required',
    ]);
    exit;
}

try {

        // Use team schema table and column names (Requests, requester_identifier)
        $stmt = $db->prepare('INSERT INTO Requests (
            requester_identifier, isbn, title, author, category, publisher, publication_year,
            edition, description, pic_path, pdf_path, status
        ) VALUES (
            :requester_identifier, :isbn, :title, :author, :category, :publisher, :publication_year,
            :edition, :description, :pic_path, :pdf_path, "Pending"
        )');

    $stmt->execute([
        ':requester_identifier' => $userEmail,
        ':isbn' => $payload->isbn ?? null,
        ':title' => $title,
        ':author' => $author,
        ':category' => $category,
        ':publisher' => $publisher,
        ':publication_year' => $publication_year,
        ':edition' => $edition,
        ':description' => $description,
        ':pic_path' => $pic_path,
        ':pdf_path' => $pdf_path,
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Book request submitted',
        'request_id' => $db->lastInsertId(),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to create request: ' . $e->getMessage(),
    ]);
}
?>
