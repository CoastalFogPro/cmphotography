<?php
/**
 * Recent Work Management
 * Manage homepage recent work photos separately from galleries
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
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = uploadImage($_FILES['photo'], $title ?: 'Recent Work', 'recent-work');
                    if ($uploadResult['success']) {
                        // Get next sort order
                        $stmt = $db->query("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM daily_photos");
                        $nextOrder = $stmt->fetchColumn();
                        
                        // Insert photo record
                        $stmt = $db->prepare("INSERT INTO daily_photos (title, description, filename, sort_order) VALUES (?, ?, ?, ?)");
                        if ($stmt->execute([$title, $description, $uploadResult['filename'], $nextOrder])) {
                            // Auto-clean to keep only 6 most recent
                            $stmt = $db->query("SELECT COUNT(*) FROM daily_photos");
                            $count = $stmt->fetchColumn();
                            
                            if ($count > 6) {
                                // Delete oldest entries
                                $stmt = $db->prepare("DELETE FROM daily_photos WHERE id NOT IN (SELECT id FROM (SELECT id FROM daily_photos ORDER BY sort_order DESC, created_at DESC LIMIT 6) as keep)");
                                $stmt->execute();
                            }
                            
                            $message = 'Photo uploaded successfully!';
                            $action = 'list';
                        } else {
                            $message = 'Error saving photo to database.';
                        }
                    } else {
                        $message = 'Error uploading photo: ' . $uploadResult['error'];
                    }
                } else {
                    $message = 'Please select a photo to upload.';
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE daily_photos SET title = ?, description = ?, sort_order = ? WHERE id = ?");
                    if ($stmt->execute([$title, $description, $sortOrder, $id])) {
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
                    // Get photo info for cleanup
                    $stmt = $db->prepare("SELECT filename FROM daily_photos WHERE id = ?");
                    $stmt->execute([$id]);
                    $photo = $stmt->fetch();
                    
                    if ($photo) {
                        // Delete the photo
                        $stmt = $db->prepare("DELETE FROM daily_photos WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            // Clean up image files
                            $fullPath = UPLOAD_PATH . 'full/' . $photo['filename'];
                            $thumbPath = UPLOAD_PATH . 'thumbs/' . $photo['filename'];
                            if (file_exists($fullPath)) unlink($fullPath);
                            if (file_exists($thumbPath)) unlink($thumbPath);
                            
                            $message = 'Photo deleted successfully!';
                        } else {
                            $message = 'Error deleting photo.';
                        }
                    }
                }
                break;
        }
    }
}

// Get recent work photos
if ($action === 'list') {
    $recentWork = $db->query("SELECT * FROM daily_photos ORDER BY sort_order DESC, created_at DESC")->fetchAll();
} elseif ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $editPhoto = null;
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM daily_photos WHERE id = ?");
        $stmt->execute([$id]);
        $editPhoto = $stmt->fetch();
    }
    if (!$editPhoto) {
        header('Location: /admin/recent-work.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Work - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-earth-brown { background-color: #8B5A3C; }
        .bg-caramel { background-color: #D4A574; }
        .bg-sage { background-color: #B8C5B1; }
        .bg-dusty-blue { background-color: #9BB3C4; }
        .bg-light-sage { background-color: #E8EDE6; }
        .bg-custom-blue { background-color: #8c9fa5; }
        .text-earth-brown { color: #8B5A3C; }
        .text-custom-blue { color: #8c9fa5; }
        .border-sage { border-color: #B8C5B1; }
        .hover\:bg-earth-brown:hover { background-color: #7A4D33; }
        .hover\:bg-custom-blue:hover { background-color: #7A8D93; }
        .hover\:text-earth-brown:hover { color: #8B5A3C; }
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
                        <a href="/admin/recent-work.php" class="text-earth-brown font-medium">Recent Work</a>
                        <a href="/admin/print-settings.php" class="text-custom-blue hover:text-earth-brown transition">Print Settings</a>
                        <a href="/admin/orders.php" class="text-custom-blue hover:text-earth-brown transition">Orders</a>
                        <a href="/admin/settings.php" class="text-custom-blue hover:text-earth-brown transition">Settings</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/" class="text-custom-blue hover:text-earth-brown transition" target="_blank">View Site</a>
                    <a href="/admin/logout.php" class="bg-sage text-white px-4 py-2 rounded hover:bg-earth-brown transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-earth-brown">Recent Work</h1>
            <p class="mt-2 text-custom-blue">Manage the photos displayed in the Recent Work section of your homepage (maximum 6 photos)</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Add/Edit Photo Form -->
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-lg shadow border border-sage">
                    <h3 class="text-lg font-medium text-earth-brown mb-4">
                        <?php echo $editingPhoto ? 'Edit Photo' : 'Add New Photo'; ?>
                    </h3>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <?php if ($editingPhoto): ?>
                            <input type="hidden" name="photo_id" value="<?php echo $editingPhoto['id']; ?>">
                        <?php endif; ?>
                        
                        <?php if (!$editingPhoto): ?>
                            <div>
                                <label class="block text-sm font-medium text-earth-brown mb-2">Photo *</label>
                                <input type="file" name="photo" accept="image/*" required
                                       class="w-full px-3 py-2 border border-sage rounded focus:outline-none focus:ring-2 focus:ring-earth-brown">
                                <p class="text-xs text-custom-blue mt-1">JPG, PNG, or GIF. Will be automatically resized.</p>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="block text-sm font-medium text-earth-brown mb-2">Title</label>
                            <input type="text" name="title"
                                   value="<?php echo htmlspecialchars($editingPhoto['title'] ?? ''); ?>"
                                   placeholder="Photo title (optional)"
                                   class="w-full px-3 py-2 border border-sage rounded focus:outline-none focus:ring-2 focus:ring-earth-brown">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-earth-brown mb-2">Description</label>
                            <textarea name="description" rows="3"
                                      placeholder="Brief description (optional)"
                                      class="w-full px-3 py-2 border border-sage rounded focus:outline-none focus:ring-2 focus:ring-earth-brown"><?php echo htmlspecialchars($editingPhoto['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-earth-brown mb-2">Alt Text</label>
                            <input type="text" name="alt_text"
                                   value="<?php echo htmlspecialchars($editingPhoto['alt_text'] ?? ''); ?>"
                                   placeholder="Alt text for accessibility"
                                   class="w-full px-3 py-2 border border-sage rounded focus:outline-none focus:ring-2 focus:ring-earth-brown">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-earth-brown mb-2">Sort Order</label>
                            <input type="number" name="sort_order"
                                   value="<?php echo $editingPhoto['sort_order'] ?? ''; ?>"
                                   placeholder="1"
                                   class="w-full px-3 py-2 border border-sage rounded focus:outline-none focus:ring-2 focus:ring-earth-brown">
                            <p class="text-xs text-custom-blue mt-1">Lower numbers appear first</p>
                        </div>
                        
                        <div class="flex gap-4">
                            <?php if ($editingPhoto): ?>
                                <button type="submit" name="update_photo" 
                                        class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-opacity-90 transition">
                                    Update Photo
                                </button>
                                <a href="/admin/recent-work.php" 
                                   class="bg-custom-blue text-white px-4 py-2 rounded hover:bg-opacity-90 transition">
                                    Cancel
                                </a>
                            <?php else: ?>
                                <button type="submit" name="add_photo" 
                                        class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-opacity-90 transition">
                                    Add Photo
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Info Box -->
                <div class="mt-6 bg-white p-6 rounded-lg shadow border border-sage">
                    <h4 class="text-sm font-medium text-earth-brown mb-2">About Recent Work</h4>
                    <div class="text-sm text-custom-blue space-y-2">
                        <p>• Only displays on the homepage</p>
                        <p>• Maximum of 6 photos shown</p>
                        <p>• Oldest photos are automatically removed when you add new ones</p>
                        <p>• Perfect for work-in-progress or daily inspiration</p>
                    </div>
                </div>
            </div>

            <!-- Current Photos -->
            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-lg shadow border border-sage">
                    <h3 class="text-lg font-medium text-earth-brown mb-4">
                        Current Recent Work Photos (<?php echo count($recentWork); ?>/6)
                    </h3>
                    
                    <?php if (empty($recentWork)): ?>
                        <div class="text-center py-12">
                            <svg class="w-12 h-12 text-custom-blue mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p class="text-custom-blue">No recent work photos yet. Add your first photo to get started!</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($recentWork as $photo): ?>
                                <div class="border border-sage rounded-lg overflow-hidden">
                                    <div class="aspect-square">
                                        <img src="/assets/uploads/thumbs/<?php echo htmlspecialchars($photo['filename']); ?>" 
                                             alt="<?php echo htmlspecialchars($photo['alt_text'] ?: $photo['title'] ?: 'Recent work'); ?>"
                                             class="w-full h-full object-cover">
                                    </div>
                                    <div class="p-4">
                                        <div class="flex justify-between items-start mb-2">
                                            <div class="flex-1">
                                                <?php if ($photo['title']): ?>
                                                    <h4 class="font-medium text-earth-brown text-sm"><?php echo htmlspecialchars($photo['title']); ?></h4>
                                                <?php endif; ?>
                                                <?php if ($photo['description']): ?>
                                                    <p class="text-xs text-custom-blue mt-1"><?php echo htmlspecialchars(substr($photo['description'], 0, 100)); ?><?php echo strlen($photo['description']) > 100 ? '...' : ''; ?></p>
                                                <?php endif; ?>
                                                <p class="text-xs text-gray-500 mt-1">Order: <?php echo $photo['sort_order']; ?></p>
                                            </div>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs text-gray-500">
                                                <?php echo date('M j, Y', strtotime($photo['created_at'])); ?>
                                            </span>
                                            <div class="flex gap-2">
                                                <a href="?edit=<?php echo $photo['id']; ?>" 
                                                   class="text-custom-blue hover:text-earth-brown text-xs">Edit</a>
                                                <form method="POST" class="inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this photo?')">
                                                    <input type="hidden" name="photo_id" value="<?php echo $photo['id']; ?>">
                                                    <button type="submit" name="delete_photo" 
                                                            class="text-red-600 hover:text-red-800 text-xs">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($recentWork) >= 6): ?>
                            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
                                <strong>Note:</strong> You have reached the maximum of 6 photos. Adding a new photo will automatically remove the oldest one.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
