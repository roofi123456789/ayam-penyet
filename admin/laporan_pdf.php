<?php
require_once '../koneksi.php';
requireRole('admin');

$periode = $_GET['periode'] ?? 'harian';
$tanggal = sanitize($_GET['tanggal'] ?? date('Y-m-d'));
$bulan   = (int)($_GET['bulan'] ?? date('m'));
$tahun   = (int)($_GET['tahun'] ?? date('Y'));
if ($bulan < 1 || $bulan > 12) $bulan = (int)date('m');
if ($tahun < 2020 || $tahun > 2099) $tahun = (int)date('Y');

// WHERE clause
if ($periode === 'bulanan') {
    $where_tgl = "MONTH(p.tanggal)=$bulan AND YEAR(p.tanggal)=$tahun";
    $where_nop = "MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun";
    $judul_periode = date('F Y', mktime(0,0,0,$bulan,1,$tahun));
    $label_periode = 'Bulanan';
} elseif ($periode === 'tahunan') {
    $where_tgl = "YEAR(p.tanggal)=$tahun";
    $where_nop = "YEAR(tanggal)=$tahun";
    $judul_periode = "Tahun $tahun";
    $label_periode = 'Tahunan';
} else {
    $periode = 'harian';
    $where_tgl = "DATE(p.tanggal)='$tanggal'";
    $where_nop = "DATE(tanggal)='$tanggal'";
    $judul_periode = date('d F Y', strtotime($tanggal));
    $label_periode = 'Harian';
}

// Pesanan list
$pesanan_list = [];
$res = $conn->query("SELECT p.*,
    (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan=p.id) as jml_item
    FROM pesanan p WHERE $where_tgl AND p.status != 'dibatalkan' ORDER BY p.tanggal ASC");
while ($row = $res->fetch_assoc()) $pesanan_list[] = $row;

$total_omzet   = array_sum(array_column(array_filter($pesanan_list, fn($p) => ($p['status_bayar']??'')==='lunas'), 'total_harga'));
$total_pesanan = count($pesanan_list);
$total_selesai = count(array_filter($pesanan_list, fn($p) => $p['status']==='selesai'));
$total_lunas   = count(array_filter($pesanan_list, fn($p) => ($p['status_bayar']??'')==='lunas'));

// Metode bayar
$metode_stats = [];
$rm = $conn->query("SELECT COALESCE(metode_bayar,'cash') as metode_bayar, COUNT(*) as jml, SUM(total_harga) as total
    FROM pesanan WHERE $where_nop AND status_bayar='lunas' GROUP BY metode_bayar");
if ($rm) while ($row = $rm->fetch_assoc()) $metode_stats[$row['metode_bayar']] = $row;

// Top menu
$top_menu = [];
$res2 = $conn->query("SELECT dp.nama_menu, SUM(dp.jumlah) as total_terjual, SUM(dp.subtotal) as total_pendapatan
    FROM detail_pesanan dp JOIN pesanan p ON dp.id_pesanan=p.id
    WHERE $where_tgl AND p.status != 'dibatalkan'
    GROUP BY dp.id_menu, dp.nama_menu ORDER BY total_terjual DESC LIMIT 10");
while ($row = $res2->fetch_assoc()) $top_menu[] = $row;

// Ringkasan bulanan / tahunan
$ringkasan = [];
$bulan_nm = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

if ($periode === 'bulanan') {
    $rb = $conn->query("SELECT DATE(tanggal) as tgl, COUNT(*) as jml_pesanan,
        SUM(CASE WHEN status_bayar='lunas' THEN total_harga ELSE 0 END) as omzet,
        SUM(CASE WHEN status_bayar='lunas' THEN 1 ELSE 0 END) as lunas
        FROM pesanan WHERE MONTH(tanggal)=$bulan AND YEAR(tanggal)=$tahun
        GROUP BY DATE(tanggal) ORDER BY tgl ASC");
    if ($rb) while ($r = $rb->fetch_assoc()) $ringkasan[] = $r;
} elseif ($periode === 'tahunan') {
    $rt = $conn->query("SELECT MONTH(tanggal) as bln, COUNT(*) as jml_pesanan,
        SUM(CASE WHEN status_bayar='lunas' THEN total_harga ELSE 0 END) as omzet,
        SUM(CASE WHEN status_bayar='lunas' THEN 1 ELSE 0 END) as lunas
        FROM pesanan WHERE YEAR(tanggal)=$tahun
        GROUP BY MONTH(tanggal) ORDER BY bln ASC");
    if ($rt) while ($r = $rt->fetch_assoc()) $ringkasan[] = $r;
}

$print_time = date('d F Y, H:i') . ' WIB';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan <?= $label_periode ?> - <?= $judul_periode ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: A4;
            margin: 1.5cm 1.8cm;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a2e;
            background: white;
            line-height: 1.5;
        }

        /* ===== HEADER ===== */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            border-bottom: 3px solid #E84040;
            margin-bottom: 18px;
        }
        .header-left { display: flex; align-items: center; gap: 14px; }
        .logo-circle {
            width: 54px; height: 54px;
            background: linear-gradient(135deg, #E84040, #FF6B6B);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }
        .header-title h1 {
            font-size: 15pt;
            font-weight: 800;
            color: #1A1A2E;
            letter-spacing: -0.3px;
        }
        .header-title p {
            font-size: 9pt;
            color: #888;
            margin-top: 2px;
        }
        .header-right { text-align: right; }
        .report-badge {
            display: inline-block;
            background: #E84040;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 8.5pt;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .header-right .print-info {
            font-size: 8.5pt;
            color: #aaa;
            line-height: 1.6;
        }

        /* ===== PERIOD TITLE ===== */
        .period-banner {
            background: linear-gradient(135deg, #1A1A2E 0%, #0F3460 100%);
            color: white;
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .period-banner h2 {
            font-size: 13pt;
            font-weight: 800;
            margin: 0;
        }
        .period-banner span {
            font-size: 9.5pt;
            color: rgba(255,255,255,0.65);
        }

        /* ===== SUMMARY GRID ===== */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }
        .sum-card {
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            padding: 12px 14px;
            text-align: center;
        }
        .sum-card.highlight { border-color: #E84040; background: #FFF5F5; }
        .sum-label { font-size: 8pt; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
        .sum-value { font-size: 17pt; font-weight: 800; color: #1A1A2E; margin: 4px 0 2px; line-height: 1; }
        .sum-card.highlight .sum-value { color: #E84040; }
        .sum-sub { font-size: 8pt; color: #aaa; }

        /* ===== SECTION TITLE ===== */
        .section-title {
            font-size: 10.5pt;
            font-weight: 800;
            color: #1A1A2E;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #F0F2F5;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ===== METODE BAYAR ===== */
        .metode-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }
        .metode-card {
            border-radius: 10px;
            padding: 12px 14px;
            border-left: 4px solid #ddd;
        }
        .metode-card.cash { border-color: #22C55E; background: #F0FDF4; }
        .metode-card.qris { border-color: #3B82F6; background: #EFF6FF; }
        .metode-card.transfer { border-color: #F59E0B; background: #FFFBEB; }
        .metode-name { font-size: 9pt; font-weight: 700; color: #444; }
        .metode-jml { font-size: 8.5pt; color: #888; margin: 2px 0; }
        .metode-total { font-size: 12pt; font-weight: 800; color: #1A1A2E; }

        /* ===== TABLES ===== */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 9.5pt;
        }
        thead tr { background: #1A1A2E; color: white; }
        thead th {
            padding: 9px 10px;
            text-align: left;
            font-weight: 700;
            font-size: 8.5pt;
            letter-spacing: 0.3px;
        }
        thead th.text-center { text-align: center; }
        thead th.text-right  { text-align: right; }
        tbody tr:nth-child(even) { background: #F8F9FA; }
        tbody tr:last-child td { border-bottom: none; }
        tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #F0F0F0;
            vertical-align: middle;
        }
        tbody td.text-center { text-align: center; }
        tbody td.text-right  { text-align: right; }
        .tfoot-row td {
            background: #1A1A2E !important;
            color: white;
            font-weight: 800;
            padding: 9px 10px;
            border: none;
        }
        .tfoot-row td.text-right { text-align: right; }
        .tfoot-row td.text-center { text-align: center; }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 8pt;
            font-weight: 700;
        }
        .badge-lunas   { background: #DCFCE7; color: #16A34A; }
        .badge-belum   { background: #FEF2F2; color: #DC2626; }
        .badge-selesai { background: #DCFCE7; color: #16A34A; }
        .badge-diproses{ background: #EFF6FF; color: #2563EB; }
        .badge-pending { background: #FFF7ED; color: #D97706; }
        .badge-batal   { background: #F3F4F6; color: #6B7280; }

        /* Top menu rank */
        .rank-no {
            display: inline-block;
            width: 20px; height: 20px;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            font-size: 8pt;
            font-weight: 800;
            color: white;
        }
        .rank-1 { background: #F59E0B; }
        .rank-2 { background: #94A3B8; }
        .rank-3 { background: #C2855F; }
        .rank-n { background: #CBD5E1; color: #444; }

        /* Progress bar */
        .bar-wrap { background: #E5E7EB; border-radius: 4px; height: 6px; overflow: hidden; }
        .bar-fill  { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #E84040, #FF8A8A); }

        /* ===== TWO COLUMN ===== */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
        .two-col-7-3 { display: grid; grid-template-columns: 7fr 3fr; gap: 16px; margin-bottom: 18px; }

        /* ===== FOOTER ===== */
        .footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1.5px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8.5pt;
            color: #aaa;
        }
        .sign-area { text-align: center; }
        .sign-line {
            width: 140px;
            border-bottom: 1.5px solid #1A1A2E;
            margin: 40px auto 6px;
        }
        .sign-name { font-size: 9pt; font-weight: 700; color: #1A1A2E; }

        /* Print controls (hidden on print) */
        .print-controls {
            position: fixed;
            bottom: 20px; right: 20px;
            display: flex; gap: 10px;
            z-index: 999;
        }
        .btn-print, .btn-back {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-print { background: #E84040; color: white; }
        .btn-back  { background: white; color: #1A1A2E; border: 1.5px solid #E5E7EB; }

        @media print {
            .print-controls { display: none !important; }
            body { font-size: 10pt; }
            .period-banner { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .summary-grid, .metode-grid { page-break-inside: avoid; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>

    <!-- PRINT CONTROLS -->
    <div class="print-controls">
        <button class="btn-back" onclick="window.history.back()">← Kembali</button>
        <button class="btn-print" onclick="window.print()">🖨️ Cetak / Save PDF</button>
    </div>

    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            <div class="logo-circle">🍗</div>
            <div class="header-title">
                <h1>Ayam Penyet Bendungan</h1>
                <p>Batusangkar, Sumatera Barat &nbsp;|&nbsp; Laporan Penjualan Resmi</p>
            </div>
        </div>
        <div class="header-right">
            <div class="report-badge">Laporan <?= $label_periode ?></div>
            <div class="print-info">
                Dicetak: <?= $print_time ?><br>
                Oleh: <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin') ?>
            </div>
        </div>
    </div>

    <!-- PERIOD BANNER -->
    <div class="period-banner">
        <h2>📊 Laporan Penjualan — <?= $judul_periode ?></h2>
        <span>Periode: <?= $label_periode ?></span>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="summary-grid">
        <div class="sum-card highlight">
            <div class="sum-label">Total Omzet</div>
            <div class="sum-value" style="font-size:13pt"><?= formatRupiah($total_omzet) ?></div>
            <div class="sum-sub">dari transaksi lunas</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Pesanan</div>
            <div class="sum-value"><?= $total_pesanan ?></div>
            <div class="sum-sub">semua status</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Pesanan Selesai</div>
            <div class="sum-value"><?= $total_selesai ?></div>
            <div class="sum-sub">dari <?= $total_pesanan ?> pesanan</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Transaksi Lunas</div>
            <div class="sum-value"><?= $total_lunas ?></div>
            <div class="sum-sub"><?= $total_selesai > 0 ? round($total_lunas/$total_selesai*100) : 0 ?>% dari selesai</div>
        </div>
    </div>

    <!-- METODE PEMBAYARAN -->
    <div class="section-title">💳 Breakdown Metode Pembayaran</div>
    <div class="metode-grid">
        <?php
        $mconf = [
            'cash'     => ['💵', 'Tunai / Cash', 'cash'],
            'qris'     => ['📱', 'QRIS', 'qris'],
            'transfer' => ['🏦', 'Transfer Bank', 'transfer'],
        ];
        foreach ($mconf as $k => [$icon, $label, $cls]):
            $md = $metode_stats[$k] ?? null;
        ?>
        <div class="metode-card <?= $cls ?>">
            <div class="metode-name"><?= $icon ?> <?= $label ?></div>
            <div class="metode-jml"><?= $md ? $md['jml'].' transaksi' : '0 transaksi' ?></div>
            <div class="metode-total"><?= $md ? formatRupiah($md['total']) : 'Rp 0' ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- TOP MENU + RINGKASAN (dua kolom) -->
    <?php if ($periode === 'harian'): ?>

        <!-- HARIAN: Top Menu -->
        <div class="section-title">🏆 Menu Terlaris — <?= $judul_periode ?></div>
        <?php if (!empty($top_menu)): ?>
        <?php $max_sold = (int)($top_menu[0]['total_terjual'] ?? 1); ?>
        <table>
            <thead>
                <tr>
                    <th style="width:32px">#</th>
                    <th>Nama Menu</th>
                    <th class="text-center">Terjual</th>
                    <th class="text-right">Pendapatan</th>
                    <th style="width:120px">Proporsi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($top_menu as $i => $m):
                $pct = $max_sold > 0 ? round(((int)$m['total_terjual']/$max_sold)*100) : 0;
                $rkls = $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-n'));
            ?>
            <tr>
                <td><span class="rank-no <?= $rkls ?>"><?= $i+1 ?></span></td>
                <td style="font-weight:600"><?= htmlspecialchars($m['nama_menu']) ?></td>
                <td class="text-center"><strong><?= $m['total_terjual'] ?></strong> porsi</td>
                <td class="text-right" style="font-weight:700;color:#E84040"><?= formatRupiah($m['total_pendapatan']) ?></td>
                <td>
                    <div class="bar-wrap">
                        <div class="bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#aaa;text-align:center;padding:14px">Belum ada data menu</p>
        <?php endif; ?>

        <!-- HARIAN: Detail Pesanan -->
        <div class="section-title">📋 Detail Pesanan — <?= $judul_periode ?></div>

    <?php elseif ($periode === 'bulanan'): ?>

        <!-- BULANAN: Ringkasan per Hari -->
        <div class="two-col">
            <div>
                <div class="section-title">📅 Ringkasan per Hari — <?= $judul_periode ?></div>
                <?php if (!empty($ringkasan)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-center">Pesanan</th>
                            <th class="text-center">Lunas</th>
                            <th class="text-right">Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ringkasan as $rb): ?>
                    <tr>
                        <td style="font-weight:600"><?= date('d F', strtotime($rb['tgl'])) ?></td>
                        <td class="text-center"><?= $rb['jml_pesanan'] ?></td>
                        <td class="text-center" style="color:#16A34A;font-weight:700"><?= $rb['lunas'] ?></td>
                        <td class="text-right" style="font-weight:700;color:#E84040"><?= formatRupiah($rb['omzet']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr class="tfoot-row">
                        <td>TOTAL</td>
                        <td class="text-center"><?= array_sum(array_column($ringkasan,'jml_pesanan')) ?></td>
                        <td class="text-center"><?= array_sum(array_column($ringkasan,'lunas')) ?></td>
                        <td class="text-right"><?= formatRupiah(array_sum(array_column($ringkasan,'omzet'))) ?></td>
                    </tr>
                    </tfoot>
                </table>
                <?php else: ?>
                <p style="color:#aaa;padding:14px;text-align:center">Belum ada data</p>
                <?php endif; ?>
            </div>
            <div>
                <div class="section-title">🏆 Menu Terlaris</div>
                <?php if (!empty($top_menu)): ?>
                <?php $max_sold = (int)($top_menu[0]['total_terjual'] ?? 1); ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:28px">#</th>
                            <th>Menu</th>
                            <th class="text-center">Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top_menu as $i => $m):
                        $rkls = $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-n'));
                    ?>
                    <tr>
                        <td><span class="rank-no <?= $rkls ?>"><?= $i+1 ?></span></td>
                        <td style="font-weight:600;font-size:9pt"><?= htmlspecialchars(mb_substr($m['nama_menu'],0,28)) ?></td>
                        <td class="text-center"><strong><?= $m['total_terjual'] ?></strong>×</td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- BULANAN: Detail Pesanan -->
        <div class="section-title">📋 Detail Pesanan — <?= $judul_periode ?></div>

    <?php elseif ($periode === 'tahunan'): ?>

        <!-- TAHUNAN: Ringkasan per Bulan -->
        <div class="two-col">
            <div>
                <div class="section-title">📆 Ringkasan per Bulan — <?= $judul_periode ?></div>
                <?php if (!empty($ringkasan)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th class="text-center">Pesanan</th>
                            <th class="text-center">Lunas</th>
                            <th class="text-right">Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ringkasan as $rt): ?>
                    <tr>
                        <td style="font-weight:600"><?= $bulan_nm[(int)$rt['bln']] ?></td>
                        <td class="text-center"><?= $rt['jml_pesanan'] ?></td>
                        <td class="text-center" style="color:#16A34A;font-weight:700"><?= $rt['lunas'] ?></td>
                        <td class="text-right" style="font-weight:700;color:#E84040"><?= formatRupiah($rt['omzet']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr class="tfoot-row">
                        <td>TOTAL</td>
                        <td class="text-center"><?= array_sum(array_column($ringkasan,'jml_pesanan')) ?></td>
                        <td class="text-center"><?= array_sum(array_column($ringkasan,'lunas')) ?></td>
                        <td class="text-right"><?= formatRupiah(array_sum(array_column($ringkasan,'omzet'))) ?></td>
                    </tr>
                    </tfoot>
                </table>
                <?php else: ?>
                <p style="color:#aaa;padding:14px;text-align:center">Belum ada data</p>
                <?php endif; ?>
            </div>
            <div>
                <div class="section-title">🏆 Menu Terlaris</div>
                <?php if (!empty($top_menu)): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:28px">#</th>
                            <th>Menu</th>
                            <th class="text-center">Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top_menu as $i => $m):
                        $rkls = $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-n'));
                    ?>
                    <tr>
                        <td><span class="rank-no <?= $rkls ?>"><?= $i+1 ?></span></td>
                        <td style="font-weight:600;font-size:9pt"><?= htmlspecialchars(mb_substr($m['nama_menu'],0,28)) ?></td>
                        <td class="text-center"><strong><?= $m['total_terjual'] ?></strong>×</td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAHUNAN: Detail Pesanan -->
        <div class="section-title">📋 Detail Pesanan — <?= $judul_periode ?></div>

    <?php endif; ?>

    <!-- TABEL DETAIL PESANAN (semua periode) -->
    <?php if (empty($pesanan_list)): ?>
    <div style="text-align:center;padding:28px;color:#aaa;border:1.5px dashed #ddd;border-radius:10px;font-size:11pt">
        Tidak ada data pesanan pada periode ini
    </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th style="width:28px">#</th>
                <th>Kode Pesanan</th>
                <th>Pemesan</th>
                <th class="text-center">Meja</th>
                <th><?= $periode === 'harian' ? 'Waktu' : 'Tanggal & Waktu' ?></th>
                <th class="text-center">Item</th>
                <th class="text-right">Total</th>
                <th class="text-center">Status</th>
                <th class="text-center">Bayar</th>
                <th>Metode</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $ml = ['cash'=>'Cash','qris'=>'QRIS','transfer'=>'Transfer'];
        foreach ($pesanan_list as $i => $p):
            $sb = $p['status_bayar'] ?? 'belum_bayar';
            $mb = $p['metode_bayar'] ?? '';
            $st = $p['status'] ?? '';
        ?>
        <tr>
            <td style="color:#aaa"><?= $i+1 ?></td>
            <td style="font-weight:700;font-size:8.5pt;color:#E84040"><?= htmlspecialchars($p['kode_pesanan']) ?></td>
            <td style="font-size:9pt">
                <?= ($p['nama_pelanggan']??'') && $p['nama_pelanggan']!=='Pelanggan'
                    ? htmlspecialchars($p['nama_pelanggan'])
                    : '<span style="color:#ccc">—</span>' ?>
            </td>
            <td class="text-center" style="font-weight:700">Meja <?= $p['nomor_meja'] ?></td>
            <td style="font-size:8.5pt;color:#555;white-space:nowrap">
                <?= $periode === 'harian'
                    ? date('H:i', strtotime($p['tanggal']))
                    : date('d/m H:i', strtotime($p['tanggal'])) ?>
            </td>
            <td class="text-center"><?= $p['jml_item'] ?></td>
            <td class="text-right" style="font-weight:700"><?= formatRupiah($p['total_harga']) ?></td>
            <td class="text-center">
                <?php
                $badge_st = ['selesai'=>'badge-selesai','diproses'=>'badge-diproses','pending'=>'badge-pending','dibatalkan'=>'badge-batal'];
                ?>
                <span class="badge <?= $badge_st[$st] ?? 'badge-batal' ?>"><?= ucfirst($st) ?></span>
            </td>
            <td class="text-center">
                <span class="badge <?= $sb==='lunas'?'badge-lunas':'badge-belum' ?>">
                    <?= $sb==='lunas' ? 'Lunas' : 'Belum' ?>
                </span>
            </td>
            <td style="font-size:9pt"><?= $mb ? ($ml[$mb]??$mb) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr class="tfoot-row">
            <td colspan="6" style="font-weight:800">TOTAL</td>
            <td class="text-right"><?= formatRupiah(array_sum(array_column($pesanan_list,'total_harga'))) ?></td>
            <td colspan="3"></td>
        </tr>
        </tfoot>
    </table>
    <?php endif; ?>

    <!-- FOOTER -->
    <div class="footer">
        <div>
            <strong>Ayam Penyet Bendungan Batusangkar</strong><br>
            Dokumen dicetak pada <?= $print_time ?>
        </div>
        <div class="sign-area">
            <div style="font-size:8.5pt;color:#555;margin-bottom:0">Mengetahui,</div>
            <div class="sign-line"></div>
            <div class="sign-name"><?= htmlspecialchars($_SESSION['nama'] ?? 'Admin') ?></div>
            <div style="font-size:8pt;color:#aaa">Administrator</div>
        </div>
    </div>

</body>
</html>
