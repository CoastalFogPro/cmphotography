<?php
/**
 * Individual Gallery View
 * Display photos from a specific gallery with pagination
 */
require_once __DIR__ . '/includes/db.php';

// Get gallery slug from URL
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    redirect('/');
}

// Get gallery data
$gallery = getGalleryBySlug($slug);
if (!$gallery) {
    http_response_code(404);
    redirect('/');
}

// Get pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;

// Get photos for this gallery
$result = getGalleryPhotos($gallery['id'], $page, $perPage);
$photos = $result['photos'];
$totalPages = $result['totalPages'];
$totalPhotos = $result['totalPhotos'];

// Get site settings for nav
$siteTitle = getSetting('site_title', 'Photography Portfolio');
$galleries = getActiveGalleries();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeInput($gallery['title']); ?> - <?php echo sanitizeInput($siteTitle); ?></title>
    <meta name="description" content="<?php echo sanitizeInput($gallery['description'] ?: $gallery['title']); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                                    class="text-blue-600 font-medium hover:text-blue-700 transition duration-200 flex items-center">
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
                                           class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?php echo $g['slug'] === $gallery['slug'] ? 'bg-blue-50 text-blue-700' : ''; ?>">
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
                               class="block px-6 py-2 text-sm text-gray-600 hover:text-blue-600 <?php echo $g['slug'] === $gallery['slug'] ? 'text-blue-600' : ''; ?>" 
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
        <!-- Gallery Header -->
        <section class="bg-white py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <!-- Gallery Info -->
                    <div class="space-y-6">
                        <nav class="text-sm text-gray-500">
                            <a href="/" class="hover:text-gray-700">Home</a>
                            <span class="mx-2">/</span>
                            <span>Galleries</span>
                            <span class="mx-2">/</span>
                            <span class="text-gray-900"><?php echo sanitizeInput($gallery['title']); ?></span>
                        </nav>
                        
                        <h1 class="text-4xl font-serif font-bold text-gray-900">
                            <?php echo sanitizeInput($gallery['title']); ?>
                        </h1>
                        
                        <?php if ($gallery['description']): ?>
                            <div class="prose text-gray-600">
                                <?php echo nl2br(sanitizeInput($gallery['description'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-sm text-gray-500">
                            <?php echo $totalPhotos; ?> photo<?php echo $totalPhotos !== 1 ? 's' : ''; ?>
                        </div>
                    </div>
                    
                    <!-- Cover Image -->
                    <?php if ($gallery['cover_image']): ?>
                        <div class="aspect-[4/3] overflow-hidden rounded-lg shadow-lg">
                            <img src="/assets/uploads/full/<?php echo $gallery['cover_image']; ?>" 
                                 alt="<?php echo sanitizeInput($gallery['title']); ?>"
                                 class="w-full h-full object-cover">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Photos Grid -->
        <section class="py-12 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <?php if (!empty($photos)): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-4 mb-8" 
                         x-data="photoGallery">
                        <?php foreach ($photos as $photo): ?>
                            <div class="aspect-square overflow-hidden rounded-lg cursor-pointer group bg-white shadow-sm hover:shadow-md transition-shadow duration-200"
                                 @click="openPhoto(<?php echo $photo['id']; ?>)">
                                <img src="/assets/uploads/thumbs/<?php echo basename($photo['thumb_path']); ?>" 
                                     alt="<?php echo sanitizeInput($photo['alt'] ?: $photo['title'] ?: 'Photo'); ?>"
                                     class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                     loading="lazy">
                                <?php if ($photo['title']): ?>
                                    <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/70 to-transparent p-3 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                        <p class="text-white text-sm font-medium truncate"><?php echo sanitizeInput($photo['title']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="flex justify-center items-center space-x-4">
                            <?php if ($page > 1): ?>
                                <a href="?slug=<?php echo urlencode($gallery['slug']); ?>&page=<?php echo $page - 1; ?>" 
                                   class="bg-white text-gray-700 px-4 py-2 rounded-lg border hover:bg-gray-50 transition">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <span class="text-gray-600">
                                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                            </span>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?slug=<?php echo urlencode($gallery['slug']); ?>&page=<?php echo $page + 1; ?>" 
                                   class="bg-white text-gray-700 px-4 py-2 rounded-lg border hover:bg-gray-50 transition">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-16">
                        <div class="w-24 h-24 mx-auto mb-4 text-gray-300">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-medium text-gray-700 mb-2">No Photos Yet</h3>
                        <p class="text-gray-500">This gallery is currently empty. Check back soon!</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('photoGallery', () => ({
                openPhoto(photoId) {
                    // Redirect to photo detail page
                    window.location.href = `/photo.php?id=${photoId}`;
                }
            }));
        });
    </script>
</body>
</html>
