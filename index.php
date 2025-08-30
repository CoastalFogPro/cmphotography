<?php
require_once __DIR__ . '/includes/db.php';

// Get all data for the page
$db = getDB();

// Get settings
$settings = [];
$settingsQuery = $db->query("SELECT setting_key, setting_value FROM settings");
while ($row = $settingsQuery->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get gallery images (exclude hero and about images by filename and title)
$excludeFiles = [];
if (!empty($settings['hero_image'])) {
    $excludeFiles[] = $settings['hero_image'];
}
if (!empty($settings['about_image'])) {
    $excludeFiles[] = $settings['about_image'];
}

// Build exclusion query - exclude hero/about images and images titled "Hero Image" or "About Image"
if (!empty($excludeFiles)) {
    $placeholders = str_repeat('?,', count($excludeFiles) - 1) . '?';
    $galleryImages = $db->prepare("
        SELECT * FROM images 
        WHERE filename NOT IN ($placeholders) 
        AND (title IS NULL OR (title != 'Hero Image' AND title != 'About Image' AND title != 'About Me' AND title != 'About Photo'))
        ORDER BY sort_order ASC, created_at DESC 
        LIMIT 12
    ");
    $galleryImages->execute($excludeFiles);
    $galleryImages = $galleryImages->fetchAll();
} else {
    $galleryImages = $db->query("
        SELECT * FROM images 
        WHERE (title IS NULL OR (title != 'Hero Image' AND title != 'About Image' AND title != 'About Me' AND title != 'About Photo'))
        ORDER BY sort_order ASC, created_at DESC 
        LIMIT 12
    ")->fetchAll();
}

// Get featured products
$featuredProducts = $db->query("SELECT p.*, i.filename as image_filename FROM products p LEFT JOIN images i ON p.image_id = i.id WHERE p.is_active = 1 ORDER BY p.sort_order ASC, p.created_at DESC LIMIT 6")->fetchAll();

// Get services
$services = $db->query("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();

// Get active galleries for navigation
$galleries = getActiveGalleries();

// Get featured galleries for homepage
$featuredGalleries = $db->query("
    SELECT g.*, COUNT(p.id) as photo_count
    FROM galleries g 
    LEFT JOIN photos p ON g.id = p.gallery_id 
    WHERE g.status = 'active' 
    GROUP BY g.id 
    ORDER BY g.sort_order ASC, g.created_at DESC 
    LIMIT 3
")->fetchAll();

// Hero image URL
$heroImage = !empty($settings['hero_image']) ? '/assets/uploads/full/' . $settings['hero_image'] : '';
$aboutImage = !empty($settings['about_image']) ? '/assets/uploads/full/' . $settings['about_image'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeInput($settings['site_title'] ?? 'Photography Portfolio'); ?></title>
    <meta name="description" content="<?php echo sanitizeInput($settings['site_tagline'] ?? 'Professional photography services'); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-serif { font-family: 'Playfair Display', serif; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 1s ease-out; }
        .animate-delay-200 { animation-delay: 0.2s; }
        .animate-delay-400 { animation-delay: 0.4s; }
        
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
        
        .hover\:bg-earth-brown:hover { background-color: #7A4D33; }
        .hover\:bg-caramel:hover { background-color: #C99A66; }
        .hover\:bg-sage:hover { background-color: #A8B5A1; }
        .hover\:bg-dusty-blue:hover { background-color: #8AA3B4; }
        
        /* Force hide any modal overlays on page load */
        .fixed.inset-0[x-show] {
            display: none !important;
        }
        
        /* Ensure body doesn't have overflow hidden */
        body {
            overflow: auto !important;
        }
    </style>
</head>
<body class="antialiased">
    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white/95 backdrop-blur-sm shadow-sm" x-data="{ isOpen: false, galleriesOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0">
                    <h1 class="text-2xl font-serif font-bold text-earth-brown">
                        <?php echo sanitizeInput($settings['site_title'] ?? 'Photography'); ?>
                    </h1>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <a href="#home" class="text-earth-brown hover:text-caramel transition duration-200 font-medium">Home</a>
                        
                        <!-- Galleries Dropdown -->
                        <?php if (!empty($galleries)): ?>
                            <div class="relative" @mouseleave="galleriesOpen = false">
                                <button @mouseenter="galleriesOpen = true" 
                                        class="text-earth-brown hover:text-caramel transition duration-200 flex items-center font-medium">
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
                                     class="absolute left-0 mt-2 w-56 bg-white rounded-md shadow-lg border border-sage ring-1 ring-sage ring-opacity-20">
                                    <div class="py-2">
                                        <?php foreach ($galleries as $gallery): ?>
                                            <a href="/gallery.php?slug=<?php echo urlencode($gallery['slug']); ?>" 
                                               class="block px-4 py-2 text-sm text-earth-brown hover:bg-light-sage hover:text-caramel transition duration-200">
                                                <?php echo sanitizeInput($gallery['title']); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <a href="#gallery" class="text-earth-brown hover:text-caramel transition duration-200 font-medium">Gallery</a>
                        <a href="#prints" class="text-earth-brown hover:text-caramel transition duration-200 font-medium">Prints</a>
                        <a href="#about" class="text-earth-brown hover:text-caramel transition duration-200 font-medium">About</a>
                        <a href="#services" class="text-earth-brown hover:text-caramel transition duration-200 font-medium">Services</a>
                        <a href="#contact" class="text-earth-brown hover:text-caramel transition duration-200 font-medium">Contact</a>
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
                    <a href="#home" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">Home</a>
                    
                    <?php if (!empty($galleries)): ?>
                        <div class="space-y-1">
                            <div class="px-3 py-2 text-gray-700 font-medium">Galleries</div>
                            <?php foreach ($galleries as $gallery): ?>
                                <a href="/gallery.php?slug=<?php echo urlencode($gallery['slug']); ?>" 
                                   class="block px-6 py-2 text-sm text-gray-600 hover:text-blue-600" 
                                   @click="isOpen = false">
                                    <?php echo sanitizeInput($gallery['title']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="#gallery" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">Gallery</a>
                    <a href="#prints" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">Prints</a>
                    <a href="#about" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">About</a>
                    <a href="#services" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">Services</a>
                    <a href="#contact" class="block px-3 py-2 text-gray-900 hover:text-blue-600" @click="isOpen = false">Contact</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="relative h-screen overflow-hidden">
        <?php if ($heroImage): ?>
            <div class="absolute inset-0">
                <img src="<?php echo $heroImage; ?>" alt="Hero background" class="w-full h-full object-cover">
            </div>
        <?php else: ?>
            <div class="absolute inset-0 bg-custom-blue"></div>
        <?php endif; ?>
    </section>

    <!-- Featured Galleries Section -->
    <?php if (!empty($featuredGalleries)): ?>
    <section class="py-20 bg-light-sage">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-serif font-bold text-earth-brown mb-4">Featured Collections</h2>
                <p class="text-xl text-gray-700">Explore our curated photo galleries</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featuredGalleries as $gallery): ?>
                    <div class="group cursor-pointer" onclick="window.location.href='/gallery.php?slug=<?php echo urlencode($gallery['slug']); ?>'">
                        <div class="relative overflow-hidden rounded-lg shadow-lg mb-4">
                            <?php if ($gallery['cover_image']): ?>
                                <div class="aspect-[4/3]">
                                    <img src="/assets/uploads/thumbs/<?php echo $gallery['cover_image']; ?>" 
                                         alt="<?php echo sanitizeInput($gallery['title']); ?>"
                                         class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                </div>
                            <?php else: ?>
                                <div class="aspect-[4/3] bg-gray-200 flex items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Gallery overlay -->
                            <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition duration-300 flex items-center justify-center">
                                <div class="text-white text-center">
                                    <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                                        <p class="text-sm font-medium">View Collection</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <h3 class="text-xl font-serif font-semibold text-earth-brown mb-2 group-hover:text-caramel transition duration-200">
                                <?php echo sanitizeInput($gallery['title']); ?>
                            </h3>
                            <?php if ($gallery['description']): ?>
                                <p class="text-gray-600 mb-2"><?php echo sanitizeInput($gallery['description']); ?></p>
                            <?php endif; ?>
                            <p class="text-sm text-gray-500">
                                <?php echo $gallery['photo_count']; ?> 
                                <?php echo $gallery['photo_count'] == 1 ? 'photo' : 'photos'; ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- View All Galleries Button -->
            <?php if (count($featuredGalleries) > 0): ?>
                <div class="text-center mt-12">
                    <a href="#galleries" class="inline-block bg-earth-brown text-white px-8 py-3 rounded-lg hover:bg-caramel transition duration-200 shadow-lg">
                        View All Collections
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Featured Prints Section -->
    <section id="prints" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-serif font-bold text-earth-brown mb-4">Featured Prints</h2>
                <p class="text-xl text-gray-600">High-quality prints available for purchase</p>
            </div>
            
            <?php if (!empty($featuredProducts)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($featuredProducts as $product): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                            <?php if ($product['image_filename']): ?>
                                <div class="aspect-[4/3] overflow-hidden">
                                    <img src="/assets/uploads/thumbs/<?php echo $product['image_filename']; ?>" 
                                         alt="<?php echo sanitizeInput($product['title']); ?>"
                                         class="w-full h-full object-cover hover:scale-105 transition duration-300">
                                </div>
                            <?php endif; ?>
                            <div class="p-6">
                                <h3 class="text-xl font-serif font-semibold text-gray-900 mb-2">
                                    <?php echo sanitizeInput($product['title']); ?>
                                </h3>
                                <?php if ($product['description']): ?>
                                    <p class="text-gray-600 mb-4"><?php echo sanitizeInput($product['description']); ?></p>
                                <?php endif; ?>
                                <div class="flex justify-between items-center">
                                    <div>
                                        <?php if ($product['size']): ?>
                                            <p class="text-sm text-gray-500"><?php echo sanitizeInput($product['size']); ?></p>
                                        <?php endif; ?>
                                        <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($product['price'], 2); ?></p>
                                    </div>
                                    <?php if ($product['stripe_link']): ?>
                                        <a href="<?php echo sanitizeInput($product['stripe_link']); ?>" 
                                           target="_blank" rel="noopener"
                                           class="bg-caramel text-white px-6 py-2 rounded-lg hover:bg-earth-brown transition duration-200 shadow-md">
                                            Purchase
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <p class="text-gray-600">Featured prints coming soon...</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-20 bg-custom-light-blue">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-serif font-bold text-earth-brown mb-4">Services</h2>
                <p class="text-xl text-gray-600">Professional photography services tailored to your needs</p>
            </div>
            
            <?php if (!empty($services)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <?php foreach ($services as $service): ?>
                        <div class="bg-white p-8 rounded-lg shadow-md text-center hover:shadow-lg transition duration-300">
                            <?php 
                            $iconName = $service['icon'] ?: 'camera';
                            $iconPaths = [
                                'camera' => 'M3 9a2 2 0 012-2h.93l.82-1.23A2 2 0 018.86 4h6.28a2 2 0 011.11.77L17.07 7H18a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V9z M15 13a3 3 0 11-6 0 3 3 0 016 0z',
                                'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                                'mountain' => 'M5 3l4 6H3m0 4l4 6H1m8-12l4 6h-6m0 4l4 6h-6m8-12l4 6h-6m0 4l4 6h-6',
                                'briefcase' => 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'
                            ];
                            $iconPath = $iconPaths[$iconName] ?? $iconPaths['camera'];
                            ?>
                            <div class="w-16 h-16 mx-auto mb-4 text-dusty-blue">
                                <svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $iconPath; ?>"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-earth-brown mb-4">
                                <?php echo sanitizeInput($service['title']); ?>
                            </h3>
                            <p class="text-gray-600">
                                <?php echo sanitizeInput($service['description']); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="order-2 lg:order-1">
                    <h2 class="text-4xl font-serif font-bold text-gray-900 mb-4">About Me</h2>
                    <blockquote class="text-xl italic text-earth-brown mb-8 border-l-4 border-caramel pl-4">
                        "All the world's a stage, and I'm here to preserve its story, one frame at a time."
                    </blockquote>
                    <div class="prose prose-lg text-gray-600">
                        <p class="mb-4">Cris Mitchell has been an artist for as long as he can remember. A musician, recording artist, and graphic designer, his creative eye naturally flows into photography, where he specializes in fine art work that celebrates both the grandeur of nature and the quiet rhythms of everyday life.</p>
                        
                        <p class="mb-4">Based in Pismo Beach, California, Cris draws inspiration from the dramatic beauty of the Central Coast and nearby icons like Big Sur, Yosemite, and the Grand Canyon. Sedona, Arizona remains one of his favorite destinations—"mystical and serene," he says—where he strives to capture not just the view but the spirit of the place.</p>
                        
                        <p class="mb-4">"The challenge of photography," Cris explains, "isn't simply documenting what's in front of you, but translating its energy so others can feel it too." Whether framing a sweeping canyon at sunset or a fleeting moment of daily life, his work reflects that pursuit.</p>
                        
                        <p>Beyond his own artistry, Cris is a respected educator and former publisher of ProPhotoResource.com, a platform that connected him with some of the most influential photographic voices of our time. While he embraces new tools and techniques, his vision remains rooted in a personal style, deep passion, and reverence for the world around us.</p>
                    </div>
                </div>
                
                <div class="order-1 lg:order-2">
                    <?php if ($aboutImage): ?>
                        <div class="aspect-[3/4] overflow-hidden rounded-lg shadow-lg">
                            <img src="<?php echo $aboutImage; ?>" alt="About photo" 
                                 class="w-full h-full object-cover">
                        </div>
                    <?php else: ?>
                        <div class="aspect-[3/4] bg-gray-200 rounded-lg shadow-lg flex items-center justify-center">
                            <svg class="w-24 h-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section id="gallery" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-serif font-bold text-gray-900 mb-4">Gallery</h2>
                <p class="text-xl text-gray-600">A curated collection of my recent work</p>
            </div>
            
            <?php if (!empty($galleryImages)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" x-data="gallery">
                    <?php foreach ($galleryImages as $index => $image): ?>
                        <div class="aspect-square overflow-hidden rounded-lg cursor-pointer group"
                             @click="openModal('<?php echo $image['filename']; ?>', '<?php echo sanitizeInput($image['title'] ?: 'Untitled'); ?>', '<?php echo sanitizeInput($image['alt_text']); ?>')">
                            <img src="/assets/uploads/thumbs/<?php echo $image['filename']; ?>" 
                                 alt="<?php echo sanitizeInput($image['alt_text']); ?>"
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Image Modal -->
                <div x-show="showModal" x-cloak x-transition 
                     class="fixed inset-0 bg-black/90 flex items-center justify-center z-50 p-4"
                     @click="closeModal()" @keydown.escape="closeModal()">
                    <div class="max-w-5xl max-h-full relative" @click.stop>
                        <img :src="'/assets/uploads/full/' + currentImage" :alt="currentAlt" 
                             class="max-w-full max-h-full object-contain">
                        <button @click="closeModal()" 
                                class="absolute top-4 right-4 text-white hover:text-gray-300 text-2xl">
                            ×
                        </button>
                        <div x-show="currentTitle" class="absolute bottom-4 left-4 text-white">
                            <h3 class="text-lg font-medium" x-text="currentTitle"></h3>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <p class="text-gray-600">Gallery coming soon...</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-light-sage">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-serif font-bold text-earth-brown mb-4">Get in Touch</h2>
                <p class="text-xl text-gray-600">Ready to capture your special moments? Let's talk!</p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Contact Info -->
                <div class="space-y-8">
                    <div>
                        <h3 class="text-2xl font-serif font-semibold text-earth-brown mb-6">Contact Information</h3>
                        <div class="space-y-4">
                            <?php if (!empty($settings['contact_email'])): ?>
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-dusty-blue mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-gray-700"><?php echo sanitizeInput($settings['contact_email']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($settings['phone'])): ?>
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-dusty-blue mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    <span class="text-gray-700"><?php echo sanitizeInput($settings['phone']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($settings['address'])): ?>
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-dusty-blue mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <span class="text-gray-700"><?php echo sanitizeInput($settings['address']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="bg-white p-8 rounded-lg border border-sage">
                    <h3 class="text-2xl font-serif font-semibold text-earth-brown mb-6">Send a Message</h3>
                    
                    <form x-data="contactForm" @submit.prevent="submitForm" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-earth-brown mb-2">Name *</label>
                                <input type="text" x-model="form.name" id="name" required
                                       class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-caramel">
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-earth-brown mb-2">Email *</label>
                                <input type="email" x-model="form.email" id="email" required
                                       class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-caramel">
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-earth-brown mb-2">Phone</label>
                                <input type="tel" x-model="form.phone" id="phone"
                                       class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-caramel">
                            </div>
                            
                            <div>
                                <label for="subject" class="block text-sm font-medium text-earth-brown mb-2">Subject</label>
                                <input type="text" x-model="form.subject" id="subject"
                                       class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-caramel">
                            </div>
                        </div>
                        
                        <div>
                            <label for="message" class="block text-sm font-medium text-earth-brown mb-2">Message *</label>
                            <textarea x-model="form.message" id="message" rows="6" required
                                      class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-caramel"></textarea>
                        </div>
                        
                        <div x-show="message" class="p-4 rounded-lg" :class="isSuccess ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
                            <p x-text="message"></p>
                        </div>
                        
                        <button type="submit" :disabled="isSubmitting" 
                                class="w-full bg-caramel text-white py-3 px-6 rounded-lg hover:bg-earth-brown disabled:opacity-50 transition duration-200"
                                :class="{ 'cursor-not-allowed': isSubmitting }">
                            <span x-show="!isSubmitting">Send Message</span>
                            <span x-show="isSubmitting">Sending...</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-custom-blue text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h3 class="text-2xl font-serif font-bold mb-4">
                    <?php echo sanitizeInput($settings['site_title'] ?? 'Photography Portfolio'); ?>
                </h3>
                <p class="text-gray-400 mb-6">
                    <?php echo sanitizeInput($settings['site_tagline'] ?? 'Capturing moments, creating memories'); ?>
                </p>
                
                <p class="text-gray-500 text-sm">
                    © <?php echo date('Y'); ?> <?php echo sanitizeInput($settings['site_title'] ?? 'Photography Portfolio'); ?>. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Gallery functionality
        document.addEventListener('alpine:init', () => {
            Alpine.data('gallery', () => ({
                showModal: false,
                currentImage: '',
                currentTitle: '',
                currentAlt: '',
                
                openModal(image, title, alt) {
                    this.currentImage = image;
                    this.currentTitle = title;
                    this.currentAlt = alt;
                    this.showModal = true;
                    document.body.style.overflow = 'hidden';
                },
                
                closeModal() {
                    this.showModal = false;
                    document.body.style.overflow = 'auto';
                }
            }));
            
            // Contact form functionality
            Alpine.data('contactForm', () => ({
                form: {
                    name: '',
                    email: '',
                    phone: '',
                    subject: '',
                    message: ''
                },
                message: '',
                isSuccess: false,
                isSubmitting: false,
                
                async submitForm() {
                    this.isSubmitting = true;
                    this.message = '';
                    
                    const formData = new FormData();
                    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                    for (const [key, value] of Object.entries(this.form)) {
                        formData.append(key, value);
                    }
                    
                    try {
                        const response = await fetch('/server/contact.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.isSuccess = true;
                            this.message = data.message;
                            this.form = { name: '', email: '', phone: '', subject: '', message: '' };
                        } else {
                            this.isSuccess = false;
                            this.message = data.error || 'An error occurred. Please try again.';
                        }
                    } catch (error) {
                        this.isSuccess = false;
                        this.message = 'Network error. Please try again.';
                    }
                    
                    this.isSubmitting = false;
                }
            }));
        });

        // Force clear any overlay issues on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure body overflow is auto
            document.body.style.overflow = 'auto';
            document.documentElement.style.overflow = 'auto';
            
            // Hide any potential modal overlays
            const overlays = document.querySelectorAll('.fixed.inset-0');
            overlays.forEach(overlay => {
                if (overlay.hasAttribute('x-show')) {
                    overlay.style.display = 'none';
                }
            });
        });

        // Smooth scrolling for navigation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
