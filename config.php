<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbajpqslhqbl22');
define('DB_USER', 'ujuuzim02dryl');
define('DB_PASS', 'coastalfog$$68');

// Site Configuration
define('SITE_URL', 'https://coastalfogdev.com');
define('ADMIN_EMAIL', 'cris@coastalfogpro.com');

// Security
define('HASH_SALT', 'your-random-salt-here-change-this');

// File Upload Settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', __DIR__ . '/assets/uploads/');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Email Configuration (for contact form)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('FROM_EMAIL', 'noreply@yourdomain.com');
define('FROM_NAME', 'Photography Website');

// Stripe Configuration
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
?>
