<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegacyEndpointRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function fines(LegacyEndpointRequest $request): JsonResponse
    {
        $email = $request->user()->email;
        $fines = DB::table('Fines')->where('user_email', $email)->where('paid', false)->orderByDesc('fine_id')->get([
            'fine_id', 'amount', 'description', 'paid', 'payment_date',
        ])->map(fn ($fine) => [
            'fine_id' => (int) $fine->fine_id,
            'amount' => (float) $fine->amount,
            'description' => $fine->description,
            'paid' => (bool) $fine->paid,
            'payment_date' => $fine->payment_date,
        ]);
        $total = $fines->sum('amount');

        $overdue = DB::table('Approved_Transactions as at')
            ->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')
            ->join('Books as b', 'b.isbn', '=', 'bc.isbn')
            ->where('tr.requester_email', $email)->whereIn('at.status', ['Borrowed', 'Overdue'])
            ->whereNull('at.return_date')->where('at.due_date', '<', now()->toDateString())
            ->get(['at.transaction_id', 'at.due_date', 'b.title']);

        $pending = $overdue->map(function ($row) use (&$total): array {
            $days = max(0, (int) Carbon::parse($row->due_date)->startOfDay()->diffInDays(now()->startOfDay()));
            $amount = $days * 10;
            $total += $amount;
            return [
                'transaction_id' => (int) $row->transaction_id,
                'book_title' => $row->title,
                'days_overdue' => $days,
                'amount' => (float) $amount,
                'description' => "Overdue fine for '{$row->title}' ({$days} days)",
            ];
        })->values();

        return ApiResponse::success([
            'total_outstanding' => (float) $total,
            'fines_count' => $fines->count(),
            'fines' => $fines,
            'pending_fines_count' => $pending->count(),
            'pending_fines' => $pending,
        ]);
    }

    public function history(LegacyEndpointRequest $request): JsonResponse
    {
        $email = $request->user()->email;
        $start = $request->input('startDate', now()->startOfMonth()->toDateString());
        $end = $request->input('endDate', now()->toDateString());
        $rows = DB::table('Fines as f')
            ->leftJoin('Payments as p', 'p.fine_id', '=', 'f.fine_id')
            ->leftJoin('Approved_Transactions as at', 'at.transaction_id', '=', 'f.transaction_id')
            ->leftJoin('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->leftJoin('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')
            ->leftJoin('Books as b', 'b.isbn', '=', 'bc.isbn')
            ->where('f.user_email', $email)->where('f.paid', true)
            ->whereBetween(DB::raw('DATE(f.payment_date)'), [$start, $end])
            ->orderByDesc('f.payment_date')->limit(50)
            ->get(['f.fine_id', 'f.amount', 'f.description', 'f.paid', 'f.payment_date', 'p.payment_id', 'p.status as payment_status', 'b.title', 'b.author', 'b.isbn']);

        $history = $rows->map(fn ($row) => [
            'id' => (int) $row->fine_id,
            'fine_id' => (int) $row->fine_id,
            'amount' => (float) $row->amount,
            'description' => $row->description,
            'paid' => (bool) $row->paid,
            'payment_date' => $row->payment_date,
            'payment_id' => $row->payment_id ? (int) $row->payment_id : null,
            'payment_status' => $row->payment_status ?? 'Completed',
            'bookTitle' => $row->title ?? 'Unknown',
            'book_title' => $row->title ?? 'Unknown',
            'author' => $row->author ?? 'Unknown',
            'isbn' => $row->isbn ?? '',
            'bookId' => $row->isbn ?? '',
            'reason' => 'Fine Payment',
            'timeAgo' => $row->payment_date ? Carbon::parse($row->payment_date)->format('M d, Y') : 'N/A',
        ])->values();

        $outstandingRows = DB::table('Fines as f')
            ->leftJoin('Approved_Transactions as at', 'at.transaction_id', '=', 'f.transaction_id')
            ->leftJoin('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')
            ->leftJoin('Books as b', 'b.isbn', '=', 'bc.isbn')
            ->where('f.user_email', $email)->where('f.paid', false)->orderByDesc('f.fine_id')
            ->get(['f.fine_id', 'f.amount', 'f.description', 'f.paid', 'f.payment_date', 'b.title', 'b.author', 'b.isbn']);
        $outstanding = $outstandingRows->map(fn ($row) => [
            'id' => (int) $row->fine_id,
            'fine_id' => (int) $row->fine_id,
            'amount' => (float) $row->amount,
            'description' => $row->description,
            'paid' => (bool) $row->paid,
            'payment_date' => $row->payment_date,
            'bookTitle' => $row->title ?? 'Book',
            'author' => $row->author ?? 'Unknown',
            'isbn' => $row->isbn ?? '',
            'bookId' => $row->isbn ?? '',
            'reason' => 'Fine Payment',
        ])->values();
        $stats = [
            'totalOutstanding' => (float) $outstanding->sum('amount'),
            'totalPaidThisMonth' => (float) $history->sum('amount'),
            'totalTransactions' => $history->count(),
        ];

        return ApiResponse::success([
            'payment_history' => $history,
            'total_payments' => $history->count(),
            'paymentHistory' => $history,
            'outstandingFines' => $outstanding,
            'stats' => $stats,
        ]);
    }

    public function process(LegacyEndpointRequest $request): JsonResponse
    {
        $email = $request->user()->email;
        $fineIds = array_values(array_unique(array_map('intval', $request->input('fine_ids', []))));
        $transactionIds = array_values(array_unique(array_map('intval', $request->input('transaction_ids', []))));
        $paymentMethod = trim((string) $request->input('payment_method', 'cash'));

        $result = DB::transaction(function () use ($email, $fineIds, $transactionIds, $paymentMethod): array {
            $createdFineIds = [];
            foreach ($transactionIds as $transactionId) {
                $transaction = DB::table('Approved_Transactions as at')
                    ->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
                    ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')
                    ->join('Books as b', 'b.isbn', '=', 'bc.isbn')
                    ->where('at.transaction_id', $transactionId)->where('tr.requester_email', $email)
                    ->whereIn('at.status', ['Borrowed', 'Overdue'])->whereNull('at.return_date')
                    ->lockForUpdate()->first(['at.transaction_id', 'at.copy_id', 'at.due_date', 'b.title']);
                if (!$transaction || now()->toDateString() <= Carbon::parse($transaction->due_date)->toDateString()) continue;

                $days = (int) Carbon::parse($transaction->due_date)->startOfDay()->diffInDays(now()->startOfDay());
                $createdFineIds[] = DB::table('Fines')->insertGetId([
                    'transaction_id' => $transactionId,
                    'user_email' => $email,
                    'amount' => $days * 10,
                    'description' => "Overdue fine for '{$transaction->title}' ({$days} days)",
                    'paid' => false,
                ]);
                DB::table('Approved_Transactions')->where('transaction_id', $transactionId)->update(['status' => 'Returned', 'return_date' => now()]);
                DB::table('Book_Copies')->where('copy_id', $transaction->copy_id)->update(['status' => 'Available']);
            }

            $allFineIds = array_values(array_unique(array_merge($fineIds, $createdFineIds)));
            $fines = DB::table('Fines')->where('user_email', $email)->where('paid', false)->whereIn('fine_id', $allFineIds)->lockForUpdate()->get();
            $total = (float) $fines->sum('amount');
            $paymentId = null;
            foreach ($fines as $fine) {
                $paymentId = DB::table('Payments')->insertGetId([
                    'fine_id' => $fine->fine_id,
                    'user_email' => $email,
                    'amount' => $fine->amount,
                    'status' => 'Completed',
                    'paid_at' => now(),
                ]);
                DB::table('Fines')->where('fine_id', $fine->fine_id)->update(['paid' => true, 'payment_date' => now()]);
            }

            DB::table('Notifications')->insert([
                'user_email' => $email,
                'message' => "Payment of {$total} BDT received successfully via {$paymentMethod}.",
                'type' => 'PaymentConfirmation',
                'sent_at' => now(),
            ]);
            return ['payment_id' => $paymentId, 'amount' => $total];
        });

        return ApiResponse::success($result, 200, 'Payment successful');
    }
}