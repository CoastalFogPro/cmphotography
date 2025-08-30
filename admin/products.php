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
            case 'create':
                $title = sanitizeInput($_POST['title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $size = sanitizeInput($_POST['size'] ?? '');
                $price = (float)($_POST['price'] ?? 0);
                $stripe_link = sanitizeInput($_POST['stripe_link'] ?? '');
                $image_id = (int)($_POST['image_id'] ?? 0) ?: null;
                
                if (empty($title) || $price <= 0) {
                    $message = 'Please fill in all required fields.';
                } else {
                    $stmt = $db->prepare("INSERT INTO products (title, description, size, price, stripe_link, image_id) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$title, $description, $size, $price, $stripe_link, $image_id])) {
                        $message = 'Product created successfully!';
                        $action = 'list';
                    } else {
                        $message = 'Error creating product.';
                    }
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $size = sanitizeInput($_POST['size'] ?? '');
                $price = (float)($_POST['price'] ?? 0);
                $stripe_link = sanitizeInput($_POST['stripe_link'] ?? '');
                $image_id = (int)($_POST['image_id'] ?? 0) ?: null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id > 0 && !empty($title) && $price > 0) {
                    $stmt = $db->prepare("UPDATE products SET title = ?, description = ?, size = ?, price = ?, stripe_link = ?, image_id = ?, is_active = ? WHERE id = ?");
                    if ($stmt->execute([$title, $description, $size, $price, $stripe_link, $image_id, $is_active, $id])) {
                        $message = 'Product updated successfully!';
                        $action = 'list';
                    } else {
                        $message = 'Error updating product.';
                    }
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $message = 'Product deleted successfully!';
                    } else {
                        $message = 'Error deleting product.';
                    }
                }
                break;
        }
    }
}

// Get data based on action
if ($action === 'list') {
    $products = $db->query("SELECT p.*, i.filename as image_filename FROM products p LEFT JOIN images i ON p.image_id = i.id ORDER BY p.sort_order ASC, p.created_at DESC")->fetchAll();
} elseif ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $editProduct = null;
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $editProduct = $stmt->fetch();
    }
    if (!$editProduct) {
        redirect('/admin/products.php');
    }
}

// Get available images for product selection
$images = $db->query("SELECT id, title, filename FROM images ORDER BY title ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin</title>
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
                        <a href="/admin/products.php" class="text-earth-brown font-medium">Products</a>
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
            <!-- Products List -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Products</h1>
                <a href="?action=add" class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">Add New Product</a>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage overflow-hidden">
                <?php if (empty($products)): ?>
                    <div class="p-8 text-center">
                        <p class="text-custom-blue">No products added yet.</p>
                        <a href="?action=add" class="text-earth-brown hover:text-caramel transition">Add your first product</a>
                    </div>
                <?php else: ?>
                    <table class="w-full">
                        <thead class="bg-light-sage">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-sage">
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <?php if ($product['image_filename']): ?>
                                                <img src="/assets/uploads/thumbs/<?php echo $product['image_filename']; ?>" 
                                                     class="w-12 h-12 rounded object-cover mr-4">
                                            <?php else: ?>
                                                <div class="w-12 h-12 bg-light-sage rounded mr-4 flex items-center justify-center border border-sage">
                                                    <svg class="w-6 h-6 text-custom-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="font-medium text-earth-brown"><?php echo sanitizeInput($product['title']); ?></div>
                                                <?php if ($product['description']): ?>
                                                    <div class="text-sm text-custom-blue"><?php echo truncateText($product['description'], 60); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-earth-brown"><?php echo sanitizeInput($product['size']); ?></td>
                                    <td class="px-6 py-4 text-sm text-earth-brown">$<?php echo number_format($product['price'], 2); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $product['is_active'] ? 'bg-sage text-white' : 'bg-caramel text-earth-brown'; ?>">
                                            <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <a href="?action=edit&id=<?php echo $product['id']; ?>" class="text-sage hover:text-earth-brown transition mr-4">Edit</a>
                                        <button onclick="deleteProduct(<?php echo $product['id']; ?>)" class="text-caramel hover:text-earth-brown transition">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'add'): ?>
            <!-- Add Product Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Add New Product</h1>
                <a href="/admin/products.php" class="text-earth-brown hover:text-caramel transition">← Back to products</a>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="create">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-custom-blue mb-2">Title *</label>
                            <input type="text" name="title" id="title" required
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div>
                            <label for="price" class="block text-sm font-medium text-custom-blue mb-2">Price *</label>
                            <input type="number" step="0.01" name="price" id="price" required
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div>
                            <label for="size" class="block text-sm font-medium text-custom-blue mb-2">Size</label>
                            <input type="text" name="size" id="size" placeholder="e.g., 8x10, 11x14, 16x20"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div>
                            <label for="image_id" class="block text-sm font-medium text-custom-blue mb-2">Associated Image</label>
                            <select name="image_id" id="image_id" 
                                    class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                                <option value="">Select an image</option>
                                <?php foreach ($images as $image): ?>
                                    <option value="<?php echo $image['id']; ?>">
                                        <?php echo $image['title'] ?: 'Untitled - ' . $image['filename']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-custom-blue mb-2">Description</label>
                        <textarea name="description" id="description" rows="4"
                                  class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"></textarea>
                    </div>

                    <div>
                        <label for="stripe_link" class="block text-sm font-medium text-custom-blue mb-2">Stripe Payment Link</label>
                        <input type="url" name="stripe_link" id="stripe_link" placeholder="https://buy.stripe.com/..."
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        <p class="text-sm text-gray-500 mt-1">Create this link in your Stripe dashboard</p>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                            Create Product
                        </button>
                        <a href="/admin/products.php" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'edit' && isset($editProduct)): ?>
            <!-- Edit Product Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Edit Product</h1>
                <a href="/admin/products.php" class="text-earth-brown hover:text-caramel transition">← Back to products</a>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="update">
                    <input type="hidden" name="id" value="<?php echo $editProduct['id']; ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-custom-blue mb-2">Title *</label>
                            <input type="text" name="title" id="title" required value="<?php echo sanitizeInput($editProduct['title']); ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div>
                            <label for="price" class="block text-sm font-medium text-custom-blue mb-2">Price *</label>
                            <input type="number" step="0.01" name="price" id="price" required value="<?php echo $editProduct['price']; ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div>
                            <label for="size" class="block text-sm font-medium text-custom-blue mb-2">Size</label>
                            <input type="text" name="size" id="size" value="<?php echo sanitizeInput($editProduct['size']); ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div>
                            <label for="image_id" class="block text-sm font-medium text-custom-blue mb-2">Associated Image</label>
                            <select name="image_id" id="image_id" 
                                    class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                                <option value="">Select an image</option>
                                <?php foreach ($images as $image): ?>
                                    <option value="<?php echo $image['id']; ?>" <?php echo $editProduct['image_id'] == $image['id'] ? 'selected' : ''; ?>>
                                        <?php echo $image['title'] ?: 'Untitled - ' . $image['filename']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-custom-blue mb-2">Description</label>
                        <textarea name="description" id="description" rows="4"
                                  class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"><?php echo sanitizeInput($editProduct['description']); ?></textarea>
                    </div>

                    <div>
                        <label for="stripe_link" class="block text-sm font-medium text-custom-blue mb-2">Stripe Payment Link</label>
                        <input type="url" name="stripe_link" id="stripe_link" value="<?php echo sanitizeInput($editProduct['stripe_link']); ?>"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>

                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" <?php echo $editProduct['is_active'] ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-earth-brown focus:ring-earth-brown border-sage rounded">
                            <span class="ml-2 text-sm text-earth-brown">Active (visible on website)</span>
                        </label>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                            Update Product
                        </button>
                        <a href="/admin/products.php" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete confirmation modal -->
    <div x-data="{ showModal: false, productId: null }" x-show="showModal" x-cloak 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4 border border-sage">
            <h3 class="text-lg font-medium text-earth-brown mb-4">Confirm Deletion</h3>
            <p class="text-custom-blue mb-6">Are you sure you want to delete this product? This action cannot be undone.</p>
            <div class="flex space-x-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="id" x-model="productId">
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
        function deleteProduct(id) {
            Alpine.data('modal', () => ({
                showModal: true,
                productId: id
            }));
        }
    </script>
</body>
</html>
