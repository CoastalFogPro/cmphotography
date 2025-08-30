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
                $icon = sanitizeInput($_POST['icon'] ?? '');
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                
                if (empty($title) || empty($description)) {
                    $message = 'Please fill in all required fields.';
                } else {
                    $stmt = $db->prepare("INSERT INTO services (title, description, icon, sort_order) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$title, $description, $icon, $sort_order])) {
                        $message = 'Service created successfully!';
                        $action = 'list';
                    } else {
                        $message = 'Error creating service.';
                    }
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $title = sanitizeInput($_POST['title'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $icon = sanitizeInput($_POST['icon'] ?? '');
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id > 0 && !empty($title) && !empty($description)) {
                    $stmt = $db->prepare("UPDATE services SET title = ?, description = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?");
                    if ($stmt->execute([$title, $description, $icon, $sort_order, $is_active, $id])) {
                        $message = 'Service updated successfully!';
                        $action = 'list';
                    } else {
                        $message = 'Error updating service.';
                    }
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $message = 'Service deleted successfully!';
                    } else {
                        $message = 'Error deleting service.';
                    }
                }
                break;
        }
    }
}

// Get data based on action
if ($action === 'list') {
    $services = $db->query("SELECT * FROM services ORDER BY sort_order ASC, created_at DESC")->fetchAll();
} elseif ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $editService = null;
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM services WHERE id = ?");
        $stmt->execute([$id]);
        $editService = $stmt->fetch();
    }
    if (!$editService) {
        redirect('/admin/services.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - Admin</title>
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
                        <a href="/admin/products.php" class="text-custom-blue hover:text-earth-brown transition">Products</a>
                        <a href="/admin/services.php" class="text-earth-brown font-medium">Services</a>
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
            <!-- Services List -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Services</h1>
                <a href="?action=add" class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">Add New Service</a>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage overflow-hidden">
                <?php if (empty($services)): ?>
                    <div class="p-8 text-center">
                        <p class="text-custom-blue">No services added yet.</p>
                        <a href="?action=add" class="text-earth-brown hover:text-caramel transition">Add your first service</a>
                    </div>
                <?php else: ?>
                    <table class="w-full">
                        <thead class="bg-light-sage">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Icon</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-sage">
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="font-medium text-earth-brown"><?php echo sanitizeInput($service['title']); ?></div>
                                            <div class="text-sm text-custom-blue"><?php echo truncateText($service['description'], 100); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-earth-brown"><?php echo sanitizeInput($service['icon']); ?></td>
                                    <td class="px-6 py-4 text-sm text-earth-brown"><?php echo $service['sort_order']; ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $service['is_active'] ? 'bg-sage text-white' : 'bg-caramel text-earth-brown'; ?>">
                                            <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <a href="?action=edit&id=<?php echo $service['id']; ?>" class="text-sage hover:text-earth-brown transition mr-4">Edit</a>
                                        <button onclick="deleteService(<?php echo $service['id']; ?>)" class="text-caramel hover:text-earth-brown transition">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Add/Edit Service Form -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">
                    <?php echo $action === 'edit' ? 'Edit Service' : 'Add New Service'; ?>
                </h1>
                <a href="/admin/services.php" class="text-earth-brown hover:text-caramel transition">← Back to services</a>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="<?php echo $action === 'edit' ? 'update' : 'create'; ?>">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?php echo $editService['id']; ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-custom-blue mb-2">Title *</label>
                            <input type="text" name="title" id="title" required
                                   value="<?php echo isset($editService) ? sanitizeInput($editService['title']) : ''; ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <div>
                            <label for="icon" class="block text-sm font-medium text-custom-blue mb-2">Icon Name</label>
                            <input type="text" name="icon" id="icon" placeholder="e.g., camera, calendar, mountain"
                                   value="<?php echo isset($editService) ? sanitizeInput($editService['icon']) : ''; ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                            <p class="text-sm text-gray-500 mt-1">Use Heroicons icon names</p>
                        </div>

                        <div>
                            <label for="sort_order" class="block text-sm font-medium text-custom-blue mb-2">Sort Order</label>
                            <input type="number" name="sort_order" id="sort_order"
                                   value="<?php echo isset($editService) ? $editService['sort_order'] : '0'; ?>"
                                   class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        </div>

                        <?php if ($action === 'edit'): ?>
                        <div class="flex items-center">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" <?php echo $editService['is_active'] ? 'checked' : ''; ?>
                                       class="h-4 w-4 text-earth-brown focus:ring-earth-brown border-sage rounded">
                                <span class="ml-2 text-sm text-earth-brown">Active (visible on website)</span>
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-custom-blue mb-2">Description *</label>
                        <textarea name="description" id="description" rows="4" required
                                  class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"><?php echo isset($editService) ? sanitizeInput($editService['description']) : ''; ?></textarea>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                            <?php echo $action === 'edit' ? 'Update' : 'Create'; ?> Service
                        </button>
                        <a href="/admin/services.php" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete confirmation modal -->
    <div x-data="{ showModal: false, serviceId: null }" x-show="showModal" x-cloak 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4 border border-sage">
            <h3 class="text-lg font-medium text-earth-brown mb-4">Confirm Deletion</h3>
            <p class="text-custom-blue mb-6">Are you sure you want to delete this service? This action cannot be undone.</p>
            <div class="flex space-x-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="id" x-model="serviceId">
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
        function deleteService(id) {
            Alpine.data('modal', () => ({
                showModal: true,
                serviceId: id
            }));
        }
    </script>
</body>
</html>
