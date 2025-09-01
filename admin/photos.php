<?php
/**
 * Admin Photo Management
 * Upload and manage photos within galleries with print pricing
 */
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$db = getDB();
$message = '';
$galleryId = (int)($_GET['gallery_id'] ?? 0);

// Validate gallery
$gallery = null;
if ($galleryId > 0) {
    $stmt = $db->prepare("SELECT * FROM galleries WHERE id = ?");
    $stmt->execute([$galleryId]);
    $gallery = $stmt->fetch();
}

if (!$gallery) {
    redirect('/admin/galleries.php');
}

// Get available print sizes
$printSizes = $db->query("SELECT * FROM print_sizes ORDER BY sort_order ASC, name ASC")->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        $message = 'Invalid request. Please try again.';
    } else {
        switch ($_POST['form_action']) {
            case 'upload_photos':
                if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                    $uploadedFiles = $_FILES['photos'];
                    $fileCount = count($uploadedFiles['name']);
                    $successCount = 0;
                    $errors = [];
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
                            // Create a single file array for the upload function
                            $singleFile = [
                                'name' => $uploadedFiles['name'][$i],
                                'type' => $uploadedFiles['type'][$i],
                                'tmp_name' => $uploadedFiles['tmp_name'][$i],
                                'error' => $uploadedFiles['error'][$i],
                                'size' => $uploadedFiles['size'][$i]
                            ];
                            
                            $uploadResult = uploadImage($singleFile, $uploadedFiles['name'][$i], 'photo');
                            if ($uploadResult['success']) {
                                // Insert photo record
                                $title = pathinfo($uploadedFiles['name'][$i], PATHINFO_FILENAME);
                                $stmt = $db->prepare("INSERT INTO photos (gallery_id, title, filename) VALUES (?, ?, ?)");
                                if ($stmt->execute([$galleryId, $title, $uploadResult['filename']])) {
                                    $photoId = $db->lastInsertId();
                                    
                                    // Add default print sizes for this photo
                                    foreach ($printSizes as $printSize) {
                                        $stmt = $db->prepare("INSERT INTO photo_prints (photo_id, print_size_id, custom_price, status) VALUES (?, ?, NULL, 'active')");
                                        $stmt->execute([$photoId, $printSize['id']]);
                                    }
                                    
                                    $successCount++;
                                } else {
                                    $errors[] = "Failed to save photo: " . $uploadedFiles['name'][$i];
                                }
                            } else {
                                $errors[] = "Failed to upload " . $uploadedFiles['name'][$i] . ": " . $uploadResult['error'];
                            }
                        }
                    }
                    
                    if ($successCount > 0) {
                        $message = "Successfully uploaded $successCount photo(s).";
                        if (!empty($errors)) {
                            $message .= " Errors: " . implode(', ', $errors);
                        }
                    } else {
                        $message = "No photos were uploaded. " . implode(', ', $errors);
                    }
                } else {
                    $message = 'Please select at least one photo to upload.';
                }
                break;
                
            case 'update_photo':
                $photoId = (int)($_POST['photo_id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $alt_text = sanitizeInput($_POST['alt_text'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                error_log("Photo update attempt - Photo ID: $photoId, Title: '$title', Gallery ID: $galleryId");
                
                if ($photoId > 0 && !empty($title)) {
                    try {
                        // Verify photo exists in this gallery
                        $stmt = $db->prepare("SELECT id FROM photos WHERE id = ? AND gallery_id = ?");
                        $stmt->execute([$photoId, $galleryId]);
                        $photoExists = $stmt->fetch();
                        
                        if ($photoExists) {
                            // Check which columns exist in the photos table (MySQL version)
                            $stmt = $db->query("SHOW COLUMNS FROM photos");
                            $columns = $stmt->fetchAll();
                            $columnNames = array_column($columns, 'Field');
                            
                            // Build dynamic update query based on existing columns
                            $updateFields = ['title = ?'];
                            $updateValues = [$title];
                            
                            if (in_array('alt_text', $columnNames)) {
                                $updateFields[] = 'alt_text = ?';
                                $updateValues[] = $alt_text;
                            } elseif (in_array('alt', $columnNames)) {
                                $updateFields[] = 'alt = ?';
                                $updateValues[] = $alt_text;
                            }
                            
                            if (in_array('description', $columnNames)) {
                                $updateFields[] = 'description = ?';
                                $updateValues[] = $description;
                            }
                            
                            // Add WHERE conditions
                            $updateValues[] = $photoId;
                            $updateValues[] = $galleryId;
                            
                            $updateQuery = "UPDATE photos SET " . implode(', ', $updateFields) . " WHERE id = ? AND gallery_id = ?";
                            $stmt = $db->prepare($updateQuery);
                            $updateResult = $stmt->execute($updateValues);
                            
                            if ($updateResult && $stmt->rowCount() > 0) {
                                $message = 'Photo updated successfully!';
                                error_log("Photo $photoId updated successfully");
                                
                                // Redirect to prevent form resubmission and blank page
                                header("Location: ?action=edit&photo_id=" . $photoId . "&gallery_id=" . $galleryId . "&updated=1&msg=" . urlencode($message));
                                exit;
                            } else {
                                $message = 'No changes were made to the photo.';
                                error_log("Photo $photoId update - no rows affected");
                            }
                        } else {
                            $message = 'Error: Photo not found in this gallery.';
                            error_log("Photo $photoId not found in gallery $galleryId");
                        }
                    } catch (Exception $e) {
                        $message = 'Error updating photo: ' . $e->getMessage();
                        error_log("Photo update error: " . $e->getMessage());
                    }
                } else {
                    $message = 'Please provide a photo ID and title.';
                    error_log("Photo update failed - missing photo ID ($photoId) or title ('$title')");
                }
                break;
                
            case 'update_print_pricing':
                $photoId = (int)($_POST['photo_id'] ?? 0);
                if ($photoId > 0) {
                    // Update print pricing for this photo
                    foreach ($_POST['print_sizes'] ?? [] as $printSizeId => $data) {
                        $printSizeId = (int)$printSizeId;
                        $customPrice = !empty($data['custom_price']) ? (int)($data['custom_price'] * 100) : null; // Convert to cents
                        $status = isset($data['enabled']) ? 'active' : 'inactive';
                        
                        // Check if record exists
                        $stmt = $db->prepare("SELECT id FROM photo_prints WHERE photo_id = ? AND print_size_id = ?");
                        $stmt->execute([$photoId, $printSizeId]);
                        $existingRecord = $stmt->fetch();
                        
                        if ($existingRecord) {
                            // Update existing record
                            $stmt = $db->prepare("UPDATE photo_prints SET custom_price = ?, status = ? WHERE photo_id = ? AND print_size_id = ?");
                            $stmt->execute([$customPrice, $status, $photoId, $printSizeId]);
                        } else {
                            // Create new record
                            $stmt = $db->prepare("INSERT INTO photo_prints (photo_id, print_size_id, custom_price, status) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$photoId, $printSizeId, $customPrice, $status]);
                        }
                    }
                    $message = 'Print pricing updated successfully!';
                }
                break;
                
            case 'delete_photo':
                $photoId = (int)($_POST['photo_id'] ?? 0);
                if ($photoId > 0) {
                    try {
                        // Start transaction for safe deletion
                        $db->beginTransaction();
                        
                        // Get photo info first
                        $stmt = $db->prepare("SELECT * FROM photos WHERE id = ? AND gallery_id = ?");
                        $stmt->execute([$photoId, $galleryId]);
                        $photo = $stmt->fetch();
                        
                        if ($photo) {
                            $deletionLog = [];
                            
                            // Step 1: Delete orders first (most critical foreign key)
                            $stmt = $db->prepare("DELETE FROM orders WHERE photo_id = ?");
                            $stmt->execute([$photoId]);
                            $ordersDeleted = $stmt->rowCount();
                            if ($ordersDeleted > 0) {
                                $deletionLog[] = "$ordersDeleted order(s)";
                            }
                            
                            // Step 2: Delete order_items (if table exists)
                            try {
                                $stmt = $db->prepare("DELETE FROM order_items WHERE photo_id = ?");
                                $stmt->execute([$photoId]);
                                $orderItemsDeleted = $stmt->rowCount();
                                if ($orderItemsDeleted > 0) {
                                    $deletionLog[] = "$orderItemsDeleted order item(s)";
                                }
                            } catch (Exception $e) {
                                // Table might not exist, continue
                            }
                            
                            // Step 3: Delete cart_items (if table exists)
                            try {
                                $stmt = $db->prepare("DELETE FROM cart_items WHERE photo_id = ?");
                                $stmt->execute([$photoId]);
                                $cartDeleted = $stmt->rowCount();
                                if ($cartDeleted > 0) {
                                    $deletionLog[] = "$cartDeleted cart item(s)";
                                }
                            } catch (Exception $e) {
                                // Table might not exist, continue
                            }
                            
                            // Step 4: Delete photo_prints
                            $stmt = $db->prepare("DELETE FROM photo_prints WHERE photo_id = ?");
                            $stmt->execute([$photoId]);
                            $printsDeleted = $stmt->rowCount();
                            if ($printsDeleted > 0) {
                                $deletionLog[] = "$printsDeleted print size(s)";
                            }
                            
                            // Step 5: Delete photo_sizes (if table exists)
                            try {
                                $stmt = $db->prepare("DELETE FROM photo_sizes WHERE photo_id = ?");
                                $stmt->execute([$photoId]);
                                $sizesDeleted = $stmt->rowCount();
                                if ($sizesDeleted > 0) {
                                    $deletionLog[] = "$sizesDeleted size variant(s)";
                                }
                            } catch (Exception $e) {
                                // Table might not exist, continue
                            }
                            
                            // Step 6: Delete favorites (if table exists)
                            try {
                                $stmt = $db->prepare("DELETE FROM favorites WHERE photo_id = ?");
                                $stmt->execute([$photoId]);
                                $favoritesDeleted = $stmt->rowCount();
                                if ($favoritesDeleted > 0) {
                                    $deletionLog[] = "$favoritesDeleted favorite(s)";
                                }
                            } catch (Exception $e) {
                                // Table might not exist, continue
                            }
                            
                            // Step 7: Now delete the photo itself
                            $stmt = $db->prepare("DELETE FROM photos WHERE id = ? AND gallery_id = ?");
                            $photoDeleted = $stmt->execute([$photoId, $galleryId]);
                            
                            if ($photoDeleted && $stmt->rowCount() > 0) {
                                $deletionLog[] = "photo record";
                                
                                // Step 8: Clean up image files
                                if (defined('UPLOAD_PATH')) {
                                    $fullPath = UPLOAD_PATH . 'full/' . $photo['filename'];
                                    $thumbPath = UPLOAD_PATH . 'thumbs/' . $photo['filename'];
                                    if (file_exists($fullPath)) {
                                        unlink($fullPath);
                                        $deletionLog[] = "full image file";
                                    }
                                    if (file_exists($thumbPath)) {
                                        unlink($thumbPath);
                                        $deletionLog[] = "thumbnail file";
                                    }
                                }
                                
                                // Commit transaction
                                $db->commit();
                                
                                $message = 'Photo deleted successfully! Removed: ' . implode(', ', $deletionLog) . '.';
                                
                                // Redirect to prevent blank page and form resubmission
                                header("Location: ?gallery_id=" . $galleryId . "&deleted=1&msg=" . urlencode($message));
                                exit;
                            } else {
                                $db->rollback();
                                $message = 'Error: Photo could not be deleted from database.';
                            }
                        } else {
                            $db->rollback();
                            $message = 'Error: Photo not found in this gallery.';
                        }
                    } catch (Exception $e) {
                        $db->rollback();
                        $message = 'Error deleting photo: ' . $e->getMessage();
                        error_log("Photo deletion error for photo ID $photoId: " . $e->getMessage());
                        
                        // Provide diagnostic link for troubleshooting
                        $message .= ' <a href="/photo_diagnostic.php?photo_id=' . $photoId . '" class="underline">Click here to diagnose the issue</a>';
                    }
                }
                break;
        }
    }
}

// Check for success messages from redirect
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $message = $_GET['msg'] ?? 'Photo deleted successfully!';
} elseif (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = $_GET['msg'] ?? 'Photo updated successfully!';
}

// Get photos for this gallery
$photos = $db->prepare("SELECT * FROM photos WHERE gallery_id = ? ORDER BY sort_order ASC, created_at DESC");
$photos->execute([$galleryId]);
$photos = $photos->fetchAll();

// Get specific photo if editing
$editPhoto = null;
$action = $_GET['action'] ?? 'list';
if ($action === 'edit') {
    $editPhotoId = (int)($_GET['photo_id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM photos WHERE id = ? AND gallery_id = ?");
    $stmt->execute([$editPhotoId, $galleryId]);
    $editPhoto = $stmt->fetch();
    
    if ($editPhoto) {
        // Get print pricing for this photo
        $stmt = $db->prepare("
            SELECT pp.*, ps.name, ps.label, ps.dimensions, ps.base_price, ps.description
            FROM photo_prints pp
            JOIN print_sizes ps ON pp.print_size_id = ps.id
            WHERE pp.photo_id = ?
            ORDER BY ps.sort_order ASC, ps.name ASC
        ");
        $stmt->execute([$editPhoto['id']]);
        $photoPrints = $stmt->fetchAll();
        
        // Index by print size ID for easier access
        $photoPrintsBySize = [];
        foreach ($photoPrints as $print) {
            $photoPrintsBySize[$print['print_size_id']] = $print;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Photos - <?php echo sanitizeInput($gallery['title']); ?></title>
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
                        <a href="/admin/recent-work.php" class="text-custom-blue hover:text-earth-brown transition">Recent Work</a>
                        <a href="/admin/print-settings.php" class="text-custom-blue hover:text-earth-brown transition">Print Settings</a>
                        <a href="/admin/orders.php" class="text-custom-blue hover:text-earth-brown transition">Orders</a>
                        <a href="/admin/settings.php" class="text-custom-blue hover:text-earth-brown transition">Settings</a>
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
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center space-x-2 text-sm text-custom-blue mb-2">
                <a href="/admin/galleries.php" class="hover:text-earth-brown transition">Galleries</a>
                <span>›</span>
                <span class="text-earth-brown font-medium"><?php echo sanitizeInput($gallery['title']); ?></span>
            </div>
            <h1 class="text-3xl font-bold text-earth-brown">Gallery Photos</h1>
            <p class="text-custom-blue mt-1">Upload and manage photos for this gallery</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-light-sage border border-sage text-earth-brown rounded-lg">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Upload Photos Section -->
            <div class="bg-white rounded-lg shadow border border-sage p-6 mb-8">
                <h3 class="text-lg font-medium text-earth-brown mb-4">Upload New Photos</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="upload_photos">
                    
                    <div>
                        <label for="photos" class="block text-sm font-medium text-custom-blue mb-2">Select Photos</label>
                        <input type="file" name="photos[]" id="photos" multiple accept=".jpg,.jpeg,.png,.webp"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        <p class="text-sm text-gray-500 mt-1">Select multiple photos to upload to this gallery</p>
                    </div>
                    
                    <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                        Upload Photos
                    </button>
                </form>
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
                            // Get print size count for this photo
                            $stmt = $db->prepare("SELECT COUNT(*) FROM photo_prints WHERE photo_id = ?");
                            $stmt->execute([$photo['id']]);
                            $printCount = $stmt->fetchColumn();
                            
                            // Determine correct image path
                            $imagePath = '';
                            if (!empty($photo['filename'])) {
                                $imagePath = '/assets/uploads/thumbs/' . $photo['filename'];
                            } elseif (!empty($photo['thumb_path'])) {
                                $imagePath = '/assets/uploads/' . $photo['thumb_path'];
                            }
                            ?>
                            <div class="bg-light-sage rounded-lg border border-sage overflow-hidden">
                                <div class="aspect-w-1 aspect-h-1 bg-gray-200">
                                    <?php if ($imagePath): ?>
                                        <img src="<?php echo $imagePath; ?>" 
                                             alt="<?php echo sanitizeInput($photo['alt_text'] ?: $photo['title']); ?>"
                                             class="w-full h-48 object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                            <span class="text-gray-500">No Image</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-4">
                                    <h4 class="font-medium text-earth-brown text-sm mb-2"><?php echo sanitizeInput($photo['title']); ?></h4>
                                    <p class="text-xs text-custom-blue mb-3">
                                        <?php echo $printCount; ?> print size<?php echo $printCount !== 1 ? 's' : ''; ?>
                                    </p>
                                    <div class="flex space-x-2">
                                        <a href="?action=edit&photo_id=<?php echo $photo['id']; ?>&gallery_id=<?php echo $galleryId; ?>" 
                                           class="text-xs text-earth-brown hover:text-caramel transition">Edit</a>
                                        <button onclick="deletePhoto(<?php echo $photo['id']; ?>)" 
                                                class="text-xs text-caramel hover:text-earth-brown transition">Delete</button>
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

        <?php elseif ($action === 'edit' && $editPhoto): ?>
            <!-- Edit Photo -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Photo Details Form -->
                <div class="bg-white rounded-lg shadow border border-sage p-6">
                    <h3 class="text-lg font-medium text-earth-brown mb-6">Photo Details</h3>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="form_action" value="update_photo">
                        <input type="hidden" name="photo_id" value="<?php echo $editPhoto['id']; ?>">
                        
                        <div>
                            <label for="title" class="block text-sm font-medium text-custom-blue mb-2">Title *</label>
                            <input type="text" name="title" id="title" required
                                   value="<?php echo sanitizeInput($editPhoto['title']); ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>
                        
                        <div>
                            <label for="alt_text" class="block text-sm font-medium text-custom-blue mb-2">Alt Text</label>
                            <input type="text" name="alt_text" id="alt_text"
                                   value="<?php echo sanitizeInput($editPhoto['alt_text'] ?: $editPhoto['alt']); ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"
                                   placeholder="Descriptive text for accessibility">
                        </div>
                        
                        <div>
                            <label for="description" class="block text-sm font-medium text-custom-blue mb-2">Description</label>
                            <textarea name="description" id="description" rows="3"
                                      class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"><?php echo $editPhoto['description']; ?></textarea>
                        </div>
                        
                        <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                            Update Photo
                        </button>
                    </form>
                </div>

                <!-- Photo Preview -->
                <div class="bg-white rounded-lg shadow border border-sage p-6">
                    <h3 class="text-lg font-medium text-earth-brown mb-6">Photo Preview</h3>
                    <?php
                    // Determine correct image path for preview
                    $previewPath = '';
                    if (!empty($editPhoto['filename'])) {
                        $previewPath = '/assets/uploads/full/' . $editPhoto['filename'];
                    } elseif (!empty($editPhoto['full_path'])) {
                        $previewPath = '/assets/uploads/' . $editPhoto['full_path'];
                    }
                    ?>
                    <?php if ($previewPath): ?>
                        <img src="<?php echo $previewPath; ?>" 
                             alt="<?php echo sanitizeInput($editPhoto['alt_text'] ?: $editPhoto['title']); ?>"
                             class="w-full rounded-lg border border-sage">
                    <?php else: ?>
                        <div class="w-full h-64 bg-gray-200 rounded-lg border border-sage flex items-center justify-center">
                            <span class="text-gray-500">No preview available</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Print Pricing Section -->
            <div class="bg-white rounded-lg shadow border border-sage p-6 mt-8">
                <h3 class="text-lg font-medium text-earth-brown mb-6">Print Sizes & Pricing</h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="update_print_pricing">
                    <input type="hidden" name="photo_id" value="<?php echo $editPhoto['id']; ?>">
                    
                    <div class="grid gap-4">
                        <?php foreach ($printSizes as $printSize): ?>
                            <?php
                            $photoPrint = $photoPrintsBySize[$printSize['id']] ?? null;
                            $isEnabled = $photoPrint ? ($photoPrint['status'] === 'active') : true;
                            $customPrice = $photoPrint && $photoPrint['custom_price'] ? ($photoPrint['custom_price'] / 100) : '';
                            ?>
                            <div class="border border-sage rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="print_sizes[<?php echo $printSize['id']; ?>][enabled]" 
                                               id="size_<?php echo $printSize['id']; ?>" <?php echo $isEnabled ? 'checked' : ''; ?>
                                               class="h-4 w-4 text-earth-brown focus:ring-earth-brown border-sage rounded">
                                        <label for="size_<?php echo $printSize['id']; ?>" class="ml-2 text-sm font-medium text-earth-brown">
                                            <?php echo sanitizeInput($printSize['label']); ?>
                                        </label>
                                    </div>
                                    <div class="text-sm text-custom-blue">
                                        <?php echo sanitizeInput($printSize['dimensions']); ?>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs text-custom-blue mb-1">Base Price</label>
                                        <div class="text-sm text-earth-brown font-medium">
                                            $<?php echo number_format($printSize['base_price'] / 100, 2); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="price_<?php echo $printSize['id']; ?>" class="block text-xs text-custom-blue mb-1">
                                            Custom Price (optional)
                                        </label>
                                        <input type="number" name="print_sizes[<?php echo $printSize['id']; ?>][custom_price]" 
                                               id="price_<?php echo $printSize['id']; ?>" step="0.01" min="0"
                                               value="<?php echo $customPrice; ?>"
                                               class="w-full px-2 py-1 text-sm border border-sage rounded focus:outline-none focus:ring-1 focus:ring-earth-brown focus:border-earth-brown"
                                               placeholder="Leave empty for base price">
                                    </div>
                                </div>
                                
                                <?php if ($printSize['description']): ?>
                                    <p class="text-xs text-custom-blue mt-2"><?php echo sanitizeInput($printSize['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="flex space-x-4 pt-4 border-t border-sage">
                        <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                            Update Print Pricing
                        </button>
                        <a href="?gallery_id=<?php echo $galleryId; ?>" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                            Back to Gallery
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal - FIXED VERSION -->
    <div id="deleteModal" style="display: none;" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Confirm Deletion</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete this photo? This will also delete all print sizes for this photo. This action cannot be undone.</p>
            <div class="flex space-x-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="delete_photo">
                    <input type="hidden" name="photo_id" id="deletePhotoId" value="">
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">
                        Delete Photo
                    </button>
                </form>
                <button onclick="hideDeleteModal()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        function deletePhoto(id) {
            console.log("Delete photo called with ID:", id);
            document.getElementById("deletePhotoId").value = id;
            document.getElementById("deleteModal").style.display = "flex";
        }

        function hideDeleteModal() {
            document.getElementById("deleteModal").style.display = "none";
        }

        // Close modal when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById("deleteModal").addEventListener("click", function(e) {
                if (e.target === this) {
                    hideDeleteModal();
                }
            });
        });
    </script>
</body>
</html>
