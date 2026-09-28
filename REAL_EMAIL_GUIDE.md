# Real Email Implementation - Complete Guide

## Overview

Your IIT Shelf authentication system now has a **production-ready SMTP email implementation** with **zero external dependencies**. 

### What's Included

✅ **SimpleSMTPMailer** - Raw SMTP protocol client  
✅ **MailService** - High-level email API  
✅ **Multi-provider support** - Mailtrap, Gmail, SendGrid, AWS SES  
✅ **HTML email templates** - Professional formatting  
✅ **OTP logging** - Development debugging  
✅ **Configuration management** - Environment-based setup  

---

## 3-Step Setup

### Step 1: Get Email Credentials (5 minutes)

**Option A: Mailtrap (Recommended - Easiest)**
1. Go to https://mailtrap.io
2. Click "Sign up" (free tier)
3. Create new inbox
4. Copy your SMTP credentials:
   - Username: (from Settings)
   - Password: (from Settings)

**Option B: Gmail**
1. Visit https://myaccount.google.com/apppasswords
2. Generate app-specific password
3. Copy username@gmail.com and the generated password

**Option C: SendGrid**
1. Sign up at sendgrid.com
2. Create API key
3. Copy the key

### Step 2: Configure Credentials

Edit `/mnt/academics/iit_shelf_test/backend/api/config/mail.php`

For **Mailtrap**, find this section:
```php
case 'mailtrap':
default:
    return [
        'host' => getenv('MAILTRAP_HOST') ?: 'smtp.mailtrap.io',
        'port' => getenv('MAILTRAP_PORT') ?: 2525,
        'username' => getenv('MAILTRAP_USERNAME') ?: 'YOUR_USERNAME_HERE',  // ← Update this
        'password' => getenv('MAILTRAP_PASSWORD') ?: 'YOUR_PASSWORD_HERE',  // ← Update this
```

Save the file.

### Step 3: Test

```bash
# Test OTP sending
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com"}'

# Check OTP log (shows what was generated)
tail /tmp/iit_shelf_otp.log

# Check provider inbox (Mailtrap web interface)
# Email should appear within seconds
```

---

## How the System Works

```
User Flow:
┌─────────────────────────────────────────────────────────────┐
│ 1. User clicks "Send OTP" on register.dart (Flutter)         │
└──────────────────┬──────────────────────────────────────────┘
                   │
                   ↓ HTTP POST
┌─────────────────────────────────────────────────────────────┐
│ 2. send_register_otp.php receives request                    │
│    - Generate 6-digit OTP                                    │
│    - Log to /tmp/iit_shelf_otp.log                          │
│    - Call MailService::sendVerificationEmail()              │
└──────────────────┬──────────────────────────────────────────┘
                   │
                   ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. MailService creates SimpleSMTPMailer                      │
│    - Load config from config/mail.php                       │
│    - Build HTML email body                                  │
│    - Connect to SMTP server                                 │
└──────────────────┬──────────────────────────────────────────┘
                   │
                   ↓ SMTP Protocol
┌─────────────────────────────────────────────────────────────┐
│ 4. SimpleSMTPMailer sends to provider                        │
│    (Mailtrap/Gmail/SendGrid/etc)                           │
└──────────────────┬──────────────────────────────────────────┘
                   │
                   ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. Email delivered to user's inbox                          │
│    (Mailtrap inbox for testing, user's email for prod)     │
└─────────────────────────────────────────────────────────────┘
```

---

## Architecture

### File Structure
```
/backend/
├── api/
│   ├── config/
│   │   └── mail.php                    ← CONFIGURATION (edit this)
│   ├── lib/
│   │   ├── simple_smtp_mailer.php      ← Raw SMTP (don't edit)
│   │   ├── mail_service.php            ← Email API (can customize templates)
│   │   ├── auth_helpers.php            ← OTP generation
│   │   └── otp_attempt_tracker.php     ← Security
│   ├── auth/
│   │   ├── send_register_otp.php       ← Registration OTP endpoint
│   │   ├── send_reset_otp.php          ← Password reset OTP endpoint
│   │   ├── verify_email.php            ← Verify OTP
│   │   └── ...
│   └── database.php
├── EMAIL_SETUP.md                      ← Detailed guide
└── test_email_setup.sh                 ← Setup test
```

### How Components Connect

1. **config/mail.php** → Returns SMTP credentials based on provider
2. **mail_service.php** → Uses config to create mailer & send emails
3. **simple_smtp_mailer.php** → Implements actual SMTP protocol
4. **Auth endpoints** → Call MailService when OTP needs to be sent

---

## Configuration Options

### Mailtrap (Default)
```php
'host' => 'smtp.mailtrap.io',
'port' => 2525,                    // Can also use 25, 465, 587
'username' => 'your_mailtrap_user',
'password' => 'your_mailtrap_pass',
'encryption' => 'tls',
```

### Gmail
```php
'host' => 'smtp.gmail.com',
'port' => 587,
'username' => 'your-email@gmail.com',
'password' => 'app-specific-password',  // NOT your regular password
'encryption' => 'tls',
```

### SendGrid
```php
'host' => 'smtp.sendgrid.net',
'port' => 587,
'username' => 'apikey',
'password' => 'SG.xxxxxxxxxxxxx',   // Your SendGrid API key
'encryption' => 'tls',
```

### Environment Variables (Advanced)
Instead of editing mail.php, you can set environment variables:
```bash
export MAIL_DRIVER=mailtrap
export MAILTRAP_USERNAME=your_user
export MAILTRAP_PASSWORD=your_pass
```

---

## Customization

### Change Email Templates

Edit `mail_service.php` methods:
- `buildVerificationEmailBody($otp)` - Registration email
- `buildPasswordResetEmailBody($otp)` - Password reset email

### Change From Email Address

In `mail.php`:
```php
'from_email' => 'support@iitshelf.edu',    // Email address
'from_name' => 'IIT Shelf Support Team',   // Display name
```

### Add More Email Types

Example: Welcome email after registration
```php
// In mail_service.php
public static function sendWelcomeEmail($email, $name)
{
    $subject = 'Welcome to IIT Shelf!';
    $htmlBody = self::buildWelcomeEmailBody($name);
    return self::sendMail($email, $subject, $htmlBody);
}

// In your registration endpoint
MailService::sendWelcomeEmail($email, $name);
```

---

## Debugging

### Check OTP Log
```bash
tail -f /tmp/iit_shelf_otp.log
```
Output: `[2024-01-15 14:30:45] EMAIL: user@example.com | OTP: 123456 | PURPOSE: EmailVerification`

### Check PHP Errors
```bash
tail -f /var/log/php-errors.log
```

### Test SMTP Connection Manually
```bash
# Try to connect
timeout 3 bash -c 'exec 3<>/dev/tcp/smtp.mailtrap.io/2525; echo "QUIT" >&3'

# If it connects without error, SMTP is reachable
```

### View Email Requests
```bash
# See what emails were generated (in OTP log)
grep "EMAIL:" /tmp/iit_shelf_otp.log

# Count emails sent today
grep "$(date +%Y-%m-%d)" /tmp/iit_shelf_otp.log | wc -l
```

---

## Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| "Connection refused" | Check host/port correct in mail.php |
| "Authentication failed" | Verify username/password spelling (copy from provider) |
| Emails not arriving | Check Mailtrap inbox; verify config loaded |
| Slow email sending | Normal for SMTP (1-3 sec per email) |
| "Permission denied" log file | Run: `chmod 777 /tmp/iit_shelf_otp*` |
| Want to disable logging | Comment out `self::logOtpToFile()` in mail_service.php |

---

## Production Deployment

### Before Going Live

1. **Change Provider** - Don't use Mailtrap in production
   ```php
   'host' => 'your-production-smtp-host',
   'username' => 'production-username',
   'password' => 'production-password',
   ```

2. **Update From Address**
   ```php
   'from_email' => 'noreply@yourdomain.com',
   'from_name' => 'IIT Library',
   ```

3. **Disable OTP Logging** (Optional)
   - Remove or comment `logOtpToFile()` call in mail_service.php

4. **Set Up DNS Records** (Gmail/SendGrid/AWS)
   - SPF record
   - DKIM keys
   - DMARC policy
   - Reverse DNS

5. **Monitor Email** (AWS/SendGrid)
   - Track bounce rates
   - Monitor complaints
   - Check delivery status

### Recommended Production Providers

- **SendGrid** - Best for transactional emails (OTPs)
- **AWS SES** - Very scalable, lowest cost
- **Gmail** - Personal/small scale (limited rate)
- **Mailgun** - Good alternative to SendGrid

---

## Testing

### Run Full Test Suite
```bash
cd /mnt/academics/iit_shelf_test/backend
./test_complete_flow.sh
```

This tests all 8 authentication steps including email sending.

### Manual Test
```bash
# 1. Send OTP
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"your-email@gmail.com"}'

# 2. Watch log
tail -f /tmp/iit_shelf_otp.log

# 3. Check Mailtrap inbox (refresh page)

# 4. Copy OTP from log
# 5. Use OTP to verify email
curl -X POST http://localhost:8000/api/auth/verify_email.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"your-email@gmail.com","otp":"123456"}'
```

---

## Summary

| Component | Status | Notes |
|-----------|--------|-------|
| SimpleSMTPMailer | ✅ Ready | No dependencies needed |
| MailService | ✅ Ready | Integrated with auth |
| Config Management | ✅ Ready | Supports 4+ providers |
| Email Templates | ✅ Ready | Professional HTML |
| Testing | ✅ Ready | Full test suite |
| Documentation | ✅ Ready | This guide + code comments |

**Your system is ready for real email. Just add credentials and test!**

---

## Quick Reference

```bash
# File to edit: UPDATE CREDENTIALS HERE
vim /mnt/academics/iit_shelf_test/backend/api/config/mail.php

# Line to change (for Mailtrap):
'username' => 'your-username-from-mailtrap',
'password' => 'your-password-from-mailtrap',

# Save and test
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"test@gmail.com"}'

# View OTP log
tail -f /tmp/iit_shelf_otp.log

# Check Mailtrap inbox
# Open https://mailtrap.io and view your inbox
```

That's it! Emails will be sent on registration and password reset.
