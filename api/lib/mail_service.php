<?php
/**
 * Mail Service - Send OTP emails via SMTP
 * No external dependencies required
 * Supports: Mailtrap, Gmail, SendGrid, AWS SES, and local SMTP servers
 */

require_once __DIR__ . '/simple_smtp_mailer.php';

class MailService
{
    private static $logFile = '/tmp/iit_shelf_otp.log';

    /**
     * Log OTP to file for development/debugging
     */
    public static function logOtpToFile($email, $otp, $purpose)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] EMAIL: $email | OTP: $otp | PURPOSE: $purpose\n";
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Send email verification OTP
     */
    public static function sendVerificationEmail($email, $otp)
    {
        self::logOtpToFile($email, $otp, 'EmailVerification');
        
        $subject = 'Verify Your IIT Shelf Account';
        $htmlBody = self::buildVerificationEmailBody($otp);
        
        return self::sendMail($email, $subject, $htmlBody);
    }

    /**
     * Send password reset OTP
     */
    public static function sendPasswordResetEmail($email, $otp)
    {
        self::logOtpToFile($email, $otp, 'PasswordReset');
        
        $subject = 'Reset Your IIT Shelf Password';
        $htmlBody = self::buildPasswordResetEmailBody($otp);
        
        return self::sendMail($email, $subject, $htmlBody);
    }

    /**
     * Send email via SMTP
     */
    private static function sendMail($email, $subject, $htmlBody)
    {
        try {
            $config = self::getMailConfig();
            
            $mailer = new SimpleSMTPMailer(
                $config['host'],
                $config['port'],
                $config['username'],
                $config['password'],
                $config['encryption'],
                $config['from_email'],
                $config['from_name']
            );
            
            return $mailer->send($email, $email, $subject, $htmlBody);
        } catch (Exception $e) {
            error_log("MailService Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get mail configuration from environment or file
     */
    private static function getMailConfig()
    {
        // Try to load from config file
        $configFile = __DIR__ . '/../config/mail.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }
        
        // Fallback to environment variables or defaults
        return [
            'host' => getenv('MAIL_HOST') ?: 'smtp.mailtrap.io',
            'port' => getenv('MAIL_PORT') ?: 2525,
            'username' => getenv('MAIL_USERNAME') ?: '',
            'password' => getenv('MAIL_PASSWORD') ?: '',
            'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
            'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@iitshelf.local',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'IIT Shelf Library'
        ];
    }

    private static function buildVerificationEmailBody($otp)
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { background-color: white; max-width: 600px; margin: 0 auto; padding: 20px; border-radius: 8px; }
        .header { color: #1e3a8a; text-align: center; margin-bottom: 20px; }
        .content { color: #333; line-height: 1.6; }
        .otp-box { background-color: #f0f0f0; padding: 15px; border-radius: 6px; text-align: center; margin: 20px 0; }
        .otp-code { font-size: 28px; font-weight: bold; color: #1e3a8a; letter-spacing: 2px; }
        .footer { color: #666; font-size: 12px; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verify Your Email</h1>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>You're creating an account on IIT Shelf. Use the code below to verify your email address.</p>
            
            <div class="otp-box">
                <p>Verification Code:</p>
                <div class="otp-code">$otp</div>
            </div>
            
            <p>This code will expire in 5 minutes. Do not share this code with anyone.</p>
            <p>If you didn't request this, you can safely ignore this email.</p>
        </div>
        <div class="footer">
            <p>IIT Shelf Library Management System</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private static function buildPasswordResetEmailBody($otp)
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { background-color: white; max-width: 600px; margin: 0 auto; padding: 20px; border-radius: 8px; }
        .header { color: #1e3a8a; text-align: center; margin-bottom: 20px; }
        .content { color: #333; line-height: 1.6; }
        .otp-box { background-color: #f0f0f0; padding: 15px; border-radius: 6px; text-align: center; margin: 20px 0; }
        .otp-code { font-size: 28px; font-weight: bold; color: #d97706; letter-spacing: 2px; }
        .footer { color: #666; font-size: 12px; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reset Your Password</h1>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>We received a request to reset your IIT Shelf password. Use the code below to proceed with resetting your password.</p>
            
            <div class="otp-box">
                <p>Password Reset Code:</p>
                <div class="otp-code">$otp</div>
            </div>
            
            <p>This code will expire in 5 minutes. Do not share this code with anyone.</p>
            <p>If you didn't request this, your account may be compromised. Please contact support immediately.</p>
        </div>
        <div class="footer">
            <p>IIT Shelf Library Management System</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
