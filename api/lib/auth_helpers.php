<?php
// Common helpers for auth endpoints (OTP issuance/validation, JSON responses).

declare(strict_types=1);

require_once __DIR__ . '/../../config/db_auth_temp.php';

function json_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function password_algorithm(): string|int {
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
}

function hash_password(string $password): string {
    return password_hash($password, password_algorithm());
}

function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function valid_password(string $password): bool {
    return strlen($password) >= 8;
}

function generate_otp(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function issue_otp(string $email, string $purpose, int $cooldownSeconds = 60, int $ttlSeconds = 300): array {
    $email = strtolower(trim($email));
    $now = new DateTimeImmutable('now');

    $authTempDb = new AuthTempDatabase();
    $authDb = $authTempDb->connect();

    $coolStmt = $authDb->prepare("SELECT created_at FROM Temp_User_Verification WHERE email = :email AND purpose = :purpose ORDER BY created_at DESC LIMIT 1");
    $coolStmt->execute([':email' => $email, ':purpose' => $purpose]);
    $last = $coolStmt->fetch(PDO::FETCH_ASSOC);
    if ($last && isset($last['created_at'])) {
        $lastTime = new DateTimeImmutable($last['created_at']);
        $wait = $cooldownSeconds - ($now->getTimestamp() - $lastTime->getTimestamp());
        if ($wait > 0) {
            return ['ok' => false, 'wait' => $wait];
        }
    }

    $otp = generate_otp();
    $expiresAt = $now->modify("+{$ttlSeconds} seconds");

    $authDb->prepare("DELETE FROM Temp_User_Verification WHERE email = :email AND purpose = :purpose")
        ->execute([':email' => $email, ':purpose' => $purpose]);

    $ins = $authDb->prepare("INSERT INTO Temp_User_Verification (email, otp_code, purpose, created_at, expires_at) VALUES (:email, :otp, :purpose, :created_at, :expires_at)");
    $ins->execute([
        ':email' => $email,
        ':otp' => $otp,
        ':purpose' => $purpose,
        ':created_at' => $now->format('Y-m-d H:i:s'),
        ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
    ]);

    return ['ok' => true, 'otp' => $otp];
}

function validate_otp(string $email, string $purpose, string $otp): array {
    $email = strtolower(trim($email));
    $otp = trim($otp);
    
    $authTempDb = new AuthTempDatabase();
    $authDb = $authTempDb->connect();
    
    $stmt = $authDb->prepare("SELECT otp_code, expires_at FROM Temp_User_Verification WHERE email = :email AND purpose = :purpose ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':email' => $email, ':purpose' => $purpose]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'message' => 'No OTP found. Please request a new one.'];
    }
    if ($row['otp_code'] !== $otp) {
        return ['ok' => false, 'message' => 'Invalid OTP.'];
    }
    if (new DateTimeImmutable($row['expires_at']) < new DateTimeImmutable('now')) {
        return ['ok' => false, 'message' => 'OTP expired. Please request a new one.'];
    }
    return ['ok' => true];
}

function ensure_verified(array $user): bool {
    return !empty($user['email_verified_at']);
}
