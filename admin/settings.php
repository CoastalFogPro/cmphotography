<?php
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$db = getDB();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        $message = 'Invalid request. Please try again.';
    } else {
        $settings = [
            'site_title', 'site_tagline', 'about_text', 'contact_email',
            'phone', 'address', 'social_instagram', 'social_facebook', 'social_twitter'
        ];
        
        $success = true;
        foreach ($settings as $setting) {
            $value = sanitizeInput($_POST[$setting] ?? '');
            if (!setSetting($setting, $value)) {
                $success = false;
                break;
            }
        }
        
        // Handle file uploads for hero and about images
        if (isset($_FILES['hero_image']) && $_FILES['hero_image']['tmp_name']) {
            $result = uploadImage($_FILES['hero_image'], 'Hero Image', 'hero');
            if ($result['success']) {
                setSetting('hero_image', $result['filename']);
            } else {
                $message = 'Error uploading hero image: ' . $result['error'];
                $success = false;
            }
        }
        
        if (isset($_FILES['about_image']) && $_FILES['about_image']['tmp_name']) {
            $result = uploadImage($_FILES['about_image'], 'About Image', 'about');
            if ($result['success']) {
                setSetting('about_image', $result['filename']);
            } else {
                $message = 'Error uploading about image: ' . $result['error'];
                $success = false;
            }
        }
        
        if ($success && empty($message)) {
            $message = 'Settings updated successfully!';
        }
    }
}

// Get current settings
$currentSettings = [];
$settingsQuery = $db->query("SELECT setting_key, setting_value FROM settings");
while ($row = $settingsQuery->fetch()) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                        <a href="/admin/galleries.php" class="text-custom-blue hover:text-earth-brown transition">Galleries</a>
                        <a href="/admin/recent-work.php" class="text-custom-blue hover:text-earth-brown transition">Recent Work</a>
                        <a href="/admin/print-settings.php" class="text-custom-blue hover:text-earth-brown transition">Print Settings</a>
                        <a href="/admin/orders.php" class="text-custom-blue hover:text-earth-brown transition">Orders</a>
                        <a href="/admin/settings.php" class="text-earth-brown font-medium">Settings</a>
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

    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-light-sage border border-sage text-earth-brown rounded-lg">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="mb-6">
            <h1 class="text-3xl font-bold text-earth-brown">Site Settings</h1>
            <p class="mt-2 text-custom-blue">Manage your website content and configuration</p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <!-- Basic Information -->
            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <h3 class="text-lg font-medium text-earth-brown mb-4">Basic Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="site_title" class="block text-sm font-medium text-custom-blue mb-2">Site Title</label>
                        <input type="text" name="site_title" id="site_title"
                               value="<?php echo sanitizeInput($currentSettings['site_title'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>

                    <div>
                        <label for="site_tagline" class="block text-sm font-medium text-custom-blue mb-2">Site Tagline</label>
                        <input type="text" name="site_tagline" id="site_tagline"
                               value="<?php echo sanitizeInput($currentSettings['site_tagline'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>
                </div>
            </div>

            <!-- Hero Section -->
            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <h3 class="text-lg font-medium text-earth-brown mb-4">Hero Section</h3>
                <div>
                    <label for="hero_image" class="block text-sm font-medium text-custom-blue mb-2">Hero Background Image</label>
                    <?php if (!empty($currentSettings['hero_image'])): ?>
                        <div class="mb-4">
                            <img src="/assets/uploads/thumbs/<?php echo $currentSettings['hero_image']; ?>" 
                                 alt="Current hero image" class="w-32 h-20 object-cover rounded border border-sage">
                            <p class="text-sm text-gray-500 mt-1">Current hero image</p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="hero_image" id="hero_image" accept=".jpg,.jpeg,.png,.webp"
                           class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    <p class="text-sm text-gray-500 mt-1">Leave empty to keep current image</p>
                </div>
            </div>

            <!-- About Section -->
            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <h3 class="text-lg font-medium text-earth-brown mb-4">About Section</h3>
                <div class="space-y-6">
                    <div>
                        <label for="about_image" class="block text-sm font-medium text-custom-blue mb-2">About Photo</label>
                        <?php if (!empty($currentSettings['about_image'])): ?>
                            <div class="mb-4">
                                <img src="/assets/uploads/thumbs/<?php echo $currentSettings['about_image']; ?>" 
                                     alt="Current about image" class="w-32 h-32 object-cover rounded border border-sage">
                                <p class="text-sm text-gray-500 mt-1">Current about photo</p>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="about_image" id="about_image" accept=".jpg,.jpeg,.png,.webp"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>

                    <div>
                        <label for="about_text" class="block text-sm font-medium text-custom-blue mb-2">About Text</label>
                        <textarea name="about_text" id="about_text" rows="6"
                                  class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"><?php echo sanitizeInput($currentSettings['about_text'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <h3 class="text-lg font-medium text-earth-brown mb-4">Contact Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-custom-blue mb-2">Email</label>
                        <input type="email" name="contact_email" id="contact_email"
                               value="<?php echo sanitizeInput($currentSettings['contact_email'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-custom-blue mb-2">Phone</label>
                        <input type="text" name="phone" id="phone"
                               value="<?php echo sanitizeInput($currentSettings['phone'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>

                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-custom-blue mb-2">Address</label>
                        <input type="text" name="address" id="address"
                               value="<?php echo sanitizeInput($currentSettings['address'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>
                </div>
            </div>

            <!-- Stripe Configuration Status (Read-Only) -->
            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <h3 class="text-lg font-medium text-earth-brown mb-4">Stripe Configuration Status</h3>
                <div class="bg-light-sage rounded-lg p-4">
                    <p class="text-sm text-custom-blue mb-4">Stripe keys are configured in config.php and cannot be edited here for security.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php
                        $stripeKeys = [
                            'Secret Key' => defined('STRIPE_SECRET_KEY') && !empty(STRIPE_SECRET_KEY) && STRIPE_SECRET_KEY !== 'sk_test_...',
                            'Publishable Key' => defined('STRIPE_PUBLISHABLE_KEY') && !empty(STRIPE_PUBLISHABLE_KEY) && STRIPE_PUBLISHABLE_KEY !== 'pk_test_...',
                            'Webhook Secret' => defined('STRIPE_WEBHOOK_SECRET') && !empty(STRIPE_WEBHOOK_SECRET) && STRIPE_WEBHOOK_SECRET !== 'whsec_...'
                        ];
                        
                        foreach ($stripeKeys as $keyName => $isConfigured):
                        ?>
                            <div class="flex items-center justify-between py-2 px-3 bg-white rounded border border-sage">
                                <span class="text-sm font-medium text-custom-blue"><?php echo $keyName; ?></span>
                                <span class="px-2 py-1 text-xs rounded <?php echo $isConfigured ? 'bg-sage text-white' : 'bg-caramel text-earth-brown'; ?>">
                                    <?php echo $isConfigured ? 'Configured' : 'Not Set'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 text-xs text-gray-500">
                        <p>Edit these values in your config.php file:</p>
                        <ul class="list-disc ml-4 mt-1 space-y-1">
                            <li>STRIPE_SECRET_KEY - Your Stripe secret key</li>
                            <li>STRIPE_PUBLISHABLE_KEY - Your Stripe publishable key</li> 
                            <li>STRIPE_WEBHOOK_SECRET - Webhook endpoint secret for order processing</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Social Media -->
            <div class="bg-white rounded-lg shadow border border-sage p-6">
                <h3 class="text-lg font-medium text-earth-brown mb-4">Social Media Links</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="social_instagram" class="block text-sm font-medium text-custom-blue mb-2">Instagram URL</label>
                        <input type="url" name="social_instagram" id="social_instagram" placeholder="https://instagram.com/username"
                               value="<?php echo sanitizeInput($currentSettings['social_instagram'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>

                    <div>
                        <label for="social_facebook" class="block text-sm font-medium text-custom-blue mb-2">Facebook URL</label>
                        <input type="url" name="social_facebook" id="social_facebook" placeholder="https://facebook.com/username"
                               value="<?php echo sanitizeInput($currentSettings['social_facebook'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>

                    <div>
                        <label for="social_twitter" class="block text-sm font-medium text-custom-blue mb-2">Twitter URL</label>
                        <input type="url" name="social_twitter" id="social_twitter" placeholder="https://twitter.com/username"
                               value="<?php echo sanitizeInput($currentSettings['social_twitter'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
                    </div>
                </div>
            </div>

            <div class="flex space-x-4">
                <button type="submit" class="bg-earth-brown text-white px-6 py-2 rounded hover:bg-earth-brown hover:bg-opacity-90 transition">
                    Save Settings
                </button>
                <a href="/admin/" class="bg-sage text-white px-6 py-2 rounded hover:bg-custom-blue transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>
