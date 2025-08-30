<?php
/**
 * Contact Form Processing
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check CSRF token
$token = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request token']);
    exit;
}

// Validate and sanitize input
$name = sanitizeInput($_POST['name'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$subject = sanitizeInput($_POST['subject'] ?? '');
$message = sanitizeInput($_POST['message'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');

// Basic validation
$errors = [];
if (empty($name)) $errors[] = 'Name is required';
if (empty($email)) $errors[] = 'Email is required';
if (empty($message)) $errors[] = 'Message is required';
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    // Save to database
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO contacts (name, email, subject, message, phone) VALUES (?, ?, ?, ?, ?)");
    $result = $stmt->execute([$name, $email, $subject, $message, $phone]);
    
    if (!$result) {
        throw new Exception('Failed to save contact submission');
    }
    
    // Send email notification
    $success = sendContactNotification($name, $email, $subject, $message, $phone);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Thank you for your message! I will get back to you soon.',
        'email_sent' => $success
    ]);
    
} catch (Exception $e) {
    error_log("Contact form error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again.']);
}

/**
 * Send email notification
 */
function sendContactNotification($name, $email, $subject, $message, $phone) {
    $to = ADMIN_EMAIL;
    $emailSubject = "New Contact Form Submission: " . $subject;
    
    $body = "New contact form submission from your photography website:\n\n";
    $body .= "Name: $name\n";
    $body .= "Email: $email\n";
    if ($phone) $body .= "Phone: $phone\n";
    $body .= "Subject: $subject\n\n";
    $body .= "Message:\n$message\n\n";
    $body .= "---\n";
    $body .= "Submitted: " . date('Y-m-d H:i:s') . "\n";
    $body .= "IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n";
    
    $headers = [
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . $name . ' <' . $email . '>',
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8'
    ];
    
    // Use PHP's mail() function (works on most shared hosting)
    return mail($to, $emailSubject, $body, implode("\r\n", $headers));
}
?>
