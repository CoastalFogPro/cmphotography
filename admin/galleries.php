<?php
/**
 * Admin Gallery Management
 * CRUD operations for galleries including cover image upload
 */
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$db = getDB();
$message = '';
$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        $message = 'Invalid request. Please try again.';
    } else {
        switch ($_POST['form_action']) {
            case 'create':
                $title = sanitizeInput($_POST['title'] ?? '');
                $slug = sanitizeInput($_POST['slug'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Auto-generate slug if empty
                if (empty($slug) && !empty($title)) {
                    $slug = createSlug($title);
                }
                
                if (empty($title) || empty($slug)) {
                    $message = 'Title and slug are required.';
                } else {
                    // Check if slug is unique
                    $stmt = $db->prepare("SELECT id FROM galleries WHERE slug = ?");
                    $stmt->execute([$slug]);
                    if ($stmt->fetch()) {
                        $message = 'Slug already exists. Please choose a different one.';
                    } else {
                        // Handle cover image upload
                        $coverImage = '';
                        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['size'] > 0) {
                            $uploadResult = uploadImage($_FILES['cover_image'], $title, 'gallery-cover');
                            if ($uploadResult['success']) {
                                $coverImage = $uploadResult['filename'];
                            } else {
                                $message = 'Error uploading cover image: ' . $uploadResult['error'];
                                break;
                            }
                        }
                        
                        // Insert gallery
                        $stmt = $db->prepare("INSERT INTO galleries (title, slug, description, cover_image, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                        if ($stmt->execute([$title, $slug, $description, $coverImage, $sortOrder, $isActive])) {
                            $message = 'Gallery created successfully!';
                            $action = 'list';
                        } else {
                            $message = 'Error creating gallery.';
                        }
                    }
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $slug = sanitizeInput($_POST['slug'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id <= 0 || empty($title) || empty($slug)) {
                    $message = 'Invalid data provided.';
                } else {
                    // Check if slug is unique (excluding current gallery)
                    $stmt = $db->prepare("SELECT id FROM galleries WHERE slug = ? AND id != ?");
                    $stmt->execute([$slug, $id]);
                    if ($stmt->fetch()) {
                        $message = 'Slug already exists. Please choose a different one.';
                    } else {
                        // Get current gallery for cover image handling
                        $stmt = $db->prepare("SELECT * FROM galleries WHERE id = ?");
                        $stmt->execute([$id]);
                        $currentGallery = $stmt->fetch();
                        
                        $coverImage = $currentGallery['cover_image'];
                        
                        // Handle cover image upload
                        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['size'] > 0) {
                            $uploadResult = uploadImage($_FILES['cover_image'], $title, 'gallery-cover');
                            if ($uploadResult['success']) {
                                // Delete old cover image
                                if ($coverImage) {
                                    $oldFullPath = UPLOAD_PATH . 'full/' . $coverImage;
                                    $oldThumbPath = UPLOAD_PATH . 'thumbs/' . $coverImage;
                                    if (file_exists($oldFullPath)) unlink($oldFullPath);
                                    if (file_exists($oldThumbPath)) unlink($oldThumbPath);
                                }
                                $coverImage = $uploadResult['filename'];
                            } else {
                                $message = 'Error uploading cover image: ' . $uploadResult['error'];
                                break;
                            }
                        }
                        
                        // Update gallery
                        $stmt = $db->prepare("UPDATE galleries SET title = ?, slug = ?, description = ?, cover_image = ?, sort_order = ?, is_active = ? WHERE id = ?");
                        if ($stmt->execute([$title, $slug, $description, $coverImage, $sortOrder, $isActive, $id])) {
                            $message = 'Gallery updated successfully!';
                            $action = 'list';
                        } else {
                            $message = 'Error updating gallery.';
                        }
                    }
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    // Get gallery info for cleanup
                    $stmt = $db->prepare("SELECT cover_image FROM galleries WHERE id = ?");
                    $stmt->execute([$id]);
                    $gallery = $stmt->fetch();
                    
                    // Delete the gallery (photos will be cascade deleted)
                    $stmt = $db->prepare("DELETE FROM galleries WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        // Clean up cover image files
                        if ($gallery && $gallery['cover_image']) {
                            $fullPath = UPLOAD_PATH . 'full/' . $gallery['cover_image'];
                            $thumbPath = UPLOAD_PATH . 'thumbs/' . $gallery['cover_image'];
                            if (file_exists($fullPath)) unlink($fullPath);
                            if (file_exists($thumbPath)) unlink($thumbPath);
                        }
                        
                        $message = 'Gallery deleted successfully!';
                    } else {
                        $message = 'Error deleting gallery.';
                    }
                }
                break;
        }
    }
}

// Get galleries for listing
if ($action === 'list') {
    $galleries = $db->query("SELECT * FROM galleries ORDER BY sort_order ASC, title ASC")->fetchAll();
} elseif ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $editGallery = null;
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM galleries WHERE id = ?");
        $stmt->execute([$id]);
        $editGallery = $stmt->fetch();
    }
    if (!$editGallery) {
        redirect('/admin/galleries.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Galleries - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        
        /* Earth-tone Color Palette for Admin */
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
        .text-custom-blue { color: #8c9fa5; }
        
        .border-earth-brown { border-color: #8B5A3C; }
        .border-caramel { border-color: #D4A574; }
        .border-sage { border-color: #B8C5B1; }
        .border-dusty-blue { border-color: #9BB3C4; }
        .border-custom-blue { border-color: #8c9fa5; }
        
        .hover\:bg-earth-brown:hover { background-color: #7A4D33; }
        .hover\:bg-caramel:hover { background-color: #C99A66; }
        .hover\:bg-sage:hover { background-color: #A8B5A1; }
        .hover\:bg-dusty-blue:hover { background-color: #8AA3B4; }
        .hover\:bg-custom-blue:hover { background-color: #7A8D93; }
        
        .hover\:text-earth-brown:hover { color: #8B5A3C; }
        .hover\:text-caramel:hover { color: #D4A574; }
        .hover\:text-sage:hover { color: #B8C5B1; }
        .hover\:text-dusty-blue:hover { color: #9BB3C4; }
        
        .focus\:ring-earth-brown:focus { --tw-ring-color: #8B5A3C; }
        .focus\:border-earth-brown:focus { border-color: #8B5A3C; }
    </style>
</head>
<body class="bg-light-sage">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-sage">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <h1 class="text-xl font-bold text-earth-brown">Admin Panel</h1>
                    <div class="hidden md:flex space-x-4">
                        <a href="/admin/" class="text-custom-blue hover:text-earth-brown transition">Dashboard</a>
                        <a href="/admin/galleries.php" class="text-earth-brown font-medium">Galleries</a>
                        <a href="/admin/images.php" class="text-custom-blue hover:text-earth-brown transition">Images</a>
                        <a href="/admin/products.php" class="text-custom-blue hover:text-earth-brown transition">Products</a>
                        <a href="/admin/services.php" class="text-custom-blue hover:text-earth-brown transition">Services</a>
                        <a href="/admin/settings.php" class="text-custom-blue hover:text-earth-brown transition">Settings</a>
                        <a href="/admin/contacts.php" class="text-custom-blue hover:text-earth-brown transition">Contacts</a>
                        <a href="/admin/orders.php" class="text-custom-blue hover:text-earth-brown transition">Orders</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/" class="text-custom-blue hover:text-earth-brown transition" target="_blank">View Site</a>
                    <span class="text-custom-blue">Hello, <?php echo sanitizeInput($_SESSION['user_name']); ?></span>
                    <a href="/admin/logout.php" class="bg-sage text-white px-4 py-2 rounded hover:bg-earth-brown transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-light-sage border border-sage text-earth-brown rounded-lg">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Gallery List View -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-earth-brown">Galleries</h1>
                    <p class="text-custom-blue mt-1">Create and organize photo galleries for your website</p>
                </div>
                <a href="?action=add" class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">Create New Gallery</a>
            </div>

            <!-- Help Text -->
            <div class="bg-light-sage border-l-4 border-sage p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-custom-blue" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-earth-brown">
                            <strong>Gallery Workflow:</strong> Create galleries here, then add photos to each gallery, and finally set up print sizes and pricing for individual photos.
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage overflow-hidden">
                <?php if (empty($galleries)): ?>
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-earth-brown">No galleries</h3>
                        <p class="mt-1 text-sm text-custom-blue">Get started by creating your first gallery.</p>
                        <div class="mt-6">
                            <a href="?action=add" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-earth-brown hover:bg-earth-brown hover:bg-opacity-90">
                                Create Gallery
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-sage">
                            <thead class="bg-light-sage">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Gallery</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Slug</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Photos</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Sort</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-sage">
                                <?php foreach ($galleries as $gallery): ?>
                                    <?php
                                    // Get photo count for this gallery
                                    $stmt = $db->prepare("SELECT COUNT(*) FROM photos WHERE gallery_id = ?");
                                    $stmt->execute([$gallery['id']]);
                                    $photoCount = $stmt->fetchColumn();
                                    ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <?php if ($gallery['cover_image']): ?>
                                                    <div class="flex-shrink-0 h-16 w-16">
                                                        <img class="h-16 w-16 rounded-lg object-cover border border-sage" src="/assets/uploads/thumbs/<?php echo $gallery['cover_image']; ?>" alt="">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="flex-shrink-0 h-16 w-16 bg-light-sage rounded-lg flex items-center justify-center border border-sage">
                                                        <svg class="h-8 w-8 text-custom-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-earth-brown"><?php echo sanitizeInput($gallery['title']); ?></div>
                                                    <?php if ($gallery['description']): ?>
                                                        <div class="text-sm text-custom-blue"><?php echo truncateText($gallery['description'], 50); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-custom-blue">
                                            <code class="bg-light-sage px-2 py-1 rounded border border-sage"><?php echo $gallery['slug']; ?></code>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-earth-brown">
                                            <a href="/admin/photos.php?gallery_id=<?php echo $gallery['id']; ?>" class="text-earth-brown hover:text-caramel transition">
                                                <?php echo $photoCount; ?> photo<?php echo $photoCount !== 1 ? 's' : ''; ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $gallery['is_active'] ? 'bg-sage text-white' : 'bg-caramel text-earth-brown'; ?>">
                                                <?php echo $gallery['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-custom-blue">
                                            <?php echo $gallery['sort_order']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-3">
                                            <a href="/gallery.php?slug=<?php echo urlencode($gallery['slug']); ?>" target="_blank" 
                                               class="text-custom-blue hover:text-earth-brown transition">View</a>
                                            <a href="/admin/photos.php?gallery_id=<?php echo $gallery['id']; ?>" 
                                               class="text-earth-brown hover:text-caramel transition">Photos</a>
                                            <a href="?action=edit&id=<?php echo $gallery['id']; ?>" 
                                               class="text-sage hover:text-earth-brown transition">Edit</a>
                                            <button onclick="deleteGallery(<?php echo $gallery['id']; ?>)" 
                                                    class="text-caramel hover:text-earth-brown transition">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'add'): ?>
            <!-- Create Gallery Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Create New Gallery</h1>
                <a href="/admin/galleries.php" class="text-earth-brown hover:text-caramel transition">← Back to galleries</a>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-6" x-data="galleryForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="create">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-custom-blue mb-2">Title *</label>
                            <input type="text" name="title" id="title" x-model="title" @input="updateSlug" required
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>
                        
                        <div>
                            <label for="slug" class="block text-sm font-medium text-custom-blue mb-2">URL Slug *</label>
                            <input type="text" name="slug" id="slug" x-model="slug" required
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                            <p class="text-sm text-gray-500 mt-1">URL-friendly version (auto-generated from title)</p>
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-custom-blue mb-2">Description</label>
                        <textarea name="description" id="description" rows="4"
                                  class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"
                                  placeholder="Optional description for this gallery"></textarea>
                    </div>

                    <div>
                        <label for="cover_image" class="block text-sm font-medium text-custom-blue mb-2">Cover Image</label>
                        <input type="file" name="cover_image" id="cover_image" accept=".jpg,.jpeg,.png,.webp"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        <p class="text-sm text-gray-500 mt-1">Optional cover image for this gallery</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="sort_order" class="block text-sm font-medium text-custom-blue mb-2">Sort Order</label>
                            <input type="number" name="sort_order" id="sort_order" value="0" min="0"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>
                        
                        <div class="flex items-center pt-6">
                            <input type="checkbox" name="is_active" id="is_active" checked
                                   class="h-4 w-4 text-earth-brown focus:ring-earth-brown border-sage rounded">
                            <label for="is_active" class="ml-2 block text-sm text-earth-brown">Gallery is active</label>
                        </div>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                            Create Gallery
                        </button>
                        <a href="/admin/galleries.php" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'edit' && isset($editGallery)): ?>
            <!-- Edit Gallery Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Edit Gallery</h1>
                <a href="/admin/galleries.php" class="text-earth-brown hover:text-caramel transition">← Back to galleries</a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow border border-sage p-6">
                        <form method="POST" enctype="multipart/form-data" class="space-y-6" x-data="galleryForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="form_action" value="update">
                            <input type="hidden" name="id" value="<?php echo $editGallery['id']; ?>">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="title" class="block text-sm font-medium text-custom-blue mb-2">Title *</label>
                                    <input type="text" name="title" id="title" x-model="title" @input="updateSlug" 
                                           value="<?php echo sanitizeInput($editGallery['title']); ?>" required
                                           class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                                </div>
                                
                                <div>
                                    <label for="slug" class="block text-sm font-medium text-custom-blue mb-2">URL Slug *</label>
                                    <input type="text" name="slug" id="slug" x-model="slug" 
                                           value="<?php echo sanitizeInput($editGallery['slug']); ?>" required
                                           class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-custom-blue mb-2">Description</label>
                                <textarea name="description" id="description" rows="4"
                                          class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"><?php echo $editGallery['description']; ?></textarea>
                            </div>

                            <div>
                                <label for="cover_image" class="block text-sm font-medium text-custom-blue mb-2">Cover Image</label>
                                <input type="file" name="cover_image" id="cover_image" accept=".jpg,.jpeg,.png,.webp"
                                       class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                                <p class="text-sm text-gray-500 mt-1">Leave empty to keep current cover image</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="sort_order" class="block text-sm font-medium text-custom-blue mb-2">Sort Order</label>
                                    <input type="number" name="sort_order" id="sort_order" 
                                           value="<?php echo $editGallery['sort_order']; ?>" min="0"
                                           class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                                </div>
                                
                                <div class="flex items-center pt-6">
                                    <input type="checkbox" name="is_active" id="is_active" <?php echo $editGallery['is_active'] ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-earth-brown focus:ring-earth-brown border-sage rounded">
                                    <label for="is_active" class="ml-2 block text-sm text-earth-brown">Gallery is active</label>
                                </div>
                            </div>

                            <div class="flex space-x-4">
                                <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                                    Update Gallery
                                </button>
                                <a href="/admin/galleries.php" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="space-y-6">
                    <!-- Current Cover Image -->
                    <?php if ($editGallery['cover_image']): ?>
                        <div class="bg-white rounded-lg shadow border border-sage p-6">
                            <h3 class="text-lg font-medium text-earth-brown mb-4">Current Cover Image</h3>
                            <img src="/assets/uploads/full/<?php echo $editGallery['cover_image']; ?>" 
                                 alt="Cover image" class="w-full rounded-lg border border-sage">
                        </div>
                    <?php endif; ?>

                    <!-- Quick Stats -->
                    <div class="bg-white rounded-lg shadow border border-sage p-6">
                        <h3 class="text-lg font-medium text-earth-brown mb-4">Gallery Info</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-custom-blue">Created:</span>
                                <span class="text-earth-brown"><?php echo formatDate($editGallery['created_at']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-custom-blue">Photos:</span>
                                <a href="/admin/photos.php?gallery_id=<?php echo $editGallery['id']; ?>" class="text-earth-brown hover:text-caramel transition">
                                    <?php 
                                    $stmt = $db->prepare("SELECT COUNT(*) FROM photos WHERE gallery_id = ?");
                                    $stmt->execute([$editGallery['id']]);
                                    echo $stmt->fetchColumn();
                                    ?> photos
                                </a>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-custom-blue">URL:</span>
                                <a href="/gallery.php?slug=<?php echo urlencode($editGallery['slug']); ?>" target="_blank" 
                                   class="text-earth-brown hover:text-caramel transition text-xs">View Live</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-data="{ showModal: false, galleryId: null }" x-show="showModal" x-cloak 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4 border border-sage">
            <h3 class="text-lg font-medium text-earth-brown mb-4">Confirm Deletion</h3>
            <p class="text-custom-blue mb-6">Are you sure you want to delete this gallery? This will also delete all photos in this gallery. This action cannot be undone.</p>
            <div class="flex space-x-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="id" x-model="galleryId">
                    <button type="submit" class="bg-caramel text-earth-brown px-4 py-2 rounded hover:bg-earth-brown hover:text-white transition font-medium">
                        Delete Gallery
                    </button>
                </form>
                <button @click="showModal = false" class="bg-sage text-white px-4 py-2 rounded hover:bg-custom-blue transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        function deleteGallery(id) {
            Alpine.store('modal', { showModal: true, galleryId: id });
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('galleryForm', () => ({
                title: '<?php echo $action === 'edit' ? sanitizeInput($editGallery['title']) : ''; ?>',
                slug: '<?php echo $action === 'edit' ? sanitizeInput($editGallery['slug']) : ''; ?>',

                updateSlug() {
                    if (this.title && (!this.slug || this.slug === this.createSlug(this.previousTitle))) {
                        this.slug = this.createSlug(this.title);
                    }
                    this.previousTitle = this.title;
                },

                createSlug(text) {
                    return text.toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/(^-|-$)+/g, '');
                }
            }));
        });
    </script>
</body>
</html>
