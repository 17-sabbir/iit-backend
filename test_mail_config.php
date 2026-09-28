#!/usr/bin/env php
<?php
// Test configuration loading

$configFile = __DIR__ . '/api/config/mail.php';
if (!file_exists($configFile)) {
    echo "❌ mail.php not found\n";
    exit(1);
}

$config = require $configFile;

echo "✅ Configuration loaded successfully\n";
echo "\n";
echo "Current Configuration:\n";
echo "  Provider: " . (getenv('MAIL_DRIVER') ?: 'mailtrap') . "\n";
echo "  Host: " . $config['host'] . "\n";
echo "  Port: " . $config['port'] . "\n";
echo "  Encryption: " . $config['encryption'] . "\n";
echo "  From: " . $config['from_name'] . " <" . $config['from_email'] . ">\n";
echo "\n";

// Check if credentials are configured
if ($config['username'] === 'your-mailtrap-username') {
    echo "⚠️  Credentials not yet configured\n";
    echo "   Edit api/config/mail.php to add your SMTP credentials\n";
} else {
    echo "✅ Custom credentials detected\n";
}

// Test SimpleSMTPMailer loading
require_once __DIR__ . '/api/lib/simple_smtp_mailer.php';
echo "\n✅ SimpleSMTPMailer loaded\n";

// Test MailService loading
require_once __DIR__ . '/api/lib/mail_service.php';
echo "✅ MailService loaded\n";

echo "\n";
echo "=== System Ready ===\n";
echo "Next: Add your SMTP credentials to api/config/mail.php\n";
