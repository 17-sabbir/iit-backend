<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegacyEndpointRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function generate(LegacyEndpointRequest $request): Response|JsonResponse
    {
        $type = $request->string('report_type')->toString();
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $semester = $request->input('semester');
        $session = $request->input('session');

        $rows = match ($type) {
            'most_borrowed' => $this->mostBorrowed($start, $end, $semester, $session),
            'most_requested' => $this->mostRequested($start, $end, $semester, $session),
            'semester_wise' => $this->semesterWise($start, $end),
            'session_wise' => $this->sessionWise($start, $end),
            default => null,
        };
        if ($rows === null) return ApiResponse::error('Invalid report type', 400);

        $format = $request->string('format')->toString() ?: 'json';
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows): void {
                $stream = fopen('php://output', 'w');
                if ($rows !== []) {
                    fputcsv($stream, array_keys((array) $rows[0]));
                    foreach ($rows as $row) fputcsv($stream, (array) $row);
                }
                fclose($stream);
            }, $type . '_' . now()->toDateString() . '.csv', ['Content-Type' => 'text/csv']);
        }

        if ($format === 'pdf') {
            $library = base_path('tcpdf/tcpdf.php');
            if (!is_file($library)) return ApiResponse::error('PDF export library is unavailable.', 501);
            require_once $library;
            $pdf = new \TCPDF(count((array) ($rows[0] ?? [])) > 5 ? 'L' : 'P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('IIT Shelf Library System');
            $pdf->SetTitle(ucwords(str_replace('_', ' ', $type)) . ' Report');
            $pdf->setPrintHeader(false);
            $pdf->AddPage();
            $html = '<h2>' . htmlspecialchars(ucwords(str_replace('_', ' ', $type)), ENT_QUOTES, 'UTF-8') . ' Report</h2>';
            if ($start && $end) $html .= '<p>Period: ' . htmlspecialchars($start . ' to ' . $end, ENT_QUOTES, 'UTF-8') . '</p>';
            $html .= '<table border="1" cellpadding="4"><thead><tr>';
            foreach (array_keys((array) ($rows[0] ?? [])) as $key) $html .= '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $key)), ENT_QUOTES, 'UTF-8') . '</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ((array) $row as $value) $html .= '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $pdf->writeHTML($html, true, false, true, false, '');
            return response($pdf->Output($type . '.pdf', 'S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $type . '.pdf"',
            ]);
        }

        return ApiResponse::success([
            'data' => $rows,
            'report_type' => $type,
            'date_range' => ['start' => $start, 'end' => $end],
        ]);
    }

    private function mostBorrowed(?string $start, ?string $end, ?string $semester, ?string $session): array
    {
        $query = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')->join('Books as b', 'b.isbn', '=', 'bc.isbn')
            ->leftJoin('Book_Courses as bcs', 'bcs.isbn', '=', 'b.isbn')->leftJoin('Courses as c', 'c.course_id', '=', 'bcs.course_id')
            ->leftJoin('Students as s', 's.email', '=', 'tr.requester_email');
        if ($start && $end) $query->whereBetween('at.issue_date', [$start, $end]);
        if ($semester) $query->where('c.semester', $semester);
        if ($session) $query->where('s.session', $session);
        return $query->groupBy('b.isbn', 'b.title', 'b.author', 'b.category')->orderByDesc('borrow_count')->limit(50)
            ->get(['b.isbn', 'b.title', 'b.author', 'b.category', DB::raw('COUNT(at.transaction_id) as borrow_count'), DB::raw('COUNT(DISTINCT tr.requester_email) as unique_borrowers')])->all();
    }

    private function mostRequested(?string $start, ?string $end, ?string $semester, ?string $session): array
    {
        $query = DB::table('Transaction_Requests as tr')->join('Books as b', 'b.isbn', '=', 'tr.isbn')
            ->leftJoin('Book_Courses as bcs', 'bcs.isbn', '=', 'b.isbn')->leftJoin('Courses as c', 'c.course_id', '=', 'bcs.course_id')
            ->leftJoin('Students as s', 's.email', '=', 'tr.requester_email')->whereIn('tr.status', ['Pending', 'Approved']);
        if ($start && $end) $query->whereBetween(DB::raw('DATE(tr.request_date)'), [$start, $end]);
        if ($semester) $query->where('c.semester', $semester);
        if ($session) $query->where('s.session', $session);
        return $query->groupBy('b.isbn', 'b.title', 'b.author', 'b.category')->orderByDesc('request_count')->limit(50)
            ->get(['b.isbn', 'b.title', 'b.author', 'b.category', DB::raw('COUNT(tr.request_id) as request_count'), DB::raw('COUNT(DISTINCT tr.requester_email) as unique_requesters'), DB::raw("SUM(CASE WHEN tr.status = 'Pending' THEN 1 ELSE 0 END) as pending_requests"), DB::raw("SUM(CASE WHEN tr.status = 'Approved' THEN 1 ELSE 0 END) as approved_requests")])->all();
    }

    private function semesterWise(?string $start, ?string $end): array
    {
        $query = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')->join('Books as b', 'b.isbn', '=', 'bc.isbn')
            ->leftJoin('Book_Courses as bcs', 'bcs.isbn', '=', 'b.isbn')->leftJoin('Courses as c', 'c.course_id', '=', 'bcs.course_id');
        if ($start && $end) $query->whereBetween('at.issue_date', [$start, $end]);
        return $query->groupBy('c.semester')->orderByRaw("CASE WHEN c.semester IS NULL THEN 999 ELSE CAST(c.semester AS UNSIGNED) END")
            ->get([DB::raw("COALESCE(c.semester, 'Unassigned') as semester"), DB::raw('COUNT(DISTINCT at.transaction_id) as borrow_count'), DB::raw('COUNT(DISTINCT tr.request_id) as request_count'), DB::raw('COUNT(DISTINCT tr.requester_email) as unique_borrowers'), DB::raw('COUNT(DISTINCT b.isbn) as book_count')])->all();
    }

    private function sessionWise(?string $start, ?string $end): array
    {
        $query = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')->join('Books as b', 'b.isbn', '=', 'bc.isbn')
            ->leftJoin('Book_Courses as bcs', 'bcs.isbn', '=', 'b.isbn')->leftJoin('Courses as c', 'c.course_id', '=', 'bcs.course_id')
            ->leftJoin('Reservations as r', 'r.user_email', '=', 'tr.requester_email');
        if ($start && $end) $query->whereBetween('at.issue_date', [$start, $end]);
        return $query->groupBy('academic_year')->orderByRaw("CASE academic_year WHEN 'Year 1' THEN 1 WHEN 'Year 2' THEN 2 WHEN 'Year 3' THEN 3 WHEN 'Year 4' THEN 4 ELSE 5 END")
            ->get([DB::raw("CASE WHEN FLOOR(CAST(c.semester AS UNSIGNED) / 10) BETWEEN 1 AND 4 THEN CONCAT('Year ', FLOOR(CAST(c.semester AS UNSIGNED) / 10)) ELSE 'Unassigned' END as academic_year"), DB::raw('COUNT(DISTINCT at.transaction_id) as borrow_count'), DB::raw('COUNT(DISTINCT tr.request_id) as request_count'), DB::raw('COUNT(DISTINCT r.reservation_id) as reservation_count'), DB::raw('COUNT(DISTINCT tr.requester_email) as unique_users'), DB::raw('COUNT(DISTINCT b.isbn) as book_count')])->all();
    }
}