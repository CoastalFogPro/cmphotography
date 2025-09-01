<?php
// Simple test version to check for errors
require_once __DIR__ . '/../includes/db.php';
requireLogin();

echo "Print Settings page is working!<br>";

$db = getDB();
echo "Database connection: OK<br>";

// Test print sizes query
try {
    $printSizes = $db->query("SELECT * FROM print_sizes ORDER BY sort_order ASC, name ASC")->fetchAll();
    echo "Print sizes found: " . count($printSizes) . "<br>";
    
    foreach ($printSizes as $size) {
        echo "- " . $size['label'] . ": $" . number_format($size['base_price'] / 100, 2) . "<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
?>
