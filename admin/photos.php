<?php
/**
 * Admin Photo Management for Galleries
 * Upload and manage photos within specific galleries
 */
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$db = getDB();
$message = '';
$galleryId = (int)($_GET['gallery_id'] ?? 0);

// Validate gallery exists
if ($galleryId <= 0) {
    redirect('/admin/galleries.php');
}

$stmt = $db->prepare("SELECT * FROM galleries WHERE id = ?");
$stmt->execute([$galleryId]);
$gallery = $stmt->fetch();

if (!$gallery) {
    redirect('/admin/galleries.php');
}

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
                $alt = sanitizeInput($_POST['alt'] ?? '');
                
                if (isset($_FILES['photo'])) {
                    $uploadResult = uploadImage($_FILES['photo'], $title, 'gallery');
                    if ($uploadResult['success']) {
                        // Insert into photos table
                        $thumbPath = 'thumbs/' . $uploadResult['filename'];
                        $fullPath = 'full/' . $uploadResult['filename'];
                        
                        $stmt = $db->prepare("INSERT INTO photos (gallery_id, title, alt, thumb_path, full_path) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt->execute([$galleryId, $title, $alt, $thumbPath, $fullPath])) {
                            $message = 'Photo uploaded successfully!';
                            $action = 'list';
                        } else {
                            $message = 'Error saving photo to database.';
                        }
                    } else {
                        $message = 'Error: ' . $uploadResult['error'];
                    }
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $alt = sanitizeInput($_POST['alt'] ?? '');
                $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE photos SET title = ?, alt = ?, is_featured = ?, sort_order = ? WHERE id = ? AND gallery_id = ?");
                    if ($stmt->execute([$title, $alt, $isFeatured, $sortOrder, $id, $galleryId])) {
                        $message = 'Photo updated successfully!';
                        $action = 'list';
                    } else {
                        $message = 'Error updating photo.';
                    }
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    // Get photo info first
                    $stmt = $db->prepare("SELECT * FROM photos WHERE id = ? AND gallery_id = ?");
                    $stmt->execute([$id, $galleryId]);
                    $photo = $stmt->fetch();
                    
                    if ($photo) {
                        // Delete files
                        $fullPath = UPLOAD_PATH . $photo['full_path'];
                        $thumbPath = UPLOAD_PATH . $photo['thumb_path'];
                        if (file_exists($fullPath)) unlink($fullPath);
                        if (file_exists($thumbPath)) unlink($thumbPath);
                        
                        // Delete from database (this will also cascade delete photo_sizes)
                        $stmt = $db->prepare("DELETE FROM photos WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = 'Photo deleted successfully!';
                        } else {
                            $message = 'Error deleting photo from database.';
                        }
                    }
                }
                break;
        }
    }
}

// Get photos for this gallery
if ($action === 'list') {
    $photos = $db->prepare("SELECT * FROM photos WHERE gallery_id = ? ORDER BY sort_order ASC, created_at DESC");
    $photos->execute([$galleryId]);
    $photos = $photos->fetchAll();
} elseif ($action === 'edit') {
    $photoId = (int)($_GET['id'] ?? 0);
    $editPhoto = null;
    if ($photoId > 0) {
        $stmt = $db->prepare("SELECT * FROM photos WHERE id = ? AND gallery_id = ?");
        $stmt->execute([$photoId, $galleryId]);
        $editPhoto = $stmt->fetch();
    }
    if (!$editPhoto) {
        redirect("/admin/photos.php?gallery_id=$galleryId");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Photos - <?php echo sanitizeInput($gallery['title']); ?> - Admin</title>
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
            <span class="text-gray-900"><?php echo sanitizeInput($gallery['title']); ?></span>
            <span class="mx-2">/</span>
            <span>Photos</span>
        </nav>

        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Photos List View -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Photos - <?php echo sanitizeInput($gallery['title']); ?></h1>
                    <p class="text-gray-600 mt-1">Upload and manage photos for this gallery</p>
                </div>
                <a href="?gallery_id=<?php echo $galleryId; ?>&action=add" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Upload Photo</a>
            </div>

            <!-- Help Text -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Next Step:</strong> After uploading photos, click "Edit" on each photo to set up print sizes and Stripe pricing. You'll need Stripe Price IDs from your Stripe Dashboard.
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <?php if (empty($photos)): ?>
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No photos</h3>
                        <p class="mt-1 text-sm text-gray-500">Start by uploading photos to this gallery.</p>
                        <div class="mt-6">
                            <a href="?gallery_id=<?php echo $galleryId; ?>&action=add" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                Upload Photo
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-6">
                        <?php foreach ($photos as $photo): ?>
                            <?php
                            // Get size count for this photo
                            $stmt = $db->prepare("SELECT COUNT(*) FROM photo_sizes WHERE photo_id = ?");
                            $stmt->execute([$photo['id']]);
                            $sizeCount = $stmt->fetchColumn();
                            ?>
                            <div class="bg-white rounded-lg shadow overflow-hidden">
                                <div class="aspect-square bg-gray-200 overflow-hidden">
                                    <img src="/assets/uploads/<?php echo $photo['thumb_path']; ?>" 
                                         alt="<?php echo sanitizeInput($photo['alt']); ?>"
                                         class="w-full h-full object-cover">
                                </div>
                                <div class="p-4">
                                    <h3 class="font-medium text-gray-900 mb-1 truncate">
                                        <?php echo $photo['title'] ?: 'Untitled'; ?>
                                    </h3>
                                    <p class="text-sm text-gray-500 mb-3">
                                        <?php echo $sizeCount; ?> size<?php echo $sizeCount !== 1 ? 's' : ''; ?>
                                        <?php if ($photo['is_featured']): ?>
                                            <span class="ml-2 bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">Featured</span>
                                        <?php endif; ?>
                                    </p>
                                    <div class="flex space-x-2">
                                        <a href="/admin/photo_edit.php?id=<?php echo $photo['id']; ?>" 
                                           class="flex-1 bg-blue-600 text-white text-center py-2 px-3 text-sm rounded hover:bg-blue-700 transition">
                                            Edit
                                        </a>
                                        <button onclick="deletePhoto(<?php echo $photo['id']; ?>)" 
                                                class="bg-red-600 text-white py-2 px-3 text-sm rounded hover:bg-red-700 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'add'): ?>
            <!-- Upload Photo Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Upload Photo to <?php echo sanitizeInput($gallery['title']); ?></h1>
                <a href="/admin/photos.php?gallery_id=<?php echo $galleryId; ?>" class="text-blue-600 hover:text-blue-700">← Back to photos</a>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="upload">

                    <div>
                        <label for="photo" class="block text-sm font-medium text-gray-700 mb-2">Photo File *</label>
                        <input type="file" name="photo" id="photo" accept=".jpg,.jpeg,.png,.webp" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-sm text-gray-500 mt-1">Supported formats: JPG, PNG, WebP. Max size: 5MB</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                            <input type="text" name="title" id="title"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Optional title for this photo">
                        </div>
                        
                        <div>
                            <label for="alt" class="block text-sm font-medium text-gray-700 mb-2">Alt Text *</label>
                            <input type="text" name="alt" id="alt" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Description for accessibility">
                        </div>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                            Upload Photo
                        </button>
                        <a href="/admin/photos.php?gallery_id=<?php echo $galleryId; ?>" class="bg-gray-200 text-gray-700 px-6 py-2 rounded hover:bg-gray-300 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'edit' && isset($editPhoto)): ?>
            <!-- Edit Photo Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Edit Photo</h1>
                <a href="/admin/photos.php?gallery_id=<?php echo $galleryId; ?>" class="text-blue-600 hover:text-blue-700">← Back to photos</a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="form_action" value="update">
                        <input type="hidden" name="id" value="<?php echo $editPhoto['id']; ?>">

                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                            <input type="text" name="title" id="title" value="<?php echo sanitizeInput($editPhoto['title']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="alt" class="block text-sm font-medium text-gray-700 mb-2">Alt Text *</label>
                            <input type="text" name="alt" id="alt" value="<?php echo sanitizeInput($editPhoto['alt']); ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="sort_order" id="sort_order" value="<?php echo $editPhoto['sort_order']; ?>" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="is_featured" id="is_featured" <?php echo $editPhoto['is_featured'] ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_featured" class="ml-2 block text-sm text-gray-900">Featured photo</label>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                                Update Photo
                            </button>
                            <a href="/admin/photos.php?gallery_id=<?php echo $galleryId; ?>" class="bg-gray-200 text-gray-700 px-6 py-2 rounded hover:bg-gray-300 transition">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <!-- Current Photo -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Current Photo</h3>
                        <img src="/assets/uploads/<?php echo $editPhoto['full_path']; ?>" 
                             alt="<?php echo sanitizeInput($editPhoto['alt']); ?>"
                             class="w-full rounded-lg">
                        <div class="mt-4 text-sm text-gray-600">
                            <p><strong>Uploaded:</strong> <?php echo formatDate($editPhoto['created_at']); ?></p>
                        </div>
                    </div>

                    <!-- Size Management -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Print Sizes</h3>
                            <a href="/admin/photo_edit.php?id=<?php echo $editPhoto['id']; ?>" 
                               class="bg-green-600 text-white px-3 py-1 text-sm rounded hover:bg-green-700 transition">
                                Manage Sizes
                            </a>
                        </div>
                        <?php
                        $stmt = $db->prepare("SELECT * FROM photo_sizes WHERE photo_id = ? ORDER BY sort_order ASC, price_cents ASC");
                        $stmt->execute([$editPhoto['id']]);
                        $sizes = $stmt->fetchAll();
                        ?>
                        <?php if (empty($sizes)): ?>
                            <p class="text-gray-500 text-sm">No print sizes configured yet.</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($sizes as $size): ?>
                                    <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
                                        <div>
                                            <span class="font-medium"><?php echo sanitizeInput($size['size_label']); ?></span>
                                            <?php if (!$size['is_active']): ?>
                                                <span class="ml-2 text-xs text-red-600">(Inactive)</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-sm text-gray-600"><?php echo formatPrice($size['price_cents']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-data="{ showModal: false, photoId: null }" x-show="showModal" x-cloak 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Confirm Deletion</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete this photo? This will also delete all print sizes for this photo. This action cannot be undone.</p>
            <div class="flex space-x-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="id" x-model="photoId">
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">
                        Delete Photo
                    </button>
                </form>
                <button @click="showModal = false" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        function deletePhoto(id) {
            Alpine.store('modal', { showModal: true, photoId: id });
        }
    </script>
</body>
</html>
