<?php
/**
 * Admin Daily Photos Management
 * Manage work-in-progress photos for homepage display (max 6 photos)
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
            case 'upload':
                $title = sanitizeInput($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $altText = sanitizeInput($_POST['alt_text'] ?? '');
                
                if (isset($_FILES['image'])) {
                    // Check current count - limit to 6 photos
                    $countStmt = $db->query("SELECT COUNT(*) FROM daily_photos");
                    $currentCount = $countStmt->fetchColumn();
                    
                    if ($currentCount >= 6) {
                        // Remove oldest photo to make room
                        $oldestStmt = $db->query("SELECT * FROM daily_photos ORDER BY created_at ASC LIMIT 1");
                        $oldest = $oldestStmt->fetch();
                        
                        if ($oldest) {
                            // Delete files
                            $fullPath = UPLOAD_PATH . 'full/' . $oldest['filename'];
                            $thumbPath = UPLOAD_PATH . 'thumbs/' . $oldest['filename'];
                            if (file_exists($fullPath)) unlink($fullPath);
                            if (file_exists($thumbPath)) unlink($thumbPath);
                            
                            // Delete from database
                            $deleteStmt = $db->prepare("DELETE FROM daily_photos WHERE id = ?");
                            $deleteStmt->execute([$oldest['id']]);
                        }
                    }
                    
                    $result = uploadImage($_FILES['image'], $title, 'daily');
                    if ($result['success']) {
                        // Insert into daily_photos table
                        $stmt = $db->prepare("INSERT INTO daily_photos (filename, title, description, alt_text, sort_order) VALUES (?, ?, ?, ?, ?)");
                        $sortOrder = $currentCount >= 6 ? $currentCount - 1 : $currentCount; // Adjust for removed photo
                        
                        if ($stmt->execute([$result['filename'], $title, $description, $altText, $sortOrder])) {
                            $message = 'Daily photo uploaded successfully!';
                            $action = 'list';
                        } else {
                            $message = 'Error saving photo to database.';
                        }
                    } else {
                        $message = 'Error: ' . $result['error'];
                    }
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    // Get photo info first
                    $stmt = $db->prepare("SELECT filename FROM daily_photos WHERE id = ?");
                    $stmt->execute([$id]);
                    $photo = $stmt->fetch();
                    
                    if ($photo) {
                        // Delete files
                        $fullPath = UPLOAD_PATH . 'full/' . $photo['filename'];
                        $thumbPath = UPLOAD_PATH . 'thumbs/' . $photo['filename'];
                        if (file_exists($fullPath)) unlink($fullPath);
                        if (file_exists($thumbPath)) unlink($thumbPath);
                        
                        // Delete from database
                        $stmt = $db->prepare("DELETE FROM daily_photos WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = 'Daily photo deleted successfully!';
                        } else {
                            $message = 'Error deleting photo from database.';
                        }
                    }
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $altText = sanitizeInput($_POST['alt_text'] ?? '');
                
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE daily_photos SET title = ?, description = ?, alt_text = ? WHERE id = ?");
                    if ($stmt->execute([$title, $description, $altText, $id])) {
                        $message = 'Daily photo updated successfully!';
                        $action = 'list';
                    } else {
                        $message = 'Error updating photo.';
                    }
                }
                break;
                
            case 'reorder':
                $orders = $_POST['order'] ?? [];
                foreach ($orders as $id => $order) {
                    $stmt = $db->prepare("UPDATE daily_photos SET sort_order = ? WHERE id = ?");
                    $stmt->execute([(int)$order, (int)$id]);
                }
                $message = 'Photo order updated successfully!';
                break;
        }
    }
}

// Get daily photos for listing
if ($action === 'list') {
    $dailyPhotos = $db->query("SELECT * FROM daily_photos ORDER BY sort_order ASC, created_at DESC")->fetchAll();
} elseif ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $editPhoto = null;
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM daily_photos WHERE id = ?");
        $stmt->execute([$id]);
        $editPhoto = $stmt->fetch();
    }
    if (!$editPhoto) {
        redirect('/admin/daily-photos.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Daily Photos - Admin</title>
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
                        <a href="/admin/images.php" class="text-custom-blue hover:text-earth-brown transition">Images</a>
                        <a href="/admin/daily-photos.php" class="text-earth-brown font-medium">Daily Photos</a>
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
            <!-- Daily Photos List View -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-earth-brown">Daily Photos</h1>
                    <p class="text-custom-blue mt-1">Work-in-progress photos for homepage display (max 6 photos)</p>
                </div>
                <?php if (count($dailyPhotos) < 6): ?>
                    <a href="?action=add" class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">Upload New Photo</a>
                <?php endif; ?>
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
                            <strong>Daily Photos:</strong> Upload work-in-progress photos to showcase your daily creativity. Maximum 6 photos - oldest will be automatically removed when adding new ones. These photos appear only on the homepage gallery section.
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage overflow-hidden">
                <?php if (empty($dailyPhotos)): ?>
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-earth-brown">No daily photos</h3>
                        <p class="mt-1 text-sm text-custom-blue">Start sharing your work in progress!</p>
                        <div class="mt-6">
                            <a href="?action=add" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-earth-brown hover:bg-earth-brown hover:bg-opacity-90">
                                Upload First Photo
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-earth-brown">Current Daily Photos (<?php echo count($dailyPhotos); ?>/6)</h3>
                            <?php if (count($dailyPhotos) < 6): ?>
                                <a href="?action=add" class="text-earth-brown hover:text-caramel transition text-sm font-medium">+ Add Photo</a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($dailyPhotos as $photo): ?>
                                <div class="relative group">
                                    <div class="aspect-square bg-light-sage rounded-lg overflow-hidden border border-sage">
                                        <img src="/assets/uploads/thumbs/<?php echo $photo['filename']; ?>" 
                                             alt="<?php echo sanitizeInput($photo['alt_text']); ?>"
                                             class="w-full h-full object-cover">
                                    </div>
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-200 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100">
                                        <div class="flex space-x-2">
                                            <a href="?action=edit&id=<?php echo $photo['id']; ?>" 
                                               class="bg-sage text-white p-2 rounded hover:bg-earth-brown transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>
                                            <button onclick="deletePhoto(<?php echo $photo['id']; ?>)" 
                                                    class="bg-caramel text-earth-brown p-2 rounded hover:bg-earth-brown hover:text-white transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <p class="text-sm font-medium text-earth-brown truncate"><?php echo $photo['title'] ?: 'Untitled'; ?></p>
                                        <p class="text-xs text-custom-blue"><?php echo formatDate($photo['created_at']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'add'): ?>
            <!-- Upload Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Upload Daily Photo</h1>
                <a href="/admin/daily-photos.php" class="text-earth-brown hover:text-caramel transition">← Back to daily photos</a>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="upload">

                    <div>
                        <label for="image" class="block text-sm font-medium text-custom-blue mb-2">Image File *</label>
                        <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp" required
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        <p class="text-sm text-gray-500 mt-1">Supported formats: JPG, PNG, WebP. Max size: 5MB</p>
                    </div>

                    <div>
                        <label for="title" class="block text-sm font-medium text-custom-blue mb-2">Title</label>
                        <input type="text" name="title" id="title"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"
                               placeholder="Optional title for this photo">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-custom-blue mb-2">Description</label>
                        <textarea name="description" id="description" rows="3"
                                  class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"
                                  placeholder="Optional description or story behind this photo"></textarea>
                    </div>

                    <div>
                        <label for="alt_text" class="block text-sm font-medium text-custom-blue mb-2">Alt Text</label>
                        <input type="text" name="alt_text" id="alt_text"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"
                               placeholder="Describe the image for accessibility">
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                            Upload Photo
                        </button>
                        <a href="/admin/daily-photos.php" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'edit' && isset($editPhoto)): ?>
            <!-- Edit Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Edit Daily Photo</h1>
                <a href="/admin/daily-photos.php" class="text-earth-brown hover:text-caramel transition">← Back to daily photos</a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-lg shadow border border-sage p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="form_action" value="update">
                        <input type="hidden" name="id" value="<?php echo $editPhoto['id']; ?>">

                        <div>
                            <label for="title" class="block text-sm font-medium text-custom-blue mb-2">Title</label>
                            <input type="text" name="title" id="title" value="<?php echo sanitizeInput($editPhoto['title']); ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-custom-blue mb-2">Description</label>
                            <textarea name="description" id="description" rows="4"
                                      class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"><?php echo $editPhoto['description']; ?></textarea>
                        </div>

                        <div>
                            <label for="alt_text" class="block text-sm font-medium text-custom-blue mb-2">Alt Text</label>
                            <input type="text" name="alt_text" id="alt_text" value="<?php echo sanitizeInput($editPhoto['alt_text']); ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                                Update Photo
                            </button>
                            <a href="/admin/daily-photos.php" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-lg shadow border border-sage p-6">
                    <h3 class="text-lg font-medium text-earth-brown mb-4">Current Photo</h3>
                    <img src="/assets/uploads/full/<?php echo $editPhoto['filename']; ?>" 
                         alt="<?php echo sanitizeInput($editPhoto['alt_text']); ?>"
                         class="w-full rounded-lg border border-sage">
                    <div class="mt-4 text-sm text-custom-blue">
                        <p><strong class="text-earth-brown">Filename:</strong> <?php echo $editPhoto['filename']; ?></p>
                        <p><strong class="text-earth-brown">Uploaded:</strong> <?php echo formatDate($editPhoto['created_at']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete confirmation modal -->
    <div x-data="deleteModal" x-show="showModal" x-cloak 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4 border border-sage">
            <h3 class="text-lg font-medium text-earth-brown mb-4">Confirm Deletion</h3>
            <p class="text-custom-blue mb-6">Are you sure you want to delete this daily photo? This action cannot be undone.</p>
            <div class="flex space-x-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="id" x-model="photoId">
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
                photoId: null,
                
                openModal(id) {
                    this.photoId = id;
                    this.showModal = true;
                }
            }));
        });
        
        function deletePhoto(id) {
            const modal = Alpine.$data(document.querySelector('[x-data="deleteModal"]'));
            modal.openModal(id);
        }
    </script>
</body>
</html>
