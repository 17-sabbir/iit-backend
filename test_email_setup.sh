#!/bin/bash
# Test SMTP email connectivity

echo "=== IIT Shelf Email Setup Test ==="
echo ""

# Check if mail.php exists
if [ -f "/mnt/academics/iit_shelf_test/backend/api/config/mail.php" ]; then
    echo "✓ mail.php configuration found"
else
    echo "✗ mail.php not found"
    exit 1
fi

# Check if SimpleSMTPMailer exists
if [ -f "/mnt/academics/iit_shelf_test/backend/api/lib/simple_smtp_mailer.php" ]; then
    echo "✓ SimpleSMTPMailer found"
else
    echo "✗ SimpleSMTPMailer not found"
    exit 1
fi

# Check if mail_service.php exists
if [ -f "/mnt/academics/iit_shelf_test/backend/api/lib/mail_service.php" ]; then
    echo "✓ MailService found"
else
    echo "✗ MailService not found"
    exit 1
fi

echo ""
echo "=== Configuration Status ==="

# Check for placeholders in mail.php
grep -q "your-mailtrap-username" /mnt/academics/iit_shelf_test/backend/api/config/mail.php
if [ $? -eq 0 ]; then
    echo "⚠ Mailtrap credentials not yet configured"
    echo ""
    echo "To enable real email sending:"
    echo "1. Sign up at https://mailtrap.io (free)"
    echo "2. Edit /backend/api/config/mail.php"
    echo "3. Replace 'your-mailtrap-username' with your actual username"
    echo "4. Replace 'your-mailtrap-password' with your actual password"
else
    echo "✓ Appears to have custom credentials configured"
fi

echo ""
echo "=== Testing SMTP Connectivity ==="
echo ""
echo "Mailtrap SMTP Details:"
echo "- Host: smtp.mailtrap.io"
echo "- Port: 2525 (or 25, 465, 587)"
echo "- Encryption: TLS"
echo ""

# Try to ping the SMTP host
if command -v nc &> /dev/null; then
    echo -n "Testing connection to smtp.mailtrap.io:2525... "
    if timeout 3 nc -zv smtp.mailtrap.io 2525 2>&1 | grep -q succeeded; then
        echo "✓ Connected"
    else
        echo "✗ Unable to reach (may be firewall or offline)"
    fi
else
    echo "nc command not found, skipping connectivity test"
fi

echo ""
echo "=== Email Send Test ==="
echo ""
echo "To test real email sending:"
echo ""
echo "# 1. Send registration OTP:"
echo "curl -X POST http://localhost:8000/api/auth/send_register_otp.php \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  -d '{\"email\":\"your-test-email@example.com\"}'"
echo ""
echo "# 2. Check OTP log:"
echo "tail -f /tmp/iit_shelf_otp.log"
echo ""
echo "# 3. Check provider inbox (Mailtrap/Gmail/etc)"
echo ""
echo "# 4. Run full auth test suite:"
echo "./test_complete_flow.sh"
echo ""
echo "=== Setup Complete ==="
echo "Next: Get email credentials and update /backend/api/config/mail.php"
