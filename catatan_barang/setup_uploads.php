<?php
// Script untuk membuat dan memberikan permissions pada folder uploads
$base_dir = __DIR__;
$uploads_dir = $base_dir . '/uploads/nota/';

echo "<pre>";
echo "Setting up uploads directory...\n";
echo "Base directory: $base_dir\n";
echo "Uploads directory: $uploads_dir\n";

// Buat direktori uploads
if (!is_dir($uploads_dir)) {
    if (mkdir($uploads_dir, 0755, true)) {
        echo "✓ Directory created successfully\n";
        
        // Buat file .htaccess untuk keamanan
        $htaccess_content = "Deny from all\n";
        file_put_contents($uploads_dir . '.htaccess', $htaccess_content);
        echo "✓ .htaccess created\n";
        
        // Buat file index.html untuk keamanan
        file_put_contents($uploads_dir . 'index.html', '<html><body><h1>Directory access forbidden</h1></body></html>');
        echo "✓ index.html created\n";
    } else {
        echo "✗ Failed to create directory\n";
    }
}

// Check permissions
echo "Directory exists: " . (is_dir($uploads_dir) ? 'YES' : 'NO') . "\n";
echo "Directory writable: " . (is_writable($uploads_dir) ? 'YES' : 'NO') . "\n";

// Test file upload capability
$test_file = $uploads_dir . 'test_write.txt';
if (file_put_contents($test_file, 'test') !== false) {
    echo "✓ Directory is writable\n";
    unlink($test_file);
} else {
    echo "✗ Directory is not writable\n";
}

if (is_dir($uploads_dir)) {
    echo "Current permissions: " . substr(sprintf('%o', fileperms($uploads_dir)), -4) . "\n";
}

echo "Setup complete!\n";
echo "</pre>";
?>