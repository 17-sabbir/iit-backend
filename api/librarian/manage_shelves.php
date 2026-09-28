<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// GET - Fetch all shelves
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT * FROM Shelves 
            WHERE is_deleted = 0 
            ORDER BY shelf_id ASC
        ");
        $shelves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'shelves' => $shelves
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

// POST - Add new shelf
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $shelf_id = intval($data['shelf_id'] ?? 0);
    $compartment = intval($data['compartment'] ?? 0);
    $subcompartment = intval($data['subcompartment'] ?? 0);
    
    if ($shelf_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Shelf ID must be greater than 0'
        ]);
        exit;
    }
    
    if ($compartment <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Compartment must be greater than 0'
        ]);
        exit;
    }
    
    if ($subcompartment <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Subcompartment must be greater than 0'
        ]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Shelves (shelf_id, compartment, subcompartment, is_deleted) 
            VALUES (:shelf_id, :compartment, :subcompartment, 0)
        ");
        
        $stmt->execute([
            'shelf_id' => $shelf_id,
            'compartment' => $compartment,
            'subcompartment' => $subcompartment
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Shelf location added successfully',
            'shelf_id' => $shelf_id,
            'compartment' => $compartment,
            'subcompartment' => $subcompartment
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

// PUT - Update shelf
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $shelf_id = intval($data['shelf_id'] ?? 0);
    $compartment = intval($data['compartment'] ?? 0);
    $subcompartment = intval($data['subcompartment'] ?? 0);
    
    if ($shelf_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid shelf ID'
        ]);
        exit;
    }
    
    if ($compartment <= 0 || $subcompartment <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Compartment and subcompartment must be greater than 0'
        ]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE Shelves 
            SET compartment = :compartment, 
                subcompartment = :subcompartment 
            WHERE shelf_id = :shelf_id AND is_deleted = 0
        ");
        
        $stmt->execute([
            'compartment' => $compartment,
            'subcompartment' => $subcompartment,
            'shelf_id' => $shelf_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Shelf location updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

// DELETE - Soft delete shelf
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $shelf_id = intval($data['shelf_id'] ?? 0);
    
    if ($shelf_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid shelf ID'
        ]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE Shelves 
            SET is_deleted = 1 
            WHERE shelf_id = :shelf_id
        ");
        
        $stmt->execute(['shelf_id' => $shelf_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Shelf deleted successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
