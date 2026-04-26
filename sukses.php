<?php
require_once 'koneksi.php';

$kode       = sanitize($_GET['kode'] ?? '');
$nomor_meja = (int)($_GET['meja'] ?? 0);

$pesanan = null;
$details = [];

if ($kode) {
    $stmt = $conn->prepare("SELECT * FROM pesanan WHERE kode_pesanan = ?");
    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $pesanan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($pesanan) {
        $stmt2 = $conn->prepare("SELECT * FROM detail_pesanan WHERE id_pesanan = ?");
        $stmt2->bind_param('i', $pesanan['id']);
        $stmt2->execute();
        $r = $stmt2->get_result();
        while ($row = $r->fetch_assoc()) $details[] = $row;
        $stmt2->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:      #E84040;
            --primary-dark: #C42E2E;
            --dark:         #1A1A2E;
            --success:      #22C55E;
            --bg:           #F5F5F5;
            --border:       #E5E7EB;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            margin: 0;
            min-height: 100vh;
            padding-bottom: 32px;
        }

        /* ── HERO ── */
        .hero {
            background: linear-gradient(135deg, var(--success) 0%, #15803D 100%);
            padding: 48px 24px 72px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 180px; height: 180px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: -40px; left: -40px;
            width: 140px; height: 140px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .checkmark {
            width: 88px; height: 88px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 18px;
            font-size: 40px;
            border: 3px solid rgba(255,255,255,0.35);
            animation: popIn .5s cubic-bezier(.175,.885,.32,1.275) both;
            position: relative; z-index: 1;
        }
        @keyframes popIn {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }
        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 26px; color: white;
            margin: 0 0 6px; position: relative; z-index: 1;
        }
        .hero p {
            color: rgba(255,255,255,0.8);
            font-size: 14px; margin: 0; position: relative; z-index: 1;
        }

        /* ── ORDER CARD ── */
        .order-card {
            margin: -32px 16px 14px;
            background: white;
            border-radius: 20px;
            padding: 22px 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            position: relative; z-index: 10;
        }
        .kode-wrap {
            text-align: center;
            padding-bottom: 16px;
            border-bottom: 1.5px dashed var(--border);
            margin-bottom: 16px;
        }
        .kode-label { font-size: 11px; color: #888; margin: 0 0 4px; letter-spacing: .5px; text-transform: uppercase; }
        .kode-val {
            font-size: 20px; font-weight: 800;
            color: var(--primary); letter-spacing: 2px;
            font-family: monospace; margin: 0;
        }
        .info-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 0; border-bottom: 1px solid #F8F8F8; font-size: 14px;
        }
        .info-row:last-child { border: none; padding-bottom: 0; }
        .info-lbl { color: #888; }
        .info-val { font-weight: 700; color: var(--dark); }
        .pending-badge {
            background: #FFF7ED; color: #D97706;
            border-radius: 50px; padding: 4px 12px;
            font-size: 12px; font-weight: 700;
        }

        /* ── DETAIL ── */
        .detail-card {
            margin: 0 16px 14px;
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .detail-title { font-size: 14px; font-weight: 800; color: var(--dark); margin: 0 0 14px; }
        .detail-row {
            display: flex; justify-content: space-between;
            font-size: 13px; color: #444; margin-bottom: 9px;
        }
        .detail-row:last-of-type { margin-bottom: 0; }
        .detail-divider { border: none; border-top: 1.5px dashed var(--border); margin: 12px 0; }
        .detail-total { display: flex; justify-content: space-between; font-size: 15px; }
        .detail-total-lbl { font-weight: 700; color: var(--dark); }
        .detail-total-val { font-weight: 800; color: var(--primary); }

        /* ── INFO BOX ── */
        .info-notice {
            margin: 0 16px 16px;
            background: #FFFBEB;
            border: 1.5px solid #FDE68A;
            border-radius: 14px;
            padding: 14px 16px;
            display: flex; gap: 12px; align-items: flex-start;
        }
        .info-notice i { color: #D97706; font-size: 18px; margin-top: 1px; flex-shrink: 0; }
        .info-notice p { margin: 0; font-size: 13px; color: #92400E; line-height: 1.55; }

        /* ── ACTION BUTTONS ── */
        .action-wrap { margin: 0 16px 24px; display: flex; flex-direction: column; gap: 10px; }

        .btn-bayar {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; border-radius: 50px; padding: 17px;
            font-size: 16px; font-weight: 800; text-decoration: none;
            box-shadow: 0 6px 20px rgba(232,64,64,0.35);
            transition: all .2s;
        }
        .btn-bayar:hover { color: white; transform: translateY(-2px); box-shadow: 0 10px 28px rgba(232,64,64,0.45); }

        .btn-tambah {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            background: white; color: var(--dark);
            border: 2px solid var(--border);
            border-radius: 50px; padding: 14px;
            font-size: 15px; font-weight: 700; text-decoration: none;
            transition: all .2s;
        }
        .btn-tambah:hover { border-color: var(--dark); color: var(--dark); background: #F8F9FA; }
    </style>
</head>
<body>

<?php if ($pesanan): ?>

<!-- Hero -->
<div class="hero">
    <div class="checkmark">✅</div>
    <h1>Pesanan Diterima!</h1>
    <p>Dapur sedang menyiapkan pesanan Anda</p>
</div>

<!-- Info Pesanan -->
<div class="order-card">
    <div class="kode-wrap">
        <p class="kode-label">Kode Pesanan</p>
        <p class="kode-val"><?= htmlspecialchars($pesanan['kode_pesanan']) ?></p>
    </div>

    <div class="info-row">
        <span class="info-lbl">Nomor Meja</span>
        <span class="info-val">Meja <?= $pesanan['nomor_meja'] ?></span>
    </div>
    <?php if (($pesanan['nama_pelanggan'] ?? '') && $pesanan['nama_pelanggan'] !== 'Pelanggan'): ?>
    <div class="info-row">
        <span class="info-lbl">Nama Pemesan</span>
        <span class="info-val" style="color:#2563EB">👤 <?= htmlspecialchars($pesanan['nama_pelanggan']) ?></span>
    </div>
    <?php endif; ?>
    <div class="info-row">
        <span class="info-lbl">Waktu Pesan</span>
        <span class="info-val"><?= date('H:i · d M Y', strtotime($pesanan['tanggal'])) ?></span>
    </div>
    <div class="info-row">
        <span class="info-lbl">Status Pesanan</span>
        <span class="pending-badge">⏳ Menunggu Diproses</span>
    </div>
    <?php if ($pesanan['catatan']): ?>
    <div class="info-row">
        <span class="info-lbl">Catatan</span>
        <span class="info-val" style="text-align:right;max-width:200px;font-size:13px">
            <?= htmlspecialchars($pesanan['catatan']) ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<!-- Detail Item -->
<div class="detail-card">
    <h3 class="detail-title">🍽️ Detail Pesanan</h3>
    <?php foreach ($details as $d): ?>
    <div class="detail-row">
        <span><?= htmlspecialchars($d['nama_menu']) ?> <span style="color:#aaa">×<?= $d['jumlah'] ?></span></span>
        <span style="font-weight:600"><?= formatRupiah($d['subtotal']) ?></span>
    </div>
    <?php endforeach; ?>
    <hr class="detail-divider">
    <div class="detail-total">
        <span class="detail-total-lbl">Total Tagihan</span>
        <span class="detail-total-val"><?= formatRupiah($pesanan['total_harga']) ?></span>
    </div>
</div>

<!-- Notice -->
<div class="info-notice">
    <i class="fas fa-info-circle"></i>
    <p>Silakan nikmati makanan Anda. Pembayaran dilakukan <strong>setelah selesai makan</strong> melalui tombol <strong>"Bayar Sekarang"</strong> di bawah, atau langsung ke kasir.</p>
</div>

<!-- ✅ HANYA 2 TOMBOL: Bayar Sekarang + Tambah Pesanan -->
<div class="action-wrap">
    <a href="payment.php?kode=<?= urlencode($pesanan['kode_pesanan']) ?>&meja=<?= $nomor_meja ?>"
       class="btn-bayar">
        <i class="fas fa-credit-card"></i>
        Bayar Sekarang · <?= formatRupiah($pesanan['total_harga']) ?>
    </a>

    <a href="index.php?meja=<?= $nomor_meja ?>" class="btn-tambah">
        <i class="fas fa-plus-circle"></i>
        Tambah Pesanan Lagi
    </a>
</div>

<?php else: ?>
<!-- Pesanan tidak ditemukan -->
<div style="text-align:center;padding:80px 30px">
    <div style="font-size:64px;margin-bottom:16px">❌</div>
    <h3 style="font-size:20px;font-weight:800;color:#1A1A2E">Pesanan Tidak Ditemukan</h3>
    <p style="color:#888;font-size:14px;margin:8px 0 24px">Kode pesanan tidak valid atau sudah kadaluarsa.</p>
    <a href="index.php<?= $nomor_meja > 0 ? '?meja='.$nomor_meja : '' ?>"
       style="display:inline-flex;align-items:center;gap:8px;background:#E84040;color:white;padding:14px 28px;border-radius:50px;font-size:14px;font-weight:700;text-decoration:none">
        <i class="fas fa-utensils"></i> Kembali ke Menu
    </a>
</div>
<?php endif; ?>

</body>
</html>
