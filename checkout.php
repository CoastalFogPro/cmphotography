<?php
/**
 * Custom Checkout Page with Stripe Elements
 * Full branding control - checkout stays on your site
 */
require_once __DIR__ . '/includes/db.php';

// Get parameters from the photo page
$photoId = (int)($_GET['photo'] ?? 0);
$sizeId = (int)($_GET['size'] ?? 0);

if ($photoId <= 0 || $sizeId <= 0) {
    redirect('/');
}

// Get photo and size data
$photo = getPhoto($photoId);
$size = getPhotoSize($sizeId);

if (!$photo || !$size) {
    redirect('/');
}

// Get site settings
$siteTitle = getSetting('site_title', 'Photography Portfolio');
$galleries = getActiveGalleries();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo sanitizeInput($siteTitle); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-serif { font-family: 'Playfair Display', serif; }
        [x-cloak] { display: none !important; }
        
        /* Your custom earth-tone colors */
        .bg-earth-brown { background-color: #8B5A3C; }
        .bg-caramel { background-color: #D4A574; }
        .bg-sage { background-color: #B8C5B1; }
        .bg-dusty-blue { background-color: #9BB3C4; }
        .bg-light-sage { background-color: #E8EDE6; }
        .bg-custom-blue { background-color: #8c9fa5; }
        .bg-custom-light-blue { background-color: #c5d4d9; }
        
        .text-earth-brown { color: #8B5A3C; }
        .text-custom-blue { color: #8c9fa5; }
        
        .border-earth-brown { border-color: #8B5A3C; }
        .border-custom-blue { border-color: #8c9fa5; }
        
        .hover\:bg-earth-brown:hover { background-color: #7A4D33; }
        
        /* Stripe Elements styling to match your theme */
        .StripeElement {
            background-color: white;
            border: 1px solid #8c9fa5;
            border-radius: 0.5rem;
            padding: 12px 16px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        
        .StripeElement:focus {
            border-color: #8B5A3C;
            box-shadow: 0 0 0 3px rgba(139, 90, 60, 0.1);
        }
        
        .StripeElement--invalid {
            border-color: #dc2626;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm" x-data="{ isOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0">
                    <a href="/" class="text-2xl font-serif font-bold text-earth-brown">
                        <?php echo sanitizeInput($siteTitle); ?>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-serif font-bold text-gray-900 mb-4">Complete Your Purchase</h1>
                <p class="text-gray-600">Secure checkout powered by Stripe</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Order Summary -->
                <div class="bg-white rounded-lg p-6 shadow-sm border border-sage">
                    <h2 class="text-xl font-serif font-semibold text-earth-brown mb-6">Order Summary</h2>
                    
                    <div class="space-y-4">
                        <!-- Photo Preview -->
                        <div class="flex items-center space-x-4">
                            <img src="/assets/uploads/thumbs/<?php echo $photo['filename']; ?>" 
                                 alt="<?php echo sanitizeInput($photo['alt'] ?: $photo['title']); ?>"
                                 class="w-20 h-20 object-cover rounded-lg">
                            <div>
                                <h3 class="font-medium text-gray-900"><?php echo sanitizeInput($photo['title'] ?: 'Untitled Photo'); ?></h3>
                                <p class="text-sm text-gray-500">From <?php echo sanitizeInput($photo['gallery_title']); ?></p>
                            </div>
                        </div>
                        
                        <!-- Size Details -->
                        <div class="border-t pt-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Size:</span>
                                <span class="font-medium"><?php echo sanitizeInput($size['label']); ?></span>
                            </div>
                            <div class="flex justify-between mt-2">
                                <span class="text-gray-600">Dimensions:</span>
                                <span class="font-medium"><?php echo sanitizeInput($size['dimensions']); ?></span>
                            </div>
                            <div class="flex justify-between mt-2">
                                <span class="text-gray-600">Price:</span>
                                <span class="font-medium"><?php echo formatPrice($size['base_price']); ?></span>
                            </div>
                        </div>
                        
                        <!-- Total -->
                        <div class="border-t pt-4">
                            <div class="flex justify-between text-lg font-semibold">
                                <span>Total:</span>
                                <span><?php echo formatPrice($size['base_price']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Form -->
                <div class="bg-white rounded-lg p-6 shadow-sm border border-sage" x-data="customCheckout">
                    <h2 class="text-xl font-serif font-semibold text-earth-brown mb-6">Payment Details</h2>
                    
                    <form @submit.prevent="submitPayment" class="space-y-6">
                        <!-- Customer Information -->
                        <div class="space-y-4">
                            <h3 class="font-medium text-gray-900">Contact Information</h3>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" id="email" x-model="customerEmail" required
                                       class="w-full px-3 py-2 border border-custom-blue rounded-lg focus:outline-none focus:border-earth-brown focus:ring-1 focus:ring-earth-brown">
                            </div>
                            
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                <input type="text" id="name" x-model="customerName" required
                                       class="w-full px-3 py-2 border border-custom-blue rounded-lg focus:outline-none focus:border-earth-brown focus:ring-1 focus:ring-earth-brown">
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="space-y-4">
                            <h3 class="font-medium text-gray-900">Payment Method</h3>
                            
                            <div>
                                <label for="card-element" class="block text-sm font-medium text-gray-700 mb-1">Card Details</label>
                                <div id="card-element" class="StripeElement">
                                    <!-- Stripe Elements will create form elements here -->
                                </div>
                                <div id="card-errors" role="alert" class="text-red-600 text-sm mt-1"></div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" 
                                :disabled="isProcessing"
                                :class="isProcessing ? 'bg-gray-300 cursor-not-allowed' : 'bg-custom-blue hover:bg-earth-brown'"
                                class="w-full py-4 px-6 text-white font-medium rounded-lg transition duration-200">
                            <span x-show="!isProcessing">Complete Purchase - <?php echo formatPrice($size['base_price']); ?></span>
                            <span x-show="isProcessing">Processing...</span>
                        </button>
                        
                        <div x-show="errorMessage" class="text-red-600 text-sm text-center" x-text="errorMessage"></div>
                    </form>

                    <!-- Security Badge -->
                    <div class="mt-6 pt-6 border-t text-center">
                        <p class="text-sm text-gray-500">🔒 Secured by Stripe • Your payment information is encrypted</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Initialize Stripe
        const stripe = Stripe('<?php echo STRIPE_PUBLISHABLE_KEY; ?>');
        const elements = stripe.elements();

        // Create card element with custom styling
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#424770',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
                invalid: {
                    color: '#9e2146',
                },
            },
        });

        cardElement.mount('#card-element');

        // Handle real-time validation errors from the card Element
        cardElement.on('change', ({error}) => {
            const displayError = document.getElementById('card-errors');
            if (error) {
                displayError.textContent = error.message;
            } else {
                displayError.textContent = '';
            }
        });

        // Alpine.js checkout component
        document.addEventListener('alpine:init', () => {
            Alpine.data('customCheckout', () => ({
                customerEmail: '',
                customerName: '',
                isProcessing: false,
                errorMessage: '',

                async submitPayment() {
                    if (this.isProcessing) return;
                    
                    this.isProcessing = true;
                    this.errorMessage = '';

                    try {
                        // Create payment method
                        const {error, paymentMethod} = await stripe.createPaymentMethod({
                            type: 'card',
                            card: cardElement,
                            billing_details: {
                                name: this.customerName,
                                email: this.customerEmail,
                            },
                        });

                        if (error) {
                            this.errorMessage = error.message;
                            this.isProcessing = false;
                            return;
                        }

                        // Create payment intent
                        const response = await fetch('/server/create-payment-intent.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                photo_id: <?php echo $photoId; ?>,
                                size_id: <?php echo $sizeId; ?>,
                                customer_email: this.customerEmail,
                                customer_name: this.customerName,
                                payment_method_id: paymentMethod.id,
                                csrf_token: '<?php echo generateCSRFToken(); ?>'
                            }),
                        });

                        const paymentData = await response.json();

                        if (!paymentData.success) {
                            this.errorMessage = paymentData.error || 'Payment failed';
                            this.isProcessing = false;
                            return;
                        }

                        // Confirm payment
                        const {error: confirmError} = await stripe.confirmCardPayment(
                            paymentData.client_secret
                        );

                        if (confirmError) {
                            this.errorMessage = confirmError.message;
                            this.isProcessing = false;
                            return;
                        }

                        // Success! Redirect to success page
                        window.location.href = '/?order=success';

                    } catch (error) {
                        console.error('Payment error:', error);
                        this.errorMessage = 'An unexpected error occurred. Please try again.';
                        this.isProcessing = false;
                    }
                }
            }));
        });
    </script>
</body>
</html>
