<?php
require_once '../koneksi.php';
requireRole('admin');

// Statistik real-time
$stat_pending   = (int)$conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='pending'")->fetch_assoc()['c'];
$stat_diproses  = (int)$conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='diproses'")->fetch_assoc()['c'];
$stat_selesai   = (int)$conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='selesai'")->fetch_assoc()['c'];
$stat_dibatalkan= (int)$conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='dibatalkan'")->fetch_assoc()['c'];
$omzet_hari_ini = (int)$conn->query("SELECT COALESCE(SUM(total_harga),0) t FROM pesanan WHERE DATE(tanggal)=CURDATE() AND status_bayar='lunas'")->fetch_assoc()['t'];
$omzet_bulan    = (int)$conn->query("SELECT COALESCE(SUM(total_harga),0) t FROM pesanan WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE()) AND status_bayar='lunas'")->fetch_assoc()['t'];

// Pesanan hari ini
$filter_status = $_GET['status'] ?? 'all';
$where = "WHERE DATE(p.tanggal)=CURDATE()";
if ($filter_status !== 'all') $where .= " AND p.status='$filter_status'";

$result = $conn->query("SELECT p.*, (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan=p.id) as jml_item
    FROM pesanan p $where ORDER BY FIELD(p.status,'pending','diproses','selesai','dibatalkan'), p.tanggal DESC LIMIT 50");
$pesanan_list = [];
if ($result) while ($row = $result->fetch_assoc()) $pesanan_list[] = $row;

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #7C3AED; --primary-dark: #5B21B6;
            --sidebar-bg: #1A1A2E; --bg: #F0F2F5; --surface: #fff;
            --text: #2D2D2D; --text-muted: #888; --border: #E5E7EB;
            --radius: 14px; --shadow: 0 2px 16px rgba(0,0,0,0.07);
        }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); margin: 0; }
        .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: var(--sidebar-bg); z-index: 1000; display: flex; flex-direction: column; overflow-y: auto; }
       .sidebar-logo{
    text-align: center;
    padding: 20px;
}

.sidebar-logo img{
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;

    background: white;
    padding: 5px;

    border: 3px solid #ff5722;

    box-shadow: 0 0 15px rgba(255,87,34,.4);
    margin-bottom: 10px;
}

.sidebar-logo h2{
    color: white;
    font-size: 24px;
    margin: 0;
}

.sidebar-logo p{
    color: rgba(255,255,255,.7);
    font-size: 14px;
    margin-top: 5px;
}
        .role-label { display: inline-block; background: rgba(124,58,237,0.25); color: #C084FC; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; margin-top: 6px; border: 1px solid rgba(124,58,237,0.4); }
        .nav-section { padding: 16px 12px 8px; flex: 1; }
        .nav-section-label { color: rgba(255,255,255,0.3); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 0 8px; margin-bottom: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: rgba(255,255,255,0.6); font-size: 13.5px; font-weight: 500; text-decoration: none; transition: all 0.2s; margin-bottom: 2px; }
        .nav-item a:hover { background: rgba(255,255,255,0.07); color: white; }
        .nav-item a.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; box-shadow: 0 4px 12px rgba(124,58,237,0.4); }
        .nav-item a i { width: 18px; text-align: center; }
        .sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .admin-info { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .admin-avatar { width: 34px; height: 34px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px; flex-shrink: 0; }
        .admin-name { color: white; font-size: 13px; font-weight: 700; }
        .admin-role { color: rgba(255,255,255,0.4); font-size: 11px; }
        .btn-logout { display: flex; align-items: center; gap: 8px; width: 100%; padding: 9px 12px; background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.2); border-radius: 10px; color: #FCA5A5; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s; justify-content: center; }
        .btn-logout:hover { background: rgba(239,68,68,0.2); color: #FEE2E2; }
        .main-content { margin-left: 240px; min-height: 100vh; }
        .topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 28px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 8px rgba(0,0,0,0.04); }
        .topbar h1 { font-size: 18px; font-weight: 800; color: var(--text); margin: 0; }
        .badge-admin { background: rgba(124,58,237,0.12); color: #7C3AED; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid rgba(124,58,237,0.2); }
        .page-body { padding: 28px; }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--surface); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); border-left: 4px solid transparent; }
        .stat-card.pending { border-left-color: #F59E0B; }
        .stat-card.diproses { border-left-color: #3B82F6; }
        .stat-card.selesai { border-left-color: #22C55E; }
        .stat-card.omzet { border-left-color: var(--primary); }
        .stat-card.bulan { border-left-color: #E84040; }
        .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-bottom: 12px; }
        .stat-value { font-size: 22px; font-weight: 800; color: var(--text); line-height: 1; margin-bottom: 4px; }
        .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        /* Table */
        .card { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 15px; font-weight: 700; color: var(--text); margin: 0; }
        .filter-pills { display: flex; gap: 6px; flex-wrap: wrap; }
        .filter-pill { padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none; background: var(--bg); color: var(--text-muted); border: 1px solid var(--border); transition: all 0.15s; }
        .filter-pill.active, .filter-pill:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .table { margin: 0; }
        .table thead th { background: #F8FAFC; color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px; border: none; }
        .table tbody td { padding: 14px 16px; vertical-align: middle; border-bottom: 1px solid var(--border); font-size: 13.5px; }
        .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .status-pending { background: #FEF3C7; color: #D97706; }
        .status-diproses { background: #DBEAFE; color: #2563EB; }
        .status-selesai { background: #DCFCE7; color: #16A34A; }
        .status-dibatalkan { background: #FEE2E2; color: #DC2626; }
        .pay-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .pay-lunas { background: #DCFCE7; color: #16A34A; }
        .pay-belum { background: #FEE2E2; color: #DC2626; }
        .btn-detail { padding: 5px 12px; border-radius: 8px; background: rgba(124,58,237,0.08); color: var(--primary); border: 1px solid rgba(124,58,237,0.2); font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-detail:hover { background: var(--primary); color: white; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { transform: translateX(-100%); transition: transform 0.3s; } .sidebar.open { transform: translateX(0); } }
        .btn-hamburger { display: none; background: none; border: none; color: var(--text); font-size: 20px; cursor: pointer; }
        @media (max-width: 768px) { .btn-hamburger { display: block; } }
        .auto-refresh-badge { background: rgba(34,197,94,0.1); color: #16A34A; border: 1px solid rgba(34,197,94,0.2); padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .pulse { width: 7px; height: 7px; border-radius: 50%; background: #22C55E; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.4;} }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
    <img src="assets/images/logo.png" alt="KlikPenyet Logo">
</div>
        <h2>Ayam Penyet</h2>
        <p>Bendungan Batusangkar</p>
        <span class="role-label">👑 Admin Panel</span>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Pantau</div>
        <div class="nav-item">
            <a href="dashboard.php" class="active">
                <i class="fas fa-chart-line"></i> Dashboard Pesanan
            </a>
        </div>
        <div class="nav-item">
            <a href="laporan.php">
                <i class="fas fa-file-chart-line fa-chart-bar"></i> Laporan
            </a>
        </div>
        <div class="nav-item">
            <a href="admin_user.php">
                <i class="fas fa-users-cog"></i> Kelola Pengguna
            </a>
        </div>
    </div>
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><?= strtoupper(substr($_SESSION['user_nama'] ?? 'A', 0, 1)) ?></div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['user_nama'] ?? '') ?></div>
                <div class="admin-role">Administrator</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn-hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
            <h1><i class="fas fa-chart-line me-2" style="color:var(--primary)"></i>Dashboard Admin</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="auto-refresh-badge"><span class="pulse"></span> Live Monitor</span>
            <span class="badge-admin"><i class="fas fa-crown me-1"></i>Admin</span>
        </div>
    </div>

    <div class="page-body">
        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-icon" style="background:#FEF3C7;color:#D97706"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?= $stat_pending ?></div>
                <div class="stat-label">Pesanan Pending</div>
            </div>
            <div class="stat-card diproses">
                <div class="stat-icon" style="background:#DBEAFE;color:#2563EB"><i class="fas fa-fire"></i></div>
                <div class="stat-value"><?= $stat_diproses ?></div>
                <div class="stat-label">Sedang Diproses</div>
            </div>
            <div class="stat-card selesai">
                <div class="stat-icon" style="background:#DCFCE7;color:#16A34A"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?= $stat_selesai ?></div>
                <div class="stat-label">Selesai Hari Ini</div>
            </div>
            <div class="stat-card omzet">
                <div class="stat-icon" style="background:rgba(124,58,237,0.1);color:var(--primary)"><i class="fas fa-wallet"></i></div>
                <div class="stat-value" style="font-size:16px"><?= formatRupiah($omzet_hari_ini) ?></div>
                <div class="stat-label">Omzet Hari Ini</div>
            </div>
            <div class="stat-card bulan">
                <div class="stat-icon" style="background:rgba(232,64,64,0.1);color:#E84040"><i class="fas fa-calendar"></i></div>
                <div class="stat-value" style="font-size:16px"><?= formatRupiah($omzet_bulan) ?></div>
                <div class="stat-label">Omzet Bulan Ini</div>
            </div>
        </div>

        <!-- Pesanan Table -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list me-2" style="color:var(--primary)"></i>Monitor Pesanan Hari Ini</h2>
                <div class="filter-pills">
                    <a href="?status=all" class="filter-pill <?= $filter_status==='all'?'active':'' ?>">Semua</a>
                    <a href="?status=pending" class="filter-pill <?= $filter_status==='pending'?'active':'' ?>">⏳ Pending</a>
                    <a href="?status=diproses" class="filter-pill <?= $filter_status==='diproses'?'active':'' ?>">🔥 Diproses</a>
                    <a href="?status=selesai" class="filter-pill <?= $filter_status==='selesai'?'active':'' ?>">✅ Selesai</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode</th><th>Meja</th><th>Item</th><th>Total</th>
                            <th>Status</th><th>Pembayaran</th><th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody id="pesananTbody">
                    <?php foreach ($pesanan_list as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['kode_pesanan']) ?></strong></td>
                        <td><span class="badge bg-secondary">Meja <?= $p['nomor_meja'] ?></span></td>
                        <td><?= $p['jml_item'] ?> item</td>
                        <td><strong><?= formatRupiah($p['total_harga']) ?></strong></td>
                        <td>
                            <span class="status-badge status-<?= $p['status'] ?>">
                                <?php $icons=['pending'=>'⏳','diproses'=>'🔥','selesai'=>'✅','dibatalkan'=>'❌']; ?>
                                <?= ($icons[$p['status']] ?? '') . ' ' . ucfirst($p['status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="pay-badge pay-<?= ($p['status_bayar'] ?? 'belum_bayar') === 'lunas' ? 'lunas' : 'belum' ?>">
                                <?= ($p['status_bayar'] ?? 'belum_bayar') === 'lunas' ? '💰 Lunas' : '⏳ Belum' ?>
                            </span>
                            <?php if ($p['metode_bayar']): ?>
                            <small class="text-muted d-block"><?= strtoupper($p['metode_bayar']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted)"><?= date('H:i', strtotime($p['tanggal'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pesanan_list)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada pesanan hari ini</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto refresh setiap 30 detik
setInterval(() => location.reload(), 30000);
</script>
</body>
</html>
