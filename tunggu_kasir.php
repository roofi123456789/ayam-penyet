<?php
// ============================================
// tunggu_kasir.php
// Customer melihat instruksi bayar ke kasir
// + polling status (auto redirect saat lunas)
// ============================================
require_once 'koneksi.php';

$kode       = sanitize($_GET['kode'] ?? '');
$nomor_meja = (int)($_GET['meja'] ?? getNomorMeja());

if (!$kode) redirect('index.php');

$stmt = $conn->prepare("SELECT * FROM pesanan WHERE kode_pesanan=?");
$stmt->bind_param('s', $kode);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) redirect('index.php');
if ($pesanan['status_bayar'] === 'lunas') redirect("struk.php?kode=$kode&meja=$nomor_meja");

$details = [];
$s2 = $conn->prepare("SELECT * FROM detail_pesanan WHERE id_pesanan=?");
$s2->bind_param('i', $pesanan['id']);
$s2->execute();
$r = $s2->get_result();
while ($row = $r->fetch_assoc()) $details[] = $row;
$s2->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menunggu Kasir - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#E84040;--dark:#1A1A2E;--bg:#F5F5F5;}
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);margin:0;min-height:100vh;}

        .header{background:var(--dark);padding:16px 20px;display:flex;align-items:center;gap:14px;}
        .back{width:36px;height:36px;background:rgba(255,255,255,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;text-decoration:none;}
        .back:hover{background:rgba(255,255,255,0.2);color:white;}
        .htitle{color:white;font-size:17px;font-weight:800;margin:0;flex:1;}

        .hero{background:linear-gradient(135deg,#FF8C00,#F59E0B);padding:36px 24px;text-align:center;position:relative;overflow:hidden;}
        .hero::before{content:'';position:absolute;top:-40px;right:-40px;width:140px;height:140px;background:rgba(255,255,255,0.1);border-radius:50%;}
        .cashier-icon{font-size:60px;margin-bottom:14px;animation:bounce 2s ease-in-out infinite;}
        @keyframes bounce{0%,100%{transform:translateY(0);}50%{transform:translateY(-10px);}}
        .hero h2{font-size:22px;font-weight:800;color:white;margin:0 0 6px;}
        .hero p{color:rgba(255,255,255,0.85);font-size:14px;margin:0;}

        .card-box{background:white;border-radius:16px;margin:16px;padding:20px;box-shadow:0 2px 16px rgba(0,0,0,0.07);}
        .card-title{font-size:14px;font-weight:800;color:var(--dark);margin:0 0 14px;}

        /* Kode pesanan besar */
        .kode-display{text-align:center;background:#1A1A2E;border-radius:14px;padding:20px;margin-bottom:14px;}
        .kode-lbl{font-size:11px;color:rgba(255,255,255,0.5);margin:0 0 8px;letter-spacing:1px;text-transform:uppercase;}
        .kode-val{font-size:26px;font-weight:800;color:white;letter-spacing:3px;font-family:monospace;margin:0 0 8px;}
        .meja-val{font-size:14px;color:rgba(255,255,255,0.6);margin:0;}

        /* Total */
        .total-row{display:flex;justify-content:space-between;align-items:center;background:#FFF7ED;border-radius:12px;padding:14px 16px;border:1.5px solid #FCD34D;}
        .total-lbl{font-size:14px;font-weight:700;color:#92400E;}
        .total-val{font-size:22px;font-weight:800;color:#B45309;}

        /* Steps */
        .steps{display:flex;flex-direction:column;gap:12px;}
        .step{display:flex;gap:12px;align-items:flex-start;}
        .step-icon{width:36px;height:36px;border-radius:10px;background:var(--dark);color:white;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
        .step-text{font-size:13px;color:#555;padding-top:8px;}
        .step-text strong{color:var(--dark);}

        /* Rincian */
        .item-row{display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;color:#444;}
        .item-row span:last-child{font-weight:700;}
        .total-rincian{display:flex;justify-content:space-between;font-size:15px;font-weight:800;padding-top:10px;border-top:1.5px dashed #E5E7EB;margin-top:6px;color:var(--dark);}

        /* Status indicator */
        .status-bar{background:white;border-radius:16px;margin:0 16px 16px;padding:16px 18px;box-shadow:0 2px 12px rgba(0,0,0,0.06);display:flex;align-items:center;gap:12px;}
        .pulse{width:12px;height:12px;border-radius:50%;background:#F59E0B;flex-shrink:0;animation:puls 1.5s ease-in-out infinite;}
        @keyframes puls{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.4;transform:scale(.7);}}
        .status-text{font-size:13px;font-weight:700;color:var(--dark);}
        .status-sub{font-size:11px;color:#888;margin-top:2px;}
        .timer{margin-left:auto;font-size:12px;color:#888;font-family:monospace;}
    </style>
</head>
<body>

<div class="header">
    <a href="payment.php?kode=<?= urlencode($kode) ?>&meja=<?= $nomor_meja ?>" class="back">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="htitle">Menunggu Kasir</h1>
</div>

<div class="hero">
    <div class="cashier-icon">🏪</div>
    <h2>Silakan ke Kasir</h2>
    <p>Tunjukkan kode pesanan di bawah ini kepada kasir</p>
</div>

<!-- Kode Pesanan -->
<div class="card-box">
    <div class="kode-display">
        <p class="kode-lbl">Kode Pesanan</p>
        <p class="kode-val"><?= htmlspecialchars($kode) ?></p>
        <p class="meja-val">Meja <?= $nomor_meja ?></p>
        <?php if(($pesanan['nama_pelanggan']??'') && $pesanan['nama_pelanggan']!=='Pelanggan'): ?>
        <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:4px 0 0;font-weight:600">
            👤 <?= htmlspecialchars($pesanan['nama_pelanggan']) ?>
        </p>
        <?php endif; ?>
    </div>
    <div class="total-row">
        <span class="total-lbl">💰 Total yang harus dibayar</span>
        <span class="total-val"><?= formatRupiah($pesanan['total_harga']) ?></span>
    </div>
</div>

<!-- Status Polling -->
<div class="status-bar">
    <div class="pulse" id="pulseEl"></div>
    <div>
        <div class="status-text" id="statusText">Menunggu konfirmasi kasir...</div>
        <div class="status-sub">Otomatis update setiap 5 detik</div>
    </div>
    <div class="timer" id="timerEl">5s</div>
</div>

<!-- Langkah -->
<div class="card-box">
    <div class="card-title">📋 Langkah Pembayaran</div>
    <div class="steps">
        <div class="step">
            <div class="step-icon">1️⃣</div>
            <div class="step-text"><strong>Pergi ke kasir</strong> di area restoran</div>
        </div>
        <div class="step">
            <div class="step-icon">📱</div>
            <div class="step-text">Tunjukkan <strong>kode pesanan</strong> atau sebutkan <strong>Meja <?= $nomor_meja ?></strong></div>
        </div>
        <div class="step">
            <div class="step-icon">💵</div>
            <div class="step-text">Bayar tunai sebesar <strong><?= formatRupiah($pesanan['total_harga']) ?></strong> ke kasir</div>
        </div>
        <div class="step">
            <div class="step-icon">🧾</div>
            <div class="step-text">Kasir akan konfirmasi dan <strong>struk digital</strong> otomatis muncul di sini</div>
        </div>
    </div>
</div>

<!-- Rincian Pesanan -->
<div class="card-box">
    <div class="card-title">🍽️ Rincian Pesanan</div>
    <?php foreach ($details as $d): ?>
    <div class="item-row">
        <span><?= htmlspecialchars($d['nama_menu']) ?> ×<?= $d['jumlah'] ?></span>
        <span><?= formatRupiah($d['subtotal']) ?></span>
    </div>
    <?php endforeach; ?>
    <div class="total-rincian">
        <span>Total</span>
        <span><?= formatRupiah($pesanan['total_harga']) ?></span>
    </div>
</div>

<script>
const KODE = '<?= htmlspecialchars($kode) ?>';
const MEJA = <?= $nomor_meja ?>;
let countdown = 5;

function cekStatus() {
    fetch('api/bayar.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=cek_status&kode=' + encodeURIComponent(KODE)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const d = data.data;
        if (d.status_bayar === 'lunas') {
            document.getElementById('statusText').textContent = '✅ Pembayaran dikonfirmasi!';
            document.getElementById('pulseEl').style.background = '#22C55E';
            setTimeout(() => window.location.href = 'struk.php?kode=' + KODE + '&meja=' + MEJA, 1000);
        } else if (d.status_verifikasi === 'ditolak') {
            document.getElementById('statusText').textContent = '❌ Ada masalah – hubungi kasir';
        }
    })
    .catch(() => {});
}

// Countdown dan polling
setInterval(() => {
    countdown--;
    document.getElementById('timerEl').textContent = countdown + 's';
    if (countdown <= 0) {
        countdown = 5;
        cekStatus();
    }
}, 1000);

// Cek langsung saat load
cekStatus();
</script>
</body>
</html>
