<?php
/**
 * Photo Replacement Tool
 * Replace placeholder images with real photographs
 */
require_once __DIR__ . '/../includes/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = getDB();
$message = '';
$photoId = (int)($_GET['id'] ?? 1);

echo "<h2>Photo Replacement Tool</h2>";
echo "<p style='background: #e3f2fd; padding: 10px; border-left: 4px solid #2196f3;'>This tool helps you replace the blue placeholder image with a real photograph.</p>";

// Get photo data
$stmt = $db->prepare("SELECT p.*, g.title as gallery_title FROM photos p JOIN galleries g ON p.gallery_id = g.id WHERE p.id = ?");
$stmt->execute([$photoId]);
$photo = $stmt->fetch();

if (!$photo) {
    echo "<p style='color: red;'>Photo not found with ID: $photoId</p>";
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_image'])) {
    echo "<h3>Processing Image Upload...</h3>";
    
    $uploadedFile = $_FILES['new_image'];
    
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        $message = "<p style='color: red;'>Upload error: " . $uploadedFile['error'] . "</p>";
    } else {
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $fileType = $uploadedFile['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $message = "<p style='color: red;'>Invalid file type. Please upload JPEG, PNG, or WebP images.</p>";
        } else {
            // Create upload directory if it doesn't exist
            $uploadDir = __DIR__ . '/../assets/uploads/full/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate new filename or use existing one
            $useExistingName = isset($_POST['keep_filename']) && $_POST['keep_filename'] === '1';
            
            if ($useExistingName) {
                $newFilename = $photo['filename'];
                $backupName = pathinfo($photo['filename'], PATHINFO_FILENAME) . '_backup_' . time() . '.' . pathinfo($photo['filename'], PATHINFO_EXTENSION);
                $backupPath = $uploadDir . $backupName;
                
                // Backup existing file
                if (file_exists($uploadDir . $photo['filename'])) {
                    copy($uploadDir . $photo['filename'], $backupPath);
                    echo "<p style='color: blue;'>✓ Created backup: $backupName</p>";
                }
            } else {
                // Generate new unique filename
                $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
                $newFilename = uniqid() . '.' . $extension;
            }
            
            $targetPath = $uploadDir . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                echo "<p style='color: green;'>✓ File uploaded successfully: $newFilename</p>";
                
                // Update database if filename changed
                if (!$useExistingName) {
                    $updateStmt = $db->prepare("UPDATE photos SET filename = ? WHERE id = ?");
                    if ($updateStmt->execute([$newFilename, $photoId])) {
                        echo "<p style='color: green;'>✓ Database updated with new filename</p>";
                        $photo['filename'] = $newFilename; // Update local variable
                    } else {
                        echo "<p style='color: red;'>✗ Failed to update database</p>";
                    }
                }
                
                // Create thumbnail if needed
                $thumbDir = __DIR__ . '/../assets/uploads/thumbs/';
                if (!is_dir($thumbDir)) {
                    mkdir($thumbDir, 0755, true);
                }
                
                $thumbPath = $thumbDir . $newFilename;
                if (createThumbnailSimple($targetPath, $thumbPath, 300, 300)) {
                    echo "<p style='color: green;'>✓ Thumbnail created</p>";
                } else {
                    echo "<p style='color: orange;'>⚠ Could not create thumbnail (but main image uploaded successfully)</p>";
                }
                
                $message = "<p style='color: green; font-weight: bold;'>🎉 Photo replacement completed successfully!</p>";
                
            } else {
                $message = "<p style='color: red;'>✗ Failed to move uploaded file</p>";
            }
        }
    }
}

// Simple thumbnail creation function
function createThumbnailSimple($source, $destination, $width, $height) {
    if (!function_exists('imagecreatefromjpeg')) {
        return false; // GD not available
    }
    
    $imageInfo = getimagesize($source);
    if (!$imageInfo) {
        return false;
    }
    
    list($sourceWidth, $sourceHeight, $sourceType) = $imageInfo;
    
    // Calculate dimensions maintaining aspect ratio
    $ratio = min($width / $sourceWidth, $height / $sourceHeight);
    $newWidth = $sourceWidth * $ratio;
    $newHeight = $sourceHeight * $ratio;
    
    // Create image resource based on type
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($source);
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Create thumbnail
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG
    if ($sourceType == IMAGETYPE_PNG) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    
    // Save based on source type
    $result = false;
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($thumb, $destination, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($thumb, $destination, 8);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($thumb, $destination, 90);
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($thumb);
    
    return $result;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Photo Replacement Tool - Photo ID <?php echo $photoId; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .preview { border: 2px solid #ddd; padding: 15px; margin: 15px 0; background: #f9f9f9; }
        .preview img { max-width: 300px; max-height: 300px; border: 1px solid #ccc; }
        .form-group { margin: 15px 0; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="file"] { padding: 8px; border: 1px solid #ccc; }
        input[type="checkbox"] { margin-right: 8px; }
        button { background: #0066cc; color: white; padding: 12px 24px; border: none; cursor: pointer; font-size: 16px; }
        button:hover { background: #0052a3; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; }
        .info { background: #e3f2fd; border: 1px solid #bbdefb; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <h1>Replace Photo: <?php echo htmlspecialchars($photo['title'] ?: 'Untitled'); ?></h1>
    
    <?php if ($message): ?>
        <div><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="preview">
        <h3>Current Image (Blue Placeholder)</h3>
        <p><strong>Filename:</strong> <?php echo htmlspecialchars($photo['filename']); ?></p>
        <p><strong>Gallery:</strong> <?php echo htmlspecialchars($photo['gallery_title']); ?></p>
        <img src="/assets/uploads/full/<?php echo htmlspecialchars($photo['filename']); ?>" 
             alt="Current image" 
             onerror="this.parentElement.innerHTML='<p style=\'color: red;\'>Image failed to load</p>'">
    </div>
    
    <div class="warning">
        <strong>⚠️ Important:</strong> This will replace the placeholder image with a real photograph. 
        The blue placeholder will be backed up if you choose to keep the same filename.
    </div>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="new_image">Select New Image:</label>
            <input type="file" name="new_image" id="new_image" accept="image/jpeg,image/jpg,image/png,image/webp" required>
            <small>Supported formats: JPEG, PNG, WebP</small>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="keep_filename" value="1" checked>
                Keep existing filename (<?php echo htmlspecialchars($photo['filename']); ?>)
            </label>
            <small>If unchecked, a new unique filename will be generated</small>
        </div>
        
        <button type="submit">Replace Image</button>
    </form>
    
    <div class="info">
        <h4>What this tool does:</h4>
        <ul>
            <li>Uploads your new image to replace the blue placeholder</li>
            <li>Creates a backup of the existing file (if keeping same filename)</li>
            <li>Generates a thumbnail version</li>
            <li>Updates the database if needed</li>
        </ul>
    </div>
    
    <hr>
    <p>
        <a href="/admin/photo_edit_test.php?id=<?php echo $photoId; ?>">← Back to Photo Editor</a> | 
        <a href="/photo.php?id=<?php echo $photoId; ?>" target="_blank">View Live Photo</a>
    </p>
</body>
</html>
