<?php
// ============================================
// riwayat.php - Riwayat pesanan pelanggan
// ============================================
require_once 'koneksi.php';

$nomor_meja = getNomorMeja();
$limit = 10;

$riwayat = [];
if ($nomor_meja > 0) {
    $res = $conn->query("SELECT p.*, 
        (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan=p.id) as jml_item
        FROM pesanan p 
        WHERE p.nomor_meja = $nomor_meja 
        AND DATE(p.tanggal) = CURDATE()
        ORDER BY p.tanggal DESC 
        LIMIT $limit");
    while ($row = $res->fetch_assoc()) $riwayat[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Meja <?= $nomor_meja ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#E84040;--dark:#1A1A2E;--bg:#F5F5F5;--border:#E5E7EB;--radius:16px;}
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);margin:0;padding-bottom:30px;}

        .page-header{background:var(--dark);padding:16px 20px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:100;}
        .back-btn{width:36px;height:36px;background:rgba(255,255,255,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;text-decoration:none;font-size:16px;transition:background 0.2s;}
        .back-btn:hover{background:rgba(255,255,255,0.2);color:white;}
        .header-title{color:white;font-size:17px;font-weight:800;margin:0;flex:1;}
        .meja-chip{background:rgba(255,255,255,0.15);color:white;border-radius:50px;padding:5px 12px;font-size:12px;font-weight:700;}

        .empty-box{text-align:center;padding:80px 30px;}
        .empty-box .icon{font-size:70px;margin-bottom:16px;opacity:0.4;}
        .empty-box h3{font-size:20px;font-weight:800;color:var(--dark);}
        .empty-box p{color:#888;font-size:14px;}

        .riwayat-item{background:white;border-radius:var(--radius);margin:12px 16px;padding:16px 18px;box-shadow:0 2px 12px rgba(0,0,0,0.06);transition:transform 0.2s;}
        .riwayat-item:hover{transform:translateY(-1px);}

        .ri-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}
        .ri-kode{font-size:13px;font-weight:800;color:var(--primary);font-family:monospace;}
        .ri-waktu{font-size:11px;color:#888;margin-top:2px;}

        .status-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:50px;font-size:11px;font-weight:700;}
        .s-pending{background:#FFF7ED;color:#D97706;}
        .s-diproses{background:#EFF6FF;color:#2563EB;}
        .s-selesai{background:#ECFDF5;color:#16A34A;}
        .s-dibatalkan{background:#F8FAFC;color:#64748B;}

        .bayar-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:50px;font-size:10px;font-weight:700;margin-left:6px;}
        .b-lunas{background:#ECFDF5;color:#16A34A;}
        .b-blm{background:#FEF2F2;color:#DC2626;}

        .ri-body{display:flex;align-items:center;justify-content:space-between;}
        .ri-info{font-size:13px;color:#555;}
        .ri-total{font-size:16px;font-weight:800;color:var(--dark);}

        .ri-actions{display:flex;gap:8px;margin-top:12px;padding-top:10px;border-top:1px solid #F5F5F5;}
        .ri-btn{flex:1;border-radius:50px;padding:9px;font-size:12px;font-weight:700;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:5px;transition:all 0.2s;border:none;cursor:pointer;font-family:inherit;}
        .ri-btn-status{background:#EFF6FF;color:#2563EB;}
        .ri-btn-status:hover{background:#2563EB;color:white;}
        .ri-btn-pay{background:var(--primary);color:white;}
        .ri-btn-pay:hover{background:#C42E2E;color:white;}
        .ri-btn-struk{background:#ECFDF5;color:#16A34A;}
        .ri-btn-struk:hover{background:#16A34A;color:white;}

        .date-badge{background:var(--dark);color:white;border-radius:50px;padding:5px 14px;font-size:12px;font-weight:700;display:inline-block;margin:16px 16px 6px;}
        .total-section{background:white;border-radius:var(--radius);margin:0 16px 8px;padding:14px 18px;box-shadow:0 2px 12px rgba(0,0,0,0.06);display:flex;justify-content:space-between;align-items:center;}
        .total-section-lbl{font-size:13px;color:#888;font-weight:600;}
        .total-section-val{font-size:18px;font-weight:800;color:var(--primary);}
    </style>
</head>
<body>

<div class="page-header">
    <a href="index.php<?= $nomor_meja > 0 ? '?meja='.$nomor_meja : '' ?>" class="back-btn">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="header-title">📋 Riwayat Pesanan</h1>
    <?php if ($nomor_meja > 0): ?>
    <span class="meja-chip">Meja <?= $nomor_meja ?></span>
    <?php endif; ?>
</div>

<?php if ($nomor_meja <= 0): ?>
<div class="empty-box">
    <div class="icon">🔍</div>
    <h3>Nomor Meja Tidak Dikenali</h3>
    <p>Silakan scan ulang QR Code meja Anda.</p>
</div>

<?php elseif (empty($riwayat)): ?>
<div class="empty-box">
    <div class="icon">📋</div>
    <h3>Belum Ada Pesanan</h3>
    <p>Anda belum memesan apapun hari ini di meja <?= $nomor_meja ?>.</p>
    <a href="index.php?meja=<?= $nomor_meja ?>"
       style="display:inline-block;margin-top:16px;background:var(--primary);color:white;padding:13px 28px;border-radius:50px;font-size:14px;font-weight:700;text-decoration:none">
        🍗 Lihat Menu
    </a>
</div>

<?php else: ?>

<div class="date-badge">
    <i class="fas fa-calendar me-1"></i>Hari ini · <?= date('d M Y') ?>
</div>

<?php
$total_semua = 0;
$status_classes = ['pending'=>'s-pending','diproses'=>'s-diproses','selesai'=>'s-selesai','dibatalkan'=>'s-dibatalkan'];
$status_icons   = ['pending'=>'⏳','diproses'=>'🔥','selesai'=>'✅','dibatalkan'=>'❌'];

foreach ($riwayat as $p):
    if ($p['status'] !== 'dibatalkan') $total_semua += $p['total_harga'];
    $sb = $p['status_bayar'] ?? 'belum_bayar';
?>
<div class="riwayat-item">
    <div class="ri-header">
        <div>
            <div class="ri-kode"><?= htmlspecialchars($p['kode_pesanan']) ?></div>
            <div class="ri-waktu"><i class="fas fa-clock me-1"></i><?= date('H:i', strtotime($p['tanggal'])) ?></div>
        </div>
        <div style="text-align:right">
            <span class="status-badge <?= $status_classes[$p['status']] ?? 's-pending' ?>">
                <?= $status_icons[$p['status']] ?? '?' ?> <?= ucfirst($p['status']) ?>
            </span>
            <span class="bayar-badge <?= $sb==='lunas'?'b-lunas':'b-blm' ?>">
                <?= $sb==='lunas'?'✅ Lunas':'⏳ Belum Bayar' ?>
            </span>
        </div>
    </div>

    <div class="ri-body">
        <div class="ri-info">
            <?php if(($p['nama_pelanggan']??'') && $p['nama_pelanggan']!=='Pelanggan'): ?>
            <div style="color:#2563EB;font-weight:700;font-size:12px;margin-bottom:3px">
                👤 <?= htmlspecialchars($p['nama_pelanggan']) ?>
            </div>
            <?php endif; ?>
            <div><i class="fas fa-list me-1" style="color:#ccc"></i><?= $p['jml_item'] ?> item dipesan</div>
        </div>
        <div class="ri-total"><?= formatRupiah($p['total_harga']) ?></div>
    </div>

    <div class="ri-actions">
        <a href="status.php?kode=<?= urlencode($p['kode_pesanan']) ?>&meja=<?= $nomor_meja ?>"
           class="ri-btn ri-btn-status">
            <i class="fas fa-search"></i> Status
        </a>

        <?php if ($sb !== 'lunas' && $p['status'] === 'selesai'): ?>
        <a href="payment.php?kode=<?= urlencode($p['kode_pesanan']) ?>&meja=<?= $nomor_meja ?>"
           class="ri-btn ri-btn-pay">
            <i class="fas fa-credit-card"></i> Bayar
        </a>
        <?php elseif ($sb === 'lunas'): ?>
        <a href="struk.php?kode=<?= urlencode($p['kode_pesanan']) ?>&meja=<?= $nomor_meja ?>"
           class="ri-btn ri-btn-struk">
            <i class="fas fa-receipt"></i> Struk
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- Total -->
<?php if ($total_semua > 0): ?>
<div class="total-section">
    <span class="total-section-lbl">Total Semua Pesanan Hari Ini</span>
    <span class="total-section-val"><?= formatRupiah($total_semua) ?></span>
</div>
<?php endif; ?>

<!-- Tambah pesanan -->
<div style="padding:12px 16px">
    <a href="index.php?meja=<?= $nomor_meja ?>"
       style="display:flex;align-items:center;justify-content:center;gap:8px;background:var(--primary);color:white;padding:14px;border-radius:50px;font-size:14px;font-weight:700;text-decoration:none;box-shadow:0 4px 14px rgba(232,64,64,0.3)">
        <i class="fas fa-plus"></i> Tambah Pesanan Lagi
    </a>
</div>

<?php endif; ?>
</body>
</html>
