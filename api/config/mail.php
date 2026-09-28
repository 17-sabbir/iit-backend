<?php
/**
 * Mail Configuration
 * 
 * Supports multiple email providers for different environments:
 * - Mailtrap: Free sandbox SMTP for testing (recommended for development)
 * - Gmail: Personal Gmail SMTP (requires app-specific password)
 * - SendGrid: Transactional email service
 * - AWS SES: Amazon's email service
 * - Local Mail: Uses system's local mail server
 */

// Get the mail driver from environment or use default
$driver = getenv('MAIL_DRIVER') ?: 'gmail';  // Use Outlook by default

switch ($driver) {
    case 'gmail':
        return [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => getenv('GMAIL_USERNAME') ?: 'tamaltm143@gmail.com',
            'password' => getenv('GMAIL_PASSWORD') ?: 'xtisfcuudxkkscpj',
            'encryption' => 'tls',
            'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@iitshelf.edu',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'IIT Shelf Library'
        ];

    case 'sendgrid':
        return [
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'username' => 'apikey',
            'password' => getenv('SENDGRID_API_KEY') ?: 'your-sendgrid-api-key',
            'encryption' => 'tls',
            'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@iitshelf.edu',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'IIT Shelf Library'
        ];

    case 'aws_ses':
        return [
            'host' => getenv('AWS_SES_HOST') ?: 'email-smtp.us-east-1.amazonaws.com',
            'port' => 587,
            'username' => getenv('AWS_SES_USERNAME') ?: 'your-aws-ses-username',
            'password' => getenv('AWS_SES_PASSWORD') ?: 'your-aws-ses-password',
            'encryption' => 'tls',
            'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@iitshelf.edu',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'IIT Shelf Library'
        ];

    case 'outlook':
        return [
            'host' => 'smtp-mail.outlook.com',
            'port' => 587,
            'username' => getenv('OUTLOOK_USERNAME') ?: 'your-email@outlook.com',
            'password' => getenv('OUTLOOK_PASSWORD') ?: 'your-outlook-password',
            'encryption' => 'tls',
            'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'your-email@outlook.com',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'IIT Shelf Library'
        ];

    case 'local':
        return [
            'host' => 'localhost',
            'port' => 25,
            'username' => '',
            'password' => '',
            'encryption' => '',
            'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@iitshelf.local',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'IIT Shelf Library'
        ];

    case 'mailtrap':
    default:
        // Default: Mailtrap (FREE sandbox SMTP for testing)
        // Sign up at https://mailtrap.io and get your SMTP credentials
        return [
            'host' => getenv('MAILTRAP_HOST') ?: 'smtp.mailtrap.io',
            'port' => getenv('MAILTRAP_PORT') ?: 2525,
            'username' => getenv('MAILTRAP_USERNAME') ?: 'your-mailtrap-username',
            'password' => getenv('MAILTRAP_PASSWORD') ?: 'your-mailtrap-password',
            'encryption' => getenv('MAILTRAP_ENCRYPTION') ?: 'tls',
            'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@iitshelf.local',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'IIT Shelf Library'
        ];
}
