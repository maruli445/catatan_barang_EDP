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

// Query data yang sudah dihapus (recid = 1)
$sql = "SELECT * FROM transactions WHERE recid = 1 ORDER BY updated_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Terhapus - PGA EDP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .toggle-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 15px;
            cursor: pointer;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: -250px;
            width: 250px;
            height: 100%;
            background: #343a40;
            transition: left 0.3s;
            z-index: 999;
        }
        .sidebar.active {
            left: 0;
        }
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .table th {
            background-color: #343a40;
            color: white;
        }
        .badge-masuk {
            background-color: #28a745;
        }
        .badge-keluar {
            background-color: #dc3545;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>
    <?php include "../sidebar.php"; ?>
    <!-- Sidebar End -->

    <div class="container mt-4">
        <h2 class="text-center mb-4">🗑️ Data Barang Terhapus</h2>
        
        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Header Actions -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="data.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Kembali ke Data Barang
            </a>
            
            <div class="d-flex gap-2">
                <!-- Restore All Button -->
                <a href="restore_all.php" class="btn btn-success" onclick="return confirm('Yakin ingin memulihkan SEMUA data yang terhapus?')">
                <i class="fas fa-trash-restore"></i> Pulihkan Semua
                </a>
                
                <!-- Empty Trash Button -->
                <a href="empty_trash.php" class="btn btn-danger" onclick="return confirm('PERINGATAN! Semua data di trash akan dihapus PERMANEN dan tidak dapat dikembalikan. Yakin ingin melanjutkan?')">
                <i class="fas fa-trash"></i> Kosongkan Trash
                </a>
            </div>
        </div>

        <!-- Info Summary -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            Total <strong><?= $result->num_rows ?></strong> data terhapus
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th width="50">No</th>
                        <th width="100">Jenis</th>
                        <th width="100">Tanggal</th>
                        <th width="150">Serial Number</th>
                        <th width="120">Aktiva</th>
                        <th>Deskripsi</th>
                        <th width="80">Jumlah</th>
                        <th>Keterangan</th>
                        <th width="150">Dihapus Pada</th>
                        <th width="200">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        $no = 1;
                        while($row = $result->fetch_assoc()) {
                            $badge_class = $row['transaction_type'] == 'masuk' ? 'badge-masuk' : 'badge-keluar';
                            $badge_text = $row['transaction_type'] == 'masuk' ? 'MASUK' : 'KELUAR';
                            $deleted_at = date('d/m/Y H:i', strtotime($row['updated_at']));
                            
                            // Format tanggal transaksi
                            $tanggal_transaksi = date('d/m/Y', strtotime($row['tanggal']));
                            
                            echo "<tr>
                                <td class='text-center'><strong>{$no}</strong></td>
                                <td class='text-center'>
                                    <span class='badge {$badge_class}'>{$badge_text}</span>
                                </td>
                                <td>{$tanggal_transaksi}</td>
                                <td><code>{$row['serial_number']}</code></td>
                                <td>{$row['aktiva']}</td>
                                <td>{$row['deskripsi']}</td>
                                <td class='text-center'><span class='badge bg-secondary'>{$row['jumlah']}</span></td>
                                <td>" . nl2br(htmlspecialchars($row['keterangan'])) . "</td>
                                <td class='text-center'><small>{$deleted_at}</small></td>
                                <td>
                                    <div class='action-buttons'>
                                        <!-- Button Pulihkan -->
                                        <a href='restore.php?transaction_id={$row['transaction_id']}' class='btn btn-success btn-sm' onclick='return confirm(\"Yakin ingin memulihkan data dengan serial number {$row['serial_number']}?\")'>
                                            <i class='fas fa-trash-restore'></i> Pulihkan
                                        </a>
                                        
                                        <!-- Button Delete Selamanya -->
                                        <a href='delete_permanen.php?transaction_id={$row['transaction_id']}&serial_number={$row['serial_number']}' class='btn btn-dark btn-sm' onclick='return confirm(\"🚨 PERINGATAN! Data dengan serial number {$row['serial_number']} akan dihapus PERMANEN dan tidak dapat dikembalikan.\\n\\nYakin ingin melanjutkan?\")'>
                                            <i class='fas fa-skull-crossbones'></i> Hapus Permanen
                                        </a>
                                    </div>
                                </td>
                            </tr>";
                            $no++;
                        }
                    } else {
                        echo "<tr>
                                <td colspan='10' class='text-center py-4'>
                                    <i class='fas fa-trash fa-3x text-muted mb-3'></i>
                                    <br>
                                    <h5 class='text-muted'>Tidak ada data terhapus</h5>
                                    <p class='text-muted'>Trash kosong</p>
                                </td>
                            </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Footer Info -->
        <?php if ($result->num_rows > 0): ?>
        <div class="card mt-4">
            <div class="card-body text-center">
                <p class="text-muted mb-0">
                    <i class="fas fa-lightbulb"></i> 
                    <strong>Tips:</strong> Data yang sudah dihapus permanen tidak dapat dikembalikan
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar
        document.getElementById('toggle-btn').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Auto close alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>