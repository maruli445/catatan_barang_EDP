<?php
// path_helper.php
function getUploadPath($filename = '') {
    $base_dir = __DIR__ . '/uploads/nota/';
    return !empty($filename) ? $base_dir . $filename : $base_dir;
}

function getRelativeUploadPath($filename = '') {
    // Sesuaikan dengan struktur folder Anda
    $relative_path = '../uploads/nota/';
    return !empty($filename) ? $relative_path . $filename : $relative_path;
}

function fileExistsInUploads($filename) {
    if (empty($filename)) return false;
    
    $absolute_path = getUploadPath($filename);
    return file_exists($absolute_path);
}

function getFotoDisplay($filename, $serial_number = '') {
    if (empty($filename)) {
        return "
            <div class='text-center'>
                <span class='badge bg-secondary'>Tidak Ada</span>
            </div>";
    }
    
    $relative_path = getRelativeUploadPath($filename);
    $absolute_path = getUploadPath($filename);
    
    if (file_exists($absolute_path)) {
        return "
            <div class='text-center'>
                <img src='{$relative_path}' 
                     class='foto-preview-small' 
                     onclick='showFotoModal(\"{$relative_path}\")'
                     alt='Foto Nota {$serial_number}' 
                     title='Klik untuk melihat foto nota'>
            </div>";
    } else {
        return "
            <div class='text-center'>
                <span class='badge bg-warning'>File Missing</span>
            </div>";
    }
}
?>