<?php
require_once __DIR__ . '/../includes/db.php';
requireLogin();

// Get dashboard stats
$db = getDB();

$stats = [
    'galleries' => $db->query("SELECT COUNT(*) FROM galleries WHERE status = 'active'")->fetchColumn(),
    'photos' => $db->query("SELECT COUNT(*) FROM photos")->fetchColumn(),
    'orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'contacts' => $db->query("SELECT COUNT(*) FROM contacts WHERE is_read = 0")->fetchColumn(),
    'revenue' => $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'")->fetchColumn() / 100
];

// Recent contacts
$recentContacts = $db->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo getSetting('site_title', 'Photography Portfolio'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
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
                        <a href="/admin/" class="text-earth-brown font-medium">Dashboard</a>
                        <a href="/admin/galleries.php" class="text-custom-blue hover:text-earth-brown transition">Galleries</a>
                        <a href="/admin/images.php" class="text-custom-blue hover:text-earth-brown transition">Images</a>
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
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-earth-brown">Dashboard</h1>
            <p class="mt-2 text-custom-blue">Welcome to your photography website admin panel</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-custom-light-blue">
                        <svg class="w-6 h-6 text-custom-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-earth-brown"><?php echo $stats['galleries']; ?></p>
                        <p class="text-custom-blue">Active Galleries</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-light-sage">
                        <svg class="w-6 h-6 text-sage" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-earth-brown"><?php echo $stats['photos']; ?></p>
                        <p class="text-custom-blue">Photos</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-light-sage">
                        <svg class="w-6 h-6 text-earth-brown" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-earth-brown"><?php echo $stats['orders']; ?></p>
                        <p class="text-custom-blue">Total Orders</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-custom-light-blue">
                        <svg class="w-6 h-6 text-caramel" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-earth-brown">$<?php echo number_format($stats['revenue'], 2); ?></p>
                        <p class="text-custom-blue">Total Revenue</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-light-sage">
                        <svg class="w-6 h-6 text-dusty-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-earth-brown"><?php echo $stats['contacts']; ?></p>
                        <p class="text-custom-blue">Unread Messages</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <h3 class="text-lg font-medium text-earth-brown mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="/admin/galleries.php?action=add" class="block w-full bg-earth-brown text-white text-center py-2 px-4 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                        Create New Gallery
                    </a>
                    <a href="/admin/photos.php" class="block w-full bg-sage text-white text-center py-2 px-4 rounded hover:bg-custom-blue transition">
                        Upload Photos
                    </a>
                    <a href="/admin/orders.php" class="block w-full bg-dusty-blue text-white text-center py-2 px-4 rounded hover:bg-custom-blue transition">
                        View Orders
                    </a>
                    <a href="/admin/settings.php" class="block w-full bg-caramel text-white text-center py-2 px-4 rounded hover:bg-earth-brown transition">
                        Edit Site Settings
                    </a>
                </div>
            </div>

            <!-- Recent Contact Messages -->
            <div class="bg-white p-6 rounded-lg shadow border border-sage">
                <h3 class="text-lg font-medium text-earth-brown mb-4">Recent Messages</h3>
                <?php if (empty($recentContacts)): ?>
                    <p class="text-custom-blue">No messages yet</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recentContacts as $contact): ?>
                            <div class="border-l-4 <?php echo $contact['is_read'] ? 'border-sage' : 'border-earth-brown'; ?> pl-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-earth-brown"><?php echo sanitizeInput($contact['name']); ?></p>
                                        <p class="text-sm text-custom-blue"><?php echo sanitizeInput($contact['email']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo formatDate($contact['created_at']); ?></p>
                                    </div>
                                    <?php if (!$contact['is_read']): ?>
                                        <span class="bg-custom-light-blue text-earth-brown text-xs px-2 py-1 rounded font-medium">New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <a href="/admin/contacts.php" class="block text-earth-brown hover:text-caramel text-sm transition">View all messages →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
