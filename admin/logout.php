<?php
require_once __DIR__ . '/../includes/db.php';

// Clear session
session_destroy();

// Redirect to login
redirect('/admin/login.php');
?>
