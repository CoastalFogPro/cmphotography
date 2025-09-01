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
    <meta name="description" content="<?php echo sanitizeInput((isset($photo['alt_text']) ? $photo['alt_text'] : '') ?: $photo['title'] ?: 'Photography print available for purchase'); ?>">
    
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
        
        /* Custom Earth-tone Color Palette */
        .bg-earth-brown { background-color: #8B5A3C; }
        .bg-caramel { background-color: #D4A574; }
        .bg-sage { background-color: #B8C5B1; }
        .bg-dusty-blue { background-color: #9BB3C4; }
        .bg-light-sage { background-color: #E8EDE6; }
        .bg-custom-blue { background-color: #8c9fa5; }
        .bg-custom-light-blue { background-color: #c5d4d9; }
        
        .text-earth-brown { color: #8B5A3C; }
        .text-caramel { color: #D4A574; }
        .text-sage { color: #B8C5B1; }
        .text-dusty-blue { color: #9BB3C4; }
        
        .border-earth-brown { border-color: #8B5A3C; }
        .border-caramel { border-color: #D4A574; }
        .border-sage { border-color: #B8C5B1; }
        .border-dusty-blue { border-color: #9BB3C4; }
        .border-custom-blue { border-color: #8c9fa5; }
        
        .hover\:bg-earth-brown:hover { background-color: #7A4D33; }
        .hover\:bg-caramel:hover { background-color: #C99A66; }
        .hover\:bg-sage:hover { background-color: #A8B5A1; }
        .hover\:bg-dusty-blue:hover { background-color: #8AA3B4; }
        
        .hover\:text-earth-brown:hover { color: #8B5A3C; }
        .hover\:text-caramel:hover { color: #D4A574; }
        .hover\:text-custom-blue:hover { color: #8c9fa5; }
    </style>
</head>
<body class="antialiased">
    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white/95 backdrop-blur-sm shadow-sm" x-data="{ isOpen: false, galleriesOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0">
                    <a href="/" class="text-2xl font-serif font-bold text-earth-brown">
                        <?php echo sanitizeInput($siteTitle); ?>
                    </a>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <a href="/" class="text-earth-brown hover:text-custom-blue transition duration-200 font-medium">Home</a>
                        <a href="/#featured-collections" class="text-earth-brown hover:text-custom-blue transition duration-200 font-medium">Featured Collections</a>
                        <a href="/#about" class="text-earth-brown hover:text-custom-blue transition duration-200 font-medium">About</a>
                        <a href="/#gallery" class="text-earth-brown hover:text-custom-blue transition duration-200 font-medium">Recent Work</a>
                        
                        <!-- Galleries Dropdown -->
                        <div class="relative" @click.away="galleriesOpen = false">
                            <button @click="galleriesOpen = !galleriesOpen" 
                                    class="text-earth-brown hover:text-custom-blue transition duration-200 flex items-center font-medium">
                                Galleries
                                <svg class="ml-1 w-4 h-4 transform transition-transform" :class="{ 'rotate-180': galleriesOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                 class="absolute left-0 mt-2 w-56 bg-white rounded-md shadow-lg border border-sage ring-1 ring-sage ring-opacity-20 z-50">
                                <div class="py-2">
                                    <?php foreach ($galleries as $g): ?>
                                        <a href="/gallery.php?slug=<?php echo urlencode($g['slug']); ?>" 
                                           class="block px-4 py-2 text-sm text-earth-brown hover:bg-light-sage hover:text-caramel transition duration-200">
                                            <?php echo sanitizeInput($g['title']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <a href="/#contact" class="text-earth-brown hover:text-custom-blue transition duration-200 font-medium">Contact</a>
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
                    <div class="w-full bg-gray-100 rounded-lg p-2" style="min-height: 300px;">
                        <img src="/assets/uploads/full/<?php echo $photo['filename']; ?>" 
                             alt="<?php echo sanitizeInput((isset($photo['alt_text']) ? $photo['alt_text'] : '') ?: $photo['title'] ?: 'Photo'); ?>"
                             class="w-full h-auto max-w-full rounded-lg shadow-sm"
                             style="display: block; margin: 0 auto; max-height: 80vh; object-fit: contain;"
                             loading="eager"
                             onerror="console.error('Image failed to load:', this.src); this.parentElement.innerHTML='<div class=\'text-center p-8\'><p class=\'text-gray-500\'>Image could not be loaded</p></div>';">
                    </div>
                </div>

                <!-- Photo Info & Purchase -->
                <div class="space-y-8" x-data="photoPurchase">
                    <div>
                        <h1 class="text-3xl font-serif font-bold text-gray-900 mb-4">
                            <?php 
                            // Show the custom title if set and it's not a filename, otherwise show alt_text or 'Untitled Photo'
                            $displayTitle = !empty($photo['title']) && !preg_match('/^[A-Z0-9]+-[A-Za-z0-9-]+$/', $photo['title']) 
                                ? $photo['title'] 
                                : (isset($photo['alt_text']) && !empty($photo['alt_text']) ? $photo['alt_text'] : 'Untitled Photo');
                            echo sanitizeInput($displayTitle);
                            ?>
                        </h1>
                        
                        <!-- Photo Description -->
                        <?php if (isset($photo['description']) && !empty(trim($photo['description']))): ?>
                            <div class="prose text-gray-700 mb-6">
                                <?php echo nl2br(sanitizeInput($photo['description'])); ?>
                            </div>
                        <?php endif; ?>
                        
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
                                    <div class="border rounded-lg p-4 cursor-pointer transition-colors hover:border-custom-blue"
                                         :class="selectedSize === <?php echo $size['id']; ?> ? 'border-custom-blue bg-custom-light-blue' : 'border-gray-200'"
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
                                                 :class="selectedSize === <?php echo $size['id']; ?> ? 'border-custom-blue bg-custom-blue' : 'border-gray-300'">
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
                                    :class="selectedSize && !isProcessing ? 'bg-custom-blue hover:bg-earth-brown' : 'bg-gray-300 cursor-not-allowed'"
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

    <!-- Contact Section -->
    <section class="py-16 bg-light-sage">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-serif font-bold text-earth-brown mb-4">Get in Touch</h2>
                <p class="text-lg text-gray-600">Comments, Questions or Order Information</p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Contact Info -->
                <div class="bg-white p-6 rounded-lg border border-sage">
                    <h3 class="text-xl font-serif font-semibold text-earth-brown mb-6">Contact Information</h3>
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-dusty-blue mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-earth-brown">Email</p>
                                <a href="mailto:cris@crismitchellphotography.com" class="text-gray-700 hover:text-caramel transition duration-200">
                                    cris@crismitchellphotography.com
                                </a>
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-dusty-blue mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-earth-brown">Phone</p>
                                <a href="tel:8054411187" class="text-gray-700 hover:text-caramel transition duration-200">
                                    805.441.1187
                                </a>
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-dusty-blue mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-earth-brown">Location</p>
                                <span class="text-gray-700">PO Box 3091<br>Shell Beach, CA 93448</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Social Media -->
                <div class="bg-white p-6 rounded-lg border border-sage">
                    <h3 class="text-xl font-serif font-semibold text-earth-brown mb-6">Follow My Work</h3>
                    
                    <div class="space-y-4">
                        <p class="text-gray-600 mb-4">Stay updated with my latest work and behind-the-scenes content.</p>
                        
                        <!-- Facebook -->
                        <a href="https://www.facebook.com/CrisMitchellPhotography" target="_blank" rel="noopener noreferrer" 
                           class="group flex items-center p-3 border border-sage rounded-lg hover:bg-light-sage transition duration-300">
                            <div class="flex items-center justify-center w-10 h-10 bg-dusty-blue text-white rounded-full group-hover:bg-earth-brown transition duration-300 mr-3">
                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform duration-200" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-earth-brown group-hover:text-caramel transition duration-200">Facebook</p>
                                <p class="text-sm text-gray-600">Cris Mitchell Photography</p>
                            </div>
                        </a>
                        
                        <!-- Instagram -->
                        <a href="https://www.instagram.com/crismitchellphotography/" target="_blank" rel="noopener noreferrer" 
                           class="group flex items-center p-3 border border-sage rounded-lg hover:bg-light-sage transition duration-300">
                            <div class="flex items-center justify-center w-10 h-10 bg-dusty-blue text-white rounded-full group-hover:bg-earth-brown transition duration-300 mr-3">
                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform duration-200" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-earth-brown group-hover:text-caramel transition duration-200">Instagram</p>
                                <p class="text-sm text-gray-600">@crismitchellphotography</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-custom-blue text-white py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h3 class="text-2xl font-serif font-bold mb-4">
                    <?php echo sanitizeInput($siteTitle); ?>
                </h3>
                <p class="text-white mb-6">
                    Fine Art Landscape Photography
                </p>
                
                <p class="text-white text-sm">
                    © 2025 Cris Mitchell Photography. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('photoPurchase', () => ({
                selectedSize: null,
                selectedSizeLabel: '',
                selectedPrice: 0,
                isProcessing: false,
                errorMessage: '',

                selectSize(sizeId, label, price) {
                    console.log('selectSize called:', { sizeId, label, price });
                    this.selectedSize = sizeId;
                    this.selectedSizeLabel = label;
                    this.selectedPrice = price;
                    this.errorMessage = '';
                    console.log('selectedSize set to:', this.selectedSize);
                },

                formatPrice(cents) {
                    return '$' + (cents / 100).toFixed(2);
                },

                async purchasePhoto() {
                    console.log('purchasePhoto called');
                    console.log('selectedSize:', this.selectedSize);
                    console.log('selectedSizeLabel:', this.selectedSizeLabel);
                    console.log('selectedPrice:', this.selectedPrice);
                    
                    if (!this.selectedSize || this.isProcessing) {
                        console.log('Returning early - no size selected or already processing');
                        return;
                    }

                    this.isProcessing = true;
                    this.errorMessage = '';

                    try {
                        const formData = new FormData();
                        formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                        formData.append('photo_id', '<?php echo $photoId; ?>');
                        formData.append('size_id', this.selectedSize);
                        
                        console.log('Sending request with data:', {
                            photo_id: '<?php echo $photoId; ?>',
                            size_id: this.selectedSize
                        });

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
