<?php
session_start();
// include 'config.php';

// if (!isset($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit();
// }

// Filter tanggal
$tanggal_filter = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Query data berdasarkan filter
$sql = "SELECT * FROM transactions WHERE DATE(tanggal) = ? ORDER BY tanggal DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tanggal_filter);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Harian - PGA EDP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2 class="text-center">Laporan Harian Barang</h2>
        
        <!-- Filter Form -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row">
                    <div class="col-md-6">
                        <label class="form-label">Pilih Tanggal:</label>
                        <input type="date" class="form-control" name="tanggal" value="<?php echo $tanggal_filter; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">&nbsp;</label><br>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="laporan_harian.php" class="btn btn-secondary">Hari Ini</a>
                        <a href="export_harian.php?tanggal=<?php echo $tanggal_filter; ?>" class="btn btn-success">Export Excel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <?php
            // Hitung total masuk dan keluar
            $sql_summary = "SELECT 
                transaction_type,
                SUM(jumlah) as total
                FROM transactions 
                WHERE DATE(tanggal) = ? 
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
            ?>
            
            <div class="col-md-6">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <h4>Barang Masuk</h4>
                        <h2><?php echo $total_masuk; ?></h2>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-white bg-danger">
                    <div class="card-body text-center">
                        <h4>Barang Keluar</h4>
                        <h2><?php echo $total_keluar; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Table -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Jenis</th>
                        <th>Waktu</th>
                        <th>Serial Number</th>
                        <th>Deskripsi</th>
                        <th>Jumlah</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        $no = 1;
                        while($row = $result->fetch_assoc()) {
                            $badge = $row['transaction_type'] == 'masuk' ? 'success' : 'danger';
                            echo "<tr>
                                <td>{$no}</td>
                                <td><span class='badge bg-{$badge}'>{$row['transaction_type']}</span></td>
                                <td>".date('H:i', strtotime($row['tanggal']))."</td>
                                <td>{$row['serial_number']}</td>
                                <td>{$row['deskripsi']}</td>
                                <td>{$row['jumlah']}</td>
                                <td>{$row['keterangan']}</td>
                            </tr>";
                            $no++;
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>Tidak ada data untuk tanggal ini</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            <a href="index.php" class="btn btn-primary">Kembali ke Input</a>
            <a href="data.php" class="btn btn-secondary">Lihat Semua Data</a>
        </div>
    </div>
</body>
</html>