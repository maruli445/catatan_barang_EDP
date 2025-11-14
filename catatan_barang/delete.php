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

error_log("=== DELETE.PHP SESSION DEBUG ===");
error_log("Session UID: " . ($_SESSION['uid'] ?? 'NOT SET'));

if (isset($_GET['transaction_id'])) {
    $transaction_id = trim($_GET['transaction_id']);
    
    error_log("Deleting transaction_id: '$transaction_id'");
    
    if (!empty($transaction_id)) {
        try {
            // Validasi transaction_id harus numeric
            if (!is_numeric($transaction_id)) {
                throw new Exception("Transaction ID tidak valid!");
            }
            
            // Update recid menjadi 1 (soft delete) berdasarkan transaction_id
            $sql = "UPDATE transactions SET recid = 1, updated_at = CURRENT_TIMESTAMP WHERE transaction_id = ? AND recid = 0";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $transaction_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success'] = "Data dengan ID {$transaction_id} berhasil dihapus!";
                    error_log("Successfully soft deleted transaction_id: '$transaction_id'");
                } else {
                    $_SESSION['error'] = "Data tidak ditemukan atau sudah dihapus sebelumnya!";
                    error_log("No rows affected for transaction_id: '$transaction_id'");
                }
            } else {
                throw new Exception("Gagal menghapus data: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            error_log("Delete error: " . $e->getMessage());
        }
    } else {
        $_SESSION['error'] = "Transaction ID tidak valid!";
        error_log("Empty transaction_id provided");
    }
} else {
    $_SESSION['error'] = "Transaction ID tidak ditemukan!";
    error_log("No transaction_id parameter");
}

// Redirect kembali ke halaman data
header("Location: data.php");
exit();
?>