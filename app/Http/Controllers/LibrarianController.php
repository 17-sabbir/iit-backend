<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegacyEndpointRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LibrarianController extends Controller
{
    public function requests(LegacyEndpointRequest $request): JsonResponse
    {
        $type = $request->string('type')->toString();
        $search = trim($request->string('search')->toString());
        if ($type === 'borrow') {
            DB::table('Transaction_Requests')->where('status', 'Pending')->where('request_date', '<', now()->subHours(24))->delete();
            $query = DB::table('Transaction_Requests as tr')->join('Users as u', 'u.email', '=', 'tr.requester_email')->join('Books as b', 'b.isbn', '=', 'tr.isbn')
                ->where('tr.status', 'Pending');
            if ($search !== '') $query->where(fn ($q) => $q->where('u.name', 'like', "%{$search}%")->orWhere('u.email', 'like', "%{$search}%")->orWhere('b.title', 'like', "%{$search}%")->orWhere('tr.isbn', 'like', "%{$search}%"));
            $rows = $query->orderByDesc('tr.request_date')->get(['tr.request_id', 'tr.isbn', 'tr.request_date', 'u.name', 'u.email', 'b.title']);
            $items = $rows->map(function ($row): array {
                $minutes = max(0, (int) Carbon::parse($row->request_date)->addHours(24)->diffInMinutes(now(), false) * -1);
                return (array) $row + [
                    'hours_old' => (int) Carbon::parse($row->request_date)->diffInHours(now()),
                    'minutes_until_expiry' => $minutes,
                    'expires_in_hours' => (int) floor($minutes / 60),
                    'expires_in_minutes' => $minutes % 60,
                    'is_expired' => $minutes === 0,
                ];
            });
            return ApiResponse::success(['count' => $items->count(), 'items' => $items]);
        }

        if ($type === 'addition') {
            $query = DB::table('Requests as req')->leftJoin('Users as u', 'u.email', '=', 'req.requester_identifier')->where('req.status', 'Pending');
            if ($search !== '') $query->where(fn ($q) => $q->where('u.name', 'like', "%{$search}%")->orWhere('req.requester_identifier', 'like', "%{$search}%")->orWhere('req.title', 'like', "%{$search}%")->orWhere('req.isbn', 'like', "%{$search}%"));
            $items = $query->orderByDesc('req.request_id')->get([
                'req.request_id', 'req.title as requested_title', 'req.isbn', 'req.requester_identifier as email',
                DB::raw("COALESCE(u.name, 'Unknown') as name"), 'req.status', 'req.approved_by', 'req.approved_at', 'req.description',
            ]);
            return ApiResponse::success(['count' => $items->count(), 'items' => $items]);
        }

        if ($type === 'reserve') {
            $isbns = DB::table('Reservations')->where('status', 'Active')->distinct()->pluck('isbn');
            foreach ($isbns as $isbn) $this->renumberQueue($isbn);
            $query = DB::table('Reservations as r')->join('Users as u', 'u.email', '=', 'r.user_email')->join('Books as b', 'b.isbn', '=', 'r.isbn')->where('r.status', 'Active');
            if ($search !== '') $query->where(fn ($q) => $q->where('u.name', 'like', "%{$search}%")->orWhere('u.email', 'like', "%{$search}%")->orWhere('b.title', 'like', "%{$search}%")->orWhere('r.isbn', 'like', "%{$search}%"));
            $items = $query->orderBy('r.isbn')->orderBy('r.queue_position')->orderBy('r.created_at')->get([
                'r.reservation_id', 'r.isbn', 'r.queue_position', 'r.status', 'r.created_at', 'u.name', 'u.email', 'b.title',
            ]);
            return ApiResponse::success(['count' => $items->count(), 'items' => $items]);
        }

        if ($type === 'return') {
            $items = [];
            foreach (DB::table('Notifications')->where('type', 'ReturnRequestPending')->orderByDesc('sent_at')->get(['user_email', 'message', 'sent_at']) as $notification) {
                if (!preg_match('/Transaction\s+#(\d+)/', $notification->message, $match)) continue;
                $row = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
                    ->join('Users as u', 'u.email', '=', 'tr.requester_email')->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')
                    ->join('Books as b', 'b.isbn', '=', 'bc.isbn')->where('at.transaction_id', (int) $match[1])->where('at.status', 'Borrowed')
                    ->first(['at.transaction_id', 'at.copy_id', 'at.issue_date', 'at.due_date', 'tr.isbn', 'u.name', 'u.email', 'b.title']);
                if (!$row) continue;
                $row->requested_at = $notification->sent_at;
                $row->days_overdue = max(0, (int) Carbon::parse($row->due_date)->startOfDay()->diffInDays(now()->startOfDay()));
                if ($search !== '' && stripos(implode(' ', [(string) $row->name, (string) $row->email, (string) $row->title, (string) $row->isbn]), $search) === false) continue;
                $items[] = $row;
            }
            return ApiResponse::success(['count' => count($items), 'items' => $items]);
        }

        return ApiResponse::error('Invalid type', 400);
    }

    public function dashboard(): JsonResponse
    {
        $pendingReturnIds = [];
        foreach (DB::table('Notifications')->where('type', 'ReturnRequestPending')->pluck('message') as $message) {
            if (preg_match('/Transaction\s+#(\d+)/', $message, $match)) $pendingReturnIds[$match[1]] = true;
        }
        $recent = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->join('Users as u', 'u.email', '=', 'tr.requester_email')->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')
            ->join('Books as b', 'b.isbn', '=', 'bc.isbn')->orderByDesc('at.issue_date')->limit(5)
            ->get(['at.transaction_id', 'at.copy_id', 'at.issue_date', 'at.return_date', 'at.status', 'u.name', 'tr.requester_email as email', 'b.title']);

        return ApiResponse::success([
            'stats' => [
                'total_books' => DB::table('Books')->where('title', 'not like', '[DELETED]%')->distinct('isbn')->count('isbn'),
                'pending_returns' => count($pendingReturnIds),
                'pending_requests' => DB::table('Transaction_Requests')->where('status', 'Pending')->count(),
                'fines_collected_today' => (float) DB::table('Fines')->where('paid', true)->whereDate('payment_date', today())->sum('amount'),
                'return_approvals' => count($pendingReturnIds),
                'new_book_requests' => DB::table('Requests')->where('status', 'Pending')->count(),
                'payment_verifications' => DB::table('Fines')->where('paid', false)->count(),
            ],
            'recent_activity' => $recent,
        ]);
    }

    public function transactionHistory(LegacyEndpointRequest $request): JsonResponse
    {
        $filter = $request->string('filter')->toString() ?: 'All';
        $search = trim($request->string('search')->toString());
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $transactions = [];
        $base = function (string $dateColumn, string $type, string $status, bool $requireReturn = false) use ($search, $start, $end) {
            $query = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
                ->join('Users as u', 'u.email', '=', 'tr.requester_email')->join('Books as b', 'b.isbn', '=', 'tr.isbn');
            if ($type === 'Borrow') $query->whereIn('at.status', ['Borrowed', 'Returned']);
            else $query->where('at.status', 'Returned')->whereNotNull('at.return_date');
            if ($requireReturn) $query->whereNotNull('at.return_date');
            if ($search !== '') $query->where(fn ($q) => $q->where('u.email', 'like', "%{$search}%")->orWhere('u.name', 'like', "%{$search}%")->orWhere('b.title', 'like', "%{$search}%"));
            if ($start) $query->whereDate($dateColumn, '>=', $start);
            if ($end) $query->whereDate($dateColumn, '<=', $end);
            return $query->get([
                DB::raw("'{$type}' as type"), 'b.title as book_title', 'u.email as user_id', 'u.name as user_name',
                DB::raw("DATE({$dateColumn}) as date"), DB::raw("DATE_FORMAT({$dateColumn}, '%h:%i %p') as time"), DB::raw("'{$status}' as status"),
            ]);
        };
        if (in_array($filter, ['All', 'Borrow'], true)) $transactions = array_merge($transactions, $base('at.issue_date', 'Borrow', 'Completed')->all());
        if (in_array($filter, ['All', 'Return'], true)) $transactions = array_merge($transactions, $base('at.return_date', 'Return', 'Completed', true)->all());

        $reservations = function () use ($search, $start, $end) {
            $query = DB::table('Reservations as r')->join('Users as u', 'u.email', '=', 'r.user_email')->join('Books as b', 'b.isbn', '=', 'r.isbn');
            if ($search !== '') $query->where(fn ($q) => $q->where('r.user_email', 'like', "%{$search}%")->orWhere('u.name', 'like', "%{$search}%")->orWhere('b.title', 'like', "%{$search}%"));
            if ($start) $query->whereDate('r.created_at', '>=', $start);
            if ($end) $query->whereDate('r.created_at', '<=', $end);
            return $query->get([
                DB::raw("'Reservation' as type"), 'b.title as book_title', 'r.user_email as user_id', 'u.name as user_name',
                DB::raw('DATE(r.created_at) as date'), DB::raw("DATE_FORMAT(r.created_at, '%h:%i %p') as time"), 'r.status',
            ]);
        };
        if (in_array($filter, ['All', 'Reservation'], true)) $transactions = array_merge($transactions, $reservations()->all());

        usort($transactions, static fn ($left, $right) => strtotime($right->date) <=> strtotime($left->date));
        return ApiResponse::success(['count' => count($transactions), 'transactions' => $transactions]);
    }

    public function shelves(LegacyEndpointRequest $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return ApiResponse::success(['shelves' => DB::table('Shelves')->where('is_deleted', false)->orderBy('shelf_id')->get()]);
        }
        $shelfId = $request->integer('shelf_id');
        if ($request->isMethod('POST')) {
            DB::table('Shelves')->insert(['shelf_id' => $shelfId, 'compartment' => $request->integer('compartment'), 'subcompartment' => $request->integer('subcompartment'), 'is_deleted' => false]);
            return ApiResponse::success(['shelf_id' => $shelfId, 'compartment' => $request->integer('compartment'), 'subcompartment' => $request->integer('subcompartment')], 200, 'Shelf location added successfully');
        }
        if ($request->isMethod('PUT')) {
            $updated = DB::table('Shelves')->where('shelf_id', $shelfId)->where('is_deleted', false)->update(['compartment' => $request->integer('compartment'), 'subcompartment' => $request->integer('subcompartment')]);
            if (!$updated && !DB::table('Shelves')->where('shelf_id', $shelfId)->exists()) return ApiResponse::error('Shelf not found.', 404);
            return ApiResponse::success([], 200, 'Shelf location updated successfully');
        }
        DB::table('Shelves')->where('shelf_id', $shelfId)->update(['is_deleted' => true]);
        return ApiResponse::success([], 200, 'Shelf deleted successfully');
    }

    public function syncStatus(): JsonResponse
    {
        return ApiResponse::success([
            'counts' => [
                'pending_borrow_requests' => DB::table('Transaction_Requests')->where('status', 'Pending')->count(),
                'borrowed_transactions' => DB::table('Approved_Transactions')->where('status', 'Borrowed')->count(),
                'active_reservations' => DB::table('Reservations')->where('status', 'Active')->count(),
                'pending_addition_requests' => DB::table('Requests')->where('status', 'Pending')->count(),
            ],
        ], 200, 'Database sync status');
    }

    public function approveReturnNotification(LegacyEndpointRequest $request): JsonResponse
    {
        $row = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')->join('Books as b', 'b.isbn', '=', 'bc.isbn')
            ->where('at.transaction_id', $request->integer('transaction_id'))
            ->first(['tr.requester_email', 'b.title']);
        if (!$row) return ApiResponse::error('Transaction not found', 404);
        DB::table('Notifications')->insert([
            'user_email' => $row->requester_email,
            'message' => "Your return request for '{$row->title}' has been approved.",
            'type' => 'ReturnRequestApproved',
            'sent_at' => now(),
        ]);
        return ApiResponse::success([], 200, 'Return request approved');
    }

    private function renumberQueue(string $isbn): void
    {
        DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')->whereNotNull('expires_at')->where('expires_at', '<', now())->update(['status' => 'Cancelled']);
        $rows = DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')->orderBy('queue_position')->orderBy('reservation_id')->get(['reservation_id', 'queue_position']);
        foreach ($rows as $index => $row) {
            if ((int) $row->queue_position !== $index + 1) DB::table('Reservations')->where('reservation_id', $row->reservation_id)->update(['queue_position' => $index + 1]);
        }
    }
}