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

// Ambil transaction_id dari URL
$transaction_id = $_GET['transaction_id'] ?? '';

if (empty($transaction_id)) {
    $_SESSION['error'] = "Transaction ID tidak valid!";
    header("Location: data.php");
    exit();
}

// Ambil data berdasarkan transaction_id
$sql = "SELECT * FROM transactions WHERE transaction_id = ? AND recid = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    $_SESSION['error'] = "Data tidak ditemukan!";
    header("Location: data.php");
    exit();
}

// Proses update data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $transaction_type = $_POST['transaction_type'];
    $tanggal = $_POST['tanggal'];
    $serial_number = $_POST['serial_number'];
    $aktiva = $_POST['aktiva'];
    $deskripsi = $_POST['deskripsi'];
    $jumlah = $_POST['jumlah'];
    $keterangan = $_POST['keterangan'];
    
    // Handle upload foto baru
    $foto_nota = $data['nota_foto']; // Default: foto lama
    
    if (isset($_FILES['nota_foto']) && $_FILES['nota_foto']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/nota/";
        
        // Buat direktori jika belum ada
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_tmp = $_FILES['nota_foto']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['nota_foto']['name']);
        $file_path = $upload_dir . $file_name;
        
        // Validasi tipe file
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = $_FILES['nota_foto']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            // Pindahkan file ke direktori uploads
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Hapus foto lama jika ada dan foto baru berhasil diupload
                if (!empty($data['nota_foto']) && file_exists($upload_dir . $data['nota_foto'])) {
                    unlink($upload_dir . $data['nota_foto']);
                }
                $foto_nota = $file_name;
            } else {
                $_SESSION['error'] = "Gagal mengupload foto nota.";
                header("Location: edit.php?transaction_id=" . $transaction_id);
                exit();
            }
        } else {
            $_SESSION['error'] = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
            header("Location: edit.php?transaction_id=" . $transaction_id);
            exit();
        }
    }
    
    // Handle hapus foto yang ada
    if (isset($_POST['hapus_foto']) && $_POST['hapus_foto'] == '1') {
        if (!empty($data['nota_foto']) && file_exists($upload_dir . $data['nota_foto'])) {
                unlink($upload_dir . $data['nota_foto']);
            }
            $foto_nota = NULL;
    }
    
    try {
        $sql = "UPDATE transactions SET 
                transaction_type = ?, 
                tanggal = ?, 
                serial_number = ?, 
                aktiva = ?, 
                deskripsi = ?, 
                jumlah = ?, 
                keterangan = ?,
                nota_foto = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE transaction_id = ? AND recid = 0";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssissi", 
            $transaction_type, 
            $tanggal, 
            $serial_number, 
            $aktiva, 
            $deskripsi, 
            $jumlah, 
            $keterangan, 
            $foto_nota,
            $transaction_id
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Data berhasil diupdate!";
            header("Location: data.php");
            exit();
        } else {
            $_SESSION['error'] = "Gagal mengupdate data.";
        }
        
    } catch(Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data - EDP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .foto-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 5px;
            cursor: pointer;
        }
        .foto-container {
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            padding: 10px;
            background-color: #f8f9fa;
        }
        .delete-foto-btn {
            position: absolute;
            top: 5px;
            right: 5px;
        }
    </style>
</head>
<body>

     <!-- Sidebar -->
    <button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>
    <?php include "../sidebar.php"; ?>
    <!-- Sidebar End -->

    <div class="container mt-4">
        <h2 class="text-center">Edit Data Barang</h2>
        
        <!-- Notifikasi -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-edit"></i> Form Edit Data
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="transaction_id" value="<?= $transaction_id ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jenis Transaksi *</label>
                                <select class="form-select" name="transaction_type" required>
                                    <option value="masuk" <?= $data['transaction_type'] == 'masuk' ? 'selected' : '' ?>>Barang Masuk</option>
                                    <option value="keluar" <?= $data['transaction_type'] == 'keluar' ? 'selected' : '' ?>>Barang Keluar</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Tanggal *</label>
                                <input type="date" class="form-control" name="tanggal" value="<?= $data['tanggal'] ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Serial Number *</label>
                                <input type="text" class="form-control" name="serial_number" value="<?= $data['serial_number'] ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Aktiva </label>
                                <input type="text" class="form-control" name="aktiva" value="<?= $data['aktiva'] ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Deskripsi Barang *</label>
                                <textarea class="form-control" name="deskripsi" rows="3" required><?= $data['deskripsi'] ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Jumlah *</label>
                                <input type="number" class="form-control" name="jumlah" value="<?= $data['jumlah'] ?>" min="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Keterangan</label>
                                <textarea class="form-control" name="keterangan" rows="2"><?= $data['keterangan'] ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section Foto Nota -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <i class="fas fa-camera"></i> Foto Nota
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Upload Foto Nota Baru</label>
                                        <input type="file" class="form-control" name="nota_foto" accept="image/*">
                                        <small class="text-muted">
                                            Format yang didukung: JPG, PNG, GIF. Maksimal 5MB.
                                        </small>
                                    </div>
                                    
                                    <!-- Preview Foto Saat Ini -->
                                    <div class="mb-3">
                                        <label class="form-label">Foto Nota Saat Ini</label>
                                        <div class="foto-container position-relative">
                                            <?php
                                            $foto_path = "uploads/nota/" . $data['nota_foto'];
                                            if (!empty($data['nota_foto']) && file_exists($foto_path)): 
                                            ?>
                                                <img src="<?= $foto_path ?>" class="foto-preview" 
                                                     onclick="showFotoModal('<?= $foto_path ?>')"
                                                     alt="Foto Nota Saat Ini">
                                                <button type="button" class="btn btn-danger btn-sm delete-foto-btn" 
                                                            onclick="confirmDeleteFoto()">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <input type="hidden" name="hapus_foto" id="hapus_foto" value="0">
                                            <?php else: ?>
                                                <div class="text-center py-4">
                                                    <i class="fas fa-image fa-3x text-muted"></i>
                                                    <p class="text-muted mt-2">Tidak ada foto nota</p>
                                            <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informasi Data -->
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Data dibuat pada: <?= date('d/m/Y H:i', strtotime($data['created_at'])) ?>
                            <?php if ($data['updated_at'] != $data['created_at']): ?>
                                <br>Terakhir diubah: <?= date('d/m/Y H:i', strtotime($data['updated_at'])) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="data.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk Preview Foto -->
    <div class="modal fade" id="fotoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Foto Nota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalFoto" src="" class="img-fluid rounded" alt="Foto Nota">
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
            const modal = new bootstrap.Modal(document.getElementById('fotoModal'));
            const modalImage = document.getElementById('modalFoto');
            
            modalImage.src = fotoPath;
            modal.show();
        }
        
        // Fungsi konfirmasi hapus foto
        function confirmDeleteFoto() {
            if (confirm('Yakin ingin menghapus foto nota? Foto akan dihapus secara permanen.')) {
                document.getElementById('hapus_foto').value = '1';
                document.querySelector('.foto-container').innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-trash fa-2x text-danger"></i>
                        <p class="text-danger mt-2">Foto akan dihapus</p>
                    </div>
                `;
            }
        }
        
        // Validasi sebelum submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const serialNumber = document.querySelector('input[name="serial_number"]').value.trim();
            const jumlah = parseInt(document.querySelector('input[name="jumlah"]').value);
            
            // Validasi serial number tidak boleh kosong
            if (serialNumber === '') {
                e.preventDefault();
                alert('Serial Number tidak boleh kosong!');
                return false;
            }
            
            // Validasi jumlah harus positif
            if (jumlah <= 0) {
                e.preventDefault();
                alert('Jumlah harus lebih dari 0!');
                return false;
            }
        });
        
        // Preview foto sebelum upload
        document.querySelector('input[name="nota_foto"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewContainer = document.querySelector('.foto-container');
                    previewContainer.innerHTML = `
                        <div class="position-relative">
                            <img src="${e.target.result}" class="foto-preview" alt="Preview Foto Baru">
                            <button type="button" class="btn btn-danger btn-sm delete-foto-btn" 
                                        onclick="cancelUploadFoto()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <p class="text-success mt-2"><i class="fas fa-check"></i> Foto baru siap diupload</p>
                    `;
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Fungsi batalkan upload foto baru
        function cancelUploadFoto() {
            document.querySelector('input[name="nota_foto"]').value = '';
            location.reload();
        }
    </script>
</body>
</html>