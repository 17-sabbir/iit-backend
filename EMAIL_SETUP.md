# Email Sending Setup Guide

Your IIT Shelf authentication system is ready to send real emails! The mail service uses a lightweight SMTP implementation (no external dependencies needed).

## Quick Start: Mailtrap (Recommended for Development)

### Step 1: Create Free Mailtrap Account
1. Go to https://mailtrap.io
2. Sign up (free tier available)
3. Click "Email Testing" in sidebar
4. Create or select a project
5. Copy your SMTP credentials

### Step 2: Update Configuration
Edit `/backend/api/config/mail.php` and replace:
```php
'username' => getenv('MAILTRAP_USERNAME') ?: 'your-mailtrap-username',
'password' => getenv('MAILTRAP_PASSWORD') ?: 'your-mailtrap-password',
```

With your actual Mailtrap username and password.

### Step 3: Test
Run the registration flow:
```bash
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com"}'
```

Emails should now appear in your Mailtrap inbox!

## Configuration Methods

### Method 1: Update mail.php directly (Simple)
```php
'username' => 'your_actual_username',
'password' => 'your_actual_password',
```

### Method 2: Use Environment Variables (Recommended)
```bash
export MAIL_DRIVER=mailtrap
export MAILTRAP_USERNAME=your_username
export MAILTRAP_PASSWORD=your_password
```

Then start your PHP server with:
```bash
php -S localhost:8000
```

## Other Email Providers

### Gmail
1. Enable "Less secure app access" or use App Passwords
2. Set driver to 'gmail' in mail.php
3. Update GMAIL_USERNAME and GMAIL_PASSWORD environment variables

### SendGrid
1. Get API key from sendgrid.com
2. Set driver to 'sendgrid'
3. Set SENDGRID_API_KEY environment variable

### AWS SES
1. Configure AWS credentials
2. Set driver to 'aws_ses'
3. Set AWS_SES_HOST, AWS_SES_USERNAME, AWS_SES_PASSWORD

### Local Mail Server
1. Install Postfix or Exim on your server
2. Set driver to 'local'
3. Emails will be processed by the system's mail server

## Testing Email Delivery

### Check OTP Log
```bash
cat /tmp/iit_shelf_otp.log
```

This file logs all OTPs for development purposes (in addition to sending real emails).

### Run Full Test Suite
```bash
cd /backend
chmod +x test_complete_flow.sh
./test_complete_flow.sh
```

All 8 authentication steps will execute and emails should appear in your provider's inbox.

### Manual Test
```bash
# Send registration OTP
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H "Content-Type: application/json" \
  -d '{"email":"yourtest@email.com"}'

# Response shows OTP (in response body during dev)
# Email also sent to yourtest@email.com
```

## Troubleshooting

### Emails not appearing in Mailtrap
- Verify credentials in mail.php match Mailtrap account
- Check PHP error logs: `tail -f /var/log/php-errors.log`
- Verify network connection: `ping smtp.mailtrap.io`

### "Connection refused" error
- Check mail host and port are correct
- Ensure SMTP port isn't blocked by firewall
- For Mailtrap: must use port 2525 (not 25 or 587)

### "Authentication failed" error
- Double-check username and password spelling
- Ensure no extra spaces in credentials
- Try copying directly from Mailtrap dashboard

### PHP can't connect to SMTP
- Verify firewall allows outbound on SMTP port
- Test with: `telnet smtp.mailtrap.io 2525`
- Check if ISP blocks port 25 (common, use 2525 instead)

## Email Templates

Both verification and password reset emails include:
- Professional HTML formatting
- Large, easy-to-read OTP code
- Clear expiration information
- Action instructions

Templates can be customized in `mail_service.php`:
- `buildVerificationEmailBody()`
- `buildPasswordResetEmailBody()`

## Production Considerations

For production deployment:
1. Use SendGrid, AWS SES, or similar (100+ emails/day limit)
2. Store credentials in `.env` file (never in code)
3. Configure appropriate from address (noreply@yourdomain.com)
4. Monitor bounce/complaint rates
5. Set up SPF, DKIM, DMARC records
6. Consider email rate limiting
7. Keep OTP log file secure or disable for production

## Architecture

The mail system consists of:
- **simple_smtp_mailer.php** - Low-level SMTP protocol client (no dependencies)
- **mail_service.php** - High-level email API (handles OTP emails)
- **config/mail.php** - Configuration management

All components work together to provide reliable, flexible email delivery.
