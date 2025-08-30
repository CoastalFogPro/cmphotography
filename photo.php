<?php
/**
 * Individual Photo Detail View
 * Display photo with size options and purchase functionality
 */
require_once __DIR__ . '/includes/db.php';

// Get photo ID from URL
$photoId = (int)($_GET['id'] ?? 0);
if ($photoId <= 0) {
    redirect('/');
}

// Get photo data
$photo = getPhoto($photoId);
if (!$photo) {
    http_response_code(404);
    redirect('/');
}

// Get available sizes for this photo
$sizes = getPhotoSizes($photoId);

// Get site settings for nav
$siteTitle = getSetting('site_title', 'Photography Portfolio');
$galleries = getActiveGalleries();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeInput($photo['title'] ?: 'Photo'); ?> - <?php echo sanitizeInput($siteTitle); ?></title>
    <meta name="description" content="<?php echo sanitizeInput($photo['alt'] ?: $photo['title'] ?: 'Photography print available for purchase'); ?>">
    
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
    </style>
</head>
<body class="antialiased">
    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white/95 backdrop-blur-sm shadow-sm" x-data="{ isOpen: false, galleriesOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0">
                    <a href="/" class="text-2xl font-serif font-bold text-gray-900">
                        <?php echo sanitizeInput($siteTitle); ?>
                    </a>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <a href="/" class="text-gray-900 hover:text-blue-600 transition duration-200">Home</a>
                        
                        <!-- Galleries Dropdown -->
                        <div class="relative" @mouseleave="galleriesOpen = false">
                            <button @mouseenter="galleriesOpen = true" 
                                    class="text-gray-900 hover:text-blue-600 transition duration-200 flex items-center">
                                Galleries
                                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="galleriesOpen" x-cloak
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute left-0 mt-2 w-56 bg-white rounded-md shadow-lg border ring-1 ring-black ring-opacity-5">
                                <div class="py-2">
                                    <?php foreach ($galleries as $g): ?>
                                        <a href="/gallery.php?slug=<?php echo urlencode($g['slug']); ?>" 
                                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <?php echo sanitizeInput($g['title']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <a href="/#prints" class="text-gray-900 hover:text-blue-600 transition duration-200">Prints</a>
                        <a href="/#about" class="text-gray-900 hover:text-blue-600 transition duration-200">About</a>
                        <a href="/#services" class="text-gray-900 hover:text-blue-600 transition duration-200">Services</a>
                        <a href="/#contact" class="text-gray-900 hover:text-blue-600 transition duration-200">Contact</a>
                    </div>
                </div>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button @click="isOpen = !isOpen" class="text-gray-900 hover:text-blue-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path x-show="!isOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path x-show="isOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Mobile menu -->
            <div x-show="isOpen" x-cloak x-transition class="md:hidden">
                <div class="px-2 pt-2 pb-3 space-y-1 bg-white border-t">
                    <a href="/" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">Home</a>
                    
                    <div class="space-y-1">
                        <div class="px-3 py-2 text-gray-700 font-medium">Galleries</div>
                        <?php foreach ($galleries as $g): ?>
                            <a href="/gallery.php?slug=<?php echo urlencode($g['slug']); ?>" 
                               class="block px-6 py-2 text-sm text-gray-600 hover:text-blue-600" 
                               @click="isOpen = false">
                                <?php echo sanitizeInput($g['title']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <a href="/#prints" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">Prints</a>
                    <a href="/#about" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">About</a>
                    <a href="/#services" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">Services</a>
                    <a href="/#contact" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">Contact</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <!-- Breadcrumb -->
            <nav class="text-sm text-gray-500 mb-8">
                <a href="/" class="hover:text-gray-700">Home</a>
                <span class="mx-2">/</span>
                <a href="/gallery.php?slug=<?php echo urlencode($photo['gallery_slug']); ?>" class="hover:text-gray-700">
                    <?php echo sanitizeInput($photo['gallery_title']); ?>
                </a>
                <span class="mx-2">/</span>
                <span class="text-gray-900"><?php echo sanitizeInput($photo['title'] ?: 'Photo'); ?></span>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Photo Display -->
                <div class="space-y-6">
                    <div class="aspect-square lg:aspect-auto lg:max-h-[80vh] overflow-hidden rounded-lg bg-gray-100">
                        <img src="/assets/uploads/full/<?php echo basename($photo['full_path']); ?>" 
                             alt="<?php echo sanitizeInput($photo['alt'] ?: $photo['title'] ?: 'Photo'); ?>"
                             class="w-full h-full object-contain">
                    </div>
                </div>

                <!-- Photo Info & Purchase -->
                <div class="space-y-8" x-data="photoPurchase">
                    <div>
                        <h1 class="text-3xl font-serif font-bold text-gray-900 mb-4">
                            <?php echo sanitizeInput($photo['title'] ?: 'Untitled Photo'); ?>
                        </h1>
                        
                        <div class="text-sm text-gray-500 mb-6">
                            From the <a href="/gallery.php?slug=<?php echo urlencode($photo['gallery_slug']); ?>" 
                                       class="text-blue-600 hover:text-blue-700"><?php echo sanitizeInput($photo['gallery_title']); ?></a> gallery
                        </div>
                    </div>

                    <?php if (!empty($sizes)): ?>
                        <!-- Size Selection -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Available Sizes</h3>
                            
                            <div class="space-y-3">
                                <?php foreach ($sizes as $size): ?>
                                    <div class="border rounded-lg p-4 cursor-pointer transition-colors hover:border-blue-300"
                                         :class="selectedSize === <?php echo $size['id']; ?> ? 'border-blue-500 bg-blue-50' : 'border-gray-200'"
                                         @click="selectSize(<?php echo $size['id']; ?>, '<?php echo sanitizeInput($size['size_label']); ?>', <?php echo $size['price_cents']; ?>)">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    <?php echo sanitizeInput($size['size_label']); ?>
                                                </div>
                                                <?php if ($size['description']): ?>
                                                    <div class="text-sm text-gray-600 mt-1">
                                                        <?php echo sanitizeInput($size['description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-lg font-semibold text-gray-900">
                                                <?php echo formatPrice($size['price_cents']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="flex justify-center mt-3">
                                            <div class="w-4 h-4 border-2 rounded-full transition-colors"
                                                 :class="selectedSize === <?php echo $size['id']; ?> ? 'border-blue-500 bg-blue-500' : 'border-gray-300'">
                                                <div x-show="selectedSize === <?php echo $size['id']; ?>" 
                                                     class="w-2 h-2 bg-white rounded-full mx-auto mt-0.5"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Purchase Button -->
                        <div class="space-y-4">
                            <button @click="purchasePhoto" 
                                    :disabled="!selectedSize || isProcessing"
                                    :class="selectedSize && !isProcessing ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-300 cursor-not-allowed'"
                                    class="w-full py-4 px-6 text-white font-medium rounded-lg transition duration-200">
                                <span x-show="!isProcessing">
                                    <span x-show="!selectedSize">Select a Size to Purchase</span>
                                    <span x-show="selectedSize" x-text="`Buy ${selectedSizeLabel} - ${formatPrice(selectedPrice)}`"></span>
                                </span>
                                <span x-show="isProcessing">Processing...</span>
                            </button>
                            
                            <div x-show="errorMessage" class="text-red-600 text-sm text-center" x-text="errorMessage"></div>
                        </div>

                        <!-- Size Guide -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h4 class="font-medium text-gray-900 mb-3">Size Guide</h4>
                            <div class="space-y-2 text-sm text-gray-600">
                                <p>• All prints are professionally printed on premium paper</p>
                                <p>• Colors may vary slightly from what you see on screen</p>
                                <p>• Shipping is calculated at checkout</p>
                                <p>• Frame not included unless specified</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- No Sizes Available -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Coming Soon</h3>
                            <p class="text-gray-600">Print options for this photo will be available soon. Check back later!</p>
                        </div>
                    <?php endif; ?>

                    <!-- Back to Gallery -->
                    <div class="border-t pt-6">
                        <a href="/gallery.php?slug=<?php echo urlencode($photo['gallery_slug']); ?>" 
                           class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Back to <?php echo sanitizeInput($photo['gallery_title']); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('photoPurchase', () => ({
                selectedSize: null,
                selectedSizeLabel: '',
                selectedPrice: 0,
                isProcessing: false,
                errorMessage: '',

                selectSize(sizeId, label, price) {
                    this.selectedSize = sizeId;
                    this.selectedSizeLabel = label;
                    this.selectedPrice = price;
                    this.errorMessage = '';
                },

                formatPrice(cents) {
                    return '$' + (cents / 100).toFixed(2);
                },

                async purchasePhoto() {
                    if (!this.selectedSize || this.isProcessing) {
                        return;
                    }

                    this.isProcessing = true;
                    this.errorMessage = '';

                    try {
                        const formData = new FormData();
                        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                        formData.append('photo_id', '<?php echo $photoId; ?>');
                        formData.append('size_id', this.selectedSize);

                        const response = await fetch('/server/checkout.php', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success && data.url) {
                            // Redirect to Stripe Checkout
                            window.location.href = data.url;
                        } else {
                            this.errorMessage = data.error || 'Failed to create checkout session';
                        }
                    } catch (error) {
                        console.error('Checkout error:', error);
                        this.errorMessage = 'Network error. Please try again.';
                    }

                    this.isProcessing = false;
                }
            }));
        });
    </script>
</body>
</html>
