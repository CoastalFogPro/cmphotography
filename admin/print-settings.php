<?php
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$db = getDB();
$success = $error = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_size'])) {
        // Add new print size
        $stmt = $db->prepare("INSERT INTO print_sizes (size_label, dimensions, price, description, sort_order) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([
            $_POST['size_label'],
            $_POST['dimensions'],
            $_POST['price'],
            $_POST['description'],
            $_POST['sort_order'] ?: 999
        ])) {
            $success = 'Print size added successfully!';
        } else {
            $error = 'Failed to add print size.';
        }
    } elseif (isset($_POST['update_size'])) {
        // Update existing print size
        $stmt = $db->prepare("UPDATE print_sizes SET size_label = ?, dimensions = ?, price = ?, description = ?, sort_order = ?, is_active = ? WHERE id = ?");
        if ($stmt->execute([
            $_POST['size_label'],
            $_POST['dimensions'],
            $_POST['price'],
            $_POST['description'],
            $_POST['sort_order'],
            isset($_POST['is_active']) ? 1 : 0,
            $_POST['size_id']
        ])) {
            $success = 'Print size updated successfully!';
        } else {
            $error = 'Failed to update print size.';
        }
    } elseif (isset($_POST['delete_size'])) {
        // Delete print size
        $stmt = $db->prepare("DELETE FROM print_sizes WHERE id = ?");
        if ($stmt->execute([$_POST['size_id']])) {
            $success = 'Print size deleted successfully!';
        } else {
            $error = 'Failed to delete print size.';
        }
    }
}

// Get all print sizes
$printSizes = $db->query("SELECT * FROM print_sizes ORDER BY sort_order ASC, size_label ASC")->fetchAll();

// Get editing size if specified
$editingSize = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM print_sizes WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editingSize = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Settings - Admin</title>
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
                        <a href="/admin/recent-work.php" class="text-custom-blue hover:text-earth-brown transition">Recent Work</a>
                        <a href="/admin/print-settings.php" class="text-earth-brown font-medium">Print Settings</a>
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
            <h1 class="text-3xl font-bold text-earth-brown">Print Settings</h1>
            <p class="mt-2 text-custom-blue">Manage global print sizes and pricing that will be used across all galleries</p>
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Add/Edit Print Size Form -->
            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <h3 class="text-lg font-medium text-earth-brown mb-4">
                    <?php echo $editingSize ? 'Edit Print Size' : 'Add New Print Size'; ?>
                </h3>
                
                <form method="POST" class="space-y-4">
                    <?php if ($editingSize): ?>
                        <input type="hidden" name="size_id" value="<?php echo $editingSize['id']; ?>">
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-medium text-earth-brown mb-2">Size Label *</label>
                        <input type="text" name="size_label" required
                               value="<?php echo $editingSize['size_label'] ?? ''; ?>"
                               placeholder="e.g., Small Print, Canvas Large"
                               class="w-full px-3 py-2 border border-sage rounded focus:outline-none focus:ring-2 focus:ring-earth-brown">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-earth-brown mb-2">Dimensions *</label>
                        <input type="text" name="dimensions" required
                               value="<?php echo $editingSize['dimensions'] ?? ''; ?>"
                               placeholder="e.g., 8x10\", 16x20\""
                               class="w-full px-3 py-2 border border-sage rounded focus:outline-none focus:ring-2 focus:ring-earth-brown">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-earth-brown mb-2">Price (USD) *</label>
                        <input type="number" name="price" step="0.01" required
                               value="<?php echo $editingSize['price'] ?? ''; ?>"
                               placeholder="25.00"
                               class="w-full px-3 py-2 border border-sage rounded focus:outline-none focus:ring-2 focus:ring-earth-brown">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-earth-brown mb-2">Description</label>
                        <textarea name="description" rows="3"
                                  placeholder="Brief description of this print size"
                                  class="w-full px-3 py-2 border border-sage rounded focus:outline-none focus:ring-2 focus:ring-earth-brown"><?php echo $editingSize['description'] ?? ''; ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-earth-brown mb-2">Sort Order</label>
                        <input type="number" name="sort_order"
                               value="<?php echo $editingSize['sort_order'] ?? ''; ?>"
                               placeholder="1"
                               class="w-full px-3 py-2 border border-sage rounded focus:outline-none focus:ring-2 focus:ring-earth-brown">
                    </div>
                    
                    <?php if ($editingSize): ?>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="is_active" 
                                   <?php echo $editingSize['is_active'] ? 'checked' : ''; ?>
                                   class="rounded border-sage text-earth-brown focus:ring-earth-brown">
                            <label for="is_active" class="ml-2 text-sm text-earth-brown">Active</label>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex gap-4">
                        <?php if ($editingSize): ?>
                            <button type="submit" name="update_size" 
                                    class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-opacity-90 transition">
                                Update Print Size
                            </button>
                            <a href="/admin/print-settings.php" 
                               class="bg-custom-blue text-white px-4 py-2 rounded hover:bg-opacity-90 transition">
                                Cancel
                            </a>
                        <?php else: ?>
                            <button type="submit" name="add_size" 
                                    class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-opacity-90 transition">
                                Add Print Size
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Current Print Sizes -->
            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <h3 class="text-lg font-medium text-earth-brown mb-4">Current Print Sizes</h3>
                
                <?php if (empty($printSizes)): ?>
                    <p class="text-custom-blue">No print sizes configured yet.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($printSizes as $size): ?>
                            <div class="border border-sage rounded p-4 <?php echo $size['is_active'] ? '' : 'bg-gray-50 opacity-75'; ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-earth-brown">
                                            <?php echo htmlspecialchars($size['size_label']); ?>
                                            <?php if (!$size['is_active']): ?>
                                                <span class="text-xs text-gray-500">(Inactive)</span>
                                            <?php endif; ?>
                                        </h4>
                                        <p class="text-sm text-custom-blue"><?php echo htmlspecialchars($size['dimensions']); ?></p>
                                        <p class="text-lg font-semibold text-earth-brown">$<?php echo number_format($size['price'], 2); ?></p>
                                        <?php if ($size['description']): ?>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($size['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="?edit=<?php echo $size['id']; ?>" 
                                           class="text-custom-blue hover:text-earth-brown text-sm">Edit</a>
                                        <form method="POST" class="inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this print size?')">
                                            <input type="hidden" name="size_id" value="<?php echo $size['id']; ?>">
                                            <button type="submit" name="delete_size" 
                                                    class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Usage Instructions -->
        <div class="mt-8 bg-white p-6 rounded-lg shadow border border-sage">
            <h3 class="text-lg font-medium text-earth-brown mb-4">How Print Settings Work</h3>
            <div class="prose text-custom-blue">
                <p>Print sizes configured here will be available when adding photos to galleries. For each photo you upload, you can:</p>
                <ul class="list-disc ml-6 mt-2 space-y-1">
                    <li>Select which print sizes to make available for purchase</li>
                    <li>Use the default price or set custom pricing per photo</li>
                    <li>Add Stripe payment links for each size</li>
                </ul>
                <p class="mt-4">This system allows you to maintain consistent pricing across your galleries while still having flexibility for individual photos.</p>
            </div>
        </div>
    </div>
</body>
</html>
