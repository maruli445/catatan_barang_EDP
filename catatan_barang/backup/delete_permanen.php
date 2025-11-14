<?php
session_start();
require_once '../db_maru.php';

// Cek session login
if (!isset($_SESSION['uid'])) {
    echo "<script>
            alert('Login dulu yaa..');
            window.location.href = '../home/login.php'; 
          </script>";
    exit();
}

error_log("=== QUICK FIX HAPUS ===");

// **SOLUSI CEPAT: Hanya butuh serial_number**
if (isset($_GET['serial_number'])) {
    $serial_number = trim($_GET['serial_number']);
    
    error_log("Deleting serial: '$serial_number'");
    
    if (!empty($serial_number)) {
        try {
            // Hapus file nota jika ada sebelum menghapus data
            $sql_select = "SELECT nota_foto FROM transactions WHERE serial_number = ?";
            $stmt_select = $conn->prepare($sql_select);
            $stmt_select->bind_param("s", $serial_number);
            $stmt_select->execute();
            $result = $stmt_select->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Hapus file nota jika ada
                if (!empty($row['nota_foto'])) {
                    $upload_dir = __DIR__ . '/uploads/nota/';
                    $file_path = $upload_dir . $row['nota_foto'];
                    
                    if (file_exists($file_path)) {
                        unlink($file_path);
                        error_log("Deleted file: " . $row['nota_foto']);
                    }
                }
            }
            $stmt_select->close();
            
            // Hapus data dari database
            $sql = "DELETE FROM transactions WHERE serial_number = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $serial_number);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $_SESSION['success'] = "Data berhasil dihapus permanen!";
            } else {
                $_SESSION['error'] = "Data tidak ditemukan atau gagal dihapus!";
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
    }
}

header("Location: trash.php");
exit();
?>