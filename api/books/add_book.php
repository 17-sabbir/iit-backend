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

// Debug: Log incoming request
error_log('ADD BOOK REQUEST - Content-Type: ' . $contentType . ' - Data: ' . json_encode($_POST) . ' - Files: ' . json_encode(array_keys($_FILES ?? [])));

// Handle both JSON and multipart/form-data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$picPath = null;
$courseId = null;
$pdfUrl = null;

if (stripos($contentType, 'application/json') === 0) {
    $data = json_decode(file_get_contents('php://input')) ?: new stdClass();
} else {
    // Multipart form data (file upload)
    $data = (object)$_POST;

    // Parse JSON fields that were sent as strings
    if (isset($_POST['copy_ids']) && is_string($_POST['copy_ids'])) {
        $decoded = json_decode($_POST['copy_ids'], true);
        $data->copy_ids = $decoded ?? $_POST['copy_ids'];
    }
    if (isset($_POST['copy_locations']) && is_string($_POST['copy_locations'])) {
        $decoded = json_decode($_POST['copy_locations'], true);
        $data->copy_locations = $decoded ?? $_POST['copy_locations'];
    }
    if (isset($_POST['pdf_url'])) {
        $data->pdf_url = $_POST['pdf_url'];
    }

    // Handle file upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/books/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = uniqid('book_') . '_' . time() . '.' . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
            $picPath = 'uploads/books/' . $fileName;
        }
    }

    // If for some reason fields are empty, try to decode JSON body as fallback
    if (empty($data->title) && empty($data->isbn) && empty($data->author)) {
        $jsonBody = json_decode(file_get_contents('php://input'));
        if ($jsonBody) {
            $data = $jsonBody;
        }
    }
}

// Normalize optional course ids (used to link books to courses)
// Support both single course_id and array of course_ids
$courseIds = [];
if (!empty($data->course_id)) {
    if (is_array($data->course_id)) {
        // Array of course_ids
        foreach ($data->course_id as $cid) {
            $trimmed = trim($cid);
            if ($trimmed !== '' && $trimmed !== 'NONE') {
                $courseIds[] = $trimmed;
            }
        }
    } else {
        // Single course_id (backward compatibility)
        $trimmed = trim($data->course_id);
        if ($trimmed !== '' && $trimmed !== 'NONE') {
            $courseIds[] = $trimmed;
        }
    }
}
// Support explicit course_ids array parameter
if (!empty($data->course_ids) && is_array($data->course_ids)) {
    foreach ($data->course_ids as $cid) {
        $trimmed = trim($cid);
        if ($trimmed !== '' && $trimmed !== 'NONE') {
            $courseIds[] = $trimmed;
        }
    }
}
$courseIds = array_values(array_unique($courseIds)); // Remove duplicates

// Normalize pdf url if provided
if (!empty($data->pdf_url)) {
    $pdfUrl = trim($data->pdf_url);
}

// Required fields
$required = ['title', 'author', 'isbn'];
foreach ($required as $field) {
    if (empty($data->$field)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "$field is required",
        ]);
        exit;
    }
}

$copiesTotal = isset($data->copies_total) ? (int)$data->copies_total : 0;
if ($copiesTotal < 0) {
    $copiesTotal = 0;
}

// Handle optional provided copy IDs (accept array or JSON string)
$copyIds = [];
if (isset($data->copy_ids)) {
    if (is_array($data->copy_ids)) {
        $copyIds = $data->copy_ids;
    } elseif (is_string($data->copy_ids)) {
        $decoded = json_decode($data->copy_ids, true);
        if (is_array($decoded)) {
            $copyIds = $decoded;
        }
    }
}

$copyIds = array_values(array_filter(array_map('trim', $copyIds ?? []), 'strlen'));

// Handle copy locations (array of location objects per copy)
$copyLocations = [];
if (isset($data->copy_locations)) {
    if (is_array($data->copy_locations)) {
        $copyLocations = $data->copy_locations;
    } elseif (is_string($data->copy_locations)) {
        $decoded = json_decode($data->copy_locations, true);
        if (is_array($decoded)) {
            $copyLocations = $decoded;
        }
    }
}

if (!empty($copyIds)) {
    $copiesFromIds = count($copyIds);

    if ($copiesTotal > 0 && $copiesTotal !== $copiesFromIds) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'copy_ids count must match copies_total',
        ]);
        exit;
    }

    $copiesTotal = $copiesFromIds;

    if (count(array_unique($copyIds)) !== $copiesFromIds) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Duplicate copy_ids provided',
        ]);
        exit;
    }
}

// Default available copies equals total
$copiesAvailable = $copiesTotal;

try {
    $db->beginTransaction();

    // Insert into Books table
    $stmt = $db->prepare('INSERT INTO Books (
        isbn, title, author, category, publisher, publication_year, edition,
        description, pic_path
    ) VALUES (
        :isbn, :title, :author, :category, :publisher, :publication_year, :edition,
        :description, :pic_path
    )');

    try {
        $stmt->execute([
            ':isbn' => $data->isbn,
            ':title' => $data->title,
            ':author' => $data->author,
            ':category' => $data->category ?? null,
            ':publisher' => $data->publisher ?? null,
            ':publication_year' => $data->publication_year ?? null,
            ':edition' => $data->edition ?? null,
            ':description' => $data->description ?? null,
            ':pic_path' => $picPath ?? $data->pic_path ?? null,
        ]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'A book with ISBN ' . $data->isbn . ' already exists. Please use a different ISBN.',
            ]);
            exit;
        }
        throw $e;
    }

    // Link book to courses if provided
    if (!empty($courseIds)) {
        // Verify all courses exist
        foreach ($courseIds as $courseId) {
            $courseCheck = $db->prepare('SELECT course_id FROM Courses WHERE course_id = :course_id');
            $courseCheck->execute([':course_id' => $courseId]);

            if ($courseCheck->fetchColumn() === false) {
                $db->rollBack();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Course not found for course_id: ' . $courseId,
                ]);
                exit;
            }
        }

        // Insert all course associations
        $linkStmt = $db->prepare('INSERT INTO Book_Courses (isbn, course_id) VALUES (:isbn, :course_id)
            ON DUPLICATE KEY UPDATE course_id = VALUES(course_id)');
        
        foreach ($courseIds as $courseId) {
            $linkStmt->execute([
                ':isbn' => $data->isbn,
                ':course_id' => $courseId,
            ]);
        }
    }

    // Generate copies in Book_Copies
    if ($copiesTotal > 0) {
        $copyInsert = $db->prepare('INSERT INTO Book_Copies (
            copy_id, isbn, shelf_id, compartment_no, subcompartment_no, status, condition_note
        ) VALUES (
            :copy_id, :isbn, :shelf_id, :compartment_no, :subcompartment_no, "Available", :condition_note
        )');

        if (!empty($copyIds)) {
            foreach ($copyIds as $index => $copyId) {
                // Get location for this specific copy if provided
                $location = $copyLocations[$index] ?? null;
                $shelfId = null;
                $compartmentNo = null;
                $subcompartmentNo = null;

                if ($location) {
                    $shelfId = $location['shelf_id'] ?? $location->shelf_id ?? null;
                    $compartmentNo = $location['compartment_no'] ?? $location->compartment_no ?? null;
                    $subcompartmentNo = $location['subcompartment_no'] ?? $location->subcompartment_no ?? null;
                }

                $copyInsert->execute([
                    ':copy_id' => $copyId,
                    ':isbn' => $data->isbn,
                    ':shelf_id' => $shelfId,
                    ':compartment_no' => $compartmentNo,
                    ':subcompartment_no' => $subcompartmentNo,
                    ':condition_note' => $data->condition_note ?? null,
                ]);
            }
        } else {
            // Determine starting sequence number for auto-generated IDs
            $seqStmt = $db->prepare('SELECT COUNT(*) as cnt FROM Book_Copies WHERE isbn = :isbn');
            $seqStmt->execute([':isbn' => $data->isbn]);
            $start = (int)$seqStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

            for ($i = 1; $i <= $copiesTotal; $i++) {
                $seq = $start + $i;
                $copyId = $data->isbn . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
                $copyInsert->execute([
                    ':copy_id' => $copyId,
                    ':isbn' => $data->isbn,
                    ':shelf_id' => $data->shelf_id ?? null,
                    ':compartment_no' => $data->compartment_no ?? null,
                    ':subcompartment_no' => $data->subcompartment_no ?? null,
                    ':condition_note' => $data->condition_note ?? null,
                ]);
            }
        }
    }

    // If a PDF URL was provided, upsert into Digital_Resources as PDF
    if (!empty($pdfUrl)) {
        $pdfStmt = $db->prepare('INSERT INTO Digital_Resources (isbn, resource_type, file_path)
            VALUES (:isbn, "PDF", :file_path)
            ON DUPLICATE KEY UPDATE file_path = VALUES(file_path)');
        $pdfStmt->execute([
            ':isbn' => $data->isbn,
            ':file_path' => $pdfUrl,
        ]);
    }

    $db->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Book added successfully',
        'isbn' => $data->isbn,
        'copies_created' => $copiesTotal,
        'course_ids' => $courseIds, // Return array of linked course IDs
    ]);
} catch (Exception $e) {
    $db->rollBack();
    error_log('ADD BOOK ERROR: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to add book: ' . $e->getMessage(),
    ]);
}
?>
