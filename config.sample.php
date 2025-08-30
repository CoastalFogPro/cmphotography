<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Site Configuration
define('SITE_URL', 'https://yourdomain.com');
define('ADMIN_EMAIL', 'admin@example.com');

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
define('STRIPE_SECRET_KEY', 'sk_test_...');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');
define('STRIPE_CURRENCY', 'usd');
define('STRIPE_TAX_ENABLED', true);
define('STRIPE_SHIPPING_COUNTRIES', ['US', 'CA']);
?>
