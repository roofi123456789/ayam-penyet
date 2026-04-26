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

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nomor  = (int)($_POST['nomor_meja'] ?? 0);
        $nama   = sanitize($_POST['nama_meja'] ?? '');
        $kap    = (int)($_POST['kapasitas'] ?? 4);

        if ($nomor > 0) {
            $stmt = $conn->prepare("INSERT INTO meja (nomor_meja, nama_meja, kapasitas) VALUES (?, ?, ?)");
            $stmt->bind_param('isi', $nomor, $nama, $kap);
            if ($stmt->execute()) setFlash('success', "Meja $nomor berhasil ditambahkan!");
            else setFlash('error', 'Nomor meja sudah ada!');
            $stmt->close();
        }
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("DELETE FROM meja WHERE id=$id");
        setFlash('success', 'Meja berhasil dihapus!');
    }

    if ($action === 'update_status') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = sanitize($_POST['status'] ?? '');
        $valid  = ['tersedia', 'terisi', 'reserved'];
        if (in_array($status, $valid)) {
            $conn->query("UPDATE meja SET status='$status' WHERE id=$id");
        }
    }

    redirect('/ayam-penyet/admin/meja.php');
}

$meja_list = [];
$res = $conn->query("SELECT m.*, 
    (SELECT COUNT(*) FROM pesanan p WHERE p.nomor_meja=m.nomor_meja AND p.status IN ('pending','diproses') AND DATE(p.tanggal)=CURDATE()) as pesanan_aktif
    FROM meja m ORDER BY m.nomor_meja ASC");
while ($row = $res->fetch_assoc()) $meja_list[] = $row;

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Meja - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #E84040; --primary-dark: #C42E2E; --dark: #1A1A2E; --bg: #F0F2F5; --border: #E5E7EB; --radius: 14px; --shadow: 0 2px 16px rgba(0,0,0,0.07); }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); margin: 0; }
        .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: #1A1A2E; z-index: 1000; display: flex; flex-direction: column; overflow-y: auto; }
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
        .btn-logout { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; color: rgba(255,255,255,0.5); text-decoration: none; font-size: 12px; font-weight: 600; }
        .btn-logout:hover { background: rgba(255,255,255,0.08); color: #FF8A8A; }
        .main-content { margin-left: 240px; }
        .topbar { background: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .topbar h1 { font-size: 20px; font-weight: 800; color: var(--dark); margin: 0; }
        .page-body { padding: 24px; }
        .flash-alert { border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .flash-success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .flash-error   { background: #FEF2F2; border: 1px solid #FCA5A5; color: #991B1B; }

        .meja-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .meja-card { background: white; border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); text-align: center; transition: transform 0.2s; position: relative; }
        .meja-card:hover { transform: translateY(-2px); }

        .meja-num { font-size: 36px; font-weight: 800; color: var(--primary); margin: 0; }
        .meja-nama { font-size: 12px; color: #888; margin: 2px 0 12px; }
        .meja-cap { font-size: 12px; color: #666; margin-bottom: 12px; }

        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .dot-tersedia { background: #22C55E; }
        .dot-terisi   { background: #E84040; }
        .dot-reserved { background: #F59E0B; }

        .status-text-tersedia { color: #16A34A; }
        .status-text-terisi   { color: #E84040; }
        .status-text-reserved { color: #D97706; }

        .meja-actions { display: flex; gap: 6px; margin-top: 12px; }
        .btn-status-change { flex: 1; border: 1.5px solid var(--border); background: var(--bg); border-radius: 7px; padding: 6px; font-size: 11px; font-weight: 700; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; transition: all 0.2s; }
        .btn-status-change:hover { background: white; border-color: var(--primary); color: var(--primary); }
        .btn-hapus-meja { border: 1.5px solid #FCA5A5; background: #FEF2F2; color: #DC2626; border-radius: 7px; padding: 6px 8px; font-size: 11px; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; transition: all 0.2s; }
        .btn-hapus-meja:hover { background: #DC2626; color: white; border-color: #DC2626; }

        .pesanan-badge { position: absolute; top: -6px; right: -6px; background: var(--primary); color: white; width: 22px; height: 22px; border-radius: 50%; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; }

        .tambah-card { background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
        .form-label { font-size: 13px; font-weight: 700; color: #444; margin-bottom: 6px; }
        .form-control, .form-select { border: 1.5px solid var(--border); border-radius: 8px; padding: 10px 14px; font-size: 14px; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; transition: border 0.2s; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); }
        .btn-tambah-submit { background: var(--primary); color: white; border: none; border-radius: 8px; padding: 11px 24px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; transition: all 0.2s; }
        .btn-tambah-submit:hover { background: var(--primary-dark); }
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
        <div class="nav-section-label">Menu Utama</div>
        <div class="nav-item"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="konfirmasi_bayar.php"><i class="fas fa-cash-register"></i> Konfirmasi Bayar</a></div>
        <div class="nav-item"><a href="menu.php"><i class="fas fa-utensils"></i> Kelola Menu</a></div>
        <div class="nav-item"><a href="kategori.php"><i class="fas fa-tags"></i> Kategori</a></div>
        <div class="nav-item"><a href="kitchen.php"><i class="fas fa-tv"></i> Kitchen Display</a></div>
        <div class="nav-item"><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></div>
        <div class="nav-item"><a href="qrcode.php"><i class="fas fa-qrcode"></i> QR Code</a></div>
        <div class="nav-item"><a href="meja.php" class="active"><i class="fas fa-chair"></i> Manajemen Meja</a></div>
        <div class="nav-item"><a href="admin_user.php"><i class="fas fa-users-cog"></i> Kelola Admin</a></div>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <h1>🪑 Manajemen Meja</h1>
        <span style="font-size:13px;color:#888"><?= count($meja_list) ?> meja terdaftar</span>
    </div>

    <div class="page-body">
        <?php if ($flash): ?>
        <div class="flash-alert flash-<?= $flash['type'] ?>">
            <i class="fas <?= $flash['type']==='success'?'fa-check-circle':'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Denah Meja -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;box-shadow:var(--shadow)">
            <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
                <span style="font-size:13px;font-weight:700;color:#666">Legenda:</span>
                <span style="font-size:13px"><span class="status-dot dot-tersedia"></span>Tersedia</span>
                <span style="font-size:13px"><span class="status-dot dot-terisi"></span>Terisi</span>
                <span style="font-size:13px"><span class="status-dot dot-reserved"></span>Reserved</span>
                <span style="font-size:12px;color:#888;margin-left:auto"><i class="fas fa-fire text-danger me-1"></i>= Ada pesanan aktif hari ini</span>
            </div>
        </div>

        <div class="meja-grid">
            <?php foreach ($meja_list as $meja): ?>
            <div class="meja-card">
                <?php if ($meja['pesanan_aktif'] > 0): ?>
                <div class="pesanan-badge"><?= $meja['pesanan_aktif'] ?></div>
                <?php endif; ?>

                <div class="meja-num"><?= $meja['nomor_meja'] ?></div>
                <div class="meja-nama"><?= htmlspecialchars($meja['nama_meja'] ?? 'Meja '.$meja['nomor_meja']) ?></div>
                <div class="meja-cap">
                    <i class="fas fa-users me-1" style="color:#aaa"></i><?= $meja['kapasitas'] ?> kursi
                </div>

                <div>
                    <span class="status-dot dot-<?= $meja['status'] ?>"></span>
                    <span class="status-text-<?= $meja['status'] ?>" style="font-size:13px;font-weight:700">
                        <?= ucfirst($meja['status']) ?>
                    </span>
                </div>

                <div class="meja-actions">
                    <form method="POST" style="flex:1">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?= $meja['id'] ?>">
                        <select name="status" class="btn-status-change w-100" onchange="this.form.submit()" style="border-radius:7px;border:1.5px solid var(--border);padding:6px;font-size:11px;font-weight:700;background:#F0F2F5;cursor:pointer;font-family:inherit">
                            <option value="tersedia" <?= $meja['status']==='tersedia'?'selected':'' ?>>✅ Tersedia</option>
                            <option value="terisi"   <?= $meja['status']==='terisi'?'selected':'' ?>>🔴 Terisi</option>
                            <option value="reserved" <?= $meja['status']==='reserved'?'selected':'' ?>>🟡 Reserved</option>
                        </select>
                    </form>
                    <form method="POST" onsubmit="return confirm('Hapus Meja <?= $meja['nomor_meja'] ?>?')">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id" value="<?= $meja['id'] ?>">
                        <button type="submit" class="btn-hapus-meja"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Form Tambah Meja -->
        <div class="tambah-card">
            <h3 style="font-size:15px;font-weight:800;margin:0 0 16px">➕ Tambah Meja Baru</h3>
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="tambah">
                <div class="col-md-3">
                    <label class="form-label">Nomor Meja *</label>
                    <input type="number" name="nomor_meja" class="form-control" placeholder="11" min="1" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Nama Meja</label>
                    <input type="text" name="nama_meja" class="form-control" placeholder="Meja 11 - Teras">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kapasitas</label>
                    <select name="kapasitas" class="form-select">
                        <?php for ($i=2;$i<=10;$i++): ?>
                        <option value="<?= $i ?>" <?= $i==4?'selected':'' ?>><?= $i ?> orang</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn-tambah-submit w-100">Tambah</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
