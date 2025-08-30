<?php
require_once __DIR__ . '/../includes/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/admin/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, password_hash, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $user['name'];
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            redirect('/admin/');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo getSetting('site_title', 'Photography Portfolio'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-light-sage min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-earth-brown">Admin Login</h2>
            <p class="mt-2 text-custom-blue">Sign in to manage your photography website</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-caramel bg-opacity-20 border border-caramel text-earth-brown px-4 py-3 rounded-lg">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div>
                <label for="email" class="block text-sm font-medium text-custom-blue mb-2">Email</label>
                <input type="email" name="email" id="email" required
                       class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown"
                       value="<?php echo isset($_POST['email']) ? sanitizeInput($_POST['email']) : ''; ?>">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-custom-blue mb-2">Password</label>
                <input type="password" name="password" id="password" required
                       class="w-full px-3 py-2 border border-sage rounded-lg focus:outline-none focus:ring-2 focus:ring-earth-brown focus:border-earth-brown">
            </div>
            
            <button type="submit" 
                    class="w-full bg-earth-brown text-white py-2 px-4 rounded-lg hover:bg-earth-brown hover:bg-opacity-90 transition duration-200">
                Sign In
            </button>
        </form>
        
        <div class="text-center">
            <a href="/" class="text-earth-brown hover:text-caramel transition text-sm">← Back to website</a>
        </div>
    </div>
</body>
</html>
