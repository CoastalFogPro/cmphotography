<?php
/**
 * Database Connection and Core Utilities
 */

// Start session if not already started (and if headers haven't been sent)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Load configuration
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    die('Configuration file not found. Please copy config.sample.php to config.php and configure your database settings.');
}

/**
 * Get database connection
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die('Database connection failed. Please check your configuration.');
        }
    }
    
    return $pdo;
}

/**
 * Security Functions
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Settings Functions
 */
function getSetting($key, $default = '') {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $default;
}

function setSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

/**
 * Image Functions
 */
function uploadImage($file, $title = '', $category = 'general') {
    // Validate file
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Generate unique filename
    $filename = uniqid() . '.' . $ext;
    $fullPath = UPLOAD_PATH . 'full/' . $filename;
    $thumbPath = UPLOAD_PATH . 'thumbs/' . $filename;
    
    // Create directories if they don't exist
    if (!is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0755, true);
    }
    if (!is_dir(dirname($thumbPath))) {
        mkdir(dirname($thumbPath), 0755, true);
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['success' => false, 'error' => 'Failed to save file'];
    }
    
    // Create thumbnail
    if (!createThumbnail($fullPath, $thumbPath, 300, 300)) {
        unlink($fullPath);
        return ['success' => false, 'error' => 'Failed to create thumbnail'];
    }
    
    // Save to database
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO images (title, filename, thumbnail, category, alt_text) VALUES (?, ?, ?, ?, ?)");
    $result = $stmt->execute([$title, $filename, $filename, $category, $title]);
    
    if ($result) {
        return ['success' => true, 'id' => $db->lastInsertId(), 'filename' => $filename];
    } else {
        unlink($fullPath);
        unlink($thumbPath);
        return ['success' => false, 'error' => 'Failed to save to database'];
    }
}

function createThumbnail($source, $destination, $width, $height) {
    $imageInfo = getimagesize($source);
    if (!$imageInfo) {
        return false;
    }
    
    list($sourceWidth, $sourceHeight, $sourceType) = $imageInfo;
    
    // Calculate dimensions maintaining aspect ratio
    $ratio = min($width / $sourceWidth, $height / $sourceHeight);
    $newWidth = $sourceWidth * $ratio;
    $newHeight = $sourceHeight * $ratio;
    
    // Create image resource
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($source);
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Create thumbnail
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG
    if ($sourceType == IMAGETYPE_PNG) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    
    // Save thumbnail
    $result = false;
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($thumb, $destination, 85);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($thumb, $destination, 8);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($thumb, $destination, 85);
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($thumb);
    
    return $result;
}

/**
 * Utility Functions
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

/**
 * Gallery System Functions
 */

/**
 * Create a slug from a string
 * @param string $text Input text to slugify
 * @return string URL-friendly slug
 */
function createSlug($text) {
    // Replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // Lowercase
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

/**
 * Format a price in cents to a display price
 * @param int $cents Price in cents
 * @param string $currency Currency code
 * @return string Formatted price
 */
function formatPrice($cents, $currency = 'usd') {
    $amount = $cents / 100;
    if (strtolower($currency) === 'usd') {
        return '$' . number_format($amount, 2);
    }
    return number_format($amount, 2) . ' ' . strtoupper($currency);
}

/**
 * Generate a base URL
 * @param string $path Optional path to append
 * @return string Full URL
 */
function baseUrl($path = '') {
    $baseUrl = rtrim(SITE_URL, '/');
    $path = ltrim($path, '/');
    return $path ? "$baseUrl/$path" : $baseUrl;
}

/**
 * Get a gallery by its slug
 * @param string $slug Gallery slug
 * @return array|null Gallery data or null if not found
 */
function getGalleryBySlug($slug) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM galleries WHERE slug = ? AND status = 'active'");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

/**
 * Get active galleries
 * @param int $limit Optional limit
 * @return array List of galleries
 */
function getActiveGalleries($limit = 0) {
    $db = getDB();
    $query = "SELECT * FROM galleries WHERE status = 'active' ORDER BY sort_order ASC, title ASC";
    
    if ($limit > 0) {
        $query .= " LIMIT " . (int)$limit;
    }
    
    return $db->query($query)->fetchAll();
}

/**
 * Get photos for a gallery with pagination
 * @param int $galleryId Gallery ID
 * @param int $page Current page number
 * @param int $perPage Items per page
 * @return array [photos, totalPages, totalPhotos]
 */
function getGalleryPhotos($galleryId, $page = 1, $perPage = 24) {
    $db = getDB();
    $galleryId = (int)$galleryId;
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM photos WHERE gallery_id = ?");
    $countStmt->execute([$galleryId]);
    $totalPhotos = $countStmt->fetchColumn();
    $totalPages = ceil($totalPhotos / $perPage);
    
    // Get photos for current page
    $stmt = $db->prepare("SELECT * FROM photos WHERE gallery_id = ? ORDER BY sort_order ASC, created_at DESC LIMIT ?, ?");
    $stmt->bindValue(1, $galleryId, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->bindValue(3, $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $photos = $stmt->fetchAll();
    
    return [
        'photos' => $photos,
        'totalPages' => $totalPages,
        'totalPhotos' => $totalPhotos
    ];
}

/**
 * Get a photo by ID
 * @param int $photoId Photo ID
 * @return array|null Photo data or null if not found
 */
function getPhoto($photoId) {
    $db = getDB();
    
    // First, check what columns exist in the photos table
    $columnsStmt = $db->query("SHOW COLUMNS FROM photos");
    $columns = $columnsStmt->fetchAll();
    $columnNames = array_column($columns, 'Field');
    
    // Build the SELECT clause dynamically
    $photoColumns = [];
    foreach ($columnNames as $col) {
        $photoColumns[] = "p.$col";
    }
    
    // Add gallery columns
    $photoColumns[] = "g.title as gallery_title";
    $photoColumns[] = "g.slug as gallery_slug";
    
    $selectClause = implode(', ', $photoColumns);
    
    $stmt = $db->prepare("SELECT $selectClause
                          FROM photos p 
                          JOIN galleries g ON p.gallery_id = g.id 
                          WHERE p.id = ?");
    $stmt->execute([(int)$photoId]);
    return $stmt->fetch();
}

/**
 * Get available sizes for a photo
 * @param int $photoId Photo ID (currently unused - returns all active sizes)
 * @return array List of size options
 */
function getPhotoSizes($photoId) {
    // DEBUG: This function was updated at 23:20 UTC
    error_log("DEBUG: getPhotoSizes called from updated function");
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, label as size_label, dimensions, base_price as price_cents, description, sort_order, status 
                          FROM print_sizes 
                          WHERE status = 'active' 
                          ORDER BY sort_order ASC, base_price ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get a specific size by ID
 * @param int $sizeId Size ID
 * @return array|null Size data or null if not found
 */
function getPhotoSize($sizeId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM print_sizes WHERE id = ?");
    $stmt->execute([(int)$sizeId]);
    return $stmt->fetch();
}

/**
 * Save an order to the database
 * @param array $orderData Order data
 * @return int|bool New order ID or false on failure
 */
function saveOrder($orderData) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO orders 
                          (stripe_session_id, customer_email, photo_id, photo_size_id, total_amount, status) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([
        $orderData['stripe_session_id'] ?? null,
        $orderData['customer_email'] ?? null,
        $orderData['photo_id'] ?? null,
        $orderData['size_id'] ?? null, // This maps to photo_size_id in the database
        $orderData['amount_cents'] ?? null, // This maps to total_amount in the database
        $orderData['status'] ?? 'pending'
    ]);
    
    return $result ? $db->lastInsertId() : false;
}

/**
 * Update an order with payment information
 * @param string $sessionId Stripe session ID
 * @param array $paymentData Payment data
 * @return bool Success status
 */
function updateOrderPayment($sessionId, $paymentData) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE orders SET 
                          stripe_payment_intent = ?, 
                          customer_email = ?, 
                          total_amount = ?,
                          status = 'completed'
                          WHERE stripe_session_id = ?");
    return $stmt->execute([
        $paymentData['payment_intent'] ?? null,
        $paymentData['customer_email'] ?? null,
        $paymentData['amount_total'] ?? null,
        $sessionId
    ]);
}
?>
