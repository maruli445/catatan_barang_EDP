<?php
session_start();
require_once '../db_maru.php';

// Debug session
error_log("=== SESSION DEBUG ===");
error_log("Session UID: " . ($_SESSION['uid'] ?? 'NOT SET'));

if (!isset($_SESSION['uid'])) {
    echo "<script>
            alert('Login dulu yaa..');
            window.location.href = '../home/login.php'; 
          </script>";
    exit();
}

$username = null;

// Coba beberapa kemungkinan nama kolom
$possible_id_columns = ['id', 'userid', 'uid'];
$user_data = null;

foreach ($possible_id_columns as $column) {
    $sql_user = "SELECT id, username FROM users WHERE $column = ?";
    $stmt_user = $conn->prepare($sql_user);
    
    if ($stmt_user !== false) {
        $stmt_user->bind_param("s", $_SESSION['uid']);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        
        if ($result_user->num_rows > 0) {
            $user_data = $result_user->fetch_assoc();
            error_log("Found user using column: $column");
            break;
        }
        $stmt_user->close();
    }
}

// Jika user ditemukan
if ($user_data) {
    $user_id = $user_data['id']; // ID dari tabel users
    $username = $user_data['username'];
} else {
    // Coba alternatif: langsung gunakan session uid sebagai user_id
    $user_id = $_SESSION['uid'];
    $username = 'User'; // Default username
    
    // Atau jika ingin lebih aman, cek struktur tabel
    error_log("User not found with session uid, trying alternative approach");
    
    // Coba ambil data user dengan asumsi session uid adalah id
    $sql_user = "SELECT id, username FROM users WHERE id = ?";
    $stmt_user = $conn->prepare($sql_user);
    
    if ($stmt_user !== false) {
        $stmt_user->bind_param("s", $_SESSION['uid']);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        
        if ($result_user->num_rows > 0) {
            $user_data = $result_user->fetch_assoc();
            $user_id = $user_data['id'];
            $username = $user_data['username'];
        } else {
            error_log("Alternative query also failed");
        }
    }
}

$stmt_user->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Barang - EDP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="setail.css">
</head>
<body>

    <!-- Toggle Button -->
    <button class="toggle-btn" id="toggle-btn">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <?php include "../sidebar.php"; ?>
    <!-- Sidebar End -->

    <div class="main-content" id="main-content">
        <div class="container mt-4">
            <!-- Info User Login -->
            <div class="user-info">
                <i class="fas fa-user"></i> 
                Login sebagai: <strong><?= htmlspecialchars($username) ?></strong> 
                | User ID: <strong><?= htmlspecialchars($user_id) ?></strong>
            </div>

            <h2 class="text-center mb-4">Input Barang Masuk/Keluar</h2>
            
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
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-plus-circle"></i> Form Input Data Baru
                </div>
                <div class="card-body">
                    <form method="POST" action="proses.php" enctype="multipart/form-data" id="inputForm">
                        <!-- Tambahkan hidden input untuk user_id -->
                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Transaksi <span class="text-danger">*</span></label>
                                    <select class="form-select" name="transaction_type" required>
                                    <option value="">Pilih Jenis...</option>
                                    <option value="masuk">Barang Masuk</option>
                                    <option value="keluar">Barang Keluar</option>
                                </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <!-- Multiple Serial Numbers -->
                                <div class="mb-3">
                                    <label class="form-label">Serial Numbers <span class="text-danger">*</span></label>
                                    <div id="serial-numbers-container">
                                        <div class="serial-input-group input-group mb-2">
                                            <input type="text" class="form-control" name="serial_numbers[]" placeholder="Masukkan serial number" required>
                                            <button type="button" class="btn btn-outline-danger remove-serial" onclick="removeSerialNumber(this)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSerialNumber()">
                                                <i class="fas fa-plus"></i> Tambah Serial Number
                                    </button>
                                    
                                    <!-- Hidden field untuk jumlah serial number yang diharapkan -->
                                    <input type="hidden" name="expected_serial_count" id="expectedSerialCount" value="1">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Aktiva</label>
                                    <input type="text" class="form-control" name="aktiva" placeholder="Masukkan kode aktiva (opsional)">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi Barang <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="deskripsi" rows="3" placeholder="Deskripsi lengkap barang..." required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="jumlah" min="1" value="1" placeholder="0" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Keterangan</label>
                                    <textarea class="form-control" name="keterangan" rows="2" placeholder="Keterangan tambahan (opsional)"></textarea>
                                </div>

                                <!-- Upload Foto Nota -->
                                <div class="mb-3">
                                    <label class="form-label">Foto Nota <small class="text-muted">(Opsional)</small></label>
                                    <div class="upload-area" onclick="triggerFileInput()" style="border: 2px dashed #dee2e6; padding: 20px; text-align: center; cursor: pointer; background: #f8f9fa;">
                                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                        <p class="mb-1">Klik atau drag & drop untuk upload foto nota</p>
                                        <small class="file-info">Format: JPG, PNG, GIF (Maks. 2MB)</small>
                                    </div>
                                    
                                    <input type="file" class="form-control d-none" id="nota_foto" name="nota_foto" accept="image/jpeg,image/png,image/gif">
                                    
                                    <div class="image-preview-container mt-2">
                                        <img id="imagePreview" class="image-preview img-thumbnail d-none" style="max-width: 200px; max-height: 200px;" alt="Preview foto nota">
                                    </div>
                                    
                                    <div class="file-info mt-1" id="fileName"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="data.php" class="btn btn-info">
                                <i class="fas fa-database"></i> Lihat Data
                            </a>
                            <div>
                                <button type="reset" class="btn btn-secondary" onclick="resetFileInput()">
                                <i class="fas fa-undo"></i> Reset
                                </button>
                                <button type="submit" class="btn btn-primary ms-2">
                                <i class="fas fa-save"></i> Simpan Data
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Multiple Serial Numbers Functionality
        function addSerialNumber() {
            const container = document.getElementById('serial-numbers-container');
            
            const newInputGroup = document.createElement('div');
            newInputGroup.className = 'serial-input-group input-group mb-2';
            
            newInputGroup.innerHTML = `
                <input type="text" class="form-control" name="serial_numbers[]" placeholder="Masukkan serial number" required>
                <button type="button" class="btn btn-outline-danger remove-serial" onclick="removeSerialNumber(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(newInputGroup);
            updateExpectedSerialCount();
        }
        
        function removeSerialNumber(button) {
            const container = document.getElementById('serial-numbers-container');
            
            // Jangan hapus jika hanya tersisa satu
            if (container.children.length > 1) {
                button.parentElement.remove();
                updateExpectedSerialCount();
            } else {
                alert('Minimal harus ada satu serial number');
            }
        }
        
        // Update expected serial count
        function updateExpectedSerialCount() {
            const container = document.getElementById('serial-numbers-container');
            const serialCount = container.children.length;
            document.getElementById('expectedSerialCount').value = serialCount;
            
            // Update jumlah barang otomatis sesuai jumlah serial number
            const jumlahInput = document.querySelector('input[name="jumlah"]');
            if (jumlahInput) {
                jumlahInput.value = serialCount;
            }
        }
        
        // File Upload Functionality
        function triggerFileInput() {
            document.getElementById('nota_foto').click();
        }
        
        function resetFileInput() {
            const fileInput = document.getElementById('nota_foto');
            const preview = document.getElementById('imagePreview');
            const fileName = document.getElementById('fileName');
            
            fileInput.value = '';
            preview.src = '';
            preview.classList.add('d-none');
            fileName.textContent = '';
            fileName.style.display = 'none';
        }
        
        // Image Preview Functionality
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const fileName = document.getElementById('fileName');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validasi ukuran file
                if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file terlalu besar! Maksimal 2MB.');
                resetFileInput();
                return;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
                
                fileName.textContent = `File: ${file.name} (${(file.size/1024).toFixed(1)} KB)`;
                fileName.style.display = 'block';
            };
            
            reader.readAsDataURL(file);
        } else {
            resetFileInput();
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('nota_foto');
        const uploadArea = document.querySelector('.upload-area');
        
        // Event listener untuk file input
        fileInput.addEventListener('change', function(e) {
            previewImage(this);
        });
        
        // Drag and drop functionality
        uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = '#0d6efd';
                this.style.backgroundColor = '#e9ecef';
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.borderColor = '#dee2e6';
                this.style.backgroundColor = '#f8f9fa';
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '#dee2e6';
                this.style.backgroundColor = '#f8f9fa';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    previewImage(fileInput);
                }
            });
            
            // Reset form handler
            const form = document.getElementById('inputForm');
            form.addEventListener('reset', function() {
                resetFileInput();
                
                // Reset serial numbers ke satu input saja
                const container = document.getElementById('serial-numbers-container');
                while (container.children.length > 1) {
                    container.lastChild.remove();
                }
                
                // Reset nilai input serial number pertama
                const firstInput = container.querySelector('input[name="serial_numbers[]"]');
                if (firstInput) {
                    firstInput.value = '';
                }
                
                updateExpectedSerialCount();
            });
            
            // Validasi form sebelum submit
            form.addEventListener('submit', function(e) {
                const container = document.getElementById('serial-numbers-container');
                const jumlahInput = document.querySelector('input[name="jumlah"]');
                
                // Pastikan jumlah barang sama dengan jumlah serial number
                const serialCount = container.children.length;
                if (jumlahInput) {
                    jumlahInput.value = serialCount;
                }
            });
            
            // Inisialisasi jumlah serial number
            updateExpectedSerialCount();
        });

        // Pastikan fungsi tersedia di global scope
        window.addSerialNumber = addSerialNumber;
        window.removeSerialNumber = removeSerialNumber;
        window.triggerFileInput = triggerFileInput;
        window.resetFileInput = resetFileInput;
        window.updateExpectedSerialCount = updateExpectedSerialCount;
    </script>
</body>
</html>