<?php

namespace App\Services;

use App\Models\Book;
use App\Models\TransactionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BorrowService
{
    public function request(User $user, string $isbn): TransactionRequest
    {
        return DB::transaction(function () use ($user, $isbn): TransactionRequest {
            $lockedUser = User::query()->where('email', $user->email)->lockForUpdate()->first();
            if (!$lockedUser) {
                throw ValidationException::withMessages(['email' => 'User not found.']);
            }

            DB::table('Transaction_Requests')->where('status', 'Pending')->where('request_date', '<', now()->subHours(24))->delete();
            $book = Book::query()->where('isbn', $isbn)->lockForUpdate()->first();
            if (!$book) {
                throw ValidationException::withMessages(['isbn' => 'Book not found.']);
            }

            $limits = ['student' => 2, 'teacher' => 5, 'librarian' => 10, 'director' => 10];
            $limit = $limits[strtolower($lockedUser->role)] ?? 2;
            $borrowedCount = DB::table('Approved_Transactions as at')
                ->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
                ->where('tr.requester_email', $lockedUser->email)
                ->where('at.status', 'Borrowed')
                ->count();
            $pendingCount = TransactionRequest::query()->where('requester_email', $lockedUser->email)->where('status', 'Pending')->count();
            if ($borrowedCount + $pendingCount >= $limit) {
                throw ValidationException::withMessages(['isbn' => "You have reached your borrowing limit ({$limit} books)."]);
            }

            if (TransactionRequest::query()->where('isbn', $isbn)->where('requester_email', $lockedUser->email)->where('status', 'Pending')->exists()) {
                throw ValidationException::withMessages(['isbn' => 'You already have a pending request for this book.']);
            }
            $alreadyBorrowed = DB::table('Approved_Transactions as at')
                ->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
                ->where('tr.isbn', $isbn)
                ->where('tr.requester_email', $lockedUser->email)
                ->where('at.status', 'Borrowed')
                ->exists();
            if ($alreadyBorrowed) {
                throw ValidationException::withMessages(['isbn' => 'You have already borrowed this book.']);
            }

            $this->cleanReservationQueue($isbn);
            $top = DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')
                ->orderBy('queue_position')->orderBy('reservation_id')->lockForUpdate()->first();
            if ($top && !$top->expires_at) {
                $expiresAt = now()->addHours(12);
                DB::table('Reservations')->where('reservation_id', $top->reservation_id)
                    ->update(['notified_at' => now(), 'expires_at' => $expiresAt]);
                DB::table('Notifications')->insert([
                    'user_email' => $top->user_email,
                    'message' => "{$book->title} is available for you to borrow until " . $expiresAt->format('M d, Y h:i A') . '.',
                    'type' => 'ReservedBookAvailable',
                    'sent_at' => now(),
                ]);
                $top->expires_at = $expiresAt;
            }
            if ($top && $top->user_email !== $lockedUser->email) {
                abort(403, 'This book is reserved for queue #1 until ' . $top->expires_at . '.');
            }

            $request = TransactionRequest::create([
                'isbn' => $isbn,
                'requester_email' => $lockedUser->email,
                'request_date' => now(),
                'status' => 'Pending',
            ]);

            if ($top && $top->user_email === $lockedUser->email) {
                DB::table('Reservations')->where('reservation_id', $top->reservation_id)->update(['status' => 'Completed']);
                $this->cleanReservationQueue($isbn);
            }

            $librarians = User::query()->whereIn('role', ['Librarian', 'librarian'])->pluck('email');
            foreach ($librarians as $librarianEmail) {
                DB::table('Notifications')->insert([
                    'user_email' => $librarianEmail,
                    'message' => "New borrow request from {$lockedUser->email} for '{$book->title}' (Request #{$request->request_id})",
                    'type' => 'BorrowRequestPending',
                    'sent_at' => now(),
                ]);
            }

            return $request;
        });
    }

    private function cleanReservationQueue(string $isbn): void
    {
        DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')
            ->whereNotNull('expires_at')->where('expires_at', '<', now())
            ->update(['status' => 'Cancelled']);

        $reservations = DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')
            ->orderBy('queue_position')->orderBy('reservation_id')->get(['reservation_id', 'queue_position']);
        foreach ($reservations as $index => $reservation) {
            $position = $index + 1;
            if ((int) $reservation->queue_position !== $position) {
                DB::table('Reservations')->where('reservation_id', $reservation->reservation_id)->update(['queue_position' => $position]);
            }
        }
    }
}