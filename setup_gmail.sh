#!/bin/bash
# Quick Gmail Setup Script

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║             IIT SHELF - GMAIL EMAIL SETUP                      ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
echo "Gmail is easier than Mailtrap for testing!"
echo ""
echo "STEP 1: Generate Gmail App Password"
echo "----------------------------------------"
echo "1. Visit: https://myaccount.google.com/apppasswords"
echo "2. Sign in with your Gmail account"
echo "3. Select 'Mail' and 'Other (Custom name)'"
echo "4. Type 'IIT Shelf' and click Generate"
echo "5. Copy the 16-character password (remove spaces)"
echo ""
echo "STEP 2: Enter Your Credentials"
echo "----------------------------------------"
read -p "Enter your Gmail address: " gmail_address
read -p "Enter your app password (16 chars): " gmail_password
echo ""

# Update mail.php to use Gmail
echo "Updating configuration..."

cat > /tmp/mail_config_gmail.php << EOF
<?php
/**
 * Mail Configuration - Using Gmail
 */

return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => '$gmail_address',
    'password' => '$gmail_password',
    'encryption' => 'tls',
    'from_email' => '$gmail_address',
    'from_name' => 'IIT Shelf Library'
];
EOF

# Backup original config
if [ -f "api/config/mail.php" ]; then
    cp api/config/mail.php api/config/mail.php.backup
    echo "✓ Backed up original config to mail.php.backup"
fi

# Update the config to use Gmail by default
sed -i "s/\$driver = getenv('MAIL_DRIVER') ?: 'mailtrap';/\$driver = getenv('MAIL_DRIVER') ?: 'gmail';/" api/config/mail.php

# Update Gmail credentials in the file
sed -i "s/'username' => getenv('GMAIL_USERNAME') ?: 'your-email@gmail.com',/'username' => getenv('GMAIL_USERNAME') ?: '$gmail_address',/" api/config/mail.php
sed -i "s/'password' => getenv('GMAIL_PASSWORD') ?: 'your-app-specific-password',/'password' => getenv('GMAIL_PASSWORD') ?: '$gmail_password',/" api/config/mail.php
sed -i "s/'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@iitshelf.edu',/'from_email' => getenv('MAIL_FROM_ADDRESS') ?: '$gmail_address',/" api/config/mail.php

echo "✓ Configuration updated"
echo ""

echo "STEP 3: Test Email Sending"
echo "----------------------------------------"
echo "Testing configuration..."
php test_mail_config.php

echo ""
echo "Sending test OTP to $gmail_address..."
curl -X POST http://localhost:8000/api/auth/send_register_otp.php \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$gmail_address\"}" \
  2>/dev/null

echo ""
echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                    SETUP COMPLETE!                            ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
echo "✓ Configuration updated to use Gmail"
echo "✓ Test OTP sent to $gmail_address"
echo ""
echo "CHECK YOUR GMAIL INBOX NOW!"
echo "You should receive an email with a 6-digit OTP code."
echo ""
echo "If you don't see it:"
echo "1. Check spam folder"
echo "2. Check OTP log: tail /tmp/iit_shelf_otp.log"
echo "3. Verify app password is correct"
echo ""
echo "To revert to original config:"
echo "cp api/config/mail.php.backup api/config/mail.php"
echo ""
