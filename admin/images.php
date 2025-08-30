<?php
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
            case 'upload':
                $title = sanitizeInput($_POST['title'] ?? '');
                $category = sanitizeInput($_POST['category'] ?? 'general');
                
                if (isset($_FILES['image'])) {
                    $result = uploadImage($_FILES['image'], $title, $category);
                    if ($result['success']) {
                        $message = 'Image uploaded successfully!';
                        $action = 'list';
                    } else {
                        $message = 'Error: ' . $result['error'];
                    }
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    // Get image info first
                    $stmt = $db->prepare("SELECT filename FROM images WHERE id = ?");
                    $stmt->execute([$id]);
                    $image = $stmt->fetch();
                    
                    if ($image) {
                        // Delete files
                        $fullPath = UPLOAD_PATH . 'full/' . $image['filename'];
                        $thumbPath = UPLOAD_PATH . 'thumbs/' . $image['filename'];
                        if (file_exists($fullPath)) unlink($fullPath);
                        if (file_exists($thumbPath)) unlink($thumbPath);
                        
                        // Delete from database
                        $stmt = $db->prepare("DELETE FROM images WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = 'Image deleted successfully!';
                        } else {
                            $message = 'Error deleting image from database.';
                        }
                    }
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $category = sanitizeInput($_POST['category'] ?? '');
                $alt_text = sanitizeInput($_POST['alt_text'] ?? '');
                
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE images SET title = ?, category = ?, alt_text = ? WHERE id = ?");
                    if ($stmt->execute([$title, $category, $alt_text, $id])) {
                        $message = 'Image updated successfully!';
                        $action = 'list';
                    } else {
                        $message = 'Error updating image.';
                    }
                }
                break;
        }
    }
}

// Get images for listing
if ($action === 'list') {
    $images = $db->query("SELECT * FROM images ORDER BY created_at DESC")->fetchAll();
} elseif ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $editImage = null;
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM images WHERE id = ?");
        $stmt->execute([$id]);
        $editImage = $stmt->fetch();
    }
    if (!$editImage) {
        redirect('/admin/images.php');
    }
}

// Get categories for filter
$categories = $db->query("SELECT DISTINCT category FROM images ORDER BY category")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Images - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
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
                        <a href="/admin/galleries.php" class="text-custom-blue hover:text-earth-brown transition">Galleries</a>
                        <a href="/admin/images.php" class="text-earth-brown font-medium">Images</a>
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
            <!-- Images List View -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Images</h1>
                <a href="?action=add" class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">Upload New Image</a>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage overflow-hidden">
                <?php if (empty($images)): ?>
                    <div class="p-8 text-center">
                        <p class="text-custom-blue">No images uploaded yet.</p>
                        <a href="?action=add" class="text-earth-brown hover:text-caramel transition">Upload your first image</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-6">
                        <?php foreach ($images as $image): ?>
                            <div class="relative group">
                                <div class="aspect-square bg-light-sage rounded-lg overflow-hidden border border-sage">
                                    <img src="/assets/uploads/thumbs/<?php echo $image['filename']; ?>" 
                                         alt="<?php echo sanitizeInput($image['alt_text']); ?>"
                                         class="w-full h-full object-cover">
                                </div>
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-200 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100">
                                    <div class="flex space-x-2">
                                        <a href="?action=edit&id=<?php echo $image['id']; ?>" 
                                           class="bg-sage text-white p-2 rounded hover:bg-earth-brown transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        <button onclick="deleteImage(<?php echo $image['id']; ?>)" 
                                                class="bg-caramel text-earth-brown p-2 rounded hover:bg-earth-brown hover:text-white transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="text-sm font-medium text-earth-brown truncate"><?php echo $image['title'] ?: 'Untitled'; ?></p>
                                    <p class="text-xs text-custom-blue"><?php echo $image['category']; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'add'): ?>
            <!-- Upload Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Upload New Image</h1>
                <a href="/admin/images.php" class="text-earth-brown hover:text-caramel transition">← Back to images</a>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="upload">

                    <div>
                        <label for="image" class="block text-sm font-medium text-custom-blue mb-2">Image File</label>
                        <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp" required
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        <p class="text-sm text-gray-500 mt-1">Supported formats: JPG, PNG, WebP. Max size: 5MB</p>
                    </div>

                    <div>
                        <label for="title" class="block text-sm font-medium text-custom-blue mb-2">Title</label>
                        <input type="text" name="title" id="title"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-medium text-custom-blue mb-2">Category</label>
                        <select name="category" id="category" 
                                class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                            <option value="general">General</option>
                            <option value="landscape">Landscape</option>
                            <option value="portrait">Portrait</option>
                            <option value="event">Event</option>
                            <option value="commercial">Commercial</option>
                        </select>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                            Upload Image
                        </button>
                        <a href="/admin/images.php" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'edit' && isset($editImage)): ?>
            <!-- Edit Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Edit Image</h1>
                <a href="/admin/images.php" class="text-earth-brown hover:text-caramel transition">← Back to images</a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-lg shadow border border-sage p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="form_action" value="update">
                        <input type="hidden" name="id" value="<?php echo $editImage['id']; ?>">

                        <div>
                            <label for="title" class="block text-sm font-medium text-custom-blue mb-2">Title</label>
                            <input type="text" name="title" id="title" value="<?php echo sanitizeInput($editImage['title']); ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div>
                            <label for="category" class="block text-sm font-medium text-custom-blue mb-2">Category</label>
                            <select name="category" id="category" 
                                    class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                                <option value="general" <?php echo $editImage['category'] === 'general' ? 'selected' : ''; ?>>General</option>
                                <option value="landscape" <?php echo $editImage['category'] === 'landscape' ? 'selected' : ''; ?>>Landscape</option>
                                <option value="portrait" <?php echo $editImage['category'] === 'portrait' ? 'selected' : ''; ?>>Portrait</option>
                                <option value="event" <?php echo $editImage['category'] === 'event' ? 'selected' : ''; ?>>Event</option>
                                <option value="commercial" <?php echo $editImage['category'] === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                            </select>
                        </div>

                        <div>
                            <label for="alt_text" class="block text-sm font-medium text-custom-blue mb-2">Alt Text</label>
                            <input type="text" name="alt_text" id="alt_text" value="<?php echo sanitizeInput($editImage['alt_text']); ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                                Update Image
                            </button>
                            <a href="/admin/images.php" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-lg shadow border border-sage p-6">
                    <h3 class="text-lg font-medium text-earth-brown mb-4">Current Image</h3>
                    <img src="/assets/uploads/full/<?php echo $editImage['filename']; ?>" 
                         alt="<?php echo sanitizeInput($editImage['alt_text']); ?>"
                         class="w-full rounded-lg border border-sage">
                    <div class="mt-4 text-sm text-custom-blue">
                        <p><strong class="text-earth-brown">Filename:</strong> <?php echo $editImage['filename']; ?></p>
                        <p><strong class="text-earth-brown">Uploaded:</strong> <?php echo formatDate($editImage['created_at']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete confirmation modal -->
    <div x-data="deleteModal" x-show="showModal" x-cloak 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4 border border-sage"
             x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
            <h3 class="text-lg font-medium text-earth-brown mb-4">Confirm Deletion</h3>
            <p class="text-custom-blue mb-6">Are you sure you want to delete this image? This action cannot be undone.</p>
            <div class="flex space-x-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="id" x-model="imageId">
                    <button type="submit" class="bg-caramel text-earth-brown px-4 py-2 rounded hover:bg-earth-brown hover:text-white transition font-medium">
                        Delete
                    </button>
                </form>
                <button @click="showModal = false" class="bg-sage text-white px-4 py-2 rounded hover:bg-custom-blue transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        // Alpine.js components
        document.addEventListener('alpine:init', () => {
            Alpine.data('deleteModal', () => ({
                showModal: false,
                imageId: null,
                
                openModal(id) {
                    this.imageId = id;
                    this.showModal = true;
                }
            }));
        });
        
        function deleteImage(id) {
            // Get the Alpine component and open the modal
            const modal = Alpine.$data(document.querySelector('[x-data="deleteModal"]'));
            modal.openModal(id);
        }
    </script>
</body>
</html>
