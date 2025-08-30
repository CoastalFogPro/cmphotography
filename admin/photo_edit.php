<?php
/**
 * Individual Photo Editor
 * Manage photo details and size variants with Stripe pricing
 */
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$db = getDB();
$message = '';
$photoId = (int)($_GET['id'] ?? 0);

// Validate photo exists
if ($photoId <= 0) {
    redirect('/admin/galleries.php');
}

$stmt = $db->prepare("SELECT p.*, g.title as gallery_title, g.id as gallery_id FROM photos p JOIN galleries g ON p.gallery_id = g.id WHERE p.id = ?");
$stmt->execute([$photoId]);
$photo = $stmt->fetch();

if (!$photo) {
    redirect('/admin/galleries.php');
}

$action = $_GET['action'] ?? 'manage';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        $message = 'Invalid request. Please try again.';
    } else {
        switch ($_POST['form_action']) {
            case 'update_photo':
                $title = sanitizeInput($_POST['title'] ?? '');
                $alt = sanitizeInput($_POST['alt'] ?? '');
                $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                
                $stmt = $db->prepare("UPDATE photos SET title = ?, alt = ?, is_featured = ?, sort_order = ? WHERE id = ?");
                if ($stmt->execute([$title, $alt, $isFeatured, $sortOrder, $photoId])) {
                    $message = 'Photo updated successfully!';
                    // Refresh photo data
                    $stmt = $db->prepare("SELECT p.*, g.title as gallery_title, g.id as gallery_id FROM photos p JOIN galleries g ON p.gallery_id = g.id WHERE p.id = ?");
                    $stmt->execute([$photoId]);
                    $photo = $stmt->fetch();
                } else {
                    $message = 'Error updating photo.';
                }
                break;
                
            case 'add_size':
                $sizeLabel = sanitizeInput($_POST['size_label'] ?? '');
                $priceCents = (int)($_POST['price_cents'] ?? 0);
                $stripePriceId = sanitizeInput($_POST['stripe_price_id'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($sizeLabel) || $priceCents <= 0 || empty($stripePriceId)) {
                    $message = 'Size label, price, and Stripe Price ID are required.';
                } else {
                    $stmt = $db->prepare("INSERT INTO photo_sizes (photo_id, size_label, price_cents, stripe_price_id, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$photoId, $sizeLabel, $priceCents, $stripePriceId, $description, $sortOrder, $isActive])) {
                        $message = 'Size added successfully!';
                    } else {
                        $message = 'Error adding size.';
                    }
                }
                break;
                
            case 'update_size':
                $sizeId = (int)($_POST['size_id'] ?? 0);
                $sizeLabel = sanitizeInput($_POST['size_label'] ?? '');
                $priceCents = (int)($_POST['price_cents'] ?? 0);
                $stripePriceId = sanitizeInput($_POST['stripe_price_id'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if ($sizeId <= 0 || empty($sizeLabel) || $priceCents <= 0 || empty($stripePriceId)) {
                    $message = 'Invalid size data provided.';
                } else {
                    $stmt = $db->prepare("UPDATE photo_sizes SET size_label = ?, price_cents = ?, stripe_price_id = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ? AND photo_id = ?");
                    if ($stmt->execute([$sizeLabel, $priceCents, $stripePriceId, $description, $sortOrder, $isActive, $sizeId, $photoId])) {
                        $message = 'Size updated successfully!';
                    } else {
                        $message = 'Error updating size.';
                    }
                }
                break;
                
            case 'delete_size':
                $sizeId = (int)($_POST['size_id'] ?? 0);
                if ($sizeId > 0) {
                    $stmt = $db->prepare("DELETE FROM photo_sizes WHERE id = ? AND photo_id = ?");
                    if ($stmt->execute([$sizeId, $photoId])) {
                        $message = 'Size deleted successfully!';
                    } else {
                        $message = 'Error deleting size.';
                    }
                }
                break;
        }
    }
}

// Get sizes for this photo
$sizes = $db->prepare("SELECT * FROM photo_sizes WHERE photo_id = ? ORDER BY sort_order ASC, price_cents ASC");
$sizes->execute([$photoId]);
$sizes = $sizes->fetchAll();

// Get size for editing if specified
$editSize = null;
if ($action === 'edit_size') {
    $sizeId = (int)($_GET['size_id'] ?? 0);
    if ($sizeId > 0) {
        $stmt = $db->prepare("SELECT * FROM photo_sizes WHERE id = ? AND photo_id = ?");
        $stmt->execute([$sizeId, $photoId]);
        $editSize = $stmt->fetch();
    }
    if (!$editSize) {
        $action = 'manage';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Photo - <?php echo sanitizeInput($photo['title'] ?: 'Untitled'); ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <h1 class="text-xl font-bold text-gray-900">Admin Panel</h1>
                    <div class="hidden md:flex space-x-4">
                        <a href="/admin/" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                        <a href="/admin/galleries.php" class="text-gray-600 hover:text-gray-900">Galleries</a>
                        <a href="/admin/images.php" class="text-gray-600 hover:text-gray-900">Images</a>
                        <a href="/admin/products.php" class="text-gray-600 hover:text-gray-900">Products</a>
                        <a href="/admin/services.php" class="text-gray-600 hover:text-gray-900">Services</a>
                        <a href="/admin/settings.php" class="text-gray-600 hover:text-gray-900">Settings</a>
                        <a href="/admin/contacts.php" class="text-gray-600 hover:text-gray-900">Contacts</a>
                        <a href="/admin/orders.php" class="text-gray-600 hover:text-gray-900">Orders</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900" target="_blank">View Site</a>
                    <span class="text-gray-600">Hello, <?php echo sanitizeInput($_SESSION['user_name']); ?></span>
                    <a href="/admin/logout.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-500 mb-6">
            <a href="/admin/galleries.php" class="hover:text-gray-700">Galleries</a>
            <span class="mx-2">/</span>
            <a href="/admin/photos.php?gallery_id=<?php echo $photo['gallery_id']; ?>" class="hover:text-gray-700"><?php echo sanitizeInput($photo['gallery_title']); ?></a>
            <span class="mx-2">/</span>
            <span class="text-gray-900"><?php echo sanitizeInput($photo['title'] ?: 'Untitled Photo'); ?></span>
        </nav>

        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Photo Preview -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6 sticky top-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Photo Preview</h3>
                    <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden mb-4">
                        <img src="/assets/uploads/<?php echo $photo['full_path']; ?>" 
                             alt="<?php echo sanitizeInput($photo['alt']); ?>"
                             class="w-full h-full object-cover">
                    </div>
                    <div class="space-y-2 text-sm text-gray-600">
                        <div><strong>Gallery:</strong> <?php echo sanitizeInput($photo['gallery_title']); ?></div>
                        <div><strong>Uploaded:</strong> <?php echo formatDate($photo['created_at']); ?></div>
                        <div><strong>Featured:</strong> <?php echo $photo['is_featured'] ? 'Yes' : 'No'; ?></div>
                    </div>
                    <div class="mt-4">
                        <a href="/photo.php?id=<?php echo $photo['id']; ?>" target="_blank" 
                           class="w-full bg-gray-100 text-gray-700 py-2 px-4 rounded text-center block hover:bg-gray-200 transition">
                            View Live
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Photo Details -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Photo Details</h2>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="form_action" value="update_photo">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                                <input type="text" name="title" id="title" value="<?php echo sanitizeInput($photo['title']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                                <input type="number" name="sort_order" id="sort_order" value="<?php echo $photo['sort_order']; ?>" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>

                        <div>
                            <label for="alt" class="block text-sm font-medium text-gray-700 mb-2">Alt Text *</label>
                            <input type="text" name="alt" id="alt" value="<?php echo sanitizeInput($photo['alt']); ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="is_featured" id="is_featured" <?php echo $photo['is_featured'] ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_featured" class="ml-2 block text-sm text-gray-900">Featured photo</label>
                        </div>

                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                            Update Photo
                        </button>
                    </form>
                </div>

                <!-- Size Management -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-gray-900">Print Sizes & Pricing</h2>
                        <?php if ($action !== 'add_size'): ?>
                            <a href="?id=<?php echo $photoId; ?>&action=add_size" 
                               class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                                Add Size
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Help Text -->
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Stripe Price IDs:</strong> You need to create Price objects in your Stripe Dashboard first. Each size requires its own Price ID (e.g., price_1ABC123).
                                </p>
                            </div>
                        </div>
                    </div>

                    <?php if ($action === 'add_size'): ?>
                        <!-- Add Size Form -->
                        <form method="POST" class="space-y-6 border-t pt-6">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="form_action" value="add_size">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="size_label" class="block text-sm font-medium text-gray-700 mb-2">Size Label *</label>
                                    <input type="text" name="size_label" id="size_label" required
                                           placeholder="e.g., 8x10, 11x14, 16x20"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label for="price_cents" class="block text-sm font-medium text-gray-700 mb-2">Price (cents) *</label>
                                    <input type="number" name="price_cents" id="price_cents" min="1" required
                                           placeholder="e.g., 2500 for $25.00"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>

                            <div>
                                <label for="stripe_price_id" class="block text-sm font-medium text-gray-700 mb-2">Stripe Price ID *</label>
                                <input type="text" name="stripe_price_id" id="stripe_price_id" required
                                       placeholder="price_1ABC123def456GHI"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-sm text-gray-500 mt-1">Price ID from your Stripe Dashboard</p>
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea name="description" id="description" rows="3"
                                          placeholder="Optional description for this size"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                                    <input type="number" name="sort_order" id="sort_order" value="0" min="0"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div class="flex items-center pt-6">
                                    <input type="checkbox" name="is_active" id="is_active" checked
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="is_active" class="ml-2 block text-sm text-gray-900">Size is active</label>
                                </div>
                            </div>

                            <div class="flex space-x-4">
                                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
                                    Add Size
                                </button>
                                <a href="?id=<?php echo $photoId; ?>" class="bg-gray-200 text-gray-700 px-6 py-2 rounded hover:bg-gray-300 transition">
                                    Cancel
                                </a>
                            </div>
                        </form>

                    <?php elseif ($action === 'edit_size' && $editSize): ?>
                        <!-- Edit Size Form -->
                        <form method="POST" class="space-y-6 border-t pt-6">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="form_action" value="update_size">
                            <input type="hidden" name="size_id" value="<?php echo $editSize['id']; ?>">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="size_label" class="block text-sm font-medium text-gray-700 mb-2">Size Label *</label>
                                    <input type="text" name="size_label" id="size_label" required
                                           value="<?php echo sanitizeInput($editSize['size_label']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label for="price_cents" class="block text-sm font-medium text-gray-700 mb-2">Price (cents) *</label>
                                    <input type="number" name="price_cents" id="price_cents" min="1" required
                                           value="<?php echo $editSize['price_cents']; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>

                            <div>
                                <label for="stripe_price_id" class="block text-sm font-medium text-gray-700 mb-2">Stripe Price ID *</label>
                                <input type="text" name="stripe_price_id" id="stripe_price_id" required
                                       value="<?php echo sanitizeInput($editSize['stripe_price_id']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea name="description" id="description" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $editSize['description']; ?></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                                    <input type="number" name="sort_order" id="sort_order" value="<?php echo $editSize['sort_order']; ?>" min="0"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div class="flex items-center pt-6">
                                    <input type="checkbox" name="is_active" id="is_active" <?php echo $editSize['is_active'] ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="is_active" class="ml-2 block text-sm text-gray-900">Size is active</label>
                                </div>
                            </div>

                            <div class="flex space-x-4">
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                                    Update Size
                                </button>
                                <a href="?id=<?php echo $photoId; ?>" class="bg-gray-200 text-gray-700 px-6 py-2 rounded hover:bg-gray-300 transition">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>

                    <!-- Existing Sizes List -->
                    <div class="<?php echo ($action === 'add_size' || $action === 'edit_size') ? 'border-t pt-6 mt-6' : ''; ?>">
                        <?php if (!empty($sizes)): ?>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Current Sizes</h3>
                            <div class="space-y-4">
                                <?php foreach ($sizes as $size): ?>
                                    <div class="border rounded-lg p-4 <?php echo !$size['is_active'] ? 'bg-gray-50' : ''; ?>">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3">
                                                    <h4 class="font-medium text-gray-900"><?php echo sanitizeInput($size['size_label']); ?></h4>
                                                    <span class="text-lg font-semibold text-green-600"><?php echo formatPrice($size['price_cents']); ?></span>
                                                    <?php if (!$size['is_active']): ?>
                                                        <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">Inactive</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm text-gray-500 mt-1">
                                                    Stripe ID: <code class="bg-gray-100 px-1 rounded"><?php echo sanitizeInput($size['stripe_price_id']); ?></code>
                                                </div>
                                                <?php if ($size['description']): ?>
                                                    <div class="text-sm text-gray-600 mt-2"><?php echo sanitizeInput($size['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex space-x-2 ml-4">
                                                <a href="?id=<?php echo $photoId; ?>&action=edit_size&size_id=<?php echo $size['id']; ?>" 
                                                   class="text-blue-600 hover:text-blue-700 text-sm">Edit</a>
                                                <button onclick="deleteSize(<?php echo $size['id']; ?>)" 
                                                        class="text-red-600 hover:text-red-700 text-sm">Delete</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No print sizes</h3>
                                <p class="mt-1 text-sm text-gray-500">Add print sizes to allow customers to purchase this photo.</p>
                                <div class="mt-6">
                                    <a href="?id=<?php echo $photoId; ?>&action=add_size" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                        Add First Size
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Size Confirmation Modal -->
    <div x-data="{ showModal: false, sizeId: null }" x-show="showModal" x-cloak 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Confirm Deletion</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete this print size? This action cannot be undone.</p>
            <div class="flex space-x-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="delete_size">
                    <input type="hidden" name="size_id" x-model="sizeId">
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">
                        Delete Size
                    </button>
                </form>
                <button @click="showModal = false" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        function deleteSize(id) {
            Alpine.store('modal', { showModal: true, sizeId: id });
        }
    </script>
</body>
</html>
