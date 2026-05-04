<?php
require_once '../koneksi.php';
requireAdminLogin();
// Pending payment badge
// Safe query - handle missing columns gracefully
$stat_pending_pay = 0;
try {
    $r = $conn->query("SELECT COUNT(*) as c FROM pesanan WHERE (metode_bayar='cash' OR metode_bayar='qris') AND status_verifikasi='menunggu' AND (status_bayar='belum_bayar' OR status_bayar IS NULL)");
    if ($r) $stat_pending_pay = (int)$r->fetch_assoc()['c'];
} catch (Exception $e) { $stat_pending_pay = 0; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $username = sanitize($_POST['username'] ?? '');
        $nama     = sanitize($_POST['nama'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role']??'', ['admin','kasir']) ? $_POST['role'] : 'kasir';

        if (strlen($password) < 6) {
            setFlash('error', 'Password minimal 6 karakter!');
        } elseif (empty($username) || empty($nama)) {
            setFlash('error', 'Username dan nama wajib diisi!');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin (username, password, nama) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $username, $hash, $nama);
            $stmt->execute() ? setFlash('success', "Admin \"$username\" berhasil ditambahkan!") : setFlash('error', 'Username sudah digunakan!');
            $stmt->close();
        }
    }

    if ($action === 'ganti_password') {
        $id       = (int)($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        if ($id > 0 && strlen($password) >= 6) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin SET password=? WHERE id=?");
            $stmt->bind_param('si', $hash, $id);
            $stmt->execute() ? setFlash('success', 'Password berhasil diubah!') : setFlash('error', 'Gagal ganti password.');
            $stmt->close();
        } else {
            setFlash('error', 'Password minimal 6 karakter!');
        }
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        // Jangan hapus diri sendiri
        if ($id === (int)$_SESSION['admin_id']) {
            setFlash('error', 'Tidak bisa menghapus akun sendiri!');
        } elseif ($id > 0) {
            $conn->query("DELETE FROM admin WHERE id=$id");
            setFlash('success', 'Admin dihapus!');
        }
    }

    redirect('/ayam-penyet/admin/admin_user.php');
}

$admin_list = [];
$res = $conn->query("SELECT id, username, nama, created_at FROM admin ORDER BY id ASC");
while ($row = $res->fetch_assoc()) $admin_list[] = $row;

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Admin - Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#E84040;--dark:#1A1A2E;--bg:#F0F2F5;--border:#E5E7EB;--radius:14px;--shadow:0 2px 16px rgba(0,0,0,0.07);}
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);margin:0;}
        .sidebar{position:fixed;top:0;left:0;width:240px;height:100vh;background:#1A1A2E;z-index:1000;display:flex;flex-direction:column;overflow-y:auto;}
        .sidebar-logo{padding:24px 20px;border-bottom:1px solid rgba(255,255,255,0.08);}
        .sidebar-logo h2{font-size:15px;font-weight:800;color:white;margin:0;}
        .sidebar-logo p{font-size:11px;color:rgba(255,255,255,0.45);margin:3px 0 0;}
        .nav-section{padding:16px 12px 8px;}
        .nav-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.3);padding:0 8px;margin-bottom:6px;}
        .nav-item a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;color:rgba(255,255,255,0.6);transition:all 0.2s;margin-bottom:2px;}
        .nav-item a:hover{background:rgba(255,255,255,0.08);color:white;}
        .nav-item a.active{background:var(--primary);color:white;}
        .nav-item a i{width:18px;text-align:center;}
        .sidebar-footer{margin-top:auto;padding:16px 12px;border-top:1px solid rgba(255,255,255,0.08);}
        .btn-logout{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;color:rgba(255,255,255,0.5);text-decoration:none;font-size:12px;font-weight:600;}
        .btn-logout:hover{background:rgba(255,255,255,0.08);color:#FF8A8A;}
        .main-content{margin-left:240px;}
        .topbar{background:white;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 4px rgba(0,0,0,0.06);position:sticky;top:0;z-index:100;}
        .topbar h1{font-size:20px;font-weight:800;color:var(--dark);margin:0;}
        .page-body{padding:24px;}

        .flash{border-radius:12px;padding:13px 18px;margin-bottom:20px;font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px;}
        .flash-success{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;}
        .flash-error{background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;}

        .admin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-bottom:24px;}
        .admin-card{background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);transition:transform 0.2s;}
        .admin-card:hover{transform:translateY(-2px);}
        .admin-card.me{border:2px solid var(--primary);}
        .admin-avatar{width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,var(--primary),#C42E2E);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:white;margin-bottom:12px;}
        .admin-name{font-size:16px;font-weight:800;color:var(--dark);margin:0 0 3px;}
        .admin-user{font-size:13px;color:#888;font-family:monospace;margin:0 0 4px;}
        .admin-date{font-size:11px;color:#aaa;}
        .me-badge{background:var(--primary);color:white;border-radius:50px;padding:2px 10px;font-size:10px;font-weight:700;margin-left:8px;}

        .card-actions{display:flex;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border);}
        .btn-pw{flex:1;background:#EFF6FF;border:none;border-radius:8px;padding:8px;font-size:12px;font-weight:700;color:#2563EB;cursor:pointer;font-family:inherit;transition:all 0.2s;}
        .btn-pw:hover{background:#2563EB;color:white;}
        .btn-del{background:#FEF2F2;border:none;border-radius:8px;padding:8px 12px;font-size:12px;font-weight:700;color:#DC2626;cursor:pointer;font-family:inherit;transition:all 0.2s;}
        .btn-del:hover{background:#DC2626;color:white;}

        .form-card{background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);}
        .form-title{font-size:15px;font-weight:800;color:var(--dark);margin:0 0 16px;padding-bottom:12px;border-bottom:1.5px solid var(--border);}
        .form-lbl{font-size:13px;font-weight:700;color:#555;margin-bottom:6px;display:block;}
        .form-inp,.form-sel{border:1.5px solid var(--border);border-radius:10px;padding:11px 14px;font-size:14px;font-family:inherit;outline:none;transition:border 0.2s;width:100%;}
        .form-inp:focus,.form-sel:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(232,64,64,0.08);}
        .btn-submit{background:var(--primary);color:white;border:none;border-radius:10px;padding:11px 24px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all 0.2s;}
        .btn-submit:hover{background:#C42E2E;}
        .modal-content{border:none;border-radius:20px;}
        .modal-header{border-bottom:1px solid var(--border);padding:18px 24px;}
        .modal-body{padding:20px 24px;}
        .modal-footer{border-top:1px solid var(--border);padding:14px 24px;}
        .btn-cancel{background:var(--bg);color:#666;border:1.5px solid var(--border);border-radius:10px;padding:9px 20px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;}
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-logo">
        <div style="font-size:28px;margin-bottom:8px">🍽️</div>
        <h2>AYAM PENYET</h2>
        <p>Bendungan Batusangkar</p>
    </div>
    <div class="nav-section">
        <div class="nav-lbl">Menu Utama</div>
        <div class="nav-item"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="konfirmasi_bayar.php"><i class="fas fa-cash-register"></i> Konfirmasi Bayar</a></div>
        <div class="nav-item"><a href="menu.php"><i class="fas fa-utensils"></i> Kelola Menu</a></div>
        <div class="nav-item"><a href="kategori.php"><i class="fas fa-tags"></i> Kategori</a></div>
        <div class="nav-item"><a href="kitchen.php"><i class="fas fa-tv"></i> Kitchen Display</a></div>
        <div class="nav-item"><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></div>
        <div class="nav-item"><a href="qrcode.php"><i class="fas fa-qrcode"></i> QR Code</a></div>
        <div class="nav-item"><a href="meja.php"><i class="fas fa-chair"></i> Manajemen Meja</a></div>
        <div class="nav-item"><a href="admin_user.php" class="active"><i class="fas fa-users-cog"></i> Kelola Admin</a></div>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <h1>👥 Kelola Admin</h1>
        <span style="font-size:13px;color:#888"><?= count($admin_list) ?> akun terdaftar</span>
    </div>

    <div class="page-body">
        <?php if ($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
            <i class="fas <?= $flash['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Daftar Admin -->
        <div class="admin-grid">
            <?php foreach ($admin_list as $adm):
                $is_me = ($adm['id'] == $_SESSION['admin_id']);
            ?>
            <div class="admin-card <?= $is_me ? 'me' : '' ?>">
                <div class="admin-avatar"><?= strtoupper(substr($adm['nama'],0,1)) ?></div>
                <div class="admin-name">
                    <?= htmlspecialchars($adm['nama']) ?>
                    <?php if ($is_me): ?><span class="me-badge">Saya</span><?php endif; ?>
                </div>
                <div class="admin-user">@<?= htmlspecialchars($adm['username']) ?></div>
                <div class="admin-date">Bergabung <?= date('d M Y', strtotime($adm['created_at'])) ?></div>
                <div class="card-actions">
                    <button class="btn-pw" onclick="gantiPassword(<?= $adm['id'] ?>,'<?= htmlspecialchars($adm['username'],ENT_QUOTES) ?>')">
                        <i class="fas fa-key me-1"></i>Ganti Password
                    </button>
                    <?php if (!$is_me): ?>
                    <form method="POST" onsubmit="return confirm('Hapus admin <?= htmlspecialchars($adm['username'],ENT_QUOTES) ?>?')">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id" value="<?= $adm['id'] ?>">
                        <button type="submit" class="btn-del"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Form Tambah Admin -->
        <div class="form-card">
            <h3 class="form-title">➕ Tambah Admin Baru</h3>
            <form method="POST">
                <input type="hidden" name="action" value="tambah">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-lbl">Username *</label>
                        <input type="text" name="username" class="form-inp" placeholder="kasir2" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-lbl">Nama Lengkap *</label>
                        <input type="text" name="nama" class="form-inp" placeholder="Nama Kasir" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-lbl">Password *</label>
                        <input type="password" name="password" class="form-inp" placeholder="Min. 6 karakter" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn-submit w-100">
                            <i class="fas fa-user-plus me-1"></i>Tambah
                        </button>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- Modal Ganti Password -->
<div class="modal fade" id="pwModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-weight:800;font-size:15px">🔑 Ganti Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="ganti_password">
                <input type="hidden" name="id" id="pwAdminId">
                <div class="modal-body">
                    <p style="font-size:13px;color:#666;margin-bottom:14px">Ganti password untuk: <strong id="pwAdminUsername"></strong></p>
                    <label class="form-lbl">Password Baru (min. 6 karakter)</label>
                    <input type="password" name="password" id="pwInput" class="form-inp" placeholder="Masukkan password baru" required minlength="6">
                    <div id="pwStrength" style="margin-top:8px;font-size:11px;color:#888"></div>
                </div>
                <div class="modal-footer" style="gap:8px">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-submit">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function gantiPassword(id, username) {
    document.getElementById('pwAdminId').value = id;
    document.getElementById('pwAdminUsername').textContent = '@' + username;
    document.getElementById('pwInput').value = '';
    new bootstrap.Modal(document.getElementById('pwModal')).show();
    setTimeout(() => document.getElementById('pwInput').focus(), 300);
}

document.getElementById('pwInput')?.addEventListener('input', function() {
    const v = this.value;
    const el = document.getElementById('pwStrength');
    if (!v) { el.textContent = ''; return; }
    if (v.length < 6) { el.textContent = '⚠️ Terlalu pendek'; el.style.color='#D97706'; return; }
    if (v.length < 8) { el.textContent = '🔓 Lemah'; el.style.color='#D97706'; return; }
    if (/[A-Z]/.test(v) && /[0-9]/.test(v)) { el.textContent = '🔒 Kuat'; el.style.color='#16A34A'; }
    else { el.textContent = '🔑 Cukup'; el.style.color='#2563EB'; }
});
</script>
</body>
</html>
