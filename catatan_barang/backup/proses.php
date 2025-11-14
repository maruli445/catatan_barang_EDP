<?php
session_start();
require_once '../db_maru.php';

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['uid'])) {
    echo "<script>
            alert('Login dulu yaa..');
            window.location.href = '../home/login.php'; 
          </script>";
    exit();
}

$id = $_SESSION['uid'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validasi dan sanitasi input
    $transaction_type = trim($_POST['transaction_type']);
    $tanggal = trim($_POST['tanggal']);
    $aktiva = trim($_POST['aktiva'] ?? '');
    $deskripsi = trim($_POST['deskripsi']);
    $jumlah = intval($_POST['jumlah']);
    $keterangan = trim($_POST['keterangan'] ?? '');
    
    // Handle multiple serial numbers
    $serial_numbers = [];
    if (isset($_POST['serial_numbers']) && is_array($_POST['serial_numbers'])) {
        foreach ($_POST['serial_numbers'] as $serial) {
            $serial = trim($serial);
            if (!empty($serial)) {
                $serial_numbers[] = $serial;
            }
        }
    }
    
    // Validasi data wajib
    $errors = [];
    
    if (empty($transaction_type) || !in_array($transaction_type, ['masuk', 'keluar'])) {
        $errors[] = "Jenis transaksi tidak valid";
    }
    
    if (empty($tanggal)) {
        $errors[] = "Tanggal harus diisi";
    }
    
    if (empty($serial_numbers)) {
        $errors[] = "Minimal satu serial number harus diisi";
    }
    
    if (empty($deskripsi)) {
        $errors[] = "Deskripsi barang harus diisi";
    }
    
    if ($jumlah <= 0) {
        $errors[] = "Jumlah harus lebih dari 0";
    }
    
    // Validasi jumlah serial number harus sama dengan jumlah barang
    if (count($serial_numbers) != $jumlah) {
        $errors[] = "Jumlah serial number (" . count($serial_numbers) . ") harus sama dengan jumlah barang (" . $jumlah . ")";
    }
    
    // **VALIDASI: Cek duplikasi serial number dengan transaction type yang sama**
    if (!empty($serial_numbers)) {
        try {
            // Buat placeholder untuk prepared statement
            $placeholders = str_repeat('?,', count($serial_numbers) - 1) . '?';
            
            $check_sql = "SELECT transaction_id, serial_number, transaction_type 
                         FROM transactions 
                         WHERE serial_number IN ($placeholders) 
                         AND transaction_type = ?";
            
            $check_stmt = $conn->prepare($check_sql);
            
            // Bind parameters: semua serial numbers + transaction_type
            $types = str_repeat('s', count($serial_numbers)) . 's';
            $params = array_merge($serial_numbers, [$transaction_type]);
            
            $check_stmt->bind_param($types, ...$params);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            $duplicates = [];
            while ($row = $result->fetch_assoc()) {
                $duplicates[] = $row['serial_number'];
            }
            $check_stmt->close();
            
         
            
        } catch (Exception $e) {
            $errors[] = "Gagal memvalidasi serial number: " . $e->getMessage();
        }
    }
    
    // Handle file upload dengan format nama seperti contoh
    $nota_foto = null;
    $upload_success = false;
    $upload_dir = __DIR__ . '/uploads/nota/';

    if (isset($_FILES['nota_foto']) && $_FILES['nota_foto']['error'] != UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['nota_foto'];
        
        if ($file['error'] == UPLOAD_ERR_OK) {
            // Validasi ukuran file
            if ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = "Ukuran file terlalu besar! Maksimal 2MB.";
            } else {
                // Validasi tipe file
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $errors[] = "Format file tidak didukung! Hanya JPG, PNG, dan GIF yang diizinkan.";
                } else {
                    // Buat direktori jika belum ada
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    if (is_dir($upload_dir) && is_writable($upload_dir)) {
                        // GENERATE FILENAME DENGAN FORMAT YANG DIINGINKAN
                        $clean_uid = uniqid();
                        
                        // Format: nota_[uniqid]_[serial1]_[serial2]_[etc]
                        $filename_parts = ['nota', $clean_uid];
                        
                        // TAMBAHKAN SEMUA SERIAL NUMBERS KE NAMA FILE
                        if (isset($serial_numbers) && is_array($serial_numbers) && !empty($serial_numbers)) {
                            foreach ($serial_numbers as $serial) {
                                if (!empty(trim($serial))) {
                                    $filename_parts[] = trim($serial);
                                }
                            }
                        } else {
                            // Fallback jika tidak ada serial numbers
                            $filename_parts[] = 'unknown';
                        }
                        
                        // Gabungkan semua bagian dengan underscore
                        $base_filename = implode('_', $filename_parts);
                        $nota_foto = $base_filename . '.' . $file_extension;
                        
                        $destination = $upload_dir . $nota_foto;
                        
                        error_log("=== DEBUG UPLOAD ===");
                        error_log("Unique ID: " . $clean_uid);
                        error_log("Serial numbers: " . implode(', ', $serial_numbers));
                        error_log("Filename parts: " . implode(' | ', $filename_parts));
                        error_log("Final filename: " . $nota_foto);
                        error_log("Upload destination: " . $destination);
                        
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            error_log("=== UPLOAD BERHASIL ===");
                            error_log("File saved as: " . $nota_foto);
                            
                            // Set file permissions
                            chmod($destination, 0644);
                            $upload_success = true;
                        } else {
                            $errors[] = "Gagal mengupload file!";
                        }
                    } else {
                        $errors[] = "Direktori upload tidak dapat ditulisi.";
                    }
                }
            }
        } else {
            // Handle upload errors
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File terlalu besar',
                UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
                UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
            ];
            
            if (isset($upload_errors[$file['error']])) {
                $errors[] = "Error upload: " . $upload_errors[$file['error']];
            }
        }
    }
    
    // Jika ada error, tampilkan dan berhenti
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: index.php");
        exit();
    }
    
    try {
        // Mulai transaksi database
        $conn->begin_transaction();
        
        // **PERBAIKAN: Debug nilai sebelum insert**
        error_log("=== BEFORE INSERT ===");
        error_log("Jumlah placeholder di SQL: 9");
        error_log("nota_foto value: " . ($nota_foto ?? 'NULL'));
        
        // **PERUBAHAN: Sekarang setiap record memiliki primary key unik**
        foreach ($serial_numbers as $serial_number) {
            // Query INSERT - transaction_id akan auto increment
            $sql = "INSERT INTO transactions 
                    (transaction_type, tanggal, serial_number, aktiva, deskripsi, jumlah, keterangan, nota_foto, id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                throw new Exception("Gagal mempersiapkan query: " . $conn->error);
            }
            
            // **PERBAIKAN KRITIS: Pattern yang benar dengan 9 parameter**
            // Pattern: "sssssisss" - 9 karakter untuk 9 parameter
            $stmt->bind_param("sssssisss", 
                $transaction_type,     // s (1)
                $tanggal,             // s (2)  
                $serial_number,       // s (3)
                $aktiva,              // s (4)
                $deskripsi,           // s (5)
                $jumlah,              // i (6) - satu-satunya integer
                $keterangan,          // s (7)
                $nota_foto,           // s (8)
                $id                   // s (9)
            );
            
            // Debug parameter sebelum execute
            error_log("Parameter untuk serial $serial_number:");
            error_log("1. transaction_type: $transaction_type");
            error_log("2. tanggal: $tanggal");
            error_log("3. serial_number: $serial_number");
            error_log("4. aktiva: $aktiva");
            error_log("5. deskripsi: $deskripsi");
            error_log("6. jumlah: $jumlah");
            error_log("7. keterangan: $keterangan");
            error_log("8. nota_foto: " . ($nota_foto ?? 'NULL'));
            error_log("9. id: $id");
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal menyimpan data untuk serial number $serial_number: " . $stmt->error);
            }
            
            // Debug setelah execute
            error_log("Insert successful for serial: $serial_number");
            
            $stmt->close();
        }
        
        // Commit transaksi jika semua berhasil
        $conn->commit();
        
        $message = "Data berhasil disimpan!";
        $message .= " Total " . count($serial_numbers) . " serial number berhasil diproses.";
        if ($upload_success && $nota_foto) {
            $message .= " Foto nota berhasil diupload sebagai: " . $nota_foto;
        }
        
        $_SESSION['success'] = $message;
        
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        $conn->rollback();
        
        // **PERBAIKAN: Jika gagal insert, hapus file yang sudah diupload (jika ada)**
        if ($upload_success && $nota_foto && file_exists($upload_dir . $nota_foto)) {
            unlink($upload_dir . $nota_foto);
            error_log("File deleted due to database error: " . $nota_foto);
        }
        
        $_SESSION['error'] = $e->getMessage();
    }
    
    // Redirect kembali ke form input
    header("Location: index.php");
    exit();
    
} else {
    $_SESSION['error'] = "Akses tidak valid!";
    header("Location: index.php");
    exit();
}
?>