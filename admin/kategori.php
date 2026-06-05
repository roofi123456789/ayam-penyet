<?php
require_once '../koneksi.php';
requireRole('admin');
// Pending payment badge
// Safe query - handle missing columns gracefully
$stat_pending_pay = 0;
try {
    $r = $conn->query("SELECT COUNT(*) as c FROM pesanan WHERE (metode_bayar='cash' OR metode_bayar='qris') AND status_verifikasi='menunggu' AND (status_bayar='belum_bayar' OR status_bayar IS NULL)");
    if ($r) $stat_pending_pay = (int)$r->fetch_assoc()['c'];
} catch (Exception $e) { $stat_pending_pay = 0; }

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nama = trim($_POST['nama_kategori'] ?? '');
        if (!empty($nama)) {
            $stmt = $conn->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
            $stmt->bind_param('s', $nama);
            $stmt->execute() ? setFlash('success', "Kategori \"$nama\" ditambahkan!") : setFlash('error', 'Gagal menambahkan kategori.');
            $stmt->close();
        }
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_kategori'] ?? '');
        if ($id > 0 && !empty($nama)) {
            $stmt = $conn->prepare("UPDATE kategori SET nama_kategori=? WHERE id=?");
            $stmt->bind_param('si', $nama, $id);
            $stmt->execute() ? setFlash('success', "Kategori diperbarui!") : setFlash('error', 'Gagal update.');
            $stmt->close();
        }
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        // Cek apakah ada menu dalam kategori ini
        $r_cek = $conn->query("SELECT COUNT(*) as c FROM menu WHERE id_kategori=$id");
        $cek = $r_cek ? (int)$r_cek->fetch_assoc()['c'] : 0;
        if ($cek > 0) {
            setFlash('error', "Tidak bisa hapus! Masih ada $cek menu dalam kategori ini.");
        } else {
            $conn->query("DELETE FROM kategori WHERE id=$id");
            setFlash('success', 'Kategori dihapus!');
        }
    }

    redirect('/ayam-penyet/admin/kategori.php');
}

// Ambil kategori beserta jumlah menu
$kategori_list = [];
$res = $conn->query("SELECT k.*, COUNT(m.id) as jml_menu 
    FROM kategori k LEFT JOIN menu m ON k.id=m.id_kategori 
    GROUP BY k.id ORDER BY k.id ASC");
if ($res) while ($row = $res->fetch_assoc()) $kategori_list[] = $row;

$flash = getFlash();

$cat_icons = ['🍗','🍳','🥤','🍟','🍜','🥘','🍱','🧃','🍖','🍰'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Menu - Admin</title>
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

        .kat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-bottom:24px;}
        .kat-card{background:white;border-radius:var(--radius);padding:18px 20px;box-shadow:var(--shadow);display:flex;align-items:center;gap:14px;transition:transform 0.2s;}
        .kat-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.1);}
        .kat-icon{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#FFE8E8,#FFD0D0);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
        .kat-info{flex:1;min-width:0;}
        .kat-nama{font-size:15px;font-weight:800;color:var(--dark);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .kat-jml{font-size:12px;color:#888;margin:2px 0 0;}
        .kat-actions{display:flex;gap:6px;flex-shrink:0;}
        .btn-edit-kat{width:32px;height:32px;background:#EFF6FF;border:none;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#2563EB;cursor:pointer;transition:all 0.2s;font-size:13px;}
        .btn-edit-kat:hover{background:#2563EB;color:white;}
        .btn-del-kat{width:32px;height:32px;background:#FEF2F2;border:none;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#DC2626;cursor:pointer;transition:all 0.2s;font-size:13px;}
        .btn-del-kat:hover{background:#DC2626;color:white;}

        .form-card{background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);}
        .form-title{font-size:15px;font-weight:800;color:var(--dark);margin:0 0 16px;padding-bottom:12px;border-bottom:1.5px solid var(--border);}
        .form-lbl{font-size:13px;font-weight:700;color:#555;margin-bottom:6px;display:block;}
        .form-inp{border:1.5px solid var(--border);border-radius:10px;padding:11px 14px;font-size:14px;font-family:inherit;outline:none;transition:border 0.2s;width:100%;}
        .form-inp:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(232,64,64,0.08);}
        .btn-submit{background:var(--primary);color:white;border:none;border-radius:10px;padding:11px 24px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all 0.2s;}
        .btn-submit:hover{background:#C42E2E;transform:translateY(-1px);}
        .btn-cancel{background:var(--bg);color:#666;border:1.5px solid var(--border);border-radius:10px;padding:11px 20px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all 0.2s;}
        .btn-cancel:hover{background:#e5e7eb;}

        /* Modal */
        .modal-content{border:none;border-radius:20px;}
        .modal-header{border-bottom:1px solid var(--border);padding:18px 24px;}
        .modal-body{padding:20px 24px;}
        .modal-footer{border-top:1px solid var(--border);padding:14px 24px;}
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
        <div class="nav-lbl">Menu Utama</div>
        <div class="nav-item"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="konfirmasi_bayar.php"><i class="fas fa-cash-register"></i> Konfirmasi Bayar</a></div>
        <div class="nav-item"><a href="menu.php"><i class="fas fa-utensils"></i> Kelola Menu</a></div>
        <div class="nav-item"><a href="kategori.php" class="active"><i class="fas fa-tags"></i> Kategori</a></div>
        <div class="nav-item"><a href="kitchen.php"><i class="fas fa-tv"></i> Kitchen Display</a></div>
        <div class="nav-item"><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></div>
        <div class="nav-item"><a href="qrcode.php"><i class="fas fa-qrcode"></i> QR Code</a></div>
        <div class="nav-item"><a href="meja.php"><i class="fas fa-chair"></i> Manajemen Meja</a></div>
        <div class="nav-item"><a href="admin_user.php"><i class="fas fa-users-cog"></i> Kelola Admin</a></div>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <h1>🏷️ Manajemen Kategori</h1>
        <span style="font-size:13px;color:#888"><?= count($kategori_list) ?> kategori</span>
    </div>

    <div class="page-body">
        <?php if ($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
            <i class="fas <?= $flash['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Daftar Kategori -->
        <div class="kat-grid">
            <?php foreach ($kategori_list as $i => $kat): ?>
            <div class="kat-card">
                <div class="kat-icon"><?= $cat_icons[$i % count($cat_icons)] ?></div>
                <div class="kat-info">
                    <p class="kat-nama"><?= htmlspecialchars($kat['nama_kategori']) ?></p>
                    <p class="kat-jml"><?= $kat['jml_menu'] ?> menu</p>
                </div>
                <div class="kat-actions">
                    <button class="btn-edit-kat" onclick="editKat(<?= $kat['id'] ?>,'<?= htmlspecialchars($kat['nama_kategori'],ENT_QUOTES) ?>')" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php if ($kat['jml_menu'] == 0): ?>
                    <form method="POST" onsubmit="return confirm('Hapus kategori ini?')">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id" value="<?= $kat['id'] ?>">
                        <button type="submit" class="btn-del-kat" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="btn-del-kat" style="opacity:0.3;cursor:not-allowed" disabled title="Ada menu di kategori ini">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Form Tambah Kategori -->
        <div class="form-card">
            <h3 class="form-title">➕ Tambah Kategori Baru</h3>
            <form method="POST" class="d-flex gap-3 align-items-end">
                <input type="hidden" name="action" value="tambah">
                <div style="flex:1">
                    <label class="form-lbl">Nama Kategori *</label>
                    <input type="text" name="nama_kategori" class="form-inp" placeholder="Contoh: Makanan Berat, Dessert, Minuman Panas..." required>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-plus me-1"></i> Tambah
                </button>
            </form>
        </div>

    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-weight:800;font-size:15px">✏️ Edit Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <label class="form-lbl">Nama Kategori</label>
                    <input type="text" name="nama_kategori" id="editNama" class="form-inp" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-submit">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editKat(id, nama) {
    document.getElementById('editId').value = id;
    document.getElementById('editNama').value = nama;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>
