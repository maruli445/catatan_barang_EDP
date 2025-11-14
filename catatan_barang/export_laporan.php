<?php
session_start();
require_once '../db_maru.php';

// Debug session
error_log("=== EXPORT LAPORAN SESSION DEBUG ===");
error_log("Session UID: " . ($_SESSION['uid'] ?? 'NOT SET'));

if (!isset($_SESSION['uid'])) {
    echo "<script>
            alert('Login dulu yaa..');
            window.location.href = '../home/login.php'; 
          </script>";
    exit();
}

// Filter tanggal
$tanggal_filter = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Set header untuk file Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"laporan_barang_EDP_{$tanggal_filter}.xls\"");
header("Cache-Control: max-age=0");

// Query data berdasarkan filter - hanya data yang tidak dihapus
$sql = "SELECT t.*, u.username 
        FROM transactions t 
        LEFT JOIN users u ON t.id = u.id 
        WHERE t.recid = 0 ";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Get user info for footer
$user_id = $_SESSION['uid'];
$username = 'User';
$role = 'User';

// Query untuk mendapatkan info user
$sql_user = "SELECT username, role FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
if ($stmt_user !== false) {
    $stmt_user->bind_param("s", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows > 0) {
        $user_data = $result_user->fetch_assoc();
        $username = $user_data['username'];
        $role = $user_data['role'] ?? 'User';
    }
    $stmt_user->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Harian</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        .table th, .table td { border: 1px solid #000; padding: 6px; }
        .table th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
        .table td { vertical-align: top; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .bg-success { background-color: #d4edda; }
        .bg-danger { background-color: #f8d7da; }
        .summary-table { width: 50%; margin-bottom: 20px; border: 1px solid #000; }
        .summary-table td { padding: 8px; border: 1px solid #000; }
        .header { text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN HARIAN BARANG PGA-EDP</h2>
        <h3>Tanggal: <?php echo date('d/m/Y', strtotime($tanggal_filter)); ?></h3>
    </div>
    
    <!-- Summary -->
    <?php
    // Hitung total
    $sql_summary = "SELECT 
        transaction_type,
        SUM(jumlah) as total
        FROM transactions 
        WHERE recid = 0 AND DATE(tanggal) = ? 
        GROUP BY transaction_type";
    $stmt_summary = $conn->prepare($sql_summary);
    $stmt_summary->bind_param("s", $tanggal_filter);
    $stmt_summary->execute();
    $result_summary = $stmt_summary->get_result();
    
    $total_masuk = 0;
    $total_keluar = 0;
    
    while($row_summary = $result_summary->fetch_assoc()) {
        if ($row_summary['transaction_type'] == 'masuk') {
            $total_masuk = $row_summary['total'];
        } else {
            $total_keluar = $row_summary['total'];
        }
    }
    $stmt_summary->close();
    ?>
    
    <table class="summary-table">
        <tr>
            <td style="font-weight: bold; width: 60%;">Total Barang Masuk:</td>
            <td style="font-weight: bold; width: 40%; text-align: center;"><?php echo $total_masuk; ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Total Barang Keluar:</td>
            <td style="font-weight: bold; text-align: center;"><?php echo $total_keluar; ?></td>
        </tr>
    </table>

    <!-- Detail Data -->
    <table class="table">
        <thead>
            <tr>
                <th width="30">No</th>
                <th width="80">Jenis</th>
                <th width="70">Waktu</th>
                <th width="120">Serial Number</th>
                <th width="80">Aktiva</th>
                <th width="200">Deskripsi</th>
                <th width="60">Jumlah</th>
                <th width="150">Keterangan</th>
                <th width="100">Input Oleh</th>
                <th width="120">Foto Nota</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                $no = 1;
                while($row = $result->fetch_assoc()) {
                    $bg_color = $row['transaction_type'] == 'masuk' ? 'bg-success' : 'bg-danger';
                    $jenis_text = $row['transaction_type'] == 'masuk' ? 'MASUK' : 'KELUAR';
                    
                    // Format waktu
                    $waktu = date('H:i', strtotime($row['tanggal']));
                    
                    // Cek apakah ada foto nota
                    $foto_info = 'Tidak Ada';
                    if (!empty($row['nota_foto'])) {
                        $foto_info = 'Ada Foto';
                    }
                    
                    echo "<tr>
                        <td class='text-center'>{$no}</td>
                        <td class='text-center {$bg_color}'>{$jenis_text}</td>
                        <td class='text-center'>{$waktu}</td>
                        <td>{$row['serial_number']}</td>
                        <td>{$row['aktiva']}</td>
                        <td>{$row['deskripsi']}</td>
                        <td class='text-center'>{$row['jumlah']}</td>
                        <td>{$row['keterangan']}</td>
                        <td class='text-center'>{$row['username']}</td>
                        <td class='text-center'>{$foto_info}</td>
                    </tr>";
                    $no++;
                }
            } else {
                echo "<tr><td colspan='9' class='text-center'>Tidak ada data untuk tanggal ini</td></tr>";
            }
            ?>
        </tbody>
    </table>
    
    <br>
    <p><strong>Dicetak pada:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
    <p><strong>Oleh:</strong> <?php echo $username; ?> (<?php echo $role; ?>)</p>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>