<?php
/**
 * Stripe Checkout Session Creation
 * Creates a Stripe checkout session for photo print purchases
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
    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        throw new Exception('Invalid request token');
    }

    // Get and validate input
    $photoId = (int)($_POST['photo_id'] ?? 0);
    $sizeId = (int)($_POST['size_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    if ($photoId <= 0 || $sizeId <= 0) {
        throw new Exception('Invalid photo or size selection');
    }

    // Get photo data
    $photo = getPhoto($photoId);
    if (!$photo) {
        throw new Exception('Photo not found');
    }

    // Get size data from global print sizes table
    $db = getDB();
    $stmt = $db->prepare("SELECT *, base_price as price_cents, label as size_label FROM print_sizes 
                         WHERE id = ? AND status = 'active'");
    $stmt->execute([$sizeId]);
    $size = $stmt->fetch();

    if (!$size) {
        throw new Exception('Invalid size selection for this photo');
    }

    // Initialize Stripe (you'll need to install Stripe PHP SDK or use direct API calls)
    // For now, I'll use cURL to make direct API calls to Stripe
    $stripeSecretKey = STRIPE_SECRET_KEY;
    $successUrl = baseUrl('/?order=success');
    $cancelUrl = $_SERVER['HTTP_REFERER'] ?? baseUrl("/gallery.php?slug={$photo['gallery_slug']}");

    // Create dynamic price data (since we don't have pre-configured Stripe price IDs)
    $lineItems = [
        [
            'price_data' => [
                'currency' => strtolower(STRIPE_CURRENCY ?? 'usd'),
                'product_data' => [
                    'name' => "{$size['size_label']} Print - {$photo['title']}",
                    'description' => $size['description'] ?: "High-quality {$size['size_label']} print"
                ],
                'unit_amount' => $size['price_cents']
            ],
            'quantity' => $quantity
        ]
    ];

    // Prepare checkout session data with custom branding
    $checkoutData = [
        'payment_method_types' => ['card'],
        'line_items' => $lineItems,
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata' => [
            'photo_id' => $photoId,
            'size_id' => $sizeId,
            'photo_title' => $photo['title'] ?: 'Untitled Photo',
            'size_label' => $size['size_label']
        ],
        // Custom branding
        'custom_text' => [
            'submit' => [
                'message' => 'Complete your photography print purchase'
            ]
        ],
        'customer_creation' => 'always',
        'phone_number_collection' => [
            'enabled' => false
        ],
        'billing_address_collection' => 'required'
    ];

    // Add shipping if enabled
    if (defined('STRIPE_SHIPPING_COUNTRIES') && !empty(STRIPE_SHIPPING_COUNTRIES)) {
        $checkoutData['shipping_address_collection'] = [
            'allowed_countries' => STRIPE_SHIPPING_COUNTRIES
        ];
    }

    // Add tax calculation if enabled
    if (defined('STRIPE_TAX_ENABLED') && STRIPE_TAX_ENABLED) {
        $checkoutData['automatic_tax'] = ['enabled' => true];
    }

    // Create Stripe checkout session using cURL
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $stripeSecretKey,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    // Convert checkout data to form-encoded format with dynamic pricing
    $currency = strtolower(STRIPE_CURRENCY ?? 'usd');
    $productName = "{$size['size_label']} Print - {$photo['title']}";
    $productDescription = $size['description'] ?: "High-quality {$size['size_label']} print";
    
    $postData = http_build_query([
        'payment_method_types[0]' => 'card',
        'line_items[0][price_data][currency]' => $currency,
        'line_items[0][price_data][product_data][name]' => $productName,
        'line_items[0][price_data][product_data][description]' => $productDescription,
        'line_items[0][price_data][unit_amount]' => $size['price_cents'],
        'line_items[0][quantity]' => $quantity,
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata[photo_id]' => $photoId,
        'metadata[size_id]' => $sizeId,
        'metadata[photo_title]' => $photo['title'] ?: 'Untitled Photo',
        'metadata[size_label]' => $size['size_label'],
        // Custom branding
        'custom_text[submit][message]' => 'Complete your photography print purchase',
        'customer_creation' => 'always',
        'billing_address_collection' => 'required'
    ]);

    // Add shipping if configured
    if (defined('STRIPE_SHIPPING_COUNTRIES') && !empty(STRIPE_SHIPPING_COUNTRIES)) {
        $countries = STRIPE_SHIPPING_COUNTRIES;
        foreach ($countries as $index => $country) {
            $postData .= "&shipping_address_collection[allowed_countries][$index]=$country";
        }
    }

    // Add tax if enabled
    if (defined('STRIPE_TAX_ENABLED') && STRIPE_TAX_ENABLED) {
        $postData .= '&automatic_tax[enabled]=true';
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Stripe API error: HTTP $httpCode - $response");
        throw new Exception('Failed to create checkout session');
    }

    $sessionData = json_decode($response, true);
    
    if (!$sessionData || !isset($sessionData['id'])) {
        error_log("Invalid Stripe response: $response");
        throw new Exception('Invalid response from payment processor');
    }

    // Save initial order record
    $orderData = [
        'stripe_session_id' => $sessionData['id'],
        'photo_id' => $photoId,
        'size_id' => $sizeId,
        'quantity' => $quantity,
        'amount_cents' => $size['price_cents'] * $quantity,
        'currency' => STRIPE_CURRENCY,
        'status' => 'pending'
    ];

    $orderId = saveOrder($orderData);
    if (!$orderId) {
        error_log("Failed to save order for session: {$sessionData['id']}");
    }

    // Return checkout URL
    echo json_encode([
        'success' => true,
        'url' => $sessionData['url'],
        'session_id' => $sessionData['id']
    ]);

} catch (Exception $e) {
    error_log("Checkout error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
