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
include_once '../lib/notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'));
$requestId = $data->request_id ?? null;
$librarian = $data->approved_by ?? 'System';

if (empty($requestId)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'request_id is required']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Get full request details before approval
    $getReq = $db->prepare('SELECT requester_identifier, isbn, title, author, publisher, publication_year, edition, pdf_path, category, pic_path, description FROM Requests WHERE request_id = :rid AND status = "Pending"');
    $getReq->execute([':rid'=>$requestId]);
    $request = $getReq->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Pending request not found or already approved']);
        exit;
    }
    
    // Check if book already exists
    $checkBook = $db->prepare('SELECT isbn FROM Books WHERE isbn = :isbn');
    $checkBook->execute([':isbn' => $request['isbn']]);
    $bookExists = $checkBook->fetch();
    
    if (!$bookExists) {
        // Insert book into Books table (without physical copies)
        $insertBook = $db->prepare('INSERT INTO Books (isbn, title, author, publisher, publication_year, edition, category, pic_path, description) VALUES (:isbn, :title, :author, :publisher, :year, :edition, :category, :pic, :desc)');
        $insertBook->execute([
            ':isbn' => $request['isbn'],
            ':title' => $request['title'],
            ':author' => $request['author'],
            ':publisher' => $request['publisher'],
            ':year' => $request['publication_year'],
            ':edition' => $request['edition'],
            ':category' => $request['category'],
            ':pic' => $request['pic_path'],
            ':desc' => $request['description']
        ]);
    }
    
    // Insert PDF into Digital_Resources if PDF path exists
    if (!empty($request['pdf_path'])) {
        // Extract filename from path
        $fileName = basename($request['pdf_path']);
        
        // Check if digital resource already exists for this ISBN
        $checkDigital = $db->prepare('SELECT resource_id FROM Digital_Resources WHERE isbn = :isbn AND file_path = :path');
        $checkDigital->execute([':isbn' => $request['isbn'], ':path' => $request['pdf_path']]);
        $digitalExists = $checkDigital->fetch();
        
        if (!$digitalExists) {
            $insertDigital = $db->prepare('INSERT INTO Digital_Resources (isbn, file_name, file_path, resource_type, uploaded_by) VALUES (:isbn, :filename, :filepath, :type, :uploader)');
            $insertDigital->execute([
                ':isbn' => $request['isbn'],
                ':filename' => $fileName,
                ':filepath' => $request['pdf_path'],
                ':type' => 'PDF',
                ':uploader' => $request['requester_identifier']
            ]);
        }
    }
    
    // Mark request as approved
    $stmt = $db->prepare('UPDATE Requests SET status = "Approved", approved_by = :by, approved_at = NOW() WHERE request_id = :rid AND status = "Pending"');
    $stmt->execute([':by'=>$librarian, ':rid'=>$requestId]);

    // Send notification to the requester
    notifyAdditionRequestApproved($db, $request['requester_identifier'], $request['title']);

    $db->commit();
    echo json_encode(['success'=>true,'message'=>'Book addition request approved and book added to library']);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
