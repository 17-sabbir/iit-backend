<?php
/**
 * OTP Attempt Tracking - prevents brute force attacks
 * Logs failed verification attempts for security audit
 */

class OtpAttemptTracker
{
    private static string $logFile = '/tmp/iit_shelf_otp_attempts.log';
    private static int $maxAttempts = 5;
    private static int $lockoutMinutes = 15;

    /**
     * Log a verification attempt
     */
    public static function logAttempt(string $email, string $purpose, bool $success, ?string $reason = null): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $reason = $reason ? " [$reason]" : '';
        $entry = "[$timestamp] $status - Purpose: $purpose, Email: $email$reason\n";
        file_put_contents(self::$logFile, $entry, FILE_APPEND);
    }

    /**
     * Check if email is locked out due to too many failed attempts
     */
    public static function isLockedOut(string $email, string $purpose): array
    {
        $key = "$email:$purpose";
        $lockKey = "lockout:$key";
        
        // In production, use Redis or database. For now, use file-based cache
        $attempts = self::getRecentFailedAttempts($email, $purpose);
        
        if ($attempts >= self::$maxAttempts) {
            return [
                'locked' => true,
                'message' => "Too many failed attempts. Please try again in " . self::$lockoutMinutes . " minutes.",
                'retry_after' => self::$lockoutMinutes * 60,
            ];
        }
        
        return ['locked' => false];
    }

    /**
     * Get count of failed attempts in last X minutes
     */
    private static function getRecentFailedAttempts(string $email, string $purpose, int $minutes = 15): int
    {
        if (!file_exists(self::$logFile)) {
            return 0;
        }
        
        $lines = file(self::$logFile, FILE_IGNORE_NEW_LINES);
        $cutoffTime = strtotime("-$minutes minutes");
        $count = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, 'FAILED') === false) continue;
            if (strpos($line, $email) === false) continue;
            if (strpos($line, $purpose) === false) continue;
            
            preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches);
            if (!empty($matches[1])) {
                $lineTime = strtotime($matches[1]);
                if ($lineTime >= $cutoffTime) {
                    $count++;
                }
            }
        }
        
        return $count;
    }

    /**
     * Get remaining attempts for email
     */
    public static function getRemainingAttempts(string $email, string $purpose): int
    {
        $failed = self::getRecentFailedAttempts($email, $purpose);
        return max(0, self::$maxAttempts - $failed);
    }
}
