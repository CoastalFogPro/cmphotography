<?php
// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
    }
}

// Load .env file
loadEnv(__DIR__ . '/.env');

// Helper function to get environment variables with fallback
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// Database Configuration
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', env('DB_PASS'));

// Site Configuration
define('SITE_URL', env('SITE_URL'));
define('ADMIN_EMAIL', env('ADMIN_EMAIL'));

// Security
define('HASH_SALT', env('HASH_SALT'));

// File Upload Settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', __DIR__ . '/assets/uploads/');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Email Configuration (for contact form)
define('SMTP_HOST', env('SMTP_HOST', 'localhost'));
define('SMTP_PORT', env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER', ''));
define('SMTP_PASS', env('SMTP_PASS', ''));
define('FROM_EMAIL', env('FROM_EMAIL', 'noreply@yourdomain.com'));
define('FROM_NAME', env('FROM_NAME', 'Photography Website'));

// Stripe Configuration
define('STRIPE_SECRET_KEY', env('STRIPE_SECRET_KEY'));
define('STRIPE_PUBLISHABLE_KEY', env('STRIPE_PUBLISHABLE_KEY'));
define('STRIPE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET'));
define('STRIPE_CURRENCY', 'usd');
define('STRIPE_TAX_ENABLED', true);
define('STRIPE_SHIPPING_COUNTRIES', ['US', 'CA']);
?>
