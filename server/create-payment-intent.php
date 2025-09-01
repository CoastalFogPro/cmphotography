<?php
/**
 * Create Payment Intent for Custom Checkout
 * Handles payment processing for the custom checkout page
 */
require_once __DIR__ . '/../includes/db.php';

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Set JSON content type
header('Content-Type: application/json');

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate CSRF token
    if (!validateCSRFToken($input['csrf_token'] ?? '')) {
        throw new Exception('Invalid request token');
    }

    // Get and validate input
    $photoId = (int)($input['photo_id'] ?? 0);
    $sizeId = (int)($input['size_id'] ?? 0);
    $customerEmail = trim($input['customer_email'] ?? '');
    $customerName = trim($input['customer_name'] ?? '');
    $paymentMethodId = $input['payment_method_id'] ?? '';

    if ($photoId <= 0 || $sizeId <= 0) {
        throw new Exception('Invalid photo or size selection');
    }

    if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email address is required');
    }

    if (empty($customerName)) {
        throw new Exception('Customer name is required');
    }

    // Get photo and size data
    $photo = getPhoto($photoId);
    if (!$photo) {
        throw new Exception('Photo not found');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT *, base_price as price_cents, label as size_label FROM print_sizes 
                         WHERE id = ? AND status = 'active'");
    $stmt->execute([$sizeId]);
    $size = $stmt->fetch();

    if (!$size) {
        throw new Exception('Invalid size selection');
    }

    // Create payment intent with Stripe
    $stripeSecretKey = STRIPE_SECRET_KEY;
    $amount = $size['price_cents']; // Amount in cents
    $currency = strtolower(STRIPE_CURRENCY ?? 'usd');

    // Create payment intent via cURL
    $ch = curl_init('https://api.stripe.com/v1/payment_intents');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $stripeSecretKey,
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $postData = http_build_query([
        'amount' => $amount,
        'currency' => $currency,
        'payment_method' => $paymentMethodId,
        'confirm' => 'true',
        'return_url' => baseUrl('/?order=success'),
        'receipt_email' => $customerEmail,
        'description' => "{$size['size_label']} Print - {$photo['title']}",
        'metadata[photo_id]' => $photoId,
        'metadata[size_id]' => $sizeId,
        'metadata[photo_title]' => $photo['title'] ?: 'Untitled Photo',
        'metadata[size_label]' => $size['size_label'],
        'metadata[customer_name]' => $customerName
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Stripe Payment Intent error: HTTP $httpCode - $response");
        throw new Exception('Failed to create payment intent');
    }

    $paymentIntent = json_decode($response, true);
    
    if (!$paymentIntent || !isset($paymentIntent['id'])) {
        error_log("Invalid Stripe Payment Intent response: $response");
        throw new Exception('Invalid response from payment processor');
    }

    // Save order record
    $orderData = [
        'stripe_session_id' => $paymentIntent['id'], // Using payment intent ID
        'customer_email' => $customerEmail,
        'customer_name' => $customerName,
        'photo_id' => $photoId,
        'size_id' => $sizeId,
        'amount_cents' => $amount,
        'currency' => STRIPE_CURRENCY,
        'status' => 'pending'
    ];

    $orderId = saveOrder($orderData);
    if (!$orderId) {
        error_log("Failed to save order for payment intent: {$paymentIntent['id']}");
    }

    // Handle different payment intent statuses
    if ($paymentIntent['status'] === 'succeeded') {
        // Payment completed immediately
        echo json_encode([
            'success' => true,
            'payment_completed' => true
        ]);
    } elseif ($paymentIntent['status'] === 'requires_action') {
        // 3D Secure or other action required
        echo json_encode([
            'success' => true,
            'requires_action' => true,
            'client_secret' => $paymentIntent['client_secret']
        ]);
    } else {
        // Payment failed
        throw new Exception('Payment could not be processed');
    }

} catch (Exception $e) {
    error_log("Payment Intent error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
