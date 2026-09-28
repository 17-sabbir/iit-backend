<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthWorkflowService
{
    public function sendRegistrationOtp(string $email): array
    {
        $email = $this->normalizeEmail($email);
        $identity = $this->findPreregestredIdentity($email);
        if (!$identity) {
            return $this->error('This email is not pre-registered. Only authorized users can create accounts. Please contact the administrator.', 403);
        }

        if (User::query()->where('email', $email)->exists()) {
            return $this->error('Account already exists. Please sign in.', 400);
        }

        $issue = $this->issueOtp($email, 'EmailVerification');
        if (isset($issue['wait'])) {
            return $this->error("Please wait {$issue['wait']}s before requesting another code.", 429, ['retry_after' => $issue['wait']]);
        }

        $this->sendOtpMail($email, $issue['otp'], 'Verify Your IIT Shelf Account');

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'message' => 'Verification code sent to your email.',
                'email' => $email,
                'role' => $identity['role'],
                'user_info' => $identity['info'],
            ],
        ];
    }

    public function verifyRegistrationOtp(string $email, string $otp): array
    {
        $email = $this->normalizeEmail($email);
        $check = $this->checkOtp($email, 'EmailVerification', $otp);
        if (isset($check['error'])) {
            return $check['error'];
        }

        $identity = $this->findPreregestredIdentity($email);
        if (!$identity) {
            return $this->error('Pre-registration data not found. Please contact administrator.', 403);
        }

        if (User::query()->where('email', $email)->exists()) {
            return $this->error('Account already exists. Please sign in.', 400);
        }

        DB::transaction(function () use ($email, $identity): void {
            $info = $identity['info'];
            DB::table('Users')->insert([
                'email' => $email,
                'name' => $info['full_name'] ?? '',
                'password_hash' => Hash::make(bin2hex(random_bytes(24))),
                'role' => $identity['role'],
                'contact' => $info['contact'] ?? '',
                'created_at' => now(),
            ]);

            if ($identity['role'] === 'Student' && isset($info['roll'], $info['session'])) {
                DB::table('Students')->insert([
                    'email' => $email,
                    'roll' => $info['roll'],
                    'session' => $info['session'],
                ]);
            } elseif ($identity['role'] === 'Teacher' && isset($info['designation'])) {
                DB::table('Teachers')->insert([
                    'email' => $email,
                    'designation' => $info['designation'],
                ]);
            }
        });

        $this->verificationCache()->put($this->verificationKey('registration', $email), true, now()->addMinutes(15));
        $this->deleteOtp($email, 'EmailVerification');
        $this->clearAttempts($email, 'EmailVerification');

        return ['status' => 200, 'payload' => ['success' => true, 'message' => 'Email verified successfully.']];
    }

    public function setRegistrationPassword(string $email, string $password): array
    {
        $email = $this->normalizeEmail($email);
        $cache = $this->verificationCache();
        $key = $this->verificationKey('registration', $email);
        if (!$cache->get($key)) {
            return $this->error('Verify your email before setting a password.', 403);
        }

        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            return $this->error('Account not found.', 404);
        }

        $user->forceFill(['password_hash' => Hash::make($password)])->save();
        $cache->forget($key);

        return ['status' => 200, 'payload' => ['success' => true, 'message' => 'Password set successfully. You can now sign in.']];
    }

    public function sendPasswordResetOtp(string $email): array
    {
        $email = $this->normalizeEmail($email);
        if (!User::query()->where('email', $email)->exists()) {
            return ['status' => 200, 'payload' => ['success' => true, 'message' => 'If an account exists, a reset code has been sent.']];
        }

        $issue = $this->issueOtp($email, 'PasswordReset');
        if (isset($issue['wait'])) {
            return $this->error("Please retry after {$issue['wait']} seconds.", 429, ['retry_after' => $issue['wait']]);
        }

        $this->sendOtpMail($email, $issue['otp'], 'Reset Your IIT Shelf Password');

        return ['status' => 200, 'payload' => ['success' => true, 'message' => 'Password reset code sent to your email.']];
    }

    public function verifyPasswordResetOtp(string $email, string $otp): array
    {
        $email = $this->normalizeEmail($email);
        $check = $this->checkOtp($email, 'PasswordReset', $otp);
        if (isset($check['error'])) {
            return $check['error'];
        }

        $this->verificationCache()->put($this->verificationKey('reset', $email), true, now()->addMinutes(15));
        $this->clearAttempts($email, 'PasswordReset');

        return ['status' => 200, 'payload' => ['success' => true, 'message' => 'OTP is valid.']];
    }

    public function resetPassword(string $email, string $otp, string $password): array
    {
        $email = $this->normalizeEmail($email);
        $cache = $this->verificationCache();
        $key = $this->verificationKey('reset', $email);
        if (!$cache->get($key)) {
            return $this->error('Verify the reset code before changing the password.', 403);
        }

        $check = $this->checkOtp($email, 'PasswordReset', $otp);
        if (isset($check['error'])) {
            return $check['error'];
        }

        $user = User::query()->where('email', $email)->first();
        if (!$user) {
            return $this->error('User not found.', 404);
        }

        $user->forceFill(['password_hash' => Hash::make($password)])->save();
        app(ApiTokenService::class)->revokeAll($user);
        $this->deleteOtp($email, 'PasswordReset');
        $cache->forget($key);

        return ['status' => 200, 'payload' => ['success' => true, 'message' => 'Password reset successful.']];
    }

    private function findPreregestredIdentity(string $email): ?array
    {
        $pre = DB::connection('preregistration');

        $student = $pre->table('PreReg_Students')->where('email', $email)->first();
        if ($student) {
            return ['role' => 'Student', 'info' => (array) $student];
        }

        $teacher = $pre->table('PreReg_Teachers')->where('email', $email)->first();
        if ($teacher) {
            return ['role' => 'Teacher', 'info' => (array) $teacher];
        }

        foreach (['PreReg_Librarians' => 'Librarian', 'PreReg_Directors' => 'Director'] as $table => $role) {
            $row = $pre->table($table)->where('email', $email)->first();
            if ($row) {
                return ['role' => $role, 'info' => (array) $row];
            }
        }

        return null;
    }

    private function issueOtp(string $email, string $purpose): array
    {
        $database = DB::connection('auth_temp');
        $last = $database->table('Temp_User_Verification')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->orderByDesc('created_at')
            ->first();

        if ($last) {
            $wait = 60 - now()->diffInSeconds($last->created_at, false) * -1;
            if ($wait > 0) {
                return ['wait' => $wait];
            }
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $database->table('Temp_User_Verification')->where('email', $email)->where('purpose', $purpose)->delete();
        $database->table('Temp_User_Verification')->insert([
            'email' => $email,
            'otp_code' => $otp,
            'purpose' => $purpose,
            'created_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        return ['otp' => $otp];
    }

    private function checkOtp(string $email, string $purpose, string $otp): array
    {
        $attemptKey = $this->attemptKey($email, $purpose);
        $cache = $this->verificationCache();
        $attempts = (int) $cache->get($attemptKey, 0);
        if ($attempts >= 5) {
            return ['error' => $this->error('Too many failed attempts. Please try again in 15 minutes.', 429, ['retry_after' => 900])];
        }

        $row = DB::connection('auth_temp')->table('Temp_User_Verification')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->first();

        if (!$row) {
            return ['error' => $this->failedOtp($attemptKey, 'No OTP found. Please request a new one.')];
        }

        if (now()->greaterThan($row->expires_at)) {
            return ['error' => $this->failedOtp($attemptKey, 'OTP expired. Please request a new one.')];
        }

        if (!hash_equals((string) $row->otp_code, trim($otp))) {
            return ['error' => $this->failedOtp($attemptKey, 'Invalid OTP.')];
        }

        return ['valid' => true];
    }

    private function failedOtp(string $key, string $message): array
    {
        $cache = $this->verificationCache();
        $attempts = (int) $cache->get($key, 0) + 1;
        $cache->put($key, $attempts, now()->addMinutes(15));
        return $this->error($message, 400, ['remaining_attempts' => max(0, 5 - $attempts)]);
    }

    private function deleteOtp(string $email, string $purpose): void
    {
        DB::connection('auth_temp')->table('Temp_User_Verification')->where('email', $email)->where('purpose', $purpose)->delete();
    }

    private function clearAttempts(string $email, string $purpose): void
    {
        $this->verificationCache()->forget($this->attemptKey($email, $purpose));
    }

    private function sendOtpMail(string $email, string $otp, string $subject): void
    {
        try {
            Mail::raw("Your IIT Shelf verification code is {$otp}. It expires in 5 minutes.", function ($message) use ($email, $subject): void {
                $message->to($email)->subject($subject);
            });
        } catch (\Throwable $exception) {
            Log::warning('Unable to send OTP email.', ['email' => $email, 'error' => $exception->getMessage()]);
        }
    }

    private function verificationCache()
    {
        return Cache::store();
    }

    private function verificationKey(string $type, string $email): string
    {
        return 'auth-workflow:' . $type . ':' . hash('sha256', $email);
    }

    private function attemptKey(string $email, string $purpose): string
    {
        return $this->verificationKey('attempt:' . $purpose, $email);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function error(string $message, int $status, array $extra = []): array
    {
        return ['status' => $status, 'payload' => array_merge(['success' => false, 'message' => $message], $extra)];
    }
}