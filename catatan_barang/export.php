<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Set header untuk file Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"data_barang_pga_edp_".date('Y-m-d').".xls\"");
header("Cache-Control: max-age=0");

// Query data
$sql = "SELECT * FROM transactions ORDER BY tanggal DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Data Barang PGA-EDP</title>
    <style>
        .table {
            border-collapse: collapse;
            width: 100%;
        }
        .table th, .table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .bg-success { background-color: #d4edda; }
        .bg-danger { background-color: #f8d7da; }
    </style>
</head>
<body>
    <h2 style="text-align: center;">DATA BARANG MASUK/KELUAR PGA-EDP</h2>
    <p style="text-align: center;">Periode: <?php echo date('d/m/Y'); ?></p>
    
    <table class="table">
        <thead>
            <tr>
                <th>No</th>
                <th>Jenis Transaksi</th>
                <th>Tanggal</th>
                <th>Serial Number</th>
                <th>Deskripsi Barang</th>
                <th>Jumlah</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                $no = 1;
                $total_masuk = 0;
                $total_keluar = 0;
                
                while($row = $result->fetch_assoc()) {
                    $jenis = $row['transaction_type'] == 'masuk' ? 'MASUK' : 'KELUAR';
                    $bg_color = $row['transaction_type'] == 'masuk' ? 'bg-success' : 'bg-danger';
                    
                    // Hitung total
                    if ($row['transaction_type'] == 'masuk') {
                        $total_masuk += $row['jumlah'];
                    } else {
                        $total_keluar += $row['jumlah'];
                    }
                    
                    echo "<tr>
                        <td class='text-center'>{$no}</td>
                        <td class='{$bg_color}'>{$jenis}</td>
                        <td>".date('d/m/Y H:i', strtotime($row['tanggal']))."</td>
                        <td>{$row['serial_number']}</td>
                        <td>{$row['deskripsi']}</td>
                        <td class='text-center'>{$row['jumlah']}</td>
                        <td>{$row['keterangan']}</td>
                    </tr>";
                    $no++;
                }
                
                // Total summary
                echo "<tr>
                    <td colspan='4' style='text-align: right; font-weight: bold;'>TOTAL BARANG MASUK:</td>
                    <td class='text-center' style='font-weight: bold;'>{$total_masuk}</td>
                    <td colspan='2'></td>
                </tr>
                <tr>
                    <td colspan='4' style='text-align: right; font-weight: bold;'>TOTAL BARANG KELUAR:</td>
                    <td class='text-center' style='font-weight: bold;'>{$total_keluar}</td>
                    <td colspan='2'></td>
                </tr>";
                
            } else {
                echo "<tr><td colspan='7' class='text-center'>Tidak ada data</td></tr>";
            }
            ?>
        </tbody>
    </table>
    
    <br>
    <p>Dicetak pada: <?php echo date('d/m/Y H:i:s'); ?></p>
    <p>Oleh: <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</p>
</body>
</html>