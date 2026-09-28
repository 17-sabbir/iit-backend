# OTP Email Logging Guide

## Development Mode: OTP Logging Instead of Email Sending

Since no mail server is configured on this system, OTP codes are being logged to a file instead of being sent via email. This allows you to test the registration and password reset flows.

### Where to Find OTP Codes

All OTP codes are logged to: `/tmp/iit_shelf_otp.log`

#### View the log file:
```bash
cat /tmp/iit_shelf_otp.log
tail -10 /tmp/iit_shelf_otp.log  # Last 10 entries
tail -f /tmp/iit_shelf_otp.log   # Watch for new entries in real-time
```

#### Log Format:
```
[TIMESTAMP] PURPOSE - Email: EMAIL_ADDRESS, OTP: CODE
```

Example:
```
[2026-01-03 18:42:07] PasswordReset - Email: student@iit.edu, OTP: 494738
[2026-01-03 18:41:59] EmailVerification - Email: dev@test.com, OTP: 312566
```

### Also Returned in API Response

For development convenience, OTP codes are also returned in the API response:

```bash
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","phone":"01234567890"}'
```

Response:
```json
{
  "success": true,
  "message": "Verification code sent to your email. (Check server logs or /tmp/iit_shelf_otp.log)",
  "email": "user@example.com",
  "otp": "312566"
}
```

### Future: Configure Real Email Sending

To send actual emails in production, update `backend/api/lib/mail_service.php`:

1. **Option A: Use SMTP (Recommended)**
   - Install PHPMailer: `composer require phpmailer/phpmailer`
   - Configure SMTP credentials (Gmail, SendGrid, AWS SES, etc.)
   - Modify `MailService` to use PHPMailer instead of `mail()`

2. **Option B: Install Local Mail Server**
   - Install Postfix or Exim4
   - Configure for local delivery
   - Ensure `php.ini` `sendmail_path` is configured

3. **Option C: Mock Email Service**
   - Use service like Mailtrap (https://mailtrap.io) for testing
   - Provides SMTP credentials for free
   - All emails viewable in web interface

### Testing Workflows

#### Registration Flow:
1. Click "Send OTP" on register page
2. Copy OTP from API response or `/tmp/iit_shelf_otp.log`
3. Paste OTP in the verification field
4. Set password and complete registration

#### Password Reset Flow:
1. Click "Forgot Password" on login page
2. Enter email (must be verified user)
3. Copy OTP from API response or `/tmp/iit_shelf_otp.log`
4. Paste OTP and set new password

### API Endpoints Reference

**Send Registration OTP:**
```bash
POST /api/auth/send_register_otp.php
Content-Type: application/json

{
  "email": "user@example.com",
  "phone": "01234567890",
  "role": "Student"
}
```

**Send Password Reset OTP:**
```bash
POST /api/auth/send_reset_otp.php
Content-Type: application/json

{
  "email": "user@example.com"
}
```

**Verify Email OTP:**
```bash
POST /api/auth/verify_email.php
Content-Type: application/json

{
  "email": "user@example.com",
  "otp": "312566"
}
```

**Verify Password Reset OTP:**
```bash
POST /api/auth/verify_reset_otp.php
Content-Type: application/json

{
  "email": "user@example.com",
  "otp": "494738"
}
```

---

**Status:** ✅ OTP generation and logging working | ⏳ Real email sending pending mail server configuration
