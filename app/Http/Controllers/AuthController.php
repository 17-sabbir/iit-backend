<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\AuthService;
use App\Services\AuthWorkflowService;
use App\Http\Requests\LegacyEndpointRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Auth\PasswordBroker;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth, private readonly AuthWorkflowService $workflow) {}

    public function sendRegistrationOtp(LegacyEndpointRequest $request): JsonResponse
    {
        return $this->workflowResponse($this->workflow->sendRegistrationOtp($request->string('email')->toString()));
    }

    public function verifyRegistrationOtp(LegacyEndpointRequest $request): JsonResponse
    {
        return $this->workflowResponse($this->workflow->verifyRegistrationOtp($request->string('email')->toString(), $request->string('otp')->toString()));
    }

    public function setRegistrationPassword(LegacyEndpointRequest $request): JsonResponse
    {
        return $this->workflowResponse($this->workflow->setRegistrationPassword($request->string('email')->toString(), $request->string('new_password')->toString()));
    }

    public function sendPasswordResetOtp(LegacyEndpointRequest $request): JsonResponse
    {
        return $this->workflowResponse($this->workflow->sendPasswordResetOtp($request->string('email')->toString()));
    }

    public function verifyPasswordResetOtp(LegacyEndpointRequest $request): JsonResponse
    {
        return $this->workflowResponse($this->workflow->verifyPasswordResetOtp($request->string('email')->toString(), $request->string('otp')->toString()));
    }

    public function resetPasswordWithOtp(LegacyEndpointRequest $request): JsonResponse
    {
        return $this->workflowResponse($this->workflow->resetPassword($request->string('email')->toString(), $request->string('otp')->toString(), $request->string('new_password')->toString()));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->auth->login($request->string('email')->toString(), $request->string('password')->toString());
        } catch (ValidationException $exception) {
            return ApiResponse::error('Invalid credentials.', 401, $exception->errors());
        }

        return ApiResponse::success([
            'user' => $result['user'],
            'role' => $result['user']->role,
            'token' => $result['token'],
        ], 200, 'Login successful.');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated());
        return ApiResponse::success(['user' => $user], 201, 'Registration successful.');
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success(['user' => request()->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user(), $request->bearerToken());
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return ApiResponse::success([], 200, 'Logged out successfully.');
    }

    public function session(Request $request): JsonResponse
    {
        return ApiResponse::success(['authenticated' => true, 'user' => $request->user()]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->auth->changePassword($request->user(), $request->string('current_password')->toString(), $request->string('new_password')->toString());
        return ApiResponse::success([], 200, 'Password changed successfully.');
    }

    public function forgotPassword(LegacyEndpointRequest $request): JsonResponse
    {
        return $this->workflowResponse($this->workflow->sendPasswordResetOtp($request->string('email')->toString()));
    }

    public function resetPassword(LegacyEndpointRequest $request): JsonResponse
    {
        return $this->workflowResponse($this->workflow->resetPassword(
            $request->string('email')->toString(),
            $request->string('otp')->toString(),
            $request->string('new_password')->toString(),
        ));
    }

    public function getProfile(LegacyEndpointRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $user->only(['email', 'name', 'role', 'created_at', 'last_login']);
        $data['phone'] = $user->contact ?: 'Not provided';
        $data['contact'] = $user->contact ?: '';
        $data['profile_image'] = $user->profile_image
            ? '/api/auth/get_image.php?path=' . urlencode($user->profile_image)
            : null;

        if ($user->role === 'Student') {
            $student = DB::table('Students')->where('email', $user->email)->first(['roll', 'session']);
            $data += $student ? (array) $student : [];
        } elseif ($user->role === 'Teacher') {
            $teacher = DB::table('Teachers')->where('email', $user->email)->first(['designation']);
            $data += $teacher ? (array) $teacher : [];
        }

        return ApiResponse::success(['user' => $data]);
    }

    public function updateProfile(LegacyEndpointRequest $request): JsonResponse
    {
        $user = $request->user();
        $updates = [];
        if ($request->filled('name')) {
            $updates['name'] = trim($request->string('name')->toString());
        }
        if ($request->filled('phone')) {
            $updates['contact'] = trim($request->string('phone')->toString());
        }
        if ($updates === []) {
            return ApiResponse::error('No fields to update.', 400);
        }

        $user->forceFill($updates)->save();
        return ApiResponse::success(['user' => [
            'email' => $user->email,
            'name' => $user->name,
            'phone' => $user->contact,
            'contact' => $user->contact,
            'role' => $user->role,
        ]], 200, 'Profile updated successfully.');
    }

    public function changePasswordLegacy(LegacyEndpointRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!\Illuminate\Support\Facades\Hash::check($request->string('current_password')->toString(), $user->password_hash)) {
            return ApiResponse::error('Current password is incorrect.', 401);
        }
        $user->forceFill(['password_hash' => \Illuminate\Support\Facades\Hash::make($request->string('new_password')->toString())])->save();
        app(\App\Services\ApiTokenService::class)->revokeAll($user);
        return ApiResponse::success([], 200, 'Password changed successfully.');
    }

    public function getNotifications(LegacyEndpointRequest $request): JsonResponse
    {
        $query = DB::table('Notifications')->where('user_email', $request->user()->email);
        $total = (clone $query)->count();
        $notifications = $query->orderByDesc('sent_at')
            ->limit(min(100, max(1, $request->integer('limit', 50))))
            ->get(['notification_id', 'message', 'type', 'sent_at']);

        return ApiResponse::success([
            'count' => $notifications->count(),
            'total_count' => $total,
            'notifications' => $notifications,
        ]);
    }

    public function studentDashboard(LegacyEndpointRequest $request): JsonResponse
    {
        $email = $request->user()->email;
        $rows = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')->join('Books as b', 'b.isbn', '=', 'bc.isbn')
            ->where('tr.requester_email', $email)->whereIn('at.status', ['Borrowed', 'Overdue'])->whereNull('at.return_date')
            ->orderByDesc('at.issue_date')->get(['at.transaction_id', 'tr.isbn', 'at.due_date', 'b.title']);
        $borrowed = [];
        $overdue = [];
        foreach ($rows as $row) {
            $due = \Illuminate\Support\Carbon::parse($row->due_date);
            if (now()->greaterThan($due)) {
                $overdue[] = [
                    'id' => (int) $row->transaction_id, 'isbn' => $row->isbn, 'title' => $row->title,
                    'due_date' => $row->due_date, 'days_overdue' => (int) $due->diffInDays(now()), 'status' => 'Overdue',
                ];
            } else {
                $borrowed[] = ['id' => (int) $row->transaction_id, 'isbn' => $row->isbn, 'title' => $row->title, 'due_date' => $row->due_date, 'status' => 'Borrowed'];
            }
        }
        return ApiResponse::success([
            'stats' => [
                'totalBorrowed' => count($borrowed) + count($overdue),
                'borrowLimit' => match (strtolower($request->user()->role)) { 'teacher' => 5, 'librarian', 'director' => 10, default => 2 },
                'outstandingFines' => (float) DB::table('Fines')->where('user_email', $email)->where('paid', false)->sum('amount'),
                'overdueCount' => count($overdue),
                'pendingRequests' => DB::table('Transaction_Requests')->where('requester_email', $email)->where('status', 'Pending')->count(),
                'readyReservations' => DB::table('Reservations')->where('user_email', $email)->where('status', 'Active')->where('expires_at', '>', now())->count(),
            ],
            'borrowedBooks' => $borrowed,
            'overdueBooks' => $overdue,
        ]);
    }

    public function sendReminders(LegacyEndpointRequest $request): JsonResponse
    {
        $type = $request->string('type')->toString() ?: 'all';
        $results = ['due_date_reminders' => 0, 'fine_reminders' => 0];
        if (in_array($type, ['all', 'due_dates'], true)) {
            $dueRows = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
                ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')->join('Books as b', 'b.isbn', '=', 'bc.isbn')
                ->where('at.status', 'Borrowed')->whereBetween('at.due_date', [now(), now()->addHours(24)])
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')->from('Notifications as n')->whereColumn('n.user_email', 'tr.requester_email')
                        ->where('n.type', 'DueDateReminder')->where('n.sent_at', '>=', now()->subHours(24));
                })->get(['tr.requester_email', 'b.title', 'at.due_date']);
            foreach ($dueRows as $row) {
                DB::table('Notifications')->insert([
                    'user_email' => $row->requester_email,
                    'message' => "Reminder: '{$row->title}' is due tomorrow (" . \Illuminate\Support\Carbon::parse($row->due_date)->format('M d, Y') . ').',
                    'type' => 'DueDateReminder', 'sent_at' => now(),
                ]);
                $results['due_date_reminders']++;
            }
        }
        if (in_array($type, ['all', 'fines'], true)) {
            $fineRows = DB::table('Fines as f')->where('f.paid', false)->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('Notifications as n')->whereColumn('n.user_email', 'f.user_email')
                    ->where('n.type', 'FineReminder')->where('n.sent_at', '>=', now()->subDays(3));
            })->get(['f.user_email', 'f.amount', 'f.description']);
            foreach ($fineRows as $row) {
                DB::table('Notifications')->insert([
                    'user_email' => $row->user_email,
                    'message' => "You have an unpaid fine of {$row->amount} BDT. Reason: {$row->description}",
                    'type' => 'FineReminder', 'sent_at' => now(),
                ]);
                $results['fine_reminders']++;
            }
        }
        return ApiResponse::success(['results' => $results], 200, 'Notifications sent successfully');
    }

    public function markNotificationRead(LegacyEndpointRequest $request): JsonResponse
    {
        $query = DB::table('Notifications')->where('user_email', $request->user()->email);
        if ($request->boolean('mark_all')) {
            $query->update(['isRead' => true]);
            return ApiResponse::success([], 200, 'All notifications marked as read.');
        }

        $updated = $query->where('notification_id', $request->integer('notification_id'))
            ->update(['isRead' => true]);
        return $updated
            ? ApiResponse::success([], 200, 'Notification marked as read.')
            : ApiResponse::error('Notification not found.', 404);
    }

    public function deleteNotification(LegacyEndpointRequest $request): JsonResponse
    {
        $deleted = DB::table('Notifications')
            ->where('user_email', $request->user()->email)
            ->where('notification_id', $request->integer('notification_id'))
            ->delete();

        return $deleted
            ? ApiResponse::success([], 200, 'Notification deleted.')
            : ApiResponse::error('Notification not found.', 404);
    }

    public function uploadProfileImage(LegacyEndpointRequest $request): JsonResponse
    {
        /** @var UploadedFile $image */
        $image = $request->file('image');
        $mime = $image->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };
        if ($extension === null) {
            return ApiResponse::error('Only JPEG, PNG, GIF, and WebP images are allowed.', 415);
        }

        $directory = base_path('uploads/profiles');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return ApiResponse::error('Failed to save image.', 500);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $image->move($directory, $filename);
        $path = 'uploads/profiles/' . $filename;
        $request->user()->forceFill(['profile_image' => $path])->save();

        return ApiResponse::success([
            'image_url' => '/api/auth/get_image.php?path=' . urlencode($path),
        ], 200, 'Profile image uploaded successfully.');
    }

    public function getImage(LegacyEndpointRequest $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        return $this->serveUpload($request->string('path')->toString(), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    public function serveImage(LegacyEndpointRequest $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        return $this->serveUpload($request->string('path')->toString(), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']);
    }

    private function serveUpload(string $path, array $allowedExtensions): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $uploadsDirectory = realpath(base_path('uploads'));
        $candidate = realpath(base_path($path));
        if (!$uploadsDirectory || !$candidate || !str_starts_with($candidate, $uploadsDirectory . DIRECTORY_SEPARATOR)) {
            return ApiResponse::error('Not found.', 404);
        }

        $extension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        if (!is_file($candidate) || !in_array($extension, $allowedExtensions, true)) {
            return ApiResponse::error('Not found.', 404);
        }

        return response()->file($candidate, ['Cache-Control' => 'public, max-age=86400']);
    }

    private function workflowResponse(array $result): JsonResponse
    {
        return response()->json($result['payload'], $result['status']);
    }
}