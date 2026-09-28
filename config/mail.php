<?php
/**
 * Mail Configuration
 * Update these values with your SMTP provider credentials
 */

// Mailtrap Configuration (for development/testing)
define('MAIL_DRIVER', 'smtp'); // 'smtp' or 'native' (for local mail server)
define('MAIL_HOST', 'smtp.mailtrap.io');
define('MAIL_PORT', 2525);
define('MAIL_USERNAME', ''); // TODO: Add your Mailtrap username
define('MAIL_PASSWORD', ''); // TODO: Add your Mailtrap password
define('MAIL_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
define('MAIL_FROM_ADDRESS', 'noreply@iitshelf.edu');
define('MAIL_FROM_NAME', 'IIT Shelf - Library Management');

// Alternative: Gmail SMTP Configuration
// define('MAIL_HOST', 'smtp.gmail.com');
// define('MAIL_PORT', 587);
// define('MAIL_USERNAME', 'your-email@gmail.com');
// define('MAIL_PASSWORD', 'your-app-password'); // Use app-specific password, not account password
// define('MAIL_ENCRYPTION', 'tls');

// Alternative: SendGrid Configuration
// define('MAIL_DRIVER', 'sendgrid');
// define('SENDGRID_API_KEY', ''); // TODO: Add SendGrid API key
// define('MAIL_FROM_ADDRESS', 'noreply@iitshelf.edu');
// define('MAIL_FROM_NAME', 'IIT Shelf - Library Management');

// Alternative: AWS SES Configuration
// define('MAIL_DRIVER', 'ses');
// define('AWS_ACCESS_KEY_ID', ''); // TODO: Add AWS access key
// define('AWS_SECRET_ACCESS_KEY', ''); // TODO: Add AWS secret key
// define('AWS_DEFAULT_REGION', 'us-east-1');
// define('MAIL_FROM_ADDRESS', 'verified-email@yourdomain.com'); // Must be verified in SES

// For local mail server (Postfix/Exim4)
// define('MAIL_DRIVER', 'native');
