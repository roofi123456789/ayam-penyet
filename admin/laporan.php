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

$tanggal = sanitize($_GET['tanggal'] ?? date('Y-m-d'));

// Pesanan hari ini
$pesanan_list = [];
$res = $conn->query("SELECT p.*,
    (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan=p.id) as jml_item
    FROM pesanan p WHERE DATE(p.tanggal)='$tanggal' ORDER BY p.tanggal DESC");
while ($row = $res->fetch_assoc()) $pesanan_list[] = $row;

$total_omzet    = array_sum(array_column(array_filter($pesanan_list, fn($p) => ($p['status_bayar']??'')  ==='lunas'), 'total_harga'));
$total_pesanan  = count($pesanan_list);
$total_selesai  = count(array_filter($pesanan_list, fn($p) => $p['status']==='selesai'));
$total_lunas    = count(array_filter($pesanan_list, fn($p) => ($p['status_bayar']??'')==='lunas'));
$total_blm      = count(array_filter($pesanan_list, fn($p) => $p['status']==='selesai' && ($p['status_bayar']??'belum_bayar')!=='lunas'));

// Breakdown metode
$metode_stats = [];
$metode_stats = [];
$rm = $conn->query("SELECT COALESCE(metode_bayar,'cash') as metode_bayar, COUNT(*) as jml, SUM(total_harga) as total
    FROM pesanan WHERE DATE(tanggal)='$tanggal' AND status_bayar='lunas' GROUP BY metode_bayar");
if ($rm) {
    while ($row = $rm->fetch_assoc()) $metode_stats[$row['metode_bayar']] = $row;
}

// Menu terlaris
$top_menu = [];
$res2 = $conn->query("SELECT dp.nama_menu, SUM(dp.jumlah) as total_terjual, SUM(dp.subtotal) as total_pendapatan
    FROM detail_pesanan dp JOIN pesanan p ON dp.id_pesanan=p.id
    WHERE DATE(p.tanggal)='$tanggal' AND p.status != 'dibatalkan'
    GROUP BY dp.id_menu, dp.nama_menu ORDER BY total_terjual DESC LIMIT 8");
while ($row = $res2->fetch_assoc()) $top_menu[] = $row;

// Tren 7 hari
$weekly = [];
$res3 = $conn->query("SELECT DATE(tanggal) as tgl,
    COUNT(*) as total_pesanan,
    SUM(total_harga) as omzet
    FROM pesanan
    WHERE tanggal >= DATE_SUB('$tanggal', INTERVAL 6 DAY) AND tanggal < DATE_ADD('$tanggal', INTERVAL 1 DAY)
    GROUP BY DATE(tanggal) ORDER BY tgl ASC");
if ($res3) while ($row = $res3->fetch_assoc()) $weekly[] = $row;

// Jam sibuk
$peak_hours = [];
$res4 = $conn->query("SELECT HOUR(tanggal) as jam, COUNT(*) as jml FROM pesanan WHERE DATE(tanggal)='$tanggal' GROUP BY HOUR(tanggal) ORDER BY jam ASC");
if ($res4) while ($row = $res4->fetch_assoc()) $peak_hours[$row['jam']] = (int)$row['jml'];
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
        <div class="nav-lbl">Menu Utama</div>
        <div class="nav-item"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="konfirmasi_bayar.php"><i class="fas fa-cash-register"></i> Konfirmasi Bayar</a></div>
        <div class="nav-item"><a href="menu.php"><i class="fas fa-utensils"></i> Kelola Menu</a></div>
        <div class="nav-item"><a href="kitchen.php"><i class="fas fa-tv"></i> Kitchen Display</a></div>
        <div class="nav-item"><a href="laporan.php" class="active"><i class="fas fa-chart-bar"></i> Laporan</a></div>
        <div class="nav-item"><a href="qrcode.php"><i class="fas fa-qrcode"></i> QR Code</a></div>
        <div class="nav-item"><a href="meja.php"><i class="fas fa-chair"></i> Manajemen Meja</a></div>
        <div class="nav-item"><a href="admin_user.php"><i class="fas fa-users-cog"></i> Kelola Admin</a></div>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
</div>

<!-- MAIN -->
<div class="main-content">
    <div class="topbar">
        <h1>📊 Laporan Penjualan</h1>
        <div class="d-flex gap-2 no-print">
            <button class="btn-s btn-light" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
            <button class="btn-s btn-dark" onclick="exportCSV()"><i class="fas fa-download"></i> CSV</button>
        </div>
    </div>

    <div class="page-body">

        <!-- FILTER -->
        <form method="GET" class="card-box filter-bar no-print" style="margin-bottom:20px">
            <span style="font-size:13px;font-weight:700;color:#888">📅 Pilih Tanggal:</span>
            <input type="date" name="tanggal" class="inp" value="<?= $tanggal ?>">
            <button type="submit" class="btn-s btn-red"><i class="fas fa-search"></i> Tampilkan</button>
            <a href="?tanggal=<?= date('Y-m-d') ?>" style="font-size:13px;color:var(--primary);text-decoration:none;font-weight:700">Hari Ini</a>
            <a href="?tanggal=<?= date('Y-m-d',strtotime('-1 day')) ?>" style="font-size:13px;color:#666;text-decoration:none;font-weight:600">Kemarin</a>
        </form>

        <!-- HERO SUMMARY -->
        <div class="hero-box">
            <div style="font-size:28px">📊</div>
            <div class="hero-item">
                <span class="hero-val"><?= date('d M Y',strtotime($tanggal)) ?></span>
                <div class="hero-lbl">Tanggal Laporan</div>
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
                <div class="ctitle">📈 Tren Omzet 7 Hari Terakhir</div>
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

        <!-- CHARTS ROW 2: Jam Sibuk + Top Menu -->
        <div class="chart-row" style="margin-bottom:20px">
            <div class="chart-box">
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
                <h3 class="ttitle">📋 Detail Pesanan · <?= date('d M Y',strtotime($tanggal)) ?></h3>
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

<script>
// Chart Tren
const wData = <?= json_encode($weekly) ?>;
const wLabels = wData.map(d=>{
    const dt=new Date(d.tgl+'T00:00:00');
    return dt.toLocaleDateString('id-ID',{weekday:'short',day:'numeric',month:'short'});
});
const ctxT = document.getElementById('chartTren');
if(ctxT){
    new Chart(ctxT,{
        type:'bar',
        data:{
            labels:wLabels,
            datasets:[{
                label:'Omzet',
                data:wData.map(d=>parseInt(d.omzet)||0),
                backgroundColor:wData.map(d=>d.tgl==='<?= $tanggal ?>'?'rgba(232,64,64,0.9)':'rgba(232,64,64,0.3)'),
                borderColor:'rgba(232,64,64,0.7)',
                borderWidth:1.5,borderRadius:6
            }]
        },
        options:{
            responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>'Rp '+c.raw.toLocaleString('id-ID')}}},
            scales:{
                y:{beginAtZero:true,ticks:{callback:v=>v>=1000?(v/1000)+'k':v,font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}},
                x:{ticks:{font:{size:10}},grid:{display:false}}
            }
        }
    });
}

// Chart Metode Pie
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
            datasets:[{
                data:mData.map(d=>parseInt(d.total)),
                backgroundColor:mKeys.map(k=>mColors[k]||'#E5E7EB'),
                borderWidth:3,borderColor:'#fff'
            }]
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
    const rows=[['No','Kode','Pemesan','Meja','Waktu','Item','Total','Status','Status Bayar','Metode','Kembalian']];
    document.querySelectorAll('#tabelPesanan tbody tr').forEach((tr,i)=>{
        const td=[...tr.querySelectorAll('td')].map(c=>c.innerText.trim());
        rows.push(td.slice(0,11));
    });
    const csv=rows.map(r=>r.map(c=>'"'+String(c||'').replace(/"/g,'""')+'"').join(',')).join('\n');
    const blob=new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='Laporan-<?= $tanggal ?>.csv';
    a.click();
}
</script>
</body>
</html>
