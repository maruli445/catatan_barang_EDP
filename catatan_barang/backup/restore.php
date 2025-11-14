<?php
session_start();
require_once '../db_maru.php';

if (!isset($_SESSION['uid'])) {
    echo "<script>
            alert('Login dulu yaa..');
            window.location.href = '../home/login.php'; 
          </script>";
    exit();
}

if (isset($_GET['transaction_id'])) {
    $transaction_id = intval($_GET['transaction_id']);
    
    if ($transaction_id > 0) {
        try {
            // Update recid menjadi 0 (restore data)
            $sql = "UPDATE transactions SET recid = 0, updated_at = CURRENT_TIMESTAMP WHERE transaction_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $transaction_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Data berhasil dipulihkan!";
            } else {
                throw new Exception("Gagal memulihkan data: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Transaction ID tidak valid!";
    }
} else {
    $_SESSION['error'] = "Transaction ID tidak ditemukan!";
}

// Redirect kembali ke halaman trash
header("Location: trash.php");
exit();
?>