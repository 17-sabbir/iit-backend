<?php

namespace App\Http\Controllers;

use App\Http\Requests\BorrowRequest;
use App\Services\BorrowService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\LegacyEndpointRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BorrowController extends Controller
{
    public function __construct(private readonly BorrowService $borrow) {}

    public function request(BorrowRequest $request): JsonResponse
    {
        $borrowRequest = $this->borrow->request($request->user(), $request->string('isbn')->toString());
        return ApiResponse::success(['request_id' => $borrowRequest->request_id], 201, 'Borrow request submitted successfully.');
    }

    public function approveBorrowRequest(LegacyEndpointRequest $request): JsonResponse
    {
        $requestId = $request->integer('request_id');
        $pending = DB::table('Transaction_Requests as tr')->join('Users as u', 'u.email', '=', 'tr.requester_email')
            ->where('tr.request_id', $requestId)->where('tr.status', 'Pending')
            ->first(['tr.request_id', 'tr.isbn', 'tr.requester_email', 'u.role']);
        if (!$pending) return ApiResponse::error('Pending request not found', 404);

        $limits = ['student' => 2, 'teacher' => 5, 'librarian' => 10, 'director' => 10];
        $role = strtolower($pending->role);
        $limit = $limits[$role] ?? 2;
        $borrowed = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->where('tr.requester_email', $pending->requester_email)->where('at.status', 'Borrowed')->count();
        if ($borrowed >= $limit) return ApiResponse::error("User has reached their borrowing limit ({$limit} books).", 400);
        $unpaid = (float) DB::table('Fines')->where('user_email', $pending->requester_email)->where('paid', false)->sum('amount');
        if ($unpaid >= 200) return ApiResponse::error("User has unpaid fines totaling {$unpaid} BDT. Fines must be below 200 BDT to borrow books.", 400);

        $loanDays = ['student' => 7, 'teacher' => 15, 'librarian' => 30, 'director' => 30][$role] ?? 14;
        $transaction = DB::transaction(function () use ($pending, $request, $loanDays): ?int {
            $copy = DB::table('Book_Copies')->where('copy_id', $request->string('copy_id')->toString())
                ->where('isbn', $pending->isbn)->where('status', 'Available')->lockForUpdate()->first();
            if (!$copy) return null;
            DB::table('Transaction_Requests')->where('request_id', $pending->request_id)->where('status', 'Pending')->update(['status' => 'Approved']);
            $transactionId = DB::table('Approved_Transactions')->insertGetId([
                'request_id' => $pending->request_id,
                'copy_id' => $copy->copy_id,
                'issue_date' => now(),
                'due_date' => now()->addDays($loanDays),
                'status' => 'Borrowed',
            ]);
            DB::table('Book_Copies')->where('copy_id', $copy->copy_id)->update(['status' => 'Borrowed']);
            $title = DB::table('Books')->where('isbn', $pending->isbn)->value('title') ?? 'Unknown Book';
            DB::table('Notifications')->insert([
                'user_email' => $pending->requester_email,
                'message' => "Your borrow request for '{$title}' is approved. Due in {$loanDays} days.",
                'type' => 'BorrowRequestApproved',
                'sent_at' => now(),
            ]);
            return $transactionId;
        });

        if (!$transaction) return ApiResponse::error('Selected copy is not available for this ISBN', 400);
        return ApiResponse::success(['copy_id' => $request->string('copy_id')->toString()], 200, 'Request approved');
    }

    public function rejectBorrowRequest(LegacyEndpointRequest $request): JsonResponse
    {
        $pending = DB::table('Transaction_Requests as tr')->join('Books as b', 'b.isbn', '=', 'tr.isbn')
            ->where('tr.request_id', $request->integer('request_id'))->where('tr.status', 'Pending')
            ->first(['tr.request_id', 'tr.requester_email', 'b.title']);
        if (!$pending) return ApiResponse::error('Pending request not found', 404);

        DB::transaction(function () use ($pending): void {
            DB::table('Transaction_Requests')->where('request_id', $pending->request_id)->update(['status' => 'Rejected']);
            DB::table('Notifications')->insert([
                'user_email' => $pending->requester_email,
                'message' => "Your borrow request for '{$pending->title}' has been rejected by the librarian.",
                'type' => 'BorrowRequestRejected',
                'sent_at' => now(),
            ]);
        });
        return ApiResponse::success([], 200, 'Request rejected successfully');
    }

    public function requestReturn(LegacyEndpointRequest $request): JsonResponse
    {
        $transaction = DB::table('Approved_Transactions as at')
            ->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->where('at.transaction_id', $request->integer('transaction_id'))
            ->where('at.status', 'Borrowed')
            ->where('tr.requester_email', $request->user()->email)
            ->first(['at.transaction_id', 'tr.requester_email', 'tr.isbn']);

        if (!$transaction) {
            return ApiResponse::error('Active borrowed transaction not found for user.', 404);
        }

        $title = DB::table('Books')->where('isbn', $transaction->isbn)->value('title') ?? 'Unknown Book';
        $message = "Return request for '{$title}' (Transaction #{$transaction->transaction_id}) is pending librarian approval.";
        DB::transaction(function () use ($request, $message, $transaction): void {
            $librarianEmails = DB::table('Users')->whereIn('role', ['Librarian', 'librarian'])->pluck('email');
            foreach ($librarianEmails as $email) {
                DB::table('Notifications')->insert([
                    'user_email' => $email,
                    'message' => "New return request from {$transaction->requester_email} for '{$message}'",
                    'type' => 'ReturnRequestPending',
                    'sent_at' => now(),
                ]);
            }
            DB::table('Notifications')->insert([
                'user_email' => $transaction->requester_email,
                'message' => $message,
                'type' => 'ReturnRequestPending',
                'sent_at' => now(),
            ]);
        });

        return ApiResponse::success([], 200, 'Return request submitted. Waiting for librarian approval.');
    }

    public function cancelRequest(LegacyEndpointRequest $request): JsonResponse
    {
        $requestId = $request->integer('request_id') ?: $request->integer('transaction_id');
        $borrowRequest = DB::table('Transaction_Requests')->where('request_id', $requestId)
            ->where('requester_email', $request->user()->email)->first(['request_id', 'status']);
        if (!$borrowRequest) {
            return ApiResponse::error('Request not found or does not belong to you.', 404);
        }
        if ($borrowRequest->status !== 'Pending') {
            return ApiResponse::error('Only pending requests can be cancelled.', 400);
        }

        DB::table('Transaction_Requests')->where('request_id', $requestId)->update(['status' => 'Cancelled']);
        return ApiResponse::success([], 200, 'Request cancelled successfully');
    }

    public function reserve(LegacyEndpointRequest $request): JsonResponse
    {
        $isbn = $request->string('isbn')->toString();
        $email = $request->user()->email;
        if (!DB::table('Books')->where('isbn', $isbn)->exists()) {
            return ApiResponse::error('Book not found', 404);
        }
        if (DB::table('Reservations')->where('isbn', $isbn)->where('user_email', $email)->where('status', 'Active')->exists()) {
            return ApiResponse::error('You already have an active reservation for this book', 409);
        }

        $reservation = DB::transaction(function () use ($isbn, $email): array {
            $position = (int) DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')->max('queue_position') + 1;
            $id = DB::table('Reservations')->insertGetId([
                'isbn' => $isbn,
                'user_email' => $email,
                'queue_position' => $position,
                'status' => 'Active',
                'created_at' => now(),
            ]);
            return ['reservation_id' => $id, 'queue_position' => $position];
        });

        return ApiResponse::success($reservation, 201, 'Book reserved successfully');
    }

    public function cancelReservation(LegacyEndpointRequest $request): JsonResponse
    {
        $query = DB::table('Reservations')->where('reservation_id', $request->integer('reservation_id'))->where('status', 'Active');
        if (!in_array($request->user()->role, ['Librarian', 'Director'], true)) {
            $query->where('user_email', $request->user()->email);
        }
        $updated = $query->update(['status' => 'Cancelled']);
        return $updated
            ? ApiResponse::success([], 200, 'Reservation cancelled')
            : ApiResponse::error('Reservation not found or already closed', 404);
    }

    public function userReservations(LegacyEndpointRequest $request): JsonResponse
    {
        $email = $request->user()->email;
        $isbns = DB::table('Reservations')->where('status', 'Active')->distinct()->pluck('isbn');
        foreach ($isbns as $isbn) {
            $this->renumberReservationQueue($isbn);
        }

        $rows = DB::table('Reservations as r')
            ->leftJoin('Books as b', 'b.isbn', '=', 'r.isbn')
            ->leftJoin('Book_Copies as bc', 'bc.isbn', '=', 'r.isbn')
            ->where('r.user_email', $email)->whereIn('r.status', ['Active', 'Pending'])
            ->groupBy('r.reservation_id', 'r.isbn', 'b.title', 'b.author', 'b.category', 'b.pic_path', 'r.queue_position', 'r.status', 'r.created_at', 'r.expires_at')
            ->orderBy('r.queue_position')->orderBy('r.created_at')
            ->get([
                'r.reservation_id', 'r.isbn', 'b.title', 'b.author', 'b.category', 'b.pic_path',
                'r.queue_position', 'r.status', 'r.created_at', 'r.expires_at',
                DB::raw('COUNT(DISTINCT bc.copy_id) as total_copies'),
                DB::raw("SUM(CASE WHEN bc.status = 'Available' THEN 1 ELSE 0 END) as available_copies"),
            ]);

        $reservations = $rows->map(function ($row): array {
            $expiresAt = $row->expires_at ? Carbon::parse($row->expires_at) : null;
            $ready = $expiresAt && $expiresAt->greaterThan(now());
            $queueCount = DB::table('Reservations')->where('isbn', $row->isbn)->where('status', 'Active')->count();
            return [
                'reservationId' => (int) $row->reservation_id,
                'isbn' => $row->isbn,
                'title' => $row->title,
                'author' => $row->author,
                'category' => $row->category,
                'cover' => $row->pic_path,
                'queuePosition' => (int) $row->queue_position,
                'status' => $row->status,
                'createdAt' => $row->created_at,
                'expiresAt' => $row->expires_at,
                'isReady' => (bool) $ready,
                'hoursRemaining' => $ready ? (int) now()->diffInHours($expiresAt) : 0,
                'totalInQueue' => $queueCount,
                'totalCopies' => (int) $row->total_copies,
                'availableCopies' => (int) ($row->available_copies ?? 0),
            ];
        })->values();

        return ApiResponse::success(['count' => $reservations->count(), 'reservations' => $reservations]);
    }

    public function userTransactions(LegacyEndpointRequest $request): JsonResponse
    {
        $email = $request->user()->email;
        $status = $request->string('status')->toString() ?: 'all';
        DB::table('Transaction_Requests')->where('requester_email', $email)->where('status', 'Pending')
            ->where('request_date', '<', now()->subHours(24))->delete();

        $pendingReturnIds = DB::table('Notifications')->where('user_email', $email)->where('type', 'ReturnRequestPending')
            ->pluck('message')->map(function ($message) {
                return preg_match('/Transaction\s+#(\d+)/', $message, $matches) ? (int) $matches[1] : null;
            })->filter()->all();

        $transactions = [];
        if (in_array($status, ['all', 'borrowed', 'returned'], true)) {
            $query = DB::table('Approved_Transactions as at')
                ->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
                ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')
                ->join('Books as b', 'b.isbn', '=', 'bc.isbn')
                ->leftJoin('Fines as f', 'f.transaction_id', '=', 'at.transaction_id')
                ->where('tr.requester_email', $email);
            if ($status === 'borrowed') $query->whereIn('at.status', ['Borrowed', 'Overdue']);
            if ($status === 'returned') $query->where('at.status', 'Returned');

            foreach ($query->orderByDesc('at.issue_date')->get([
                'at.transaction_id', 'at.copy_id', 'at.issue_date', 'at.due_date', 'at.return_date', 'at.status',
                'b.isbn', 'b.title', 'b.author', 'b.pic_path', 'f.fine_id', 'f.amount as fine_amount',
                'f.paid as fine_paid', DB::raw('DATEDIFF(at.due_date, NOW()) as days_remaining'),
            ]) as $row) {
                if (in_array((int) $row->transaction_id, $pendingReturnIds, true)) continue;
                $dueAt = Carbon::parse($row->due_date);
                $overdue = $row->status === 'Borrowed' && !$row->return_date && now()->greaterThan($dueAt);
                $transactions[] = [
                    'type' => strtolower($overdue ? 'Overdue' : $row->status),
                    'transaction_id' => $row->transaction_id,
                    'copy_id' => $row->copy_id,
                    'isbn' => $row->isbn,
                    'title' => $row->title,
                    'author' => $row->author,
                    'pic_path' => $row->pic_path,
                    'cover' => $row->pic_path,
                    'issue_date' => $row->issue_date,
                    'due_date' => $row->due_date,
                    'return_date' => $row->return_date,
                    'status' => $overdue ? 'Overdue' : $row->status,
                    'is_overdue' => $overdue,
                    'days_overdue' => $overdue ? (int) $dueAt->diffInDays(now()) : 0,
                    'days_remaining' => (int) $row->days_remaining,
                    'fine_id' => $row->fine_id,
                    'fine_amount' => (float) ($row->fine_amount ?? 0),
                    'fine_paid' => $row->fine_paid,
                ];
            }
        }

        if (in_array($status, ['all', 'reserved'], true)) {
            $rows = DB::table('Reservations as r')->join('Books as b', 'b.isbn', '=', 'r.isbn')
                ->where('r.user_email', $email)->where('r.status', 'Active')->orderByDesc('r.created_at')
                ->get(['r.reservation_id', 'r.isbn', 'r.created_at', 'r.expires_at', 'r.status', 'b.title', 'b.author', 'b.pic_path']);
            foreach ($rows as $row) {
                $transactions[] = [
                    'type' => 'reserved', 'reservation_id' => $row->reservation_id, 'isbn' => $row->isbn,
                    'title' => $row->title, 'author' => $row->author, 'pic_path' => $row->pic_path,
                    'reservation_date' => $row->created_at, 'expiry_date' => $row->expires_at, 'status' => $row->status,
                ];
            }
        }

        if (in_array($status, ['all', 'pending'], true)) {
            $rows = DB::table('Transaction_Requests as tr')->join('Books as b', 'b.isbn', '=', 'tr.isbn')
                ->where('tr.requester_email', $email)->where('tr.status', 'Pending')->orderByDesc('tr.request_date')
                ->get(['tr.request_id', 'tr.isbn', 'tr.request_date', 'b.title', 'b.author', 'b.pic_path']);
            foreach ($rows as $row) {
                $minutes = (int) now()->diffInMinutes(Carbon::parse($row->request_date)->addHours(24), false);
                $minutes = max(0, $minutes);
                $transactions[] = [
                    'type' => 'pending', 'request_id' => $row->request_id, 'isbn' => $row->isbn,
                    'title' => $row->title, 'author' => $row->author, 'pic_path' => $row->pic_path,
                    'request_date' => $row->request_date, 'hours_old' => (int) Carbon::parse($row->request_date)->diffInHours(now()),
                    'expires_in_hours' => (int) floor($minutes / 60), 'expires_in_minutes' => $minutes % 60,
                    'is_expired' => $minutes === 0, 'status' => 'Pending',
                ];
            }

            foreach (DB::table('Notifications')->where('user_email', $email)->where('type', 'ReturnRequestPending')->orderByDesc('sent_at')->get(['message', 'sent_at']) as $notification) {
                if (!preg_match('/Transaction\s+#(\d+)/', $notification->message, $matches)) continue;
                $row = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
                    ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')->join('Books as b', 'b.isbn', '=', 'bc.isbn')
                    ->where('at.transaction_id', (int) $matches[1])->where('at.status', 'Borrowed')
                    ->where('tr.requester_email', $email)
                    ->first(['at.transaction_id', 'at.copy_id', 'at.issue_date', 'at.due_date', 'b.isbn', 'b.title', 'b.author', 'b.pic_path']);
                if ($row) {
                    $transactions[] = [
                        'type' => 'pending_return', 'transaction_id' => $row->transaction_id, 'isbn' => $row->isbn,
                        'title' => $row->title, 'author' => $row->author, 'pic_path' => $row->pic_path,
                        'copy_id' => $row->copy_id, 'issue_date' => $row->issue_date, 'due_date' => $row->due_date,
                        'request_date' => $notification->sent_at, 'status' => 'Pending Return',
                    ];
                }
            }
        }

        return ApiResponse::success(['count' => count($transactions), 'transactions' => $transactions]);
    }

    private function renumberReservationQueue(string $isbn): void
    {
        DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')
            ->whereNotNull('expires_at')->where('expires_at', '<', now())->update(['status' => 'Cancelled']);
        $rows = DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')
            ->orderBy('queue_position')->orderBy('reservation_id')->get(['reservation_id', 'queue_position']);
        foreach ($rows as $index => $row) {
            $position = $index + 1;
            if ((int) $row->queue_position !== $position) {
                DB::table('Reservations')->where('reservation_id', $row->reservation_id)->update(['queue_position' => $position]);
            }
        }
    }

    public function returnBook(LegacyEndpointRequest $request): JsonResponse
    {
        $actor = $request->user();
        $transaction = DB::table('Approved_Transactions as at')
            ->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->where('at.status', 'Borrowed')
            ->when($request->filled('transaction_id'), fn ($query) => $query->where('at.transaction_id', $request->integer('transaction_id')))
            ->when(!$request->filled('transaction_id'), fn ($query) => $query->where('at.copy_id', $request->string('copy_id')->toString())->orderByDesc('at.issue_date'))
            ->when(!in_array($actor->role, ['Librarian', 'Director'], true), fn ($query) => $query->where('tr.requester_email', $actor->email))
            ->first(['at.transaction_id', 'at.copy_id', 'at.due_date', 'tr.requester_email', 'tr.isbn']);

        if (!$transaction) {
            return ApiResponse::error('Active borrow transaction not found for user.', 404);
        }

        $now = now();
        $dueAt = Carbon::parse($transaction->due_date);
        $daysOverdue = $now->greaterThan($dueAt) ? (int) $dueAt->diffInDays($now) : 0;
        $lateFine = $daysOverdue * 10;
        $damageFine = (float) $request->input('damage_fine', 0);
        $condition = strtolower(trim($request->string('book_condition')->toString()));

        $fineIds = DB::transaction(function () use ($transaction, $now, $lateFine, $damageFine, $condition, $request): array {
            DB::table('Approved_Transactions')->where('transaction_id', $transaction->transaction_id)
                ->where('status', 'Borrowed')->update(['status' => 'Returned', 'return_date' => $now]);
            $copyStatus = match ($condition) {
                'lost' => 'Lost',
                'discarded' => 'Discarded',
                default => 'Available',
            };
            DB::table('Book_Copies')->where('copy_id', $transaction->copy_id)->update([
                'status' => $copyStatus,
                'condition_note' => $condition !== '' ? ucfirst($condition) : null,
            ]);

            $ids = ['late' => null, 'damage' => null];
            foreach ([['late', $lateFine, "Late return: {$lateFine} BDT"], ['damage', $damageFine, ucfirst($condition ?: 'Damaged') . ' fine']] as [$type, $amount, $description]) {
                if ($amount > 0) {
                    $ids[$type] = DB::table('Fines')->insertGetId([
                        'transaction_id' => $transaction->transaction_id,
                        'user_email' => $transaction->requester_email,
                        'amount' => $amount,
                        'description' => $description,
                        'paid' => false,
                    ]);
                }
            }

            $bookTitle = DB::table('Books')->where('isbn', $transaction->isbn)->value('title') ?? 'Unknown Book';
            DB::table('Notifications')->insert([
                'user_email' => $transaction->requester_email,
                'message' => "Your return for '{$bookTitle}' has been processed.",
                'type' => 'ReturnRequestApproved',
                'sent_at' => $now,
            ]);
            $this->activateNextReservation($transaction->isbn, $bookTitle);
            return $ids;
        });

        return ApiResponse::success([
            'fine' => $lateFine,
            'fine_id' => $fineIds['late'],
            'damage_fine' => $damageFine,
            'damage_fine_id' => $fineIds['damage'],
        ], 200, 'Book returned successfully');
    }

    private function activateNextReservation(string $isbn, string $title): void
    {
        DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')
            ->whereNotNull('expires_at')->where('expires_at', '<', now())->update(['status' => 'Cancelled']);
        $top = DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')
            ->orderBy('queue_position')->orderBy('reservation_id')->lockForUpdate()->first();
        if ($top && !$top->expires_at) {
            $expiresAt = now()->addHours(12);
            DB::table('Reservations')->where('reservation_id', $top->reservation_id)
                ->update(['notified_at' => now(), 'expires_at' => $expiresAt]);
            DB::table('Notifications')->insert([
                'user_email' => $top->user_email,
                'message' => "'{$title}' is available until " . $expiresAt->format('M d, Y h:i A') . '.',
                'type' => 'ReservedBookAvailable',
                'sent_at' => now(),
            ]);
        }
    }
}