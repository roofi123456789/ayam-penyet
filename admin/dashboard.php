<?php
require_once '../koneksi.php';
requireAdminLogin();

// Statistik
$stat_pending  = (int)$conn->query("SELECT COUNT(*) as c FROM pesanan WHERE status='pending'")->fetch_assoc()['c'];
$stat_diproses = (int)$conn->query("SELECT COUNT(*) as c FROM pesanan WHERE status='diproses'")->fetch_assoc()['c'];
$stat_selesai  = (int)$conn->query("SELECT COUNT(*) as c FROM pesanan WHERE status='selesai'")->fetch_assoc()['c'];
$r_menu = $conn->query("SELECT COUNT(*) as c FROM menu WHERE tersedia=1");
$stat_menu = $r_menu ? (int)$r_menu->fetch_assoc()['c'] : 0;

// Payment stats - safe query (columns may not exist in older DB)
$stat_total = 0;
$stat_belum_bayar = 0;
$stat_pending_pay = 0;
$pay_col = $conn->query("SHOW COLUMNS FROM pesanan LIKE 'status_bayar'");
if ($pay_col && $pay_col->num_rows > 0) {
    $stat_total       = (int)$conn->query("SELECT COALESCE(SUM(total_harga),0) as t FROM pesanan WHERE DATE(tanggal)=CURDATE() AND status_bayar='lunas'")->fetch_assoc()['t'];
    $stat_belum_bayar = (int)$conn->query("SELECT COUNT(*) as c FROM pesanan WHERE status='selesai' AND (status_bayar='belum_bayar' OR status_bayar IS NULL)")->fetch_assoc()['c'];
    $r_pp = $conn->query("SELECT COUNT(*) as c FROM pesanan WHERE (metode_bayar='cash' OR metode_bayar='qris') AND status_verifikasi='menunggu' AND status_bayar!='lunas'");
    if ($r_pp) $stat_pending_pay = (int)$r_pp->fetch_assoc()['c'];
}

// Filter
$filter_status = $_GET['status'] ?? 'all';
$filter_meja   = (int)($_GET['meja'] ?? 0);

$where = "WHERE 1=1";
if ($filter_status !== 'all') $where .= " AND p.status = '$filter_status'";
if ($filter_meja > 0) $where .= " AND p.nomor_meja = $filter_meja";

// Ambil pesanan dengan detail count
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan = p.id) as jumlah_item
          FROM pesanan p
          $where
          ORDER BY 
            FIELD(p.status,'pending','diproses','selesai','dibatalkan'),
            p.tanggal DESC
          LIMIT 50";
$result = $conn->query($query);
$pesanan_list = [];
if ($result) {
    while ($row = $result->fetch_assoc()) $pesanan_list[] = $row;
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #E84040;
            --primary-dark: #C42E2E;
            --sidebar-bg: #1A1A2E;
            --dark: #1A1A2E;
            --bg: #F0F2F5;
            --surface: #FFFFFF;
            --text: #2D2D2D;
            --text-muted: #888;
            --border: #E5E7EB;
            --radius: 14px;
            --shadow: 0 2px 16px rgba(0,0,0,0.07);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            margin: 0;
        }

        /* SIDEBAR */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: 240px;
            height: 100vh;
            background: var(--sidebar-bg);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar-logo {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-logo h2 {
            font-size: 15px;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.3;
        }
        .sidebar-logo p {
            font-size: 11px;
            color: rgba(255,255,255,0.45);
            margin: 3px 0 0;
        }

        .nav-section {
            padding: 16px 12px 8px;
        }
        .nav-section-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.3);
            padding: 0 8px;
            margin-bottom: 6px;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            transition: all 0.2s;
            margin-bottom: 2px;
        }
        .nav-item a:hover { background: rgba(255,255,255,0.08); color: white; }
        .nav-item a.active { background: var(--primary); color: white; }
        .nav-item a i { width: 18px; text-align: center; }

        .nav-badge {
            margin-left: auto;
            background: var(--primary);
            color: white;
            border-radius: 50px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
        }
        .nav-item a.active .nav-badge { background: rgba(255,255,255,0.25); }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px 12px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
        }
        .admin-avatar {
            width: 36px; height: 36px;
            background: var(--primary);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            color: white;
            font-weight: 700;
        }
        .admin-name { font-size: 13px; font-weight: 700; color: white; }
        .admin-role { font-size: 11px; color: rgba(255,255,255,0.45); }
        .btn-logout {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
            transition: all 0.2s;
        }
        .btn-logout:hover { background: rgba(255,255,255,0.08); color: #FF8A8A; }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 240px;
            min-height: 100vh;
        }

        /* INTERACTIVE STAT CARD */
.stat-card {
    position: relative;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.2s ease;
}

/* hover naik dikit */
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 28px rgba(0,0,0,0.12);
}

/* saat ditekan */
.stat-card:active {
    transform: scale(0.96);
}

/* ripple effect */
.stat-card::after {
    content: "";
    position: absolute;
    width: 100px;
    height: 100px;
    background: rgba(0,0,0,0.08);
    border-radius: 50%;
    transform: scale(0);
    opacity: 0;
    pointer-events: none;
}

/* trigger ripple */
.stat-card.ripple::after {
    animation: rippleEffect 0.5s ease;
}

@keyframes rippleEffect {
    0% {
        transform: scale(0);
        opacity: 0.4;
    }
    100% {
        transform: scale(4);
        opacity: 0;
    }
}

        /* TOP BAR */
        .topbar {
            background: white;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            position: sticky; top: 0; z-index: 100;
        }
        .topbar h1 { font-size: 20px; font-weight: 800; color: var(--dark); margin: 0; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .auto-refresh-label { font-size: 12px; color: var(--text-muted); }

        .btn-refresh {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-refresh:hover { background: white; border-color: var(--primary); color: var(--primary); }

        .page-body { padding: 24px; }

        /* FLASH MESSAGE */
        .flash-alert {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .flash-success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .flash-error   { background: #FEF2F2; border: 1px solid #FCA5A5; color: #991B1B; }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .stat-icon.orange { background: #FFF7ED; }
        .stat-icon.blue   { background: #EFF6FF; }
        .stat-icon.green  { background: #ECFDF5; }
        .stat-icon.red    { background: #FFF0F0; }

        .stat-info p { margin: 0; }
        .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .stat-value { font-size: 24px; font-weight: 800; color: var(--dark); }

        /* FILTER BAR */
        .filter-bar {
            background: white;
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-label { font-size: 13px; font-weight: 700; color: var(--text-muted); }

        .filter-pill {
            padding: 7px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: 1.5px solid var(--border);
            color: var(--text-muted);
            background: var(--bg);
            transition: all 0.2s;
        }
        .filter-pill:hover { border-color: var(--primary); color: var(--primary); }
        .filter-pill.active { background: var(--primary); border-color: var(--primary); color: white; }

        /* PESANAN TABLE */
        .pesanan-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .pesanan-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pesanan-card-header h3 { font-size: 15px; font-weight: 800; margin: 0; }

        .order-row {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: background 0.15s;
        }
        .order-row:last-child { border-bottom: none; }
        .order-row:hover { background: #FAFAFA; }

        .meja-badge-table {
            width: 46px; height: 46px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        .meja-badge-table .meja-label { font-size: 9px; font-weight: 600; opacity: 0.8; }
        .meja-badge-table .meja-num   { font-size: 16px; font-weight: 800; line-height: 1; }

        .order-info { flex: 1; min-width: 0; }
        .order-kode { font-size: 13px; font-weight: 800; color: var(--dark); }
        .order-meta { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .order-items { font-size: 12px; color: var(--text-muted); }

        .order-total { font-size: 15px; font-weight: 800; color: var(--primary); white-space: nowrap; }

        .status-select {
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            outline: none;
            background: white;
            transition: border 0.2s;
            min-width: 120px;
        }
        .status-select:focus { border-color: var(--primary); }
        .status-select.pending  { color: #D97706; border-color: #FCD34D; }
        .status-select.diproses { color: #2563EB; border-color: #93C5FD; }
        .status-select.selesai  { color: #16A34A; border-color: #86EFAC; }
        .status-select.dibatalkan { color: #888; }

        .btn-detail {
            padding: 6px 14px;
            border-radius: 8px;
            background: var(--bg);
            border: 1px solid var(--border);
            font-size: 12px;
            font-weight: 700;
            color: var(--text);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-detail:hover { background: var(--primary); border-color: var(--primary); color: white; }

        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-orders i { font-size: 48px; opacity: 0.3; margin-bottom: 12px; }

        /* MODAL */
        .modal-content { border: none; border-radius: 20px; }
        .modal-header { border-bottom: 1px solid var(--border); padding: 20px 24px; }
        .modal-title { font-weight: 800; }
        .modal-body { padding: 20px 24px; }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 576px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .order-row { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div style="font-size:28px;margin-bottom:8px">🍗</div>
        <h2>Ayam Penyet</h2>
        <p>Bendungan Batusangkar</p>
    </div>

    <div class="nav-section">
        <div class="nav-section-label">Menu Utama</div>
        <div class="nav-item">
            <a href="dashboard.php" class="active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
                <?php if ($stat_pending > 0): ?>
                <span class="nav-badge"><?= $stat_pending ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="nav-item">
            <a href="konfirmasi_bayar.php">
                <i class="fas fa-cash-register"></i> Konfirmasi Bayar
                <?php if(($stat_pending_pay??0)>0): ?>
                <span class="nav-badge"><?= $stat_pending_pay ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="nav-item">
            <a href="menu.php">
                <i class="fas fa-utensils"></i> Kelola Menu
            </a>
        </div>
        <div class="nav-item">
            <a href="kitchen.php">
                <i class="fas fa-tv"></i> Kitchen Display
            </a></div>
        <div class="nav-item">
            <a href="laporan.php">
                <i class="fas fa-chart-bar"></i> Laporan
            </a>
        </div>
        <div class="nav-item">
            <a href="qrcode.php">
                <i class="fas fa-qrcode"></i> QR Code
            </a>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_nama'], 0, 1)) ?></div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_nama']) ?></div>
                <div class="admin-role">Administrator</div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Keluar
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn-refresh d-lg-none" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h1>📋 Dashboard Pesanan</h1>
        </div>
        <div class="topbar-right">
            <span class="auto-refresh-label" id="refreshTimer">Auto-refresh: 30s</span>
            <button class="btn-refresh" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <div class="page-body">
        <!-- Flash Message -->
        <?php if ($flash): ?>
        <div class="flash-alert flash-<?= $flash['type'] ?>">
            <i class="fas <?= $flash['type']==='success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orange">⏳</div>
                <div class="stat-info">
                    <p class="stat-label">Menunggu</p>
                    <p class="stat-value"><?= $stat_pending ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">🔥</div>
                <div class="stat-info">
                    <p class="stat-label">Diproses</p>
                    <p class="stat-value"><?= $stat_diproses ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">✅</div>
                <div class="stat-info">
                    <p class="stat-label">Selesai</p>
                    <p class="stat-value"><?= $stat_selesai ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red">💰</div>
                <div class="stat-info">
                    <p class="stat-label">Omzet Lunas Hari Ini</p>
                    <p class="stat-value" style="font-size:15px" id="liveOmzet"><?= formatRupiah($stat_total) ?></p>
                </div>
            </div>
            <?php if ($stat_pending_pay > 0): ?>
            <div class="stat-card" style="border-left:4px solid #F59E0B;cursor:pointer" onclick="window.location='konfirmasi_bayar.php'">
                <div class="stat-icon" style="background:#FFF7ED">⏳</div>
                <div class="stat-info">
                    <p class="stat-label">Menunggu Konfirmasi</p>
                    <p class="stat-value" style="color:#D97706"><?= $stat_pending_pay ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Unpaid Warning -->
        <?php if($stat_belum_bayar > 0): ?>
        <div id="unpaidWarn" style="background:#FFF7ED;border:1px solid #FCD34D;border-radius:12px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px;color:#92400E;font-weight:600">
            <i class="fas fa-exclamation-triangle text-warning"></i>
            ⚠️ <?= $stat_belum_bayar ?> pesanan selesai belum bayar — segera proses di kasir!
        </div>
        <?php else: ?>
        <div id="unpaidWarn" style="display:none;background:#FFF7ED;border:1px solid #FCD34D;border-radius:12px;padding:12px 18px;margin-bottom:16px;align-items:center;gap:10px;font-size:13px;color:#92400E;font-weight:600"></div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <span class="filter-label">Filter:</span>
            <a href="?status=all" class="filter-pill <?= $filter_status==='all'?'active':'' ?>">Semua</a>
            <a href="?status=pending" class="filter-pill <?= $filter_status==='pending'?'active':'' ?>">⏳ Pending</a>
            <a href="?status=diproses" class="filter-pill <?= $filter_status==='diproses'?'active':'' ?>">🔥 Diproses</a>
            <a href="?status=selesai" class="filter-pill <?= $filter_status==='selesai'?'active':'' ?>">✅ Selesai</a>
        </div>

        <!-- Pesanan List -->
        <div class="pesanan-card">
            <div class="pesanan-card-header">
                <h3>Daftar Pesanan</h3>
                <span style="font-size:13px;color:var(--text-muted)"><?= count($pesanan_list) ?> pesanan</span>
            </div>

            <?php if (empty($pesanan_list)): ?>
            <div class="empty-orders">
                <div><i class="fas fa-inbox"></i></div>
                <p>Belum ada pesanan</p>
            </div>
            <?php else: ?>
                <?php foreach ($pesanan_list as $p): ?>
                <div class="order-row">
                    <div class="meja-badge-table">
                        <span class="meja-label">Meja</span>
                        <span class="meja-num"><?= $p['nomor_meja'] ?></span>
                    </div>

                    <div class="order-info">
                        <div class="order-kode">
                            <?= htmlspecialchars($p['kode_pesanan']) ?>
                            <?php if (($p['nama_pelanggan'] ?? '') && $p['nama_pelanggan'] !== 'Pelanggan'): ?>
                            <span style="margin-left:6px;background:#EFF6FF;color:#2563EB;border-radius:50px;padding:2px 8px;font-size:11px;font-weight:700">
                                👤 <?= htmlspecialchars($p['nama_pelanggan']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="order-meta">
                            <i class="fas fa-clock me-1"></i><?= date('H:i, d M', strtotime($p['tanggal'])) ?>
                            &nbsp;·&nbsp;
                            <i class="fas fa-list me-1"></i><?= $p['jumlah_item'] ?> item
                        </div>
                        <?php if ($p['catatan']): ?>
                        <div class="order-items" style="color:#D97706">
                            <i class="fas fa-sticky-note me-1"></i><?= htmlspecialchars(substr($p['catatan'],0,50)) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-column align-items-end gap-1">
                        <div class="order-total"><?= formatRupiah($p['total_harga']) ?></div>
                        <?php 
                        $sb = $p['status_bayar'] ?? 'belum_bayar';
                        $mb = $p['metode_bayar'] ?? '';
                        ?>
                        <?php if ($sb === 'lunas'): ?>
                        <span style="background:#ECFDF5;color:#16A34A;border-radius:50px;padding:2px 8px;font-size:10px;font-weight:700;white-space:nowrap">
                            ✅ LUNAS <?= $mb ? '·'.strtoupper($mb) : '' ?>
                        </span>
                        <?php else: ?>
                        <span style="background:#FEF2F2;color:#DC2626;border-radius:50px;padding:2px 8px;font-size:10px;font-weight:700;white-space:nowrap">
                            ⏳ Belum Bayar
                        </span>
                        <?php endif; ?>
                    </div>

                    <select class="status-select <?= $p['status'] ?>" 
                            onchange="updateStatus(<?= $p['id'] ?>, this.value, this)">
                        <option value="pending"   <?= $p['status']==='pending'?'selected':'' ?>>⏳ Pending</option>
                        <option value="diproses"  <?= $p['status']==='diproses'?'selected':'' ?>>🔥 Diproses</option>
                        <option value="selesai"   <?= $p['status']==='selesai'?'selected':'' ?>>✅ Selesai</option>
                        <option value="dibatalkan" <?= $p['status']==='dibatalkan'?'selected':'' ?>>❌ Batal</option>
                    </select>

                    <div class="d-flex flex-column gap-1">
                        <button class="btn-detail" onclick="lihatDetail(<?= $p['id'] ?>)">
                            <i class="fas fa-eye me-1"></i>Detail
                        </button>
                        <?php if (($p['status_bayar']??'') === 'lunas' || $p['status'] === 'selesai'): ?>
                        <a href="struk_admin.php?kode=<?= htmlspecialchars($p['kode_pesanan']) ?>" 
                           target="_blank" class="btn-detail" style="font-size:11px;background:#EFF6FF;color:#2563EB;border-color:#BFDBFE;text-align:center">
                            <i class="fas fa-receipt me-1"></i>Struk
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Detail -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Pesanan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Update status via AJAX
    function updateStatus(id, status, el) {
        el.disabled = true;
        fetch('api_admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update_status&id=${id}&status=${status}`
        })
        .then(r => r.json())
        .then(data => {
            el.disabled = false;
            el.className = 'status-select ' + status;
            if (!data.success) alert('Gagal update status!');
        })
        .catch(() => { el.disabled = false; });
    }

    // Lihat detail pesanan
    function lihatDetail(id) {
        const modal = new bootstrap.Modal(document.getElementById('detailModal'));
        document.getElementById('modalBody').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
        modal.show();

        fetch(`api_admin.php?action=get_detail&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let html = `
                <div style="background:#F8F9FA;border-radius:10px;padding:14px;margin-bottom:16px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span style="font-size:12px;color:#888">Kode</span>
                        <span style="font-size:13px;font-weight:700;color:#E84040">${data.pesanan.kode_pesanan}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span style="font-size:12px;color:#888">Meja</span>
                        <span style="font-size:13px;font-weight:700">Meja ${data.pesanan.nomor_meja}</span>
                    </div>
                    ${(data.pesanan.nama_pelanggan && data.pesanan.nama_pelanggan !== 'Pelanggan') ? `
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span style="font-size:12px;color:#888">Pemesan</span>
                        <span style="font-size:13px;font-weight:700;color:#2563EB">👤 ${data.pesanan.nama_pelanggan}</span>
                    </div>` : ''}
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span style="font-size:12px;color:#888">Waktu</span>
                        <span style="font-size:13px;font-weight:600">${data.pesanan.tanggal}</span>
                    </div>
                    ${data.pesanan.catatan ? `<div style="background:#FFF3CD;border-radius:8px;padding:8px 10px;margin-top:8px;font-size:12px;color:#856404">📝 ${data.pesanan.catatan}</div>` : ''}
                </div>
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#F8F9FA">
                            <th style="padding:8px 10px;text-align:left;font-size:12px;color:#888">Item</th>
                            <th style="padding:8px 10px;text-align:center;font-size:12px;color:#888">Qty</th>
                            <th style="padding:8px 10px;text-align:right;font-size:12px;color:#888">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>`;

                data.details.forEach(d => {
                    html += `<tr style="border-bottom:1px solid #F0F0F0">
                        <td style="padding:10px;font-size:13px;font-weight:600">${d.nama_menu}</td>
                        <td style="padding:10px;text-align:center;font-size:13px">×${d.jumlah}</td>
                        <td style="padding:10px;text-align:right;font-size:13px;font-weight:700;color:#E84040">
                            Rp ${parseInt(d.subtotal).toLocaleString('id-ID')}
                        </td>
                    </tr>`;
                });

                html += `</tbody>
                    <tfoot>
                        <tr style="background:#FFF0F0">
                            <td colspan="2" style="padding:12px 10px;font-weight:800;font-size:14px">Total</td>
                            <td style="padding:12px 10px;text-align:right;font-weight:800;font-size:16px;color:#E84040">
                                Rp ${parseInt(data.pesanan.total_harga).toLocaleString('id-ID')}
                            </td>
                        </tr>
                    </tfoot>
                </table>`;
                
                // Payment & struk actions
                const sb = data.pesanan.status_bayar || 'belum_bayar';
                const mb = data.pesanan.metode_bayar || '';
                const mbLabel = {cash:'💵 Tunai',qris:'📱 QRIS',transfer:'🏦 Transfer'};
                
                if(sb === 'lunas'){
                    html += `<div style="background:#ECFDF5;border-radius:10px;padding:12px 14px;margin-top:12px;display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:13px;font-weight:700;color:#16A34A">✅ LUNAS · ${mbLabel[mb]||mb}</span>
                        <a href="struk_admin.php?kode=${data.pesanan.kode_pesanan}" target="_blank"
                           style="background:#2563EB;color:white;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none">
                           🧾 Print Struk
                        </a>
                    </div>`;
                } else if(data.pesanan.status === 'selesai') {
                    html += `<div style="background:#FFF7ED;border-radius:10px;padding:12px 14px;margin-top:12px;display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:13px;font-weight:700;color:#D97706">⏳ Belum Bayar</span>
                        <a href="../payment.php?kode=${data.pesanan.kode_pesanan}&meja=${data.pesanan.nomor_meja}" target="_blank"
                           style="background:#E84040;color:white;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none">
                           💳 Proses Bayar
                        </a>
                    </div>`;
                }

                document.getElementById('modalBody').innerHTML = html;
            }
        });
    }

    // Auto-refresh
    let countdown = 30;
    setInterval(() => {
        countdown--;
        document.getElementById('refreshTimer').textContent = `Auto-refresh: ${countdown}s`;
        if (countdown <= 0) location.reload();
    }, 1000);

    // Mobile sidebar toggle
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    // ===== REAL-TIME NOTIFICATION SYSTEM =====
    let latestOrderId = 0;
    let notifPermission = 'default';

    // Init: ambil ID pesanan terbaru
    fetch('api/notif.php?last_id=0')
        .then(r => r.json())
        .then(data => {
            if (data.success) latestOrderId = data.latest_id;
        }).catch(() => {});

    // Request browser notification permission
    if ('Notification' in window) {
        Notification.requestPermission().then(p => { notifPermission = p; });
    }

    function showBrowserNotif(title, body) {
        if (notifPermission === 'granted') {
            new Notification(title, { body, icon: '/ayam-penyet/favicon.ico', tag: 'new-order' });
        }
    }

    function showInAppNotif(msg, type='new') {
        const colors = { new: '#E84040', info: '#3B82F6', warn: '#F59E0B' };
        const icons  = { new: '🔔', info: 'ℹ️', warn: '⚠️' };
        const div = document.createElement('div');
        div.style.cssText = `
            position:fixed;top:80px;right:20px;z-index:9999;
            background:${colors[type]};color:white;
            padding:14px 20px;border-radius:12px;
            font-size:14px;font-weight:700;
            box-shadow:0 8px 24px rgba(0,0,0,0.2);
            display:flex;align-items:center;gap:10px;
            animation:slideInRight 0.35s ease;
            max-width:320px;font-family:'Plus Jakarta Sans',sans-serif;
            cursor:pointer;
        `;
        div.innerHTML = `<span style="font-size:20px">${icons[type]}</span><span>${msg}</span>`;
        div.onclick = () => { div.remove(); location.reload(); };
        document.body.appendChild(div);
        setTimeout(() => {
            div.style.opacity = '0';
            div.style.transition = 'opacity 0.4s';
            setTimeout(() => div.remove(), 400);
        }, 6000);
    }

    // Polling setiap 15 detik
    function pollNotifications() {
        fetch(`api/notif.php?last_id=${latestOrderId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                // Update server time
                const timeEl = document.getElementById('refreshTimer');
                if (timeEl) timeEl.textContent = `Server: ${data.server_time}`;

                // Update pending badge di sidebar
                const pendingBadge = document.querySelector('.nav-badge');
                if (pendingBadge && data.pending > 0) {
                    pendingBadge.textContent = data.pending;
                }

                // Update omzet display
                const omzetEl = document.getElementById('liveOmzet');
                if (omzetEl) omzetEl.textContent = 'Rp ' + data.omzet_hari.toLocaleString('id-ID');

                // Ada pesanan baru?
                if (data.new_count > 0) {
                    latestOrderId = data.latest_id;
                    const orders = data.new_orders;
                    const first = orders[0];
                    const namaInfo = first.nama_pelanggan && first.nama_pelanggan !== 'Pelanggan'
                        ? ` · ${first.nama_pelanggan}` : '';
                const msg = data.new_count === 1
                        ? `🔔 Pesanan baru! Meja ${first.nomor_meja}${namaInfo}`
                        : `🔔 ${data.new_count} pesanan baru masuk!`;

                    showInAppNotif(msg, 'new');
                    showBrowserNotif('🍗 Pesanan Baru!', msg);

                    // Play sound
                    try {
                        const audio = new AudioContext();
                        const osc = audio.createOscillator();
                        const gain = audio.createGain();
                        osc.connect(gain);
                        gain.connect(audio.destination);
                        osc.frequency.setValueAtTime(880, audio.currentTime);
                        osc.frequency.setValueAtTime(1100, audio.currentTime + 0.1);
                        gain.gain.setValueAtTime(0.3, audio.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + 0.4);
                        osc.start(audio.currentTime);
                        osc.stop(audio.currentTime + 0.4);
                    } catch(e) {}

                    // Auto reload halaman setelah 3 detik
                    setTimeout(() => location.reload(), 3500);
                }

                // Peringatan belum bayar
                if (data.unpaid > 0) {
                    const warnEl = document.getElementById('unpaidWarn');
                    if (warnEl) {
                        warnEl.textContent = `⚠️ ${data.unpaid} pesanan selesai belum bayar`;
                        warnEl.style.display = 'flex';
                    }
                }
            })
            .catch(() => {});
    }

    // Mulai polling
    setInterval(pollNotifications, 15000);
    
    // CSS animasi
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to   { opacity: 1; transform: translateX(0); }
        }
    `;
    document.head.appendChild(style);

    // ripple click effect
document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('click', function(e) {
        // reset ripple
        this.classList.remove('ripple');
        void this.offsetWidth; // trigger reflow
        this.classList.add('ripple');
    });
});
</script>
</body>
</html>
