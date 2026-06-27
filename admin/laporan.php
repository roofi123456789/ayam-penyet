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

$periode = $_GET['periode'] ?? 'harian';
$tanggal = sanitize($_GET['tanggal'] ?? date('Y-m-d'));
$bulan   = (int)($_GET['bulan'] ?? date('m'));
$tahun   = (int)($_GET['tahun'] ?? date('Y'));
if ($bulan < 1 || $bulan > 12) $bulan = (int)date('m');
if ($tahun < 2020 || $tahun > 2099) $tahun = (int)date('Y');

// WHERE clause & label judul berdasarkan periode
if ($periode === 'bulanan') {
    $where_tgl   = "MONTH(p.tanggal)=$bulan AND YEAR(p.tanggal)=$tahun";
    $where_nop   = "MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun";
    $judul_periode = date('F Y', mktime(0,0,0,$bulan,1,$tahun));
    $label_periode = 'Bulanan';
} elseif ($periode === 'tahunan') {
    $where_tgl   = "YEAR(p.tanggal)=$tahun";
    $where_nop   = "YEAR(tanggal)=$tahun";
    $judul_periode = "Tahun $tahun";
    $label_periode = 'Tahunan';
} else { // harian (default)
    $periode     = 'harian';
    $where_tgl   = "DATE(p.tanggal)='$tanggal'";
    $where_nop   = "DATE(tanggal)='$tanggal'";
    $judul_periode = date('d M Y', strtotime($tanggal));
    $label_periode = 'Harian';
}

// Pesanan list
$pesanan_list = [];
$res = $conn->query("SELECT p.*,
    (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan=p.id) as jml_item
    FROM pesanan p WHERE $where_tgl ORDER BY p.tanggal DESC");
while ($row = $res->fetch_assoc()) $pesanan_list[] = $row;

$total_omzet   = array_sum(array_column(array_filter($pesanan_list, fn($p) => ($p['status_bayar']??'')==='lunas'), 'total_harga'));
$total_pesanan = count($pesanan_list);
$total_selesai = count(array_filter($pesanan_list, fn($p) => $p['status']==='selesai'));
$total_lunas   = count(array_filter($pesanan_list, fn($p) => ($p['status_bayar']??'')==='lunas'));
$total_blm     = count(array_filter($pesanan_list, fn($p) => $p['status']==='selesai' && ($p['status_bayar']??'belum_bayar')!=='lunas'));

// Breakdown metode (sesuai periode)
$metode_stats = [];
$rm = $conn->query("SELECT COALESCE(metode_bayar,'cash') as metode_bayar, COUNT(*) as jml, SUM(total_harga) as total
    FROM pesanan WHERE $where_nop AND status_bayar='lunas' GROUP BY metode_bayar");
if ($rm) { while ($row = $rm->fetch_assoc()) $metode_stats[$row['metode_bayar']] = $row; }

// Menu terlaris (sesuai periode)
$top_menu = [];
$res2 = $conn->query("SELECT dp.nama_menu, SUM(dp.jumlah) as total_terjual, SUM(dp.subtotal) as total_pendapatan
    FROM detail_pesanan dp JOIN pesanan p ON dp.id_pesanan=p.id
    WHERE $where_tgl AND p.status != 'dibatalkan'
    GROUP BY dp.id_menu, dp.nama_menu ORDER BY total_terjual DESC LIMIT 8");
while ($row = $res2->fetch_assoc()) $top_menu[] = $row;

// Grafik tren: harian=7 hari, bulanan=hari per bulan, tahunan=12 bulan
$trend_data = [];
if ($periode === 'harian') {
    $res3 = $conn->query("SELECT DATE(tanggal) as label, COUNT(*) as total_pesanan, COALESCE(SUM(CASE WHEN status_bayar='lunas' THEN total_harga ELSE 0 END),0) as omzet
        FROM pesanan WHERE tanggal >= DATE_SUB('$tanggal', INTERVAL 6 DAY) AND tanggal <= '$tanggal 23:59:59'
        GROUP BY DATE(tanggal) ORDER BY label ASC");
    if ($res3) while ($r = $res3->fetch_assoc()) $trend_data[] = ['label'=>date('d/m',strtotime($r['label'])), 'omzet'=>(int)$r['omzet'], 'pesanan'=>(int)$r['total_pesanan']];
} elseif ($periode === 'bulanan') {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
    $res3 = $conn->query("SELECT DAY(tanggal) as label, COUNT(*) as total_pesanan, COALESCE(SUM(CASE WHEN status_bayar='lunas' THEN total_harga ELSE 0 END),0) as omzet
        FROM pesanan WHERE MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun
        GROUP BY DAY(tanggal) ORDER BY label ASC");
    $daily_map = [];
    if ($res3) while ($r = $res3->fetch_assoc()) $daily_map[(int)$r['label']] = $r;
    for ($d = 1; $d <= $days_in_month; $d++) {
        $trend_data[] = ['label'=>str_pad($d,2,'0',STR_PAD_LEFT), 'omzet'=>(int)($daily_map[$d]['omzet']??0), 'pesanan'=>(int)($daily_map[$d]['total_pesanan']??0)];
    }
} else { // tahunan
    $res3 = $conn->query("SELECT MONTH(tanggal) as label, COUNT(*) as total_pesanan, COALESCE(SUM(CASE WHEN status_bayar='lunas' THEN total_harga ELSE 0 END),0) as omzet
        FROM pesanan WHERE YEAR(tanggal)=$tahun
        GROUP BY MONTH(tanggal) ORDER BY label ASC");
    $month_map = [];
    if ($res3) while ($r = $res3->fetch_assoc()) $month_map[(int)$r['label']] = $r;
    $bulan_names = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    for ($m = 1; $m <= 12; $m++) {
        $trend_data[] = ['label'=>$bulan_names[$m], 'omzet'=>(int)($month_map[$m]['omzet']??0), 'pesanan'=>(int)($month_map[$m]['total_pesanan']??0)];
    }
}

// Jam sibuk (hanya untuk harian)
$peak_hours = [];
if ($periode === 'harian') {
    $res4 = $conn->query("SELECT HOUR(tanggal) as jam, COUNT(*) as jml FROM pesanan WHERE DATE(tanggal)='$tanggal' GROUP BY HOUR(tanggal) ORDER BY jam ASC");
    if ($res4) while ($row = $res4->fetch_assoc()) $peak_hours[$row['jam']] = (int)$row['jml'];
}

// Ringkasan bulanan per hari (untuk view bulanan - tabel ringkasan)
$ringkasan_bulanan = [];
if ($periode === 'bulanan') {
    $rb = $conn->query("SELECT DATE(tanggal) as tgl, COUNT(*) as jml_pesanan, SUM(CASE WHEN status_bayar='lunas' THEN total_harga ELSE 0 END) as omzet, SUM(CASE WHEN status_bayar='lunas' THEN 1 ELSE 0 END) as lunas
        FROM pesanan WHERE MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun GROUP BY DATE(tanggal) ORDER BY tgl ASC");
    if ($rb) while ($r = $rb->fetch_assoc()) $ringkasan_bulanan[] = $r;
}

// Ringkasan tahunan per bulan
$ringkasan_tahunan = [];
if ($periode === 'tahunan') {
    $rt = $conn->query("SELECT MONTH(tanggal) as bln, COUNT(*) as jml_pesanan, SUM(CASE WHEN status_bayar='lunas' THEN total_harga ELSE 0 END) as omzet, SUM(CASE WHEN status_bayar='lunas' THEN 1 ELSE 0 END) as lunas
        FROM pesanan WHERE YEAR(tanggal)=$tahun GROUP BY MONTH(tanggal) ORDER BY bln ASC");
    if ($rt) while ($r = $rt->fetch_assoc()) $ringkasan_tahunan[] = $r;
}

// Tahun tersedia
$tahun_list = [];
$ry = $conn->query("SELECT DISTINCT YEAR(tanggal) as yr FROM pesanan ORDER BY yr DESC");
if ($ry) while ($r = $ry->fetch_assoc()) $tahun_list[] = (int)$r['yr'];
if (empty($tahun_list)) $tahun_list = [(int)date('Y')];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Admin Ayam Penyet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .card-box{background:white;border-radius:var(--radius);box-shadow:var(--shadow);}
        .filter-bar{padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
        .inp{border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;outline:none;font-family:inherit;}
        .inp:focus{border-color:var(--primary);}
        .btn-s{border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px;}
        .btn-red{background:var(--primary);color:white;}
        .btn-dark{background:var(--dark);color:white;}
        .btn-light{background:white;border:1.5px solid var(--border);color:var(--dark);}
        .btn-light:hover{border-color:var(--dark);}

        /* Summary hero */
        .hero-box{background:linear-gradient(135deg,#1A1A2E 0%,#16213E 60%,#0F3460 100%);border-radius:var(--radius);padding:22px 24px;margin-bottom:20px;display:flex;gap:24px;flex-wrap:wrap;align-items:center;}
        .hero-item{text-align:center;padding:0 12px;border-right:1px solid rgba(255,255,255,0.1);}
        .hero-item:last-child{border:none;}
        .hero-val{font-size:24px;font-weight:800;color:white;display:block;}
        .hero-lbl{font-size:11px;color:rgba(255,255,255,0.5);margin-top:2px;}

        /* Stats */
        .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;}
        .scard{background:white;border-radius:var(--radius);padding:18px;box-shadow:var(--shadow);display:flex;align-items:center;gap:14px;}
        .sicon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
        .slabel{font-size:11px;color:#888;margin:0;}
        .sval{font-size:20px;font-weight:800;color:var(--dark);margin:0;line-height:1.2;}
        .ssub{font-size:10px;color:#aaa;margin:3px 0 0;}

        /* Metode */
        .metode-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;}
        .mcard{background:white;border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);border-top:4px solid #E5E7EB;}
        .mcard.cash{border-color:#22C55E;} .mcard.qris{border-color:#3B82F6;} .mcard.transfer{border-color:#F59E0B;}
        .micon{font-size:22px;margin-bottom:8px;}
        .mname{font-size:13px;font-weight:700;color:var(--dark);}
        .mjml{font-size:12px;color:#888;margin:2px 0;}
        .mtotal{font-size:16px;font-weight:800;color:var(--primary);}

        /* Charts */
        .chart-row{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:20px;}
        .chart-box{background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);}
        .ctitle{font-size:14px;font-weight:800;color:var(--dark);margin:0 0 14px;}
        .cwrap{position:relative;height:200px;}

        /* Peak hours */
        .peak-wrap{display:flex;gap:3px;align-items:flex-end;height:90px;padding-bottom:4px;}
        .pbar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;}
        .pbar{width:100%;border-radius:3px 3px 0 0;background:rgba(232,64,64,0.25);min-height:4px;transition:height 0.5s;}
        .pbar.active{background:linear-gradient(180deg,var(--primary),#FF6B6B);}
        .plbl{font-size:9px;color:#aaa;font-weight:600;}
        .pval{font-size:9px;color:var(--primary);font-weight:800;}

        /* Top menu bars */
        .tmitem{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #F5F5F5;}
        .tmitem:last-child{border:none;}
        .tmrank{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:white;flex-shrink:0;}
        .r1{background:#F59E0B;} .r2{background:#94A3B8;} .r3{background:#C2855F;} .rn{background:#E5E7EB;color:#666;}
        .tminfo{flex:1;min-width:0;}
        .tmname{font-size:12px;font-weight:700;color:var(--dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px;}
        .tmbar-bg{background:#F5F5F5;border-radius:50px;height:6px;overflow:hidden;}
        .tmbar-fill{height:100%;border-radius:50px;background:linear-gradient(90deg,var(--primary),#FF8A8A);}
        .tmqty{font-size:12px;font-weight:700;color:#888;white-space:nowrap;}

        /* Table */
        .tcard{background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px;}
        .thead-row{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .ttitle{font-size:14px;font-weight:800;margin:0;}
        table{width:100%;border-collapse:collapse;}
        th{padding:9px 14px;text-align:left;font-size:11px;font-weight:700;color:#888;background:#F8F9FA;text-transform:uppercase;letter-spacing:.5px;}
        td{padding:11px 14px;font-size:13px;border-bottom:1px solid #F8F8F8;}
        tr:last-child td{border:none;}
        tr:hover td{background:#FAFAFA;}
        .bs{display:inline-flex;align-items:center;padding:3px 9px;border-radius:50px;font-size:11px;font-weight:700;}
        .bp{background:#FFF7ED;color:#D97706;} .bd{background:#EFF6FF;color:#2563EB;}
        .bse{background:#ECFDF5;color:#16A34A;} .bx{background:#F8FAFC;color:#64748B;}
        .bl{background:#ECFDF5;color:#16A34A;} .bb{background:#FEF2F2;color:#DC2626;}
        .struk-a{color:#2563EB;font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:3px;}
        .struk-a:hover{text-decoration:underline;}

        @media print{
            .sidebar,.topbar,.filter-bar,.no-print{display:none!important;}
            .main-content{margin:0;}
            .chart-row{grid-template-columns:1fr;}
            .stats-row{grid-template-columns:repeat(3,1fr);}
        }
        @media(max-width:992px){
            .sidebar{display:none;} .main-content{margin:0;}
            .stats-row,.metode-row{grid-template-columns:repeat(2,1fr);}
            .chart-row{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>
<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-logo">
        <div style="font-size:28px;margin-bottom:8px">🍗</div>
        <h2>Ayam Penyet</h2>
        <p>Bendungan Batusangkar</p>
    </div>
            <div class="nav-section">
            <div class="nav-section-label">Pantau</div>
            <div class="nav-item">
                <a href="dashboard.php">
                    <i class="fas fa-chart-line"></i> Dashboard Pesanan
                </a>
            </div>
            <div class="nav-item">
                <a href="laporan.php" class="active">
                    <i class="fas fa-chart-bar"></i> Laporan
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_user.php">
                    <i class="fas fa-users-cog"></i> Kelola Pengguna
                </a>
            </div>
        </div>
        <div class="sidebar-footer">
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
</div>

<!-- MAIN -->
<div class="main-content">
    <div class="topbar">
        <h1>📊 Laporan Penjualan</h1>
        <div class="d-flex gap-2 no-print">
            <button class="btn-s btn-light" onclick="showDownloadModal()">
                <i class="fas fa-download"></i> Unduh Laporan
            </button>
        </div>
    </div>

    <div class="page-body">

        <!-- FILTER PERIODE -->
        <div class="card-box no-print" style="margin-bottom:20px;padding:16px 20px">
            <!-- Tab Periode -->
            <div style="display:flex;gap:6px;margin-bottom:14px">
                <?php foreach(['harian'=>'📅 Harian','bulanan'=>'📆 Bulanan','tahunan'=>'📊 Tahunan'] as $p_key=>$p_label): ?>
                <a href="?periode=<?= $p_key ?>&tanggal=<?= $tanggal ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>"
                   style="padding:8px 18px;border-radius:50px;font-size:13px;font-weight:700;text-decoration:none;
                          <?= $periode===$p_key ? 'background:var(--primary);color:white' : 'background:#F3F4F6;color:#666' ?>">
                    <?= $p_label ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Input sesuai periode -->
            <form method="GET" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <input type="hidden" name="periode" value="<?= $periode ?>">
                <?php if ($periode === 'harian'): ?>
                    <span style="font-size:13px;font-weight:700;color:#888">📅 Pilih Tanggal:</span>
                    <input type="date" name="tanggal" class="inp" value="<?= $tanggal ?>">
                    <button type="submit" class="btn-s btn-red"><i class="fas fa-search"></i> Tampilkan</button>
                    <a href="?periode=harian&tanggal=<?= date('Y-m-d') ?>" style="font-size:13px;color:var(--primary);text-decoration:none;font-weight:700">Hari Ini</a>
                    <a href="?periode=harian&tanggal=<?= date('Y-m-d',strtotime('-1 day')) ?>" style="font-size:13px;color:#666;text-decoration:none;font-weight:600">Kemarin</a>
                <?php elseif ($periode === 'bulanan'): ?>
                    <span style="font-size:13px;font-weight:700;color:#888">📆 Bulan:</span>
                    <select name="bulan" class="inp">
                        <?php $bulan_nm=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                        for ($bm=1;$bm<=12;$bm++) echo "<option value='$bm'".($bm==$bulan?' selected':'').">$bulan_nm[$bm]</option>"; ?>
                    </select>
                    <select name="tahun" class="inp">
                        <?php foreach($tahun_list as $ty) echo "<option value='$ty'".($ty==$tahun?' selected':'').">$ty</option>"; ?>
                    </select>
                    <button type="submit" class="btn-s btn-red"><i class="fas fa-search"></i> Tampilkan</button>
                    <a href="?periode=bulanan&bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>" style="font-size:13px;color:var(--primary);text-decoration:none;font-weight:700">Bulan Ini</a>
                <?php else: // tahunan ?>
                    <span style="font-size:13px;font-weight:700;color:#888">📊 Tahun:</span>
                    <select name="tahun" class="inp">
                        <?php foreach($tahun_list as $ty) echo "<option value='$ty'".($ty==$tahun?' selected':'').">$ty</option>"; ?>
                    </select>
                    <button type="submit" class="btn-s btn-red"><i class="fas fa-search"></i> Tampilkan</button>
                    <a href="?periode=tahunan&tahun=<?= date('Y') ?>" style="font-size:13px;color:var(--primary);text-decoration:none;font-weight:700">Tahun Ini</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- HERO SUMMARY -->
        <div class="hero-box">
            <div style="font-size:28px">📊</div>
            <div class="hero-item">
                <span class="hero-val"><?= $judul_periode ?></span>
                <div class="hero-lbl"><?= ucfirst($periode) ?></div>
            </div>
            <div class="hero-item">
                <span class="hero-val"><?= formatRupiah($total_omzet) ?></span>
                <div class="hero-lbl">💰 Omzet Lunas</div>
            </div>
            <div class="hero-item">
                <span class="hero-val"><?= $total_pesanan ?></span>
                <div class="hero-lbl">📋 Total Pesanan</div>
            </div>
            <div class="hero-item">
                <span class="hero-val"><?= $total_lunas ?></span>
                <div class="hero-lbl">✅ Sudah Lunas</div>
            </div>
            <?php if($total_blm>0): ?>
            <div class="hero-item">
                <span class="hero-val" style="color:#FF8A8A"><?= $total_blm ?></span>
                <div class="hero-lbl">⚠️ Belum Bayar</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- STATS -->
        <div class="stats-row">
            <div class="scard">
                <div class="sicon" style="background:#ECFDF5">📋</div>
                <div><p class="slabel">Total Pesanan</p><p class="sval"><?= $total_pesanan ?></p><p class="ssub"><?= $total_selesai ?> selesai · <?= $total_pesanan-$total_selesai ?> lainnya</p></div>
            </div>
            <div class="scard">
                <div class="sicon" style="background:#EFF6FF">✅</div>
                <div><p class="slabel">Transaksi Lunas</p><p class="sval"><?= $total_lunas ?></p><p class="ssub">dari <?= $total_selesai ?> pesanan selesai</p></div>
            </div>
            <div class="scard">
                <div class="sicon" style="background:#FFF0F0">💰</div>
                <div><p class="slabel">Total Omzet</p><p class="sval" style="font-size:16px"><?= formatRupiah($total_omzet) ?></p><p class="ssub">dari pembayaran lunas</p></div>
            </div>
        </div>

        <!-- METODE BAYAR -->
        <h3 style="font-size:14px;font-weight:800;color:var(--dark);margin:0 0 12px">💳 Breakdown Metode Pembayaran</h3>
        <div class="metode-row">
            <?php
            $mconf = ['cash'=>['💵','Tunai / Cash','cash'],'qris'=>['📱','QRIS','qris'],'transfer'=>['🏦','Transfer Bank','transfer']];
            foreach($mconf as $k=>[$icon,$label,$cls]):
                $md = $metode_stats[$k] ?? null;
            ?>
            <div class="mcard <?= $cls ?>">
                <div class="micon"><?= $icon ?></div>
                <div class="mname"><?= $label ?></div>
                <div class="mjml"><?= $md ? $md['jml'].' transaksi' : '0 transaksi' ?></div>
                <div class="mtotal"><?= $md ? formatRupiah($md['total']) : 'Rp 0' ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- CHARTS ROW 1: Tren + Metode Pie -->
        <div class="chart-row">
            <div class="chart-box">
                <div class="ctitle">
                    <?php if($periode==='harian') echo '📈 Tren Omzet 7 Hari Terakhir';
                    elseif($periode==='bulanan') echo '📈 Omzet per Hari — '.$judul_periode;
                    else echo '📈 Omzet per Bulan — '.$judul_periode; ?>
                </div>
                <div class="cwrap"><canvas id="chartTren"></canvas></div>
            </div>
            <div class="chart-box">
                <div class="ctitle">🥧 Komposisi Pembayaran</div>
                <div class="cwrap" style="height:160px"><canvas id="chartMetode"></canvas></div>
                <div style="margin-top:10px">
                    <?php foreach($mconf as $k=>[$icon,$label,$cls]): ?>
                    <?php $md=$metode_stats[$k]??null; ?>
                    <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0">
                        <span><?= $icon ?> <?= $label ?></span>
                        <strong><?= $md ? formatRupiah($md['total']) : 'Rp 0' ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- CHARTS ROW 2: Jam Sibuk (harian) / Ringkasan (bulanan/tahunan) + Top Menu -->
        <div class="chart-row" style="margin-bottom:20px">
            <div class="chart-box">
                <?php if ($periode === 'harian'): ?>
                <div class="ctitle">🕐 Jam Sibuk Hari Ini</div>
                <?php
                $max_peak = max(array_merge([1], array_values($peak_hours)));
                $hours = range(7,21);
                ?>
                <div class="peak-wrap">
                    <?php foreach($hours as $h):
                        $val = $peak_hours[$h] ?? 0;
                        $ht  = $max_peak > 0 ? round(($val/$max_peak)*80) : 4;
                        $is_peak = ($val === $max_peak && $val > 0);
                    ?>
                    <div class="pbar-col">
                        <?php if($val>0): ?><div class="pval"><?= $val ?></div><?php endif; ?>
                        <div class="pbar <?= $is_peak?'active':'' ?>" style="height:<?= $ht ?>px"></div>
                        <div class="plbl"><?= $h ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if(empty($peak_hours)): ?>
                <div style="text-align:center;padding:20px;color:#aaa;font-size:13px">Belum ada data</div>
                <?php endif; ?>

                <?php elseif ($periode === 'bulanan' && !empty($ringkasan_bulanan)): ?>
                <div class="ctitle">📋 Ringkasan Harian — <?= $judul_periode ?></div>
                <div style="overflow-y:auto;max-height:240px">
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead><tr style="background:#F8F9FA">
                        <th style="padding:7px 10px;text-align:left;color:#888;font-weight:700">Tanggal</th>
                        <th style="padding:7px 10px;text-align:center;color:#888;font-weight:700">Pesanan</th>
                        <th style="padding:7px 10px;text-align:center;color:#888;font-weight:700">Lunas</th>
                        <th style="padding:7px 10px;text-align:right;color:#888;font-weight:700">Omzet</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($ringkasan_bulanan as $rb): ?>
                    <tr style="border-bottom:1px solid #F5F5F5">
                        <td style="padding:7px 10px;font-weight:600"><?= date('d M', strtotime($rb['tgl'])) ?></td>
                        <td style="padding:7px 10px;text-align:center"><?= $rb['jml_pesanan'] ?></td>
                        <td style="padding:7px 10px;text-align:center;color:#16A34A;font-weight:700"><?= $rb['lunas'] ?></td>
                        <td style="padding:7px 10px;text-align:right;font-weight:700;color:var(--primary)"><?= formatRupiah($rb['omzet']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:#FFF0F0">
                        <td style="padding:8px 10px;font-weight:800">Total</td>
                        <td style="padding:8px 10px;text-align:center;font-weight:800"><?= array_sum(array_column($ringkasan_bulanan,'jml_pesanan')) ?></td>
                        <td style="padding:8px 10px;text-align:center;font-weight:800;color:#16A34A"><?= array_sum(array_column($ringkasan_bulanan,'lunas')) ?></td>
                        <td style="padding:8px 10px;text-align:right;font-weight:800;color:var(--primary)"><?= formatRupiah(array_sum(array_column($ringkasan_bulanan,'omzet'))) ?></td>
                    </tr>
                    </tbody>
                </table>
                </div>

                <?php elseif ($periode === 'tahunan' && !empty($ringkasan_tahunan)): ?>
                <div class="ctitle">📋 Ringkasan Bulanan — <?= $judul_periode ?></div>
                <?php $bulan_nm2=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; ?>
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead><tr style="background:#F8F9FA">
                        <th style="padding:7px 10px;text-align:left;color:#888;font-weight:700">Bulan</th>
                        <th style="padding:7px 10px;text-align:center;color:#888;font-weight:700">Pesanan</th>
                        <th style="padding:7px 10px;text-align:center;color:#888;font-weight:700">Lunas</th>
                        <th style="padding:7px 10px;text-align:right;color:#888;font-weight:700">Omzet</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach($ringkasan_tahunan as $rt): ?>
                    <tr style="border-bottom:1px solid #F5F5F5">
                        <td style="padding:7px 10px;font-weight:600"><?= $bulan_nm2[(int)$rt['bln']] ?></td>
                        <td style="padding:7px 10px;text-align:center"><?= $rt['jml_pesanan'] ?></td>
                        <td style="padding:7px 10px;text-align:center;color:#16A34A;font-weight:700"><?= $rt['lunas'] ?></td>
                        <td style="padding:7px 10px;text-align:right;font-weight:700;color:var(--primary)"><?= formatRupiah($rt['omzet']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:#FFF0F0">
                        <td style="padding:8px 10px;font-weight:800">Total</td>
                        <td style="padding:8px 10px;text-align:center;font-weight:800"><?= array_sum(array_column($ringkasan_tahunan,'jml_pesanan')) ?></td>
                        <td style="padding:8px 10px;text-align:center;font-weight:800;color:#16A34A"><?= array_sum(array_column($ringkasan_tahunan,'lunas')) ?></td>
                        <td style="padding:8px 10px;text-align:right;font-weight:800;color:var(--primary)"><?= formatRupiah(array_sum(array_column($ringkasan_tahunan,'omzet'))) ?></td>
                    </tr>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align:center;padding:40px;color:#aaa;font-size:13px">Belum ada data periode ini</div>
                <?php endif; ?>
            </div>

            <div class="chart-box">
                <div class="ctitle">🏆 Menu Terlaris</div>
                <?php if(empty($top_menu)): ?>
                <div style="text-align:center;padding:20px;color:#aaa;font-size:13px">Belum ada data</div>
                <?php else:
                    $max_sold = (int)($top_menu[0]['total_terjual'] ?? 1);
                    foreach($top_menu as $i=>$m):
                        $pct = $max_sold > 0 ? round(((int)$m['total_terjual']/$max_sold)*100) : 0;
                        $rkls = $i===0?'r1':($i===1?'r2':($i===2?'r3':'rn'));
                ?>
                <div class="tmitem">
                    <div class="tmrank <?= $rkls ?>"><?= $i+1 ?></div>
                    <div class="tminfo">
                        <div class="tmname"><?= htmlspecialchars(mb_substr($m['nama_menu'],0,24)) ?></div>
                        <div class="tmbar-bg"><div class="tmbar-fill" style="width:<?= $pct ?>%"></div></div>
                    </div>
                    <div class="tmqty"><?= $m['total_terjual'] ?>×</div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- TABEL DETAIL -->
        <div class="tcard">
            <div class="thead-row">
                <h3 class="ttitle">📋 Detail Pesanan · <?= $judul_periode ?></h3>
                <span style="font-size:12px;color:#888"><?= count($pesanan_list) ?> pesanan</span>
            </div>
            <?php if(empty($pesanan_list)): ?>
            <div style="text-align:center;padding:40px;color:#aaa">
                <i class="fas fa-inbox fa-3x mb-3" style="opacity:0.3"></i>
                <p style="font-size:14px">Tidak ada pesanan</p>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto">
                <table id="tabelPesanan">
                    <thead>
                        <tr>
                            <th>#</th><th>Kode</th><th>Pemesan</th><th>Meja</th><th>Waktu</th>
                            <th>Item</th><th>Total</th><th>Status</th>
                            <th>Bayar</th><th>Metode</th><th>Kembalian</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($pesanan_list as $i=>$p):
                        $sb = $p['status_bayar'] ?? 'belum_bayar';
                        $mb = $p['metode_bayar'] ?? '';
                        $ml = ['cash'=>'💵 Cash','qris'=>'📱 QRIS','transfer'=>'🏦 Transfer'];
                        $sc = ['pending'=>'bp','diproses'=>'bd','selesai'=>'bse','dibatalkan'=>'bx'];
                    ?>
                    <tr>
                        <td style="color:#aaa"><?= $i+1 ?></td>
                        <td style="font-weight:700;font-size:11px;color:var(--primary)"><?= htmlspecialchars($p['kode_pesanan']) ?></td>
                        <td style="font-size:13px">
                            <?php if(($p['nama_pelanggan']??'') && $p['nama_pelanggan']!=='Pelanggan'): ?>
                            <span style="color:#2563EB;font-weight:700">👤 <?= htmlspecialchars($p['nama_pelanggan']) ?></span>
                            <?php else: ?>
                            <span style="color:#aaa">—</span>
                            <?php endif; ?>
                        </td>
                        <td><strong>Meja <?= $p['nomor_meja'] ?></strong></td>
                        <td style="color:#888;white-space:nowrap"><?= date('H:i',strtotime($p['tanggal'])) ?></td>
                        <td style="text-align:center"><?= $p['jml_item'] ?></td>
                        <td style="font-weight:700"><?= formatRupiah($p['total_harga']) ?></td>
                        <td><span class="bs <?= $sc[$p['status']]??'bx' ?>"><?= ucfirst($p['status']) ?></span></td>
                        <td><span class="bs <?= $sb==='lunas'?'bl':'bb' ?>"><?= $sb==='lunas'?'✅ Lunas':'⏳ Belum' ?></span></td>
                        <td><?= $mb ? ($ml[$mb]??$mb) : '<span style="color:#ddd">—</span>' ?></td>
                        <td style="font-weight:700;color:#16A34A">
                            <?= ($sb==='lunas'&&$mb==='cash'&&($p['kembalian']??0)>0) ? formatRupiah($p['kembalian']) : '—' ?>
                        </td>
                        <td>
                            <?php if($sb==='lunas'): ?>
                            <a href="../struk.php?kode=<?= urlencode($p['kode_pesanan']) ?>&meja=<?= $p['nomor_meja'] ?>" target="_blank" class="struk-a">
                                <i class="fas fa-receipt"></i> Struk
                            </a>
                            <?php elseif($p['status']==='selesai'): ?>
                            <a href="../payment.php?kode=<?= urlencode($p['kode_pesanan']) ?>&meja=<?= $p['nomor_meja'] ?>" target="_blank" class="struk-a" style="color:#D97706">
                                <i class="fas fa-credit-card"></i> Bayar
                            </a>
                            <?php else: ?>
                            <span style="color:#ddd;font-size:12px">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /page-body -->
</div>

<!-- MODAL DOWNLOAD -->
<div id="dlModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;">
    <div style="background:white;border-radius:18px;padding:32px 28px;width:420px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <div>
                <h3 style="font-size:16px;font-weight:800;color:var(--dark);margin:0">Unduh Laporan</h3>
                <p style="font-size:12px;color:#888;margin:4px 0 0" id="dlSubtitle"></p>
            </div>
            <button onclick="hideDownloadModal()" style="border:none;background:#F3F4F6;border-radius:8px;width:32px;height:32px;font-size:16px;cursor:pointer;color:#666;line-height:1">&#x00D7;</button>
        </div>
        <a id="dlPdfBtn" href="#" target="_blank"
           style="display:flex;align-items:center;gap:14px;padding:16px 18px;border:2px solid #FEE2E2;border-radius:12px;text-decoration:none;margin-bottom:12px;transition:all 0.2s;background:#FFF5F5"
           onmouseover="this.style.borderColor='#E84040';this.style.background='#FEF2F2'"
           onmouseout="this.style.borderColor='#FEE2E2';this.style.background='#FFF5F5'">
            <div style="width:44px;height:44px;background:#E84040;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">&#x1F4C4;</div>
            <div style="flex:1">
                <div style="font-size:14px;font-weight:800;color:var(--dark)">Cetak / Simpan PDF</div>
                <div style="font-size:12px;color:#888;margin-top:2px">Buka tampilan cetak profesional, lalu Ctrl+P &#x2192; Save as PDF</div>
            </div>
            <i class="fas fa-external-link-alt" style="color:#E84040;font-size:13px"></i>
        </a>
        <a id="dlExcelBtn" href="#"
           style="display:flex;align-items:center;gap:14px;padding:16px 18px;border:2px solid #D1FAE5;border-radius:12px;text-decoration:none;margin-bottom:12px;transition:all 0.2s;background:#F0FDF4"
           onmouseover="this.style.borderColor='#22C55E';this.style.background='#DCFCE7'"
           onmouseout="this.style.borderColor='#D1FAE5';this.style.background='#F0FDF4'">
            <div style="width:44px;height:44px;background:#22C55E;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">&#x1F4CA;</div>
            <div style="flex:1">
                <div style="font-size:14px;font-weight:800;color:var(--dark)">Unduh Excel (.xlsx)</div>
                <div style="font-size:12px;color:#888;margin-top:2px">Spreadsheet dengan 2 sheet: Ringkasan &amp; Detail Pesanan</div>
            </div>
            <i class="fas fa-download" style="color:#22C55E;font-size:13px"></i>
        </a>
        <div style="border-top:1px solid #F3F4F6;padding-top:12px;margin-top:4px">
            <button onclick="exportCSV()" style="display:flex;align-items:center;gap:8px;padding:9px 14px;border:1.5px solid #E5E7EB;border-radius:8px;background:white;font-size:12px;font-weight:700;color:#666;cursor:pointer;font-family:inherit;width:100%;justify-content:center">
                <i class="fas fa-file-csv"></i> Unduh CSV (alternatif)
            </button>
        </div>
    </div>
</div>

<script>
function buildParams(){
    const params = new URLSearchParams(window.location.search);
    const qs = params.toString();
    return qs ? '?' + qs : '?periode=<?= $periode ?>&tanggal=<?= $tanggal ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>';
}
function showDownloadModal(){
    const qs = buildParams();
    document.getElementById('dlPdfBtn').href = 'laporan_pdf.php' + qs;
    document.getElementById('dlExcelBtn').href = 'laporan_excel.php' + qs;
    document.getElementById('dlSubtitle').textContent = 'Periode: <?= ucfirst($periode) ?> \u2014 <?= $judul_periode ?>';
    document.getElementById('dlModal').style.display = 'flex';
}
function hideDownloadModal(){
    document.getElementById('dlModal').style.display = 'none';
}
document.getElementById('dlModal').addEventListener('click', function(e){
    if(e.target === this) hideDownloadModal();
});

// Chart Tren
const trendData = <?= json_encode($trend_data) ?>;
const periode = '<?= $periode ?>';
const ctxT = document.getElementById('chartTren');
if(ctxT && trendData.length > 0){
    const isActive = trendData.map(d => periode==='harian' ? d.label==='<?= date('d/m', strtotime($tanggal)) ?>' : false);
    new Chart(ctxT,{
        type:'bar',
        data:{
            labels: trendData.map(d => d.label),
            datasets:[{
                label:'Omzet',
                data: trendData.map(d => d.omzet),
                backgroundColor: trendData.map((d,i) => isActive[i] ? 'rgba(232,64,64,0.9)' : 'rgba(232,64,64,0.35)'),
                borderColor:'rgba(232,64,64,0.7)',
                borderWidth:1.5, borderRadius:6
            }]
        },
        options:{
            responsive:true, maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>'Rp '+c.raw.toLocaleString('id-ID')}}},
            scales:{
                y:{beginAtZero:true,ticks:{callback:v=>v>=1000?(v/1000)+'k':v,font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}},
                x:{ticks:{font:{size:9},maxRotation:45},grid:{display:false}}
            }
        }
    });
}

// Chart Metode
const mData = <?= json_encode(array_values($metode_stats)) ?>;
const mKeys = <?= json_encode(array_keys($metode_stats)) ?>;
const mLabels={cash:'Tunai',qris:'QRIS',transfer:'Transfer'};
const mColors={cash:'#22C55E',qris:'#3B82F6',transfer:'#F59E0B'};
const ctxM = document.getElementById('chartMetode');
if(ctxM && mData.length>0){
    new Chart(ctxM,{
        type:'doughnut',
        data:{
            labels:mKeys.map(k=>mLabels[k]||k),
            datasets:[{data:mData.map(d=>parseInt(d.total)),backgroundColor:mKeys.map(k=>mColors[k]||'#E5E7EB'),borderWidth:3,borderColor:'#fff'}]
        },
        options:{
            responsive:true,maintainAspectRatio:false,
            plugins:{
                legend:{position:'bottom',labels:{font:{size:10},padding:8,boxWidth:10}},
                tooltip:{callbacks:{label:c=>c.label+': Rp '+c.raw.toLocaleString('id-ID')}}
            },
            cutout:'65%'
        }
    });
}

// Export CSV
function exportCSV(){
    hideDownloadModal();
    const rows=[['No','Kode','Pemesan','Meja','Waktu','Item','Total','Status','Status Bayar','Metode','Kembalian']];
    document.querySelectorAll('#tabelPesanan tbody tr').forEach((tr)=>{
        const td=[...tr.querySelectorAll('td')].map(c=>c.innerText.trim());
        rows.push(td.slice(0,11));
    });
    const csv=rows.map(r=>r.map(c=>'"'+String(c||'').replace(/"/g,'""')+'"').join(',')).join('\n');
    const blob=new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='Laporan-<?= $judul_periode ?>-<?= $periode ?>.csv';
    a.click();
}
</script>
</body>
</html>
