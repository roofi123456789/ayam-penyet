<?php
require_once '../koneksi.php';
requireKasirLogin();
// Pending payment badge
// Safe query - handle missing columns gracefully
$stat_pending_pay = 0;
try {
    $r = $conn->query("SELECT COUNT(*) as c FROM pesanan WHERE (metode_bayar='cash' OR metode_bayar='qris') AND status_verifikasi='menunggu' AND (status_bayar='belum_bayar' OR status_bayar IS NULL)");
    if ($r) $stat_pending_pay = (int)$r->fetch_assoc()['c'];
} catch (Exception $e) { $stat_pending_pay = 0; }

$errors = [];
$success = false;

$kategori_list = [];
$res = $conn->query("SELECT * FROM kategori ORDER BY id");
while ($row = $res->fetch_assoc()) $kategori_list[] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_menu   = trim($_POST['nama_menu'] ?? '');
    $id_kategori = (int)($_POST['id_kategori'] ?? 1);
    $harga       = (int)($_POST['harga'] ?? 0);
    $deskripsi   = trim($_POST['deskripsi'] ?? '');
    $tersedia    = isset($_POST['tersedia']) ? 1 : 0;

    // Validasi
    if (empty($nama_menu))     $errors[] = 'Nama menu wajib diisi';
    if ($harga <= 0)           $errors[] = 'Harga harus lebih dari 0';
    if ($id_kategori <= 0)     $errors[] = 'Pilih kategori';

    // Upload gambar
    $gambar = 'default.jpg';
    if (!empty($_FILES['gambar']['name'])) {
        $file     = $_FILES['gambar'];
        $allowed  = ['jpg','jpeg','png','webp'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $maxSize  = 2 * 1024 * 1024; // 2MB

        if (!in_array($ext, $allowed)) {
            $errors[] = 'Format gambar harus JPG, PNG, atau WEBP';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Ukuran gambar maksimal 2MB';
        } else {
            $filename = 'menu_' . time() . '_' . rand(100,999) . '.' . $ext;
            $dest = __DIR__ . '/../assets/images/' . $filename;
            if (!is_dir(__DIR__ . '/../assets/images/')) {
                mkdir(__DIR__ . '/../assets/images/', 0755, true);
            }
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $gambar = $filename;
            } else {
                $errors[] = 'Gagal mengupload gambar';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO menu (id_kategori, nama_menu, deskripsi, harga, gambar, tersedia) VALUES (?, ?, ?, ?, ?, ?)");
        // Types: id_kategori=i, nama_menu=s, deskripsi=s, harga=i, gambar=s, tersedia=i
        $stmt->bind_param('issisi', $id_kategori, $nama_menu, $deskripsi, $harga, $gambar, $tersedia);

        if ($stmt->execute()) {
            $stmt->close();
            setFlash('success', "Menu \"$nama_menu\" berhasil ditambahkan!");
            redirect('/ayam-penyet/kasir/menu.php');
        } else {
            $errors[] = 'Gagal menyimpan ke database';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Menu - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #E84040; --primary-dark: #C42E2E; --dark: #1A1A2E; --bg: #F0F2F5; --border: #E5E7EB; --radius: 14px; --shadow: 0 2px 16px rgba(0,0,0,0.07); }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); margin: 0; }
        .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: #1A1A2E; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logo h2 { font-size: 15px; font-weight: 800; color: white; margin: 0; }
        .sidebar-logo p { font-size: 11px; color: rgba(255,255,255,0.45); margin: 3px 0 0; }
        .nav-section { padding: 16px 12px 8px; }
        .nav-section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.3); padding: 0 8px; margin-bottom: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 2px; }
        .nav-item a:hover { background: rgba(255,255,255,0.08); color: white; }
        .nav-item a.active { background: var(--primary); color: white; }
        .nav-item a i { width: 18px; text-align: center; }
        .sidebar-footer { margin-top: auto; padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .btn-logout { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; color: rgba(255,255,255,0.5); text-decoration: none; font-size: 12px; font-weight: 600; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(255,255,255,0.08); color: #FF8A8A; }
        .main-content { margin-left: 240px; }
        .topbar { background: white; padding: 16px 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); position: sticky; top: 0; z-index: 100; }
        .back-btn { width: 36px; height: 36px; background: var(--bg); border: none; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--dark); text-decoration: none; transition: background 0.2s; }
        .back-btn:hover { background: #e0e0e0; color: var(--dark); }
        .topbar h1 { font-size: 20px; font-weight: 800; color: var(--dark); margin: 0; }
        .page-body { padding: 24px; max-width: 700px; }
        .form-card { background: white; border-radius: var(--radius); padding: 28px; box-shadow: var(--shadow); }
        .form-section-title { font-size: 14px; font-weight: 800; color: var(--dark); margin: 0 0 16px; padding-bottom: 10px; border-bottom: 1.5px solid var(--border); }
        .form-label { font-size: 13px; font-weight: 700; color: #444; margin-bottom: 6px; }
        .form-control, .form-select {
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            outline: none;
            transition: border 0.2s;
            width: 100%;
        }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(232,64,64,0.1); }
        .input-prefix { background: var(--bg); border: 1.5px solid var(--border); border-right: none; border-radius: 10px 0 0 10px; padding: 11px 14px; font-size: 13px; font-weight: 700; color: #666; }
        .form-control.with-prefix { border-radius: 0 10px 10px 0; }
        .input-group { display: flex; }
        .upload-area {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 28px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .upload-area:hover { border-color: var(--primary); background: #FFF5F5; }
        .upload-area input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-icon { font-size: 36px; margin-bottom: 8px; }
        .upload-text { font-size: 14px; font-weight: 600; color: #666; margin: 0; }
        .upload-hint { font-size: 12px; color: #aaa; margin: 4px 0 0; }
        .preview-img { max-width: 100%; max-height: 200px; border-radius: 10px; margin-top: 12px; display: none; }
        .error-box { background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; }
        .error-box li { font-size: 13px; color: #991B1B; }
        .btn-save { background: var(--primary); color: white; border: none; border-radius: 10px; padding: 13px 28px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; align-items: center; gap: 8px; }
        .btn-save:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 6px 18px rgba(232,64,64,0.35); }
        .btn-cancel { background: var(--bg); color: #666; border: 1.5px solid var(--border); border-radius: 10px; padding: 13px 24px; font-size: 15px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-cancel:hover { background: #e5e7eb; color: var(--dark); }
        .toggle-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; border-radius: 50px; transition: 0.3s; }
        .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background: white; border-radius: 50%; transition: 0.3s; }
        input:checked + .toggle-slider { background: #22C55E; }
        input:checked + .toggle-slider:before { transform: translateX(24px); }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-logo">
        <div style="font-size:28px;margin-bottom:8px">🍗</div>
        <h2>Ayam Penyet</h2>
        <p>Bendungan Batusangkar</p>
    </div>
            <div class="nav-section">
            <div class="nav-section-label">Menu Kasir</div>
            <div class="nav-item">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="menu.php" class="active">
                    <i class="fas fa-utensils"></i> Kelola Menu
                </a>
            </div>
            <div class="nav-item">
                <a href="kategori.php">
                    <i class="fas fa-tags"></i> Kategori
                </a>
            </div>
            <div class="nav-item">
                <a href="konfirmasi_bayar.php">
                    <i class="fas fa-cash-register"></i> Konfirmasi Bayar
                </a>
            </div>
            <div class="nav-item">
                <a href="qrcode.php">
                    <i class="fas fa-qrcode"></i> QR Code Meja
                </a>
            </div>
        </div>
        <div class="sidebar-footer">
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <a href="menu.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h1>➕ Tambah Menu Baru</h1>
    </div>

    <div class="page-body">
        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <ul style="margin:0;padding-left:16px">
                <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-card">
                <h3 class="form-section-title">Informasi Menu</h3>

                <div class="mb-3">
                    <label class="form-label">Nama Menu *</label>
                    <input type="text" name="nama_menu" class="form-control"
                           placeholder="Contoh: Ayam Penyet Original"
                           value="<?= htmlspecialchars($_POST['nama_menu'] ?? '') ?>" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Kategori *</label>
                        <select name="id_kategori" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($kategori_list as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= (($_POST['id_kategori']??'') == $k['id'])?'selected':'' ?>>
                                <?= htmlspecialchars($k['nama_kategori']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Harga *</label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input type="number" name="harga" class="form-control with-prefix"
                                   placeholder="25000" min="0"
                                   value="<?= htmlspecialchars($_POST['harga'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi (Opsional)</label>
                    <textarea name="deskripsi" class="form-control" rows="3"
                              placeholder="Deskripsi singkat menu..."><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Foto Menu (Opsional)</label>
                    <div class="upload-area" id="uploadArea">
                        <input type="file" name="gambar" accept="image/*" onchange="previewImg(event)">
                        <div id="uploadContent">
                            <div class="upload-icon">📷</div>
                            <p class="upload-text">Klik untuk upload foto</p>
                            <p class="upload-hint">JPG, PNG, WEBP · Maks. 2MB</p>
                        </div>
                        <img id="previewImage" class="preview-img" alt="Preview">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Status Ketersediaan</label>
                    <div class="d-flex align-items-center gap-3">
                        <label class="toggle-switch">
                            <input type="checkbox" name="tersedia" value="1" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <span style="font-size:13px;color:#666">Tersedia untuk dipesan</span>
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Simpan Menu
                    </button>
                    <a href="menu.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function previewImg(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
        const img = document.getElementById('previewImage');
        img.src = ev.target.result;
        img.style.display = 'block';
        document.getElementById('uploadContent').style.display = 'none';
    };
    reader.readAsDataURL(file);
}
</script>
</body>
</html>
