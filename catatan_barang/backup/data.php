<?php
session_start();
require_once '../db_maru.php';
require_once 'foto.php';

// Cek session login
if (!isset($_SESSION['uid'])) {
    echo "<script>
            alert('Login dulu yaa..');
            window.location.href = '../home/login.php'; 
          </script>";
    exit();
}

error_log("=== DATA.PHP SESSION DEBUG ===");
error_log("Session UID: " . ($_SESSION['uid'] ?? 'NOT SET'));

// Konfigurasi pagination
$rows_per_page = 10;

// Ambil parameter pencarian dan halaman
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Query dasar - hanya tampilkan data yang belum dihapus (recid = 0)
$sql_where = "WHERE t.recid = 0";
$sql_params = [];

// Jika ada pencarian
if (!empty($search)) {
    $sql_where .= " AND (t.serial_number LIKE ? OR t.deskripsi LIKE ? OR t.aktiva LIKE ? OR t.keterangan LIKE ?)";
    $search_term = "%$search%";
    $sql_params = array_fill(0, 4, $search_term);
}

// Hitung total data untuk pagination
$sql_count = "SELECT COUNT(*) as total FROM transactions t $sql_where";
$stmt_count = $conn->prepare($sql_count);

if ($stmt_count === false) {
    die("Error preparing count query: " . $conn->error);
}

if (!empty($sql_params)) {
    $types = str_repeat('s', count($sql_params));
    $stmt_count->bind_param($types, ...$sql_params);
}

$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_rows = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $rows_per_page);

// Pastikan page tidak melebihi total pages
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}

// Hitung offset
$offset = ($page - 1) * $rows_per_page;

// Query utama dengan JOIN yang benar
$sql = "SELECT t.*, u.username, u.id AS user_id 
          FROM transactions t 
          LEFT JOIN users u ON t.id = u.id  
          $sql_where
          ORDER BY t.tanggal DESC, t.created_at DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}

// Tambahkan parameter untuk LIMIT dan OFFSET
$limit_param = $rows_per_page;
$offset_param = $offset;

if (!empty($sql_params)) {
    $types = str_repeat('s', count($sql_params)) . 'ii';
    $all_params = array_merge($sql_params, [$limit_param, $offset_param]);
    $stmt->bind_param($types, ...$all_params);
} else {
    $types = 'ii';
    $stmt->bind_param($types, $limit_param, $offset_param);
}

$stmt->execute();
$result = $stmt->get_result();

// Debug query results
error_log("=== QUERY RESULTS ===");
error_log("Total rows: " . $total_rows);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang - EDP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="setail.css">
    <style>
        .user-id-badge {
            font-size: 0.8rem;
            background-color: #6f42c1;
        }
        
        
        .foto-preview-small {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
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
        <h2 class="text-center mb-4">Data Barang Masuk/Keluar</h2>
        
        <!-- Info User yang Login -->
        <div class="alert alert-info text-center">
            <i class="fas fa-user"></i> Login sebagai: 
            ID: <span class="badge user-id-badge"><?= htmlspecialchars($_SESSION['uid']) ?></span>
        </div>
        
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
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Input Data Baru
                </a>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="export_laporan.php" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
                <a href="trash.php" class="btn btn-warning">
                    <i class="fas fa-trash"></i> Data Terhapus
                </a>
            </div>
        </div>

        <!-- Search Box -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <div class="input-group search-box">
                            <input type="text" class="form-control" id="search" name="search" 
                   value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Cari berdasarkan Serial Number, Deskripsi, Aktiva, atau Keterangan..."
                   aria-label="Pencarian data">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-search"></i> Cari
                            </button>
                            <?php if (!empty($search)): ?>
                            <a href="data.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Reset
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="page-info mt-2 mt-md-0">
                            Menampilkan 
                            <strong><?= $offset + 1 ?> - <?= min($offset + $rows_per_page, $total_rows) ?></strong> 
                            dari <strong><?= $total_rows ?></strong> data
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div>
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th width="10">No</th>
                        <th width="100">Jenis</th>
                        <th width="100">Tanggal</th>
                        <th width="100">Serial Number</th>
                        <th width="120">Aktiva</th>
                        <th>Deskripsi</th>
                        <th width="80">Jumlah</th>
                        <th>Keterangan</th>
                        <th width="120">Foto Nota</th>                    
                        <th width="150">Tanggal Input</th>
                        <th width="150">Tanggal Edit</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        $no = $offset + 1;
                        while($row = $result->fetch_assoc()) {
                            $badge_class = $row['transaction_type'] == 'masuk' ? 'badge-masuk' : 'badge-keluar';
                            $badge_text = $row['transaction_type'] == 'masuk' ? 'MASUK' : 'KELUAR';
                            $created_at = date('d/m/Y H:i', strtotime($row['created_at']));
                            $updated_at = ($row['updated_at'] != $row['created_at']) 
                                ? date('d/m/Y H:i', strtotime($row['updated_at']))
                                : '-';
                            
                            // Format tanggal transaksi
                            $tanggal_transaksi = date('d/m/Y', strtotime($row['tanggal']));
                            
                             // Cek apakah ada foto nota
                            $foto_nota = $row['nota_foto'];
                            $has_foto = !empty($foto_nota);

                            
                               // Tampilkan foto atau placeholder
                            $foto_display = '';
                            if ($has_foto && file_exists("uploads/nota/{$foto_nota}")) {
                                $foto_display = "
                                    <div class='text-center'>
                                        <img src='uploads/nota/{$foto_nota}' 
                                             class='foto-preview-small' 
                                             onclick='showFotoModal(\"uploads/nota/{$foto_nota}\")'
                                             alt='Foto Nota {$row['serial_number']}' 
                                             title='Klik untuk melihat foto nota'>
                                    </div>";
                            } else {
                                $foto_display = "
                                    <div class='text-center'>
                                        <span class='badge bg-secondary'>Tidak Ada</span>
                                    </div>
                                ";
                            }
                            
                            echo "<tr>
                                <td class='text-center'><strong>{$no}</strong></td>
                                <td class='text-center'>
                                    <span class='badge {$badge_class}'>{$badge_text}</span>
                                </td>
                                <td class='text-center'>{$tanggal_transaksi}</td>
                                <td><code>{$row['serial_number']}</code></td>
                                <td>{$row['aktiva']}</td>
                                <td>{$row['deskripsi']}</td>
                                <td class='text-center'><span class='badge bg-secondary'>{$row['jumlah']}</span></td>
                                <td>" . nl2br(htmlspecialchars($row['keterangan'])) . "</td>
                                <td class='text-center'>{$foto_display}</td>                       
                                <td class='text-center'><small>{$created_at}</small></td>
                                <td class='text-center'><small>{$updated_at}</small></td>
                                <td>
                                    <div class='action-buttons'>
                                        <!-- Button Edit -->
                                        <a href='edit.php?transaction_id={$row['transaction_id']}' class='btn btn-warning btn-sm'>
                                            <i class='fas fa-edit'></i> Edit
                                        </a>
                                        
                                        <!-- Button Hapus -->
                                        <a href='delete.php?transaction_id={$row['transaction_id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Yakin ingin menghapus data dengan serial number {$row['serial_number']}?\\n\\nData akan dipindahkan ke trash dan dapat dipulihkan kembali.\")'>
                                            <i class='fas fa-trash'></i> Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>";
                            $no++;
                        }
                    } else {
                        echo "<tr>
                                <td colspan='10' class='text-center py-4'>
                                    <i class='fas fa-inbox fa-3x text-muted mb-3'></i>
                                    <br>
                                    <h5 class='text-muted'>Tidak ada data transaksi ditemukan</h5>
                                    <?php if (!empty($search)): ?>
                                    <p class='text-muted'>Coba gunakan kata kunci lain atau reset pencarian</p>
                                    <?php endif; ?>
                                </td>
                            </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <!-- Tombol Previous -->
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= 
                        http_build_query(array_merge(
                            ['page' => $page - 1],
                            !empty($search) ? ['search' => $search] : []
                    )) ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <!-- Tampilkan beberapa halaman sekitar current page -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= 
                        http_build_query(array_merge(
                            ['page' => $i],
                            !empty($search) ? ['search' => $search] : []
                    )) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <!-- Tombol Next -->
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= 
                        http_build_query(array_merge(
                            ['page' => $page + 1],
                            !empty($search) ? ['search' => $search] : []
                    )) ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Global Modal untuk Foto Nota -->
    <div class="modal fade foto-modal" id="globalFotoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fotoModalTitle">Foto Nota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalFotoImage" src="" class="img-fluid rounded" alt="Foto Nota">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>


        // Fungsi untuk menampilkan modal foto
        function showFotoModal(fotoPath) {
            const modal = new bootstrap.Modal(document.getElementById('globalFotoModal'));
            const modalImage = document.getElementById('modalFotoImage');
            const modalTitle = document.getElementById('fotoModalTitle');
            
            // Set gambar dan judul
            modalImage.src = fotoPath;
            modalTitle.textContent = 'Foto Nota';
            
            // Tampilkan modal
            modal.show();
        }
        
        // Auto focus pada search box saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }

            // Auto close alerts after 5 seconds
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

<?php
$stmt->close();
$stmt_count->close();
$conn->close();
?>