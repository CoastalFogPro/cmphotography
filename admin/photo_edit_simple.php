<?php
/**
 * Simplified Photo Editor with Error Handling
 * Debug version to identify photo update issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>Simple Photo Editor (Debug Mode)</h2>";

try {
    require_once __DIR__ . '/../includes/db.php';
    
    // Check if user is logged in (simple check)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo "<p style='color: red;'>Error: Not logged in</p>";
        echo "<a href='/admin/login.php'>Please login</a>";
        exit;
    }

    $db = getDB();
    $message = '';
    $photoId = (int)($_GET['id'] ?? 1);

    echo "<h3>Photo ID: $photoId</h3>";

    // Get photo data
    $stmt = $db->prepare("SELECT p.*, g.title as gallery_title, g.id as gallery_id FROM photos p JOIN galleries g ON p.gallery_id = g.id WHERE p.id = ?");
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();

    if (!$photo) {
        echo "<p style='color: red;'>Photo not found with ID: $photoId</p>";
        exit;
    }

    echo "<h3>Photo Data:</h3>";
    echo "<pre>";
    print_r($photo);
    echo "</pre>";

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<h3>Processing Form Submission...</h3>";
        
        $title = $_POST['title'] ?? '';
        $altText = $_POST['alt_text'] ?? '';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        
        echo "<p>Received data:</p>";
        echo "<ul>";
        echo "<li>Title: '$title'</li>";
        echo "<li>Alt Text: '$altText'</li>";
        echo "<li>Sort Order: $sortOrder</li>";
        echo "</ul>";
        
        try {
            $stmt = $db->prepare("UPDATE photos SET title = ?, alt_text = ?, sort_order = ? WHERE id = ?");
            $result = $stmt->execute([$title, $altText, $sortOrder, $photoId]);
            
            if ($result) {
                $message = "<p style='color: green;'>✓ Photo updated successfully! Rows affected: " . $stmt->rowCount() . "</p>";
                
                // Refresh photo data
                $stmt = $db->prepare("SELECT p.*, g.title as gallery_title, g.id as gallery_id FROM photos p JOIN galleries g ON p.gallery_id = g.id WHERE p.id = ?");
                $stmt->execute([$photoId]);
                $photo = $stmt->fetch();
                
                echo "<h3>Updated Photo Data:</h3>";
                echo "<pre>";
                print_r($photo);
                echo "</pre>";
            } else {
                $errorInfo = $stmt->errorInfo();
                $message = "<p style='color: red;'>✗ Update failed: " . $errorInfo[2] . "</p>";
            }
        } catch (Exception $e) {
            $message = "<p style='color: red;'>✗ Exception during update: " . $e->getMessage() . "</p>";
        }
    }

    if ($message) {
        echo $message;
    }

} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>FATAL ERROR: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple Photo Editor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin: 15px 0; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input, textarea { width: 300px; padding: 5px; }
        button { background: #0066cc; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        button:hover { background: #0052a3; }
        .photo-preview { border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
        .photo-preview img { max-width: 200px; max-height: 200px; }
    </style>
</head>
<body>
    <h1>Edit Photo: <?php echo htmlspecialchars($photo['title'] ?: 'Untitled'); ?></h1>
    
    <div class="photo-preview">
        <h3>Photo Preview</h3>
        <img src="/assets/uploads/full/<?php echo htmlspecialchars($photo['filename']); ?>" 
             alt="<?php echo htmlspecialchars($photo['alt_text'] ?? 'Photo'); ?>"
             onerror="this.parentElement.innerHTML='<p style=\'color: red;\'>Image failed to load: /assets/uploads/full/<?php echo htmlspecialchars($photo['filename']); ?></p>'">
        <p><strong>Filename:</strong> <?php echo htmlspecialchars($photo['filename']); ?></p>
        <p><strong>Gallery:</strong> <?php echo htmlspecialchars($photo['gallery_title']); ?></p>
        <p><strong>Uploaded:</strong> <?php echo $photo['created_at']; ?></p>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label for="title">Title:</label>
            <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($photo['title']); ?>">
        </div>
        
        <div class="form-group">
            <label for="alt_text">Alt Text:</label>
            <input type="text" name="alt_text" id="alt_text" value="<?php echo htmlspecialchars($photo['alt_text'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="sort_order">Sort Order:</label>
            <input type="number" name="sort_order" id="sort_order" value="<?php echo $photo['sort_order']; ?>" min="0">
        </div>
        
        <button type="submit">Update Photo</button>
    </form>
    
    <p><a href="/admin/galleries.php">← Back to Galleries</a></p>
    <p><a href="/photo.php?id=<?php echo $photoId; ?>" target="_blank">View Live Photo</a></p>
</body>
</html>
