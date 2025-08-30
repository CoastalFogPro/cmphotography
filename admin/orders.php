<?php
/**
 * Admin Orders Management
 * View order history and purchase records
 */
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$db = getDB();

// Get filter parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$queryParams = [];

if (!empty($status)) {
    $whereConditions[] = "o.status = ?";
    $queryParams[] = $status;
}

if (!empty($search)) {
    $whereConditions[] = "(o.customer_email LIKE ? OR p.title LIKE ? OR ps.size_label LIKE ?)";
    $searchTerm = "%$search%";
    $queryParams[] = $searchTerm;
    $queryParams[] = $searchTerm;
    $queryParams[] = $searchTerm;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get orders with photo and gallery information
$query = "SELECT o.*, 
                 p.title as photo_title, p.thumb_path, 
                 ps.size_label, 
                 g.title as gallery_title, g.slug as gallery_slug
          FROM orders o
          LEFT JOIN photos p ON o.photo_id = p.id
          LEFT JOIN photo_sizes ps ON o.size_id = ps.id  
          LEFT JOIN galleries g ON p.gallery_id = g.id
          $whereClause
          ORDER BY o.created_at DESC
          LIMIT 100";

$stmt = $db->prepare($query);
$stmt->execute($queryParams);
$orders = $stmt->fetchAll();

// Get order statistics
$stats = [
    'total' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'completed' => $db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn(),
    'pending' => $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'total_revenue' => $db->query("SELECT SUM(amount_cents) FROM orders WHERE status = 'completed'")->fetchColumn() ?: 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Admin</title>
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
                        <a href="/admin/services.php" class="text-custom-blue hover:text-earth-brown transition">Services</a>
                        <a href="/admin/settings.php" class="text-custom-blue hover:text-earth-brown transition">Settings</a>
                        <a href="/admin/contacts.php" class="text-custom-blue hover:text-earth-brown transition">Contacts</a>
                        <a href="/admin/orders.php" class="text-earth-brown font-medium">Orders</a>
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
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-earth-brown">Orders</h1>
            <p class="mt-2 text-custom-blue">View and track print purchases from customers</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-custom-light-blue">
                        <svg class="w-6 h-6 text-custom-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-earth-brown"><?php echo $stats['total']; ?></p>
                        <p class="text-custom-blue">Total Orders</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-light-sage">
                        <svg class="w-6 h-6 text-sage" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-earth-brown"><?php echo $stats['completed']; ?></p>
                        <p class="text-custom-blue">Completed</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-caramel bg-opacity-30">
                        <svg class="w-6 h-6 text-caramel" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-earth-brown"><?php echo $stats['pending']; ?></p>
                        <p class="text-custom-blue">Pending</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-light-sage">
                        <svg class="w-6 h-6 text-sage" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-earth-brown"><?php echo formatPrice($stats['total_revenue']); ?></p>
                        <p class="text-custom-blue">Total Revenue</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-lg shadow border border-sage mb-6">
            <form method="GET" class="flex flex-wrap items-center gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-custom-blue mb-1">Status</label>
                    <select name="status" id="status" class="px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                
                <div class="flex-1 min-w-0">
                    <label for="search" class="block text-sm font-medium text-custom-blue mb-1">Search</label>
                    <input type="text" name="search" id="search" value="<?php echo sanitizeInput($search); ?>"
                           placeholder="Email, photo title, or size..."
                           class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                </div>
                
                <div class="pt-6">
                    <button type="submit" class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                        Filter
                    </button>
                    <?php if (!empty($status) || !empty($search)): ?>
                        <a href="/admin/orders.php" class="ml-2 bg-sage text-white px-4 py-2 rounded hover:bg-custom-blue transition">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-lg shadow border border-sage overflow-hidden">
            <?php if (empty($orders)): ?>
                <div class="p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-custom-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-earth-brown">No orders found</h3>
                    <p class="mt-1 text-sm text-custom-blue">
                        <?php echo (!empty($status) || !empty($search)) ? 'Try adjusting your filters.' : 'Orders will appear here once customers start purchasing prints.'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-sage">
                        <thead class="bg-light-sage">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Photo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-sage">
                            <?php foreach ($orders as $order): ?>
                                <tr class="hover:bg-light-sage">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm">
                                            <div class="font-medium text-earth-brown">#<?php echo $order['id']; ?></div>
                                            <?php if ($order['stripe_payment_intent']): ?>
                                                <div class="text-custom-blue">
                                                    <code class="text-xs"><?php echo substr($order['stripe_payment_intent'], 0, 20); ?>...</code>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-earth-brown">
                                            <?php echo $order['customer_email'] ?: 'N/A'; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php if ($order['thumb_path']): ?>
                                                <div class="flex-shrink-0 h-12 w-12">
                                                    <img class="h-12 w-12 rounded-lg object-cover border border-sage" 
                                                         src="/assets/uploads/<?php echo $order['thumb_path']; ?>" 
                                                         alt="">
                                                </div>
                                            <?php endif; ?>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-earth-brown">
                                                    <?php echo $order['photo_title'] ?: 'Deleted Photo'; ?>
                                                </div>
                                                <?php if ($order['gallery_title']): ?>
                                                    <div class="text-sm text-custom-blue">
                                                        <?php echo sanitizeInput($order['gallery_title']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-earth-brown">
                                        <?php echo $order['size_label'] ?: 'N/A'; ?>
                                        <?php if ($order['quantity'] > 1): ?>
                                            <span class="text-custom-blue"> × <?php echo $order['quantity']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-earth-brown">
                                        <?php if ($order['amount_cents']): ?>
                                            <?php echo formatPrice($order['amount_cents'], $order['currency']); ?>
                                        <?php else: ?>
                                            <span class="text-custom-blue">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                     <?php echo $order['status'] === 'completed' ? 'bg-sage text-white' : 'bg-caramel text-earth-brown'; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-custom-blue">
                                        <?php echo formatDate($order['created_at']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if (count($orders) === 100): ?>
            <div class="mt-6 text-center">
                <p class="text-sm text-custom-blue">Showing latest 100 orders. Use filters to find specific orders.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
