<?php
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$db = getDB();
$message = '';
$action = $_GET['action'] ?? 'list';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        $message = 'Invalid request. Please try again.';
    } else {
        switch ($_POST['form_action']) {
            case 'mark_read':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE contacts SET is_read = 1 WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $message = 'Message marked as read.';
                    }
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $db->prepare("DELETE FROM contacts WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $message = 'Message deleted successfully!';
                    }
                }
                break;
        }
    }
}

// Get contacts
if ($action === 'list') {
    $contacts = $db->query("SELECT * FROM contacts ORDER BY is_read ASC, created_at DESC")->fetchAll();
} elseif ($action === 'view') {
    $id = (int)($_GET['id'] ?? 0);
    $contact = null;
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->execute([$id]);
        $contact = $stmt->fetch();
        
        // Mark as read when viewed
        if ($contact && !$contact['is_read']) {
            $stmt = $db->prepare("UPDATE contacts SET is_read = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $contact['is_read'] = 1;
        }
    }
    if (!$contact) {
        redirect('/admin/contacts.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Admin</title>
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
                        <a href="/admin/contacts.php" class="text-earth-brown font-medium">Contacts</a>
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
            <!-- Contacts List -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Contact Messages</h1>
                <p class="mt-2 text-custom-blue">Messages submitted through your website contact form</p>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage overflow-hidden">
                <?php if (empty($contacts)): ?>
                    <div class="p-8 text-center">
                        <p class="text-custom-blue">No messages received yet.</p>
                    </div>
                <?php else: ?>
                    <table class="w-full">
                        <thead class="bg-light-sage">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-custom-blue uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-sage">
                            <?php foreach ($contacts as $contact): ?>
                                <tr class="<?php echo !$contact['is_read'] ? 'bg-custom-light-blue' : ''; ?>">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="font-medium text-earth-brown"><?php echo sanitizeInput($contact['name']); ?></div>
                                            <div class="text-sm text-custom-blue"><?php echo sanitizeInput($contact['email']); ?></div>
                                            <?php if ($contact['phone']): ?>
                                                <div class="text-sm text-custom-blue"><?php echo sanitizeInput($contact['phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-earth-brown"><?php echo sanitizeInput($contact['subject']); ?></div>
                                        <div class="text-sm text-custom-blue"><?php echo truncateText($contact['message'], 60); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-earth-brown"><?php echo formatDate($contact['created_at']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $contact['is_read'] ? 'bg-caramel text-earth-brown' : 'bg-sage text-white'; ?>">
                                            <?php echo $contact['is_read'] ? 'Read' : 'Unread'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <a href="?action=view&id=<?php echo $contact['id']; ?>" class="text-sage hover:text-earth-brown transition mr-4">View</a>
                                        <button onclick="deleteContact(<?php echo $contact['id']; ?>)" class="text-caramel hover:text-earth-brown transition">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'view' && isset($contact)): ?>
            <!-- View Contact Message -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-earth-brown">Contact Message</h1>
                <a href="/admin/contacts.php" class="text-earth-brown hover:text-caramel transition">← Back to messages</a>
            </div>

            <div class="bg-white rounded-lg shadow border border-sage">
                <div class="px-6 py-4 border-b border-sage">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-xl font-medium text-earth-brown"><?php echo sanitizeInput($contact['subject']); ?></h2>
                            <p class="text-custom-blue">From: <?php echo sanitizeInput($contact['name']); ?> &lt;<?php echo sanitizeInput($contact['email']); ?>&gt;</p>
                            <?php if ($contact['phone']): ?>
                                <p class="text-custom-blue">Phone: <?php echo sanitizeInput($contact['phone']); ?></p>
                            <?php endif; ?>
                            <p class="text-gray-500 text-sm">Received: <?php echo formatDate($contact['created_at']); ?></p>
                        </div>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $contact['is_read'] ? 'bg-caramel text-earth-brown' : 'bg-sage text-white'; ?>">
                            <?php echo $contact['is_read'] ? 'Read' : 'Unread'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="px-6 py-6">
                    <div class="prose max-w-none">
                        <p class="whitespace-pre-line text-earth-brown"><?php echo sanitizeInput($contact['message']); ?></p>
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-sage bg-light-sage">
                    <div class="flex space-x-4">
                        <a href="mailto:<?php echo urlencode($contact['email']); ?>?subject=Re: <?php echo urlencode($contact['subject']); ?>" 
                           class="bg-earth-brown text-white px-4 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                            Reply via Email
                        </a>
                        <button onclick="deleteContact(<?php echo $contact['id']; ?>)" 
                                class="bg-caramel text-earth-brown px-4 py-2 rounded hover:bg-earth-brown hover:text-white transition">
                            Delete Message
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete confirmation modal -->
    <div x-data="{ showModal: false, contactId: null }" x-show="showModal" x-cloak 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4 border border-sage">
            <h3 class="text-lg font-medium text-earth-brown mb-4">Confirm Deletion</h3>
            <p class="text-custom-blue mb-6">Are you sure you want to delete this message? This action cannot be undone.</p>
            <div class="flex space-x-4">
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="id" x-model="contactId">
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
        function deleteContact(id) {
            Alpine.data('modal', () => ({
                showModal: true,
                contactId: id
            }));
        }
    </script>
</body>
</html>
