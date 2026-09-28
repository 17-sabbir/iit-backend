<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

$reportType = $input['report_type'] ?? '';
$startDate = $input['start_date'] ?? null;
$endDate = $input['end_date'] ?? null;
$semester = $input['semester'] ?? null;
$session = $input['session'] ?? null;
$format = $input['format'] ?? 'json';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Failed to connect to database');
    }
    
    $data = [];
    
    switch ($reportType) {
        case 'most_borrowed':
            $data = generateMostBorrowedReport($pdo, $startDate, $endDate, $semester, $session);
            break;
        
        case 'most_requested':
            $data = generateMostRequestedReport($pdo, $startDate, $endDate, $semester, $session);
            break;
        
        case 'semester_wise':
            $data = generateSemesterWiseReport($pdo, $startDate, $endDate);
            break;
        
        case 'session_wise':
            $data = generateSessionWiseReport($pdo, $startDate, $endDate);
            break;
        
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid report type'
            ]);
            exit;
    }
    
    if ($format === 'csv') {
        outputCSV($data, $reportType);
    } elseif ($format === 'pdf') {
        outputPDF($data, $reportType, $startDate, $endDate);
    } else {
        echo json_encode([
            'success' => true,
            'data' => $data,
            'report_type' => $reportType,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report: ' . $e->getMessage()
    ]);
}

function generateMostBorrowedReport($pdo, $startDate, $endDate, $semester, $session) {
    $sql = "SELECT 
                b.isbn,
                b.title,
                b.author,
                b.category,
                COUNT(at.transaction_id) AS borrow_count,
                COUNT(DISTINCT tr.requester_email) AS unique_borrowers
            FROM Approved_Transactions at
            JOIN Transaction_Requests tr ON at.request_id = tr.request_id
            JOIN Book_Copies bc ON at.copy_id = bc.copy_id
            JOIN Books b ON bc.isbn = b.isbn
            LEFT JOIN Book_Courses bcs ON b.isbn = bcs.isbn
            LEFT JOIN Courses c ON bcs.course_id = c.course_id
            LEFT JOIN Students s ON tr.requester_email = s.email
            WHERE 1=1";

    if ($startDate && $endDate) {
        $sql .= " AND at.issue_date BETWEEN :start_date AND :end_date";
    }

    if (!empty($semester)) {
        $sql .= " AND c.semester = :semester";
    }

    if (!empty($session)) {
        $sql .= " AND s.session = :session";
    }

    $sql .= " GROUP BY b.isbn, b.title, b.author, b.category
              ORDER BY borrow_count DESC
              LIMIT 50";

    $stmt = $pdo->prepare($sql);

    if ($startDate && $endDate) {
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
    }

    if (!empty($semester)) {
        $stmt->bindParam(':semester', $semester);
    }

    if (!empty($session)) {
        $stmt->bindParam(':session', $session);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateMostRequestedReport($pdo, $startDate, $endDate, $semester, $session) {
    $sql = "SELECT 
                b.isbn,
                b.title,
                b.author,
                b.category,
                COUNT(tr.request_id) AS request_count,
                COUNT(DISTINCT tr.requester_email) AS unique_requesters,
                SUM(CASE WHEN tr.status = 'Pending' THEN 1 ELSE 0 END) AS pending_requests,
                SUM(CASE WHEN tr.status = 'Approved' THEN 1 ELSE 0 END) AS approved_requests
            FROM Transaction_Requests tr
            JOIN Books b ON tr.isbn = b.isbn
            LEFT JOIN Book_Courses bcs ON b.isbn = bcs.isbn
            LEFT JOIN Courses c ON bcs.course_id = c.course_id
            LEFT JOIN Users u ON tr.requester_email = u.email
            LEFT JOIN Students s ON u.email = s.email
            WHERE tr.status IN ('Pending','Approved')";

    if ($startDate && $endDate) {
        $sql .= " AND DATE(tr.request_date) BETWEEN :start_date AND :end_date";
    }

    if (!empty($semester)) {
        $sql .= " AND c.semester = :semester";
    }

    if (!empty($session)) {
        $sql .= " AND s.session = :session";
    }

    $sql .= " GROUP BY b.isbn, b.title, b.author, b.category
              ORDER BY request_count DESC
              LIMIT 50";

    $stmt = $pdo->prepare($sql);

    if ($startDate && $endDate) {
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
    }

    if (!empty($semester)) {
        $stmt->bindParam(':semester', $semester);
    }

    if (!empty($session)) {
        $stmt->bindParam(':session', $session);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateSemesterWiseReport($pdo, $startDate, $endDate) {
    $sql = "SELECT 
                COALESCE(c.semester, 'Unassigned') AS semester,
                COUNT(DISTINCT at.transaction_id) AS borrow_count,
                COUNT(DISTINCT tr.request_id) AS request_count,
                COUNT(DISTINCT tr.requester_email) AS unique_borrowers,
                COUNT(DISTINCT b.isbn) AS book_count
            FROM Approved_Transactions at
            JOIN Transaction_Requests tr ON at.request_id = tr.request_id
            JOIN Book_Copies bc ON at.copy_id = bc.copy_id
            JOIN Books b ON bc.isbn = b.isbn
            LEFT JOIN Book_Courses bcs ON b.isbn = bcs.isbn
            LEFT JOIN Courses c ON bcs.course_id = c.course_id
            WHERE 1=1";

    if ($startDate && $endDate) {
        $sql .= " AND at.issue_date BETWEEN :start_date AND :end_date";
    }

    $sql .= " GROUP BY semester
              ORDER BY 
                CASE 
                    WHEN semester = 'Unassigned' THEN 999
                    ELSE CAST(semester AS UNSIGNED)
                END ASC";

    $stmt = $pdo->prepare($sql);

    if ($startDate && $endDate) {
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateSessionWiseReport($pdo, $startDate, $endDate) {
    // Session-wise report aggregates semesters by academic year
    // Year 1: semesters 11 + 12
    // Year 2: semesters 21 + 22
    // Year 3: semesters 31 + 32
    // Year 4: semesters 41 + 42
    
    $sql = "SELECT 
                CASE 
                    WHEN FLOOR(CAST(c.semester AS UNSIGNED) / 10) = 1 THEN 'Year 1'
                    WHEN FLOOR(CAST(c.semester AS UNSIGNED) / 10) = 2 THEN 'Year 2'
                    WHEN FLOOR(CAST(c.semester AS UNSIGNED) / 10) = 3 THEN 'Year 3'
                    WHEN FLOOR(CAST(c.semester AS UNSIGNED) / 10) = 4 THEN 'Year 4'
                    ELSE 'Unassigned'
                END AS academic_year,
                COUNT(DISTINCT at.transaction_id) AS borrow_count,
                COUNT(DISTINCT tr.request_id) AS request_count,
                COUNT(DISTINCT r.reservation_id) AS reservation_count,
                COUNT(DISTINCT tr.requester_email) AS unique_users,
                COUNT(DISTINCT b.isbn) AS book_count
            FROM Approved_Transactions at
            JOIN Transaction_Requests tr ON at.request_id = tr.request_id
            JOIN Book_Copies bc ON at.copy_id = bc.copy_id
            JOIN Books b ON bc.isbn = b.isbn
            LEFT JOIN Book_Courses bcs ON b.isbn = bcs.isbn
            LEFT JOIN Courses c ON bcs.course_id = c.course_id
            LEFT JOIN Reservations r ON tr.requester_email = r.user_email
            WHERE 1=1";

    if ($startDate && $endDate) {
        $sql .= " AND at.issue_date BETWEEN :start_date AND :end_date";
    }

    $sql .= " GROUP BY academic_year
              ORDER BY 
                CASE 
                    WHEN academic_year = 'Year 1' THEN 1
                    WHEN academic_year = 'Year 2' THEN 2
                    WHEN academic_year = 'Year 3' THEN 3
                    WHEN academic_year = 'Year 4' THEN 4
                    ELSE 5
                END ASC";

    $stmt = $pdo->prepare($sql);

    if ($startDate && $endDate) {
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function outputCSV($data, $reportType) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $reportType . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data) && is_array($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function outputPDF($data, $reportType, $startDate, $endDate) {
    require_once __DIR__ . '/../../tcpdf/tcpdf.php';
    
    $rows = is_array($data) ? $data : [];
    $headers = !empty($rows) ? array_keys($rows[0]) : [];
    $colCount = count($headers);
    
    // Use landscape for more columns
    $orientation = ($colCount > 5) ? 'L' : 'P';
    
    $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator('IIT Shelf Library System');
    $pdf->SetAuthor('IIT Shelf');
    $pdf->SetTitle(ucwords(str_replace('_', ' ', $reportType)) . ' Report');
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(8, 12, 8);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();
    
    // Title
    $pdf->SetFont('helvetica', 'B', 14);
    $title = ucwords(str_replace('_', ' ', $reportType)) . ' Report';
    $pdf->Cell(0, 8, $title, 0, 1, 'C');
    
    // Date range
    if ($startDate && $endDate) {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, "Period: $startDate to $endDate", 0, 1, 'C');
    }
    
    $pdf->Ln(3);
    
    if (empty($rows)) {
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 10, 'No data available for this report.', 0, 1, 'C');
    } else {
        // Calculate intelligent column widths
        $pageWidth = $pdf->getPageWidth() - 16; // accounting for margins
        $colWidths = calculatePDFColumnWidths($headers, $rows, $pageWidth, $pdf);
        
        // Table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(66, 139, 202);
        $pdf->SetTextColor(255, 255, 255);
        
        foreach ($headers as $idx => $header) {
            $width = $colWidths[$idx];
            $pdf->Cell($width, 8, ucwords(str_replace('_', ' ', $header)), 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Table rows
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(240, 245, 250);
        
        $rowCount = 0;
        foreach ($rows as $row) {
            $maxHeight = 6;
            $cellData = [];
            
            // Calculate row height based on content
            foreach ($headers as $idx => $header) {
                $cellText = (string)($row[$header] ?? '');
                $width = $colWidths[$idx];
                $numLines = ceil(strlen($cellText) / (($width - 2) / 2.5)); // Estimate based on font width
                $cellData[] = $cellText;
                $maxHeight = max($maxHeight, $numLines * 5);
            }
            
            // Ensure minimum height
            $maxHeight = max($maxHeight, 6);
            
            // Draw cells
            $startY = $pdf->GetY();
            $fill = ($rowCount % 2 == 0);
            
            foreach ($headers as $idx => $header) {
                $width = $colWidths[$idx];
                $text = $cellData[$idx];
                
                // Use MultiCell for better text wrapping
                $x = $pdf->GetX();
                $y = $startY;
                
                $pdf->SetXY($x, $y);
                $pdf->MultiCell($width, $maxHeight, $text, 1, 'L', $fill, 0);
                $pdf->SetXY($x + $width, $y);
            }
            
            $pdf->SetXY(8, $startY + $maxHeight);
            $pdf->Ln($maxHeight);
            $rowCount++;
            
            // Add new page if needed
            if ($pdf->GetY() > ($pdf->getPageHeight() - 20)) {
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(66, 139, 202);
                $pdf->SetTextColor(255, 255, 255);
                
                foreach ($headers as $idx => $header) {
                    $width = $colWidths[$idx];
                    $pdf->Cell($width, 8, ucwords(str_replace('_', ' ', $header)), 1, 0, 'C', true);
                }
                $pdf->Ln();
                
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor(0, 0, 0);
            }
        }
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $reportType . '_' . date('Y-m-d') . '.pdf"');
    $pdf->Output($reportType . '_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

function calculatePDFColumnWidths($headers, $rows, $totalWidth, $pdf) {
    $colCount = count($headers);
    $widths = array_fill(0, $colCount, $totalWidth / $colCount);
    
    // Adjust widths based on content and header length
    $textWidths = [];
    
    foreach ($headers as $idx => $header) {
        $maxLen = strlen($header);
        
        foreach ($rows as $row) {
            $cellText = (string)($row[$header] ?? '');
            $maxLen = max($maxLen, strlen($cellText));
        }
        
        $textWidths[$idx] = $maxLen;
    }
    
    // Calculate proportional widths
    $totalLen = array_sum($textWidths);
    if ($totalLen > 0) {
        foreach ($textWidths as $idx => $len) {
            $widths[$idx] = ($len / $totalLen) * $totalWidth;
            // Enforce minimum and maximum column widths
            $widths[$idx] = max(20, min(100, $widths[$idx]));
        }
    }
    
    // Normalize widths to fit page
    $totalCalc = array_sum($widths);
    foreach ($widths as &$w) {
        $w = ($w / $totalCalc) * $totalWidth;
    }
    
    return $widths;
}
?>
