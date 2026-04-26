<?php
// ============================================
// menunggu_verifikasi.php
// Customer menunggu admin verifikasi bukti QRIS
// ============================================
require_once 'koneksi.php';

$kode       = sanitize($_GET['kode'] ?? '');
$nomor_meja = (int)($_GET['meja'] ?? getNomorMeja());

if (!$kode) redirect('index.php');

$stmt = $conn->prepare("SELECT * FROM pesanan WHERE kode_pesanan=?");
$stmt->bind_param('s', $kode);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$p) redirect('index.php');
if ($p['status_bayar'] === 'lunas') redirect("struk.php?kode=$kode&meja=$nomor_meja");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menunggu Verifikasi - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#E84040;--dark:#1A1A2E;--bg:#F5F5F5;--blue:#3B82F6;}
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);margin:0;min-height:100vh;padding-bottom:30px;}

        .header{background:var(--dark);padding:16px 20px;display:flex;align-items:center;gap:14px;}
        .htitle{color:white;font-size:17px;font-weight:800;margin:0;}

        .hero{background:linear-gradient(135deg,var(--blue) 0%,#1D4ED8 100%);padding:40px 24px 36px;text-align:center;position:relative;overflow:hidden;}
        .hero::before{content:'';position:absolute;top:-40px;right:-40px;width:140px;height:140px;background:rgba(255,255,255,0.08);border-radius:50%;}
        .spinner-wrap{width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;border:3px solid rgba(255,255,255,0.3);}
        .spinner-icon{font-size:36px;animation:spin 3s linear infinite;}
        @keyframes spin{to{transform:rotate(360deg);}}
        .hero h2{font-size:22px;font-weight:800;color:white;margin:0 0 6px;}
        .hero p{color:rgba(255,255,255,0.8);font-size:14px;margin:0;}

        .status-card{background:white;border-radius:16px;margin:16px;padding:20px;box-shadow:0 2px 16px rgba(0,0,0,0.07);}
        .status-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #F5F5F5;}
        .status-row:last-child{border:none;margin:0;padding:0;}
        .srl{font-size:12px;color:#888;}
        .srv{font-size:14px;font-weight:700;color:var(--dark);}
        .badge-menunggu{background:#EFF6FF;color:#2563EB;border-radius:50px;padding:4px 12px;font-size:12px;font-weight:700;display:inline-block;}
        .badge-ditolak{background:#FEF2F2;color:#DC2626;border-radius:50px;padding:4px 12px;font-size:12px;font-weight:700;display:inline-block;}
        .badge-lunas{background:#ECFDF5;color:#16A34A;border-radius:50px;padding:4px 12px;font-size:12px;font-weight:700;display:inline-block;}

        .info-card{background:white;border-radius:16px;margin:0 16px 14px;padding:18px;box-shadow:0 2px 12px rgba(0,0,0,0.06);}
        .info-title{font-size:14px;font-weight:800;color:var(--dark);margin:0 0 12px;}
        .info-item{display:flex;gap:10px;font-size:13px;color:#555;margin-bottom:10px;align-items:flex-start;}
        .info-item:last-child{margin:0;}
        .iico{font-size:16px;flex-shrink:0;margin-top:1px;}

        .refresh-bar{display:flex;align-items:center;gap:10px;margin:0 16px 14px;background:white;border-radius:12px;padding:12px 16px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
        .pulse{width:10px;height:10px;border-radius:50%;background:var(--blue);flex-shrink:0;animation:p 1.5s ease-in-out infinite;}
        @keyframes p{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.4;transform:scale(.7);}}
        .refresh-text{font-size:13px;font-weight:700;color:var(--dark);}
        .timer{margin-left:auto;font-family:monospace;font-size:13px;color:#888;}

        /* Jika ditolak */
        .reject-card{background:#FEF2F2;border:1.5px solid #FCA5A5;border-radius:16px;margin:0 16px 14px;padding:18px;}
        .reject-title{font-size:15px;font-weight:800;color:#991B1B;margin:0 0 8px;}
        .reject-text{font-size:13px;color:#B91C1C;margin:0 0 14px;line-height:1.6;}
        .btn-retry{background:var(--primary);color:white;border:none;border-radius:50px;padding:13px 28px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}

        .btn-tambah{display:block;margin:0 16px;background:var(--primary);color:white;text-align:center;border-radius:50px;padding:14px;font-size:14px;font-weight:700;text-decoration:none;box-shadow:0 4px 14px rgba(232,64,64,0.3);}
    </style>
</head>
<body>

<div class="header">
    <h1 class="htitle">📱 Verifikasi Transfer</h1>
</div>

<div class="hero">
    <div class="spinner-wrap"><span class="spinner-icon">🔍</span></div>
    <h2>Bukti Sedang Diverifikasi</h2>
    <p>Admin sedang mengecek transfer Anda</p>
</div>

<!-- Status -->
<div class="status-card">
    <div class="status-row">
        <span class="srl">Kode Pesanan</span>
        <span class="srv" style="font-family:monospace"><?= htmlspecialchars($kode) ?></span>
    </div>
    <div class="status-row">
        <span class="srl">Meja</span>
        <span class="srv">Meja <?= $nomor_meja ?></span>
    </div>
    <?php if(($p['nama_pelanggan']??'') && $p['nama_pelanggan']!=='Pelanggan'): ?>
    <div class="status-row">
        <span class="srl">Nama Pemesan</span>
        <span class="srv" style="color:#2563EB">👤 <?= htmlspecialchars($p['nama_pelanggan']) ?></span>
    </div>
    <?php endif; ?>
    <div class="status-row">
        <span class="srl">Jumlah Transfer</span>
        <span class="srv" style="color:var(--primary)"><?= formatRupiah($p['total_harga']) ?></span>
    </div>
    <div class="status-row">
        <span class="srl">Nomor Tujuan</span>
        <span class="srv">083803293430</span>
    </div>
    <div class="status-row">
        <span class="srl">Status</span>
        <span class="badge-menunggu" id="statusBadge">⏳ Menunggu Verifikasi</span>
    </div>
</div>

<!-- Polling indicator -->
<div class="refresh-bar">
    <div class="pulse"></div>
    <div class="refresh-text" id="refreshText">Mengecek status...</div>
    <div class="timer" id="timerEl">10s</div>
</div>

<!-- Ditolak section (hidden by default) -->
<div class="reject-card" id="rejectCard" style="display:none">
    <div class="reject-title">❌ Bukti Transfer Ditolak</div>
    <p class="reject-text">Maaf, admin tidak dapat memverifikasi bukti transfer Anda. Pastikan screenshot jelas, nominal tepat, dan tujuan transfer benar ke <strong>083803293430</strong>.</p>
    <a href="payment.php?kode=<?= urlencode($kode) ?>&meja=<?= $nomor_meja ?>" class="btn-retry">
        <i class="fas fa-redo"></i> Upload Ulang Bukti
    </a>
</div>

<!-- Info -->
<div class="info-card" id="infoCard">
    <div class="info-title">ℹ️ Informasi</div>
    <div class="info-item">
        <span class="iico">⏱️</span>
        <span>Verifikasi biasanya selesai dalam <strong>1–5 menit</strong></span>
    </div>
    <div class="info-item">
        <span class="iico">📲</span>
        <span>Halaman ini otomatis update. <strong>Jangan tutup</strong> halaman ini</span>
    </div>
    <div class="info-item">
        <span class="iico">✅</span>
        <span>Setelah terverifikasi, <strong>struk otomatis muncul</strong></span>
    </div>
    <div class="info-item">
        <span class="iico">❓</span>
        <span>Butuh bantuan? Hubungi kasir di area restoran</span>
    </div>
</div>

<a href="index.php?meja=<?= $nomor_meja ?>" class="btn-tambah">
    <i class="fas fa-home me-2"></i> Kembali ke Menu
</a>

<script>
const KODE = '<?= htmlspecialchars($kode) ?>';
const MEJA = <?= $nomor_meja ?>;
let countdown = 10;
let checking = false;

function cekStatus() {
    if (checking) return;
    checking = true;
    document.getElementById('refreshText').textContent = 'Mengecek...';

    fetch('api/bayar.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=cek_status&kode=' + encodeURIComponent(KODE)
    })
    .then(r => r.json())
    .then(data => {
        checking = false;
        document.getElementById('refreshText').textContent = 'Mengecek status...';
        if (!data.success) return;
        const d = data.data;

        if (d.status_bayar === 'lunas') {
            document.getElementById('statusBadge').textContent = '✅ Terverifikasi!';
            document.getElementById('statusBadge').className = 'badge-lunas';
            document.getElementById('refreshText').textContent = '✅ Berhasil! Mengalihkan ke struk...';
            setTimeout(() => window.location.href = 'struk.php?kode=' + KODE + '&meja=' + MEJA, 1500);

        } else if (d.status_verifikasi === 'ditolak') {
            document.getElementById('statusBadge').textContent = '❌ Ditolak';
            document.getElementById('statusBadge').className = 'badge-ditolak';
            document.getElementById('rejectCard').style.display = 'block';
            document.getElementById('infoCard').style.display = 'none';
            document.getElementById('refreshText').textContent = 'Bukti ditolak oleh admin';
            clearInterval(interval);
        }
    })
    .catch(() => { checking = false; });
}

const interval = setInterval(() => {
    countdown--;
    document.getElementById('timerEl').textContent = countdown + 's';
    if (countdown <= 0) {
        countdown = 10;
        cekStatus();
    }
}, 1000);

cekStatus();
</script>
</body>
</html>
