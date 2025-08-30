<?php
/**
 * Stripe Webhook Handler
 * Processes Stripe webhook events for order completion
 */
require_once __DIR__ . '/../includes/db.php';

// Get the webhook secret
$webhookSecret = STRIPE_WEBHOOK_SECRET ?? '';
if (empty($webhookSecret)) {
    error_log('Stripe webhook secret not configured');
    http_response_code(400);
    exit;
}

// Get the raw POST body
$payload = @file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify the webhook signature
function verifySignature($payload, $sigHeader, $secret) {
    $elements = explode(',', $sigHeader);
    $timestamp = '';
    $signatures = [];
    
    foreach ($elements as $element) {
        list($key, $value) = explode('=', $element, 2);
        if ($key === 't') {
            $timestamp = $value;
        } elseif ($key === 'v1') {
            $signatures[] = $value;
        }
    }
    
    if (empty($timestamp) || empty($signatures)) {
        return false;
    }
    
    // Check timestamp (within 5 minutes)
    if (abs(time() - $timestamp) > 300) {
        return false;
    }
    
    // Verify signature
    $expectedSig = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    
    foreach ($signatures as $signature) {
        if (hash_equals($expectedSig, $signature)) {
            return true;
        }
    }
    
    return false;
}

// Verify the webhook signature
if (!verifySignature($payload, $sigHeader, $webhookSecret)) {
    error_log('Invalid webhook signature');
    http_response_code(400);
    exit;
}

// Parse the event
$event = json_decode($payload, true);
if (!$event) {
    error_log('Invalid JSON in webhook payload');
    http_response_code(400);
    exit;
}

// Log the event for debugging (remove in production)
error_log("Stripe webhook received: {$event['type']} - ID: {$event['id']}");

try {
    // Handle the event
    switch ($event['type']) {
        case 'checkout.session.completed':
            $session = $event['data']['object'];
            handleCheckoutCompleted($session);
            break;
            
        default:
            // Log unhandled event type
            error_log("Unhandled webhook event type: {$event['type']}");
    }
    
    http_response_code(200);
    echo json_encode(['received' => true]);
    
} catch (Exception $e) {
    error_log("Webhook processing error: " . $e->getMessage());
    http_response_code(500);
    exit;
}

/**
 * Handle completed checkout session
 */
function handleCheckoutCompleted($session) {
    $db = getDB();
    
    // Extract data from session
    $sessionId = $session['id'];
    $paymentIntentId = $session['payment_intent'];
    $customerEmail = $session['customer_details']['email'] ?? '';
    $amountTotal = $session['amount_total'];
    $currency = $session['currency'];
    
    // Get metadata
    $photoId = $session['metadata']['photo_id'] ?? null;
    $sizeId = $session['metadata']['size_id'] ?? null;
    
    // Update the existing order record
    $stmt = $db->prepare("UPDATE orders SET 
                         stripe_payment_intent = ?, 
                         customer_email = ?, 
                         amount_cents = ?,
                         currency = ?,
                         status = 'completed'
                         WHERE stripe_session_id = ?");
    
    $result = $stmt->execute([
        $paymentIntentId,
        $customerEmail,
        $amountTotal,
        $currency,
        $sessionId
    ]);
    
    if (!$result) {
        error_log("Failed to update order for session: $sessionId");
        return;
    }
    
    // Log successful order completion
    error_log("Order completed successfully - Session: $sessionId, Email: $customerEmail, Amount: $amountTotal");
    
    // Here you could add additional logic like:
    // - Sending confirmation emails
    // - Notifying fulfillment systems
    // - Updating inventory if needed
}
?>
