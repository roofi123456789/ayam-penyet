<?php
// ============================================
// setup.php - Halaman Setup Awal
// Jalankan SEKALI saat pertama install
// Hapus file ini setelah selesai!
// ============================================

// Proteksi: hanya bisa diakses dari localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('<h2>⛔ Akses ditolak. File ini hanya bisa dijalankan dari localhost.</h2>');
}

require_once 'koneksi.php';

$messages = [];
$errors   = [];

// ===== STEP 1: CEK DATABASE =====
$tables_needed = ['admin', 'kategori', 'menu', 'pesanan', 'detail_pesanan', 'meja'];
$tables_ok = [];
foreach ($tables_needed as $tbl) {
    $res = $conn->query("SHOW TABLES LIKE '$tbl'");
    $tables_ok[$tbl] = ($res->num_rows > 0);
}

$all_tables_exist = !in_array(false, $tables_ok);

// ===== STEP 2: HANDLE FORM =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_admin') {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $nama     = sanitize($_POST['nama'] ?? '');

        if (strlen($password) < 6) {
            $errors[] = 'Password minimal 6 karakter';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin SET password=?, nama=? WHERE username=?");
            $stmt->bind_param('sss', $hash, $nama, $username);
            if ($stmt->execute()) {
                $messages[] = "✅ Password admin '$username' berhasil diupdate!";
            } else {
                $errors[] = "Gagal update admin: " . $conn->error;
            }
            $stmt->close();
        }
    }

    if ($action === 'tambah_admin') {
        $username = sanitize($_POST['new_username'] ?? '');
        $password = $_POST['new_password'] ?? '';
        $nama     = sanitize($_POST['new_nama'] ?? '');

        if (strlen($password) < 6) {
            $errors[] = 'Password minimal 6 karakter';
        } elseif (empty($username)) {
            $errors[] = 'Username wajib diisi';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin (username, password, nama) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $username, $hash, $nama);
            if ($stmt->execute()) {
                $messages[] = "✅ Admin '$username' berhasil ditambahkan!";
            } else {
                $errors[] = "Gagal tambah admin (mungkin username sudah ada): " . $conn->error;
            }
            $stmt->close();
        }
    }

    if ($action === 'reset_data') {
        $conn->query("DELETE FROM detail_pesanan");
        $conn->query("DELETE FROM pesanan");
        $conn->query("ALTER TABLE pesanan AUTO_INCREMENT = 1");
        $conn->query("ALTER TABLE detail_pesanan AUTO_INCREMENT = 1");
        $messages[] = "✅ Data pesanan berhasil direset!";
    }
}

// Ambil daftar admin
$admins = [];
if ($all_tables_exist) {
    $res = $conn->query("SELECT id, username, nama, created_at FROM admin ORDER BY id");
    while ($row = $res->fetch_assoc()) $admins[] = $row;
}

// Stats
$stat_menu    = $all_tables_exist ? (($r=@$conn->query("SELECT COUNT(*) c FROM menu"))? (int)$r->fetch_assoc()['c'] : 0) : 0;
$stat_pesanan = $all_tables_exist ? (($r=@$conn->query("SELECT COUNT(*) c FROM pesanan"))? (int)$r->fetch_assoc()['c'] : 0) : 0;
$stat_meja    = $all_tables_exist ? (($r=@$conn->query("SELECT COUNT(*) c FROM meja"))? (int)$r->fetch_assoc()['c'] : 0) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F0F2F5; }
        .setup-header { background: linear-gradient(135deg, #1A1A2E, #0F3460); color: white; padding: 32px 0; margin-bottom: 32px; }
        .setup-header h1 { font-size: 24px; font-weight: 800; margin: 0; }
        .setup-header p { margin: 4px 0 0; opacity: 0.6; font-size: 14px; }
        .card { border: none; border-radius: 14px; box-shadow: 0 2px 16px rgba(0,0,0,0.07); margin-bottom: 20px; }
        .card-header { background: none; border-bottom: 1px solid #eee; font-weight: 800; font-size: 15px; padding: 16px 20px; }
        .card-body { padding: 20px; }
        .table-check { font-size: 13px; }
        .ok { color: #16A34A; font-weight: 700; }
        .fail { color: #DC2626; font-weight: 700; }
        .stat-item { text-align: center; }
        .stat-val { font-size: 28px; font-weight: 800; color: #E84040; }
        .stat-lbl { font-size: 12px; color: #888; }
        .form-label { font-weight: 700; font-size: 13px; }
        .form-control { border-radius: 8px; border: 1.5px solid #e5e7eb; font-size: 14px; }
        .form-control:focus { border-color: #E84040; box-shadow: 0 0 0 3px rgba(232,64,64,0.1); }
        .btn-danger-custom { background: #E84040; color: white; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 700; cursor: pointer; font-size: 14px; transition: all 0.2s; }
        .btn-danger-custom:hover { background: #C42E2E; }
        .warning-box { background: #FEF3C7; border: 1px solid #FCD34D; border-radius: 10px; padding: 14px 18px; font-size: 13px; color: #92400E; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="setup-header">
    <div class="container">
        <h1>⚙️ Setup & Konfigurasi</h1>
        <p><?= APP_NAME ?> · v<?= APP_VERSION ?></p>
    </div>
</div>

<div class="container" style="max-width: 900px;">

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 rounded-3">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
    <div class="alert alert-success border-0 rounded-3">
        <?php foreach ($messages as $m): ?><div><?= $m ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="warning-box">
        <strong>⚠️ Perhatian:</strong> Halaman ini hanya untuk administrator. Hapus atau proteksi file
        <code>setup.php</code> setelah konfigurasi selesai!
    </div>

    <!-- Cek Status Database -->
    <div class="card">
        <div class="card-header">📊 Status Database & Tabel</div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-4"><div class="stat-item"><div class="stat-val"><?= $stat_menu ?></div><div class="stat-lbl">Menu</div></div></div>
                <div class="col-4"><div class="stat-item"><div class="stat-val"><?= $stat_pesanan ?></div><div class="stat-lbl">Pesanan</div></div></div>
                <div class="col-4"><div class="stat-item"><div class="stat-val"><?= $stat_meja ?></div><div class="stat-lbl">Meja</div></div></div>
            </div>
            <table class="table table-check table-borderless mb-0">
                <thead><tr><th>Tabel</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($tables_ok as $tbl => $ok): ?>
                    <tr>
                        <td><code><?= $tbl ?></code></td>
                        <td><?= $ok ? '<span class="ok">✅ Ada</span>' : '<span class="fail">❌ Tidak Ditemukan — Import database.sql dulu!</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$all_tables_exist): ?>
            <div class="alert alert-warning mt-3 mb-0" style="font-size:13px">
                ⚠️ Beberapa tabel tidak ditemukan. Buka <strong>phpMyAdmin</strong> dan import file <strong>database.sql</strong>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kelola Admin -->
    <?php if ($all_tables_exist): ?>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">🔑 Update Password Admin</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_admin">
                        <div class="mb-3">
                            <label class="form-label">Pilih Admin</label>
                            <select name="username" class="form-control">
                                <?php foreach ($admins as $a): ?>
                                <option value="<?= htmlspecialchars($a['username']) ?>">
                                    <?= htmlspecialchars($a['username']) ?> (<?= htmlspecialchars($a['nama']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" placeholder="Nama admin" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 6 karakter" required>
                        </div>
                        <button type="submit" class="btn-danger-custom w-100">
                            <i class="fas fa-key me-1"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">➕ Tambah Admin Baru</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="tambah_admin">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="new_username" class="form-control" placeholder="username_baru" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="new_nama" class="form-control" placeholder="Nama lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Min. 6 karakter" required>
                        </div>
                        <button type="submit" class="btn-danger-custom w-100">
                            <i class="fas fa-user-plus me-1"></i> Tambah Admin
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daftar Admin -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">👥 Daftar Admin Terdaftar</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0" style="font-size:13px">
                        <thead class="table-light">
                            <tr><th>ID</th><th>Username</th><th>Nama</th><th>Dibuat</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $a): ?>
                            <tr>
                                <td><?= $a['id'] ?></td>
                                <td><code><?= htmlspecialchars($a['username']) ?></code></td>
                                <td><?= htmlspecialchars($a['nama']) ?></td>
                                <td><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Reset Data -->
        <div class="col-12">
            <div class="card border-danger">
                <div class="card-header text-danger">⚠️ Zona Berbahaya — Reset Data</div>
                <div class="card-body">
                    <p style="font-size:13px;color:#666">Hapus semua data pesanan (menu dan admin tidak dihapus). Gunakan hanya jika ingin memulai dari awal.</p>
                    <form method="POST" onsubmit="return confirm('⚠️ YAKIN? Semua data pesanan akan dihapus permanen!')">
                        <input type="hidden" name="action" value="reset_data">
                        <button type="submit" class="btn btn-outline-danger" style="font-size:13px;font-weight:700">
                            <i class="fas fa-trash me-1"></i> Reset Semua Data Pesanan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Link navigasi -->
    <div class="mt-4 mb-5 d-flex gap-3 flex-wrap">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">← Halaman Menu</a>
        <a href="admin/login.php" class="btn btn-outline-danger btn-sm">→ Login Admin</a>
    </div>
</div>
</body>
</html>
