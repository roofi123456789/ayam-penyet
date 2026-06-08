<?php
// ============================================
// payment.php - Halaman Pilih Metode Bayar
// CASH   → pergi ke kasir, dapat struk pending
// QRIS   → transfer ke 083803293430, upload bukti
// ============================================
require_once 'koneksi.php';

$kode       = sanitize($_GET['kode'] ?? '');
$nomor_meja = (int)($_GET['meja'] ?? getNomorMeja());

if (!$kode) {
    redirect('index.php' . ($nomor_meja > 0 ? '?meja='.$nomor_meja : ''));
}

$stmt = $conn->prepare("SELECT * FROM pesanan WHERE kode_pesanan = ?");
$stmt->bind_param('s', $kode);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    redirect('index.php' . ($nomor_meja > 0 ? '?meja='.$nomor_meja : ''));
}

// Sudah lunas → ke struk
if ($pesanan['status_bayar'] === 'lunas') {
    redirect("struk.php?kode=$kode&meja=$nomor_meja");
}

// QRIS sudah upload bukti → tampilkan halaman menunggu verifikasi
if ($pesanan['metode_bayar'] === 'qris' && $pesanan['status_verifikasi'] === 'menunggu') {
    redirect("menunggu_verifikasi.php?kode=$kode&meja=$nomor_meja");
}

// Ambil detail
$details = [];
$stmt2 = $conn->prepare("SELECT * FROM detail_pesanan WHERE id_pesanan = ?");
$stmt2->bind_param('i', $pesanan['id']);
$stmt2->execute();
$r = $stmt2->get_result();
while ($row = $r->fetch_assoc()) $details[] = $row;
$stmt2->close();

$total = $pesanan['total_harga'];
$NO_QRIS = '083803293430';
$NAMA_QRIS = 'Ayam Penyet Bendungan Batusangkar';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #E84040;
            --primary-dark: #C42E2E;
            --dark: #1A1A2E;
            --bg: #F5F5F5;
            --border: #E5E7EB;
            --radius: 16px;
            --shadow: 0 2px 16px rgba(0,0,0,0.08);
            --green: #16A34A;
            --green-bg: #ECFDF5;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            margin: 0;
            padding-bottom: 100px;
        }

        /* ── HEADER ── */
        .page-header {
            background: var(--dark);
            padding: 16px 20px;
            display: flex; align-items: center; gap: 14px;
            position: sticky; top: 0; z-index: 100;
        }
        .back-btn {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.1); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; text-decoration: none; font-size: 16px;
        }
        .back-btn:hover { background: rgba(255,255,255,0.2); color: white; }
        .header-title { color: white; font-size: 18px; font-weight: 800; margin: 0; flex: 1; }
        .meja-chip {
            background: rgba(255,255,255,0.15); color: white;
            border-radius: 50px; padding: 5px 12px; font-size: 12px; font-weight: 700;
        }

        /* ── SUMMARY ── */
        .summary-card {
            margin: 16px; background: white;
            border-radius: var(--radius); padding: 18px 20px;
            box-shadow: var(--shadow);
        }
        .summary-label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .5px; margin: 0 0 12px; }
        .summary-row { display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-bottom: 7px; }
        .summary-divider { border: none; border-top: 1.5px dashed var(--border); margin: 10px 0; }
        .summary-total { display: flex; justify-content: space-between; align-items: center; }
        .total-label { font-size: 15px; font-weight: 800; color: var(--dark); }
        .total-value { font-size: 24px; font-weight: 800; color: var(--primary); }

        /* ── METODE PILIHAN ── */
        .section-label {
            padding: 4px 16px 10px;
            font-size: 14px; font-weight: 800; color: var(--dark);
        }
        .metode-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 12px; padding: 0 16px 16px;
        }
        .metode-card {
            background: white; border: 2.5px solid var(--border);
            border-radius: var(--radius); padding: 20px 14px;
            text-align: center; cursor: pointer;
            transition: all 0.2s ease;
        }
        .metode-card:hover { border-color: var(--primary); background: #FFF5F5; transform: translateY(-2px); }
        .metode-card.active {
            border-color: var(--primary); background: #FFF0F0;
            box-shadow: 0 4px 14px rgba(232,64,64,0.2);
        }
        .metode-icon { font-size: 32px; margin-bottom: 10px; display: block; }
        .metode-name { font-size: 14px; font-weight: 800; color: var(--dark); }
        .metode-sub { font-size: 11px; color: #888; margin-top: 3px; }

        /* ── PANEL ── */
        .payment-panel {
            margin: 0 16px 16px; background: white;
            border-radius: var(--radius); padding: 20px;
            box-shadow: var(--shadow); display: none;
        }
        .payment-panel.show { display: block; animation: slideDown .25s ease; }
        @keyframes slideDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
        .panel-title { font-size: 15px; font-weight: 800; color: var(--dark); margin: 0 0 16px; display: flex; align-items: center; gap: 8px; }

        /* ── PANEL CASH ── */
        .cash-info-box {
            background: #FFF7ED; border: 1.5px solid #FCD34D;
            border-radius: 12px; padding: 16px 18px;
            display: flex; gap: 14px; align-items: flex-start;
        }
        .cash-info-icon { font-size: 30px; flex-shrink: 0; }
        .cash-info-title { font-size: 15px; font-weight: 800; color: #92400E; margin: 0 0 6px; }
        .cash-info-text { font-size: 13px; color: #B45309; margin: 0; line-height: 1.6; }
        .cash-steps {
            margin: 14px 0 0;
            display: flex; flex-direction: column; gap: 10px;
        }
        .cash-step {
            display: flex; align-items: flex-start; gap: 12px;
            font-size: 13px; color: #555;
        }
        .step-num {
            width: 24px; height: 24px; border-radius: 50%;
            background: var(--dark); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 800; flex-shrink: 0; margin-top: 1px;
        }
        .total-tagihan-box {
            background: var(--dark); color: white; border-radius: 12px;
            padding: 14px 18px; display: flex; justify-content: space-between;
            align-items: center; margin-top: 16px;
        }
        .ttb-label { font-size: 13px; opacity: .7; }
        .ttb-value { font-size: 22px; font-weight: 800; }

        /* ── PANEL QRIS ── */
        .qris-nomor-box {
            background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            border-radius: 14px; padding: 20px;
            text-align: center; margin-bottom: 14px;
        }
        .qris-app-icons { display: flex; justify-content: center; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
        .app-badge {
            background: rgba(255,255,255,0.1); border-radius: 50px;
            padding: 5px 12px; font-size: 11px; font-weight: 700; color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .qris-to-label { font-size: 12px; color: rgba(255,255,255,0.55); margin: 0 0 6px; }
        .qris-nomor {
            font-size: 26px; font-weight: 800; color: white;
            letter-spacing: 2px; font-family: 'Courier New', monospace;
            margin: 0 0 4px;
        }
        .qris-nama { font-size: 12px; color: rgba(255,255,255,0.6); margin: 0 0 14px; }
        .qris-amount-label { font-size: 11px; color: rgba(255,255,255,0.5); margin: 0; }
        .qris-amount { font-size: 20px; font-weight: 800; color: #FFD700; margin: 4px 0 0; }

        .copy-btn {
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25);
            color: white; border-radius: 50px; padding: 8px 16px;
            font-size: 12px; font-weight: 700; cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all .2s; margin-top: 10px;
        }
        .copy-btn:hover { background: rgba(255,255,255,0.22); }
        .copy-btn.copied { background: #16A34A; border-color: #16A34A; }

        .qris-steps {
            background: #F0FDF4; border: 1.5px solid #BBF7D0;
            border-radius: 12px; padding: 14px 16px; margin-bottom: 16px;
        }
        .qris-steps-title { font-size: 13px; font-weight: 800; color: #166534; margin: 0 0 10px; }
        .qris-step { font-size: 12px; color: #166534; margin-bottom: 6px; display: flex; gap: 8px; }
        .qris-step:last-child { margin: 0; }

        /* Upload bukti */
        .upload-label { font-size: 13px; font-weight: 700; color: var(--dark); margin: 0 0 8px; display: block; }
        .upload-box {
            border: 2.5px dashed var(--border); border-radius: 12px;
            padding: 24px; text-align: center; cursor: pointer;
            position: relative; transition: all .2s;
        }
        .upload-box:hover { border-color: var(--primary); background: #FFF5F5; }
        .upload-box.has-file { border-color: var(--green); background: var(--green-bg); border-style: solid; }
        .upload-box input {
            position: absolute; inset: 0; opacity: 0; width: 100%;
            height: 100%; cursor: pointer; font-size: 0;
        }
        .upload-icon { font-size: 32px; margin-bottom: 8px; display: block; }
        .upload-text { font-size: 13px; font-weight: 700; color: #555; margin: 0; }
        .upload-hint { font-size: 11px; color: #aaa; margin: 4px 0 0; }
        #previewBukti {
            max-width: 100%; max-height: 200px; border-radius: 10px;
            margin-top: 12px; display: none; object-fit: contain;
        }
        .file-info {
            display: none; margin-top: 10px;
            background: var(--green-bg); border-radius: 8px; padding: 8px 12px;
            font-size: 12px; font-weight: 700; color: var(--green);
            display: none; align-items: center; gap: 6px;
        }

        /* ── WARNING BOX ── */
        .warning-box {
            background: #FEF2F2; border: 1.5px solid #FCA5A5;
            border-radius: 12px; padding: 12px 16px;
            font-size: 12px; color: #991B1B; font-weight: 600;
            display: flex; gap: 8px; align-items: flex-start;
            margin-top: 14px;
        }

        /* ── FLOATING BUTTON ── */
        .pay-float {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: white; padding: 14px 16px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1); z-index: 999;
        }
        .btn-pay {
            width: 100%; background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; border: none; border-radius: 50px; padding: 16px;
            font-size: 16px; font-weight: 800; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            box-shadow: 0 6px 20px rgba(232,64,64,0.35); transition: all .2s;
        }
        .btn-pay:disabled { background: #D1D5DB; box-shadow: none; cursor: not-allowed; }
        .btn-pay:not(:disabled):hover { transform: translateY(-1px); box-shadow: 0 10px 28px rgba(232,64,64,0.45); }

        /* ── LOADING OVERLAY ── */
        .loading-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55); z-index: 9999;
            align-items: center; justify-content: center; flex-direction: column; gap: 16px;
        }
        .loading-overlay.show { display: flex; }
        .spin {
            width: 48px; height: 48px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white; border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { color: white; font-size: 14px; font-weight: 700; }
    </style>
</head>
<body>

<!-- Loading -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spin"></div>
    <div class="loading-text">Memproses...</div>
</div>

<!-- Header -->
<div class="page-header">
    <a href="status.php?kode=<?= htmlspecialchars($kode) ?>&meja=<?= $nomor_meja ?>" class="back-btn">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="header-title">💳 Pilih Cara Bayar</h1>
    <span class="meja-chip">Meja <?= $nomor_meja ?></span>
</div>

<!-- Ringkasan Pesanan -->
<div class="summary-card">
    <p class="summary-label">
        Ringkasan · <?= htmlspecialchars($kode) ?>
        <?php if(($pesanan['nama_pelanggan']??'') && $pesanan['nama_pelanggan']!=='Pelanggan'): ?>
        <span style="margin-left:6px;background:#EFF6FF;color:#2563EB;border-radius:50px;padding:2px 8px;font-size:10px;font-weight:700">
            👤 <?= htmlspecialchars($pesanan['nama_pelanggan']) ?>
        </span>
        <?php endif; ?>
    </p>
    <?php foreach ($details as $d): ?>
    <div class="summary-row">
        <span><?= htmlspecialchars($d['nama_menu']) ?> ×<?= $d['jumlah'] ?></span>
        <span style="font-weight:600"><?= formatRupiah($d['subtotal']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php if ($pesanan['catatan']): ?>
    <div style="background:#FFF7ED;border-radius:8px;padding:7px 12px;font-size:12px;color:#92400E;margin-bottom:6px">
        📝 <?= htmlspecialchars($pesanan['catatan']) ?>
    </div>
    <?php endif; ?>
    <hr class="summary-divider">
    <div class="summary-total">
        <span class="total-label">Total Tagihan</span>
        <span class="total-value"><?= formatRupiah($total) ?></span>
    </div>
</div>

<!-- Pilih Metode -->
<div class="section-label">Pilih Metode Pembayaran</div>

<div class="metode-grid">
    <div class="metode-card" id="card-cash" onclick="pilih('cash')">
        <span class="metode-icon">💵</span>
        <div class="metode-name">Tunai</div>
        <div class="metode-sub">Bayar di Kasir</div>
    </div>
    <div class="metode-card" id="card-qris" onclick="pilih('qris')">
        <span class="metode-icon">📱</span>
        <div class="metode-name">QRIS / GoPay</div>
        <div class="metode-sub">Transfer ke No. HP</div>
    </div>
</div>

<!-- ══ PANEL CASH ══ -->
<div class="payment-panel" id="panel-cash">
    <div class="panel-title">💵 Pembayaran Tunai</div>

    <div class="cash-info-box">
        <div class="cash-info-icon">🏪</div>
        <div>
            <p class="cash-info-title">Bayar Langsung di Kasir</p>
            <p class="cash-info-text">Silakan menuju kasir dengan membawa kode pesanan Anda. Kasir akan membantu proses pembayaran.</p>
        </div>
    </div>

    <div class="cash-steps">
        <div class="cash-step">
            <div class="step-num">1</div>
            <span>Klik tombol <strong>"Konfirmasi ke Kasir"</strong> di bawah</span>
        </div>
        <div class="cash-step">
            <div class="step-num">2</div>
            <span>Tunjukkan <strong>kode pesanan</strong> atau nomor meja ke kasir</span>
        </div>
        <div class="cash-step">
            <div class="step-num">3</div>
            <span>Kasir akan memproses pembayaran dan memberikan <strong>struk</strong></span>
        </div>
        <div class="cash-step">
            <div class="step-num">4</div>
            <span>Anda juga bisa <strong>download struk digital</strong> setelah kasir konfirmasi</span>
        </div>
    </div>

    <div class="total-tagihan-box">
        <span class="ttb-label">Yang harus dibayar</span>
        <span class="ttb-value"><?= formatRupiah($total) ?></span>
    </div>

    <!-- Kode untuk ditunjukkan ke kasir -->
    <div style="margin-top:14px;background:#F8F9FA;border-radius:12px;padding:14px 18px;text-align:center">
        <div style="font-size:11px;color:#888;margin-bottom:6px">KODE PESANAN ANDA</div>
        <div style="font-size:20px;font-weight:800;color:var(--primary);letter-spacing:2px;font-family:monospace">
            <?= htmlspecialchars($kode) ?>
        </div>
        <div style="font-size:12px;color:#888;margin-top:4px">Tunjukkan kode ini ke kasir</div>
    </div>
</div>

<!-- ══ PANEL QRIS ══ -->
<div class="payment-panel" id="panel-qris">
    <div class="panel-title">📱 Transfer via GoPay / QRIS</div>

    <!-- Nomor Tujuan -->
    <div class="qris-nomor-box">
        <div class="qris-app-icons">
            <span class="app-badge">GoPay</span>
            <span class="app-badge">OVO</span>
            <span class="app-badge">DANA</span>
            <span class="app-badge">ShopeePay</span>
            <span class="app-badge">LinkAja</span>
        </div>
        <div class="qris-to-label">Transfer ke Nomor</div>
        <div class="qris-nomor" id="displayNomor"><?= $NO_QRIS ?></div>
        <div class="qris-nama">a.n. <?= $NAMA_QRIS ?></div>
        <button class="copy-btn" onclick="copyNomor()" id="copyNomorBtn">
            <i class="fas fa-copy"></i> Salin Nomor
        </button>
        <div class="qris-amount-label">Jumlah yang harus ditransfer</div>
        <div class="qris-amount"><?= formatRupiah($total) ?></div>
        <button class="copy-btn" onclick="copyJumlah()" id="copyJumlahBtn" style="margin-top:6px">
            <i class="fas fa-copy"></i> Salin Jumlah
        </button>
    </div>

    <!-- Langkah-langkah -->
    <div class="qris-steps">
        <div class="qris-steps-title">📋 Cara Transfer:</div>
        <div class="qris-step"><span>1️⃣</span><span>Buka aplikasi GoPay / dompet digital Anda</span></div>
        <div class="qris-step"><span>2️⃣</span><span>Pilih <strong>Kirim / Transfer</strong> ke sesama atau nomor HP</span></div>
        <div class="qris-step"><span>3️⃣</span><span>Masukkan nomor <strong><?= $NO_QRIS ?></strong></span></div>
        <div class="qris-step"><span>4️⃣</span><span>Masukkan jumlah <strong><?= formatRupiah($total) ?></strong> (harus tepat)</span></div>
        <div class="qris-step"><span>5️⃣</span><span>Screenshot bukti transfer → upload di bawah ini</span></div>
    </div>

    <!-- Upload Bukti -->
    <label class="upload-label">📸 Upload Bukti Transfer *</label>
    <div class="upload-box" id="uploadBox">
        <input type="file" accept="image/*" id="buktiFoto" onchange="previewBukti(event)" capture="environment">
        <span class="upload-icon" id="uploadIcon">📤</span>
        <p class="upload-text" id="uploadText">Tap untuk pilih atau foto bukti</p>
        <p class="upload-hint">JPG, PNG – Maks. 5MB</p>
        <img id="previewBukti" alt="Preview bukti transfer">
    </div>
    <div class="file-info" id="fileInfo">
        <i class="fas fa-check-circle"></i>
        <span id="fileInfoText"></span>
    </div>

    <div class="warning-box">
        <i class="fas fa-exclamation-triangle" style="margin-top:1px;flex-shrink:0"></i>
        <span>Pastikan jumlah transfer <strong>tepat <?= formatRupiah($total) ?></strong>. Bukti transfer wajib diupload untuk verifikasi. Admin akan memverifikasi dalam beberapa menit.</span>
    </div>
</div>

<!-- Floating Button -->
<div class="pay-float">
    <button class="btn-pay" id="btnPay" disabled onclick="proses()">
        <i class="fas fa-lock"></i>
        <span id="btnText">Pilih Metode Pembayaran</span>
    </button>
</div>

<script>
const KODE  = '<?= htmlspecialchars($kode) ?>';
const MEJA  = <?= $nomor_meja ?>;
const TOTAL = <?= $total ?>;
const NO_QRIS = '<?= $NO_QRIS ?>';

let metode    = null;
let fileBase64 = null;
let fileName   = null;

// ── PILIH METODE ──
function pilih(m) {
    metode = m;
    document.querySelectorAll('.metode-card').forEach(c => c.classList.remove('active'));
    document.getElementById('card-' + m).classList.add('active');
    document.querySelectorAll('.payment-panel').forEach(p => p.classList.remove('show'));
    document.getElementById('panel-' + m).classList.add('show');

    const btn     = document.getElementById('btnPay');
    const btnText = document.getElementById('btnText');

    if (m === 'cash') {
        btn.disabled = false;
        btnText.innerHTML = '<i class="fas fa-store me-2"></i>Konfirmasi ke Kasir';
    } else if (m === 'qris') {
        btn.disabled = true;
        btnText.innerHTML = '<i class="fas fa-upload me-2"></i>Upload Bukti Dulu';
    }
}

// ── COPY NOMOR ──
function copyNomor() {
    navigator.clipboard.writeText(NO_QRIS).catch(() => fallbackCopy(NO_QRIS));
    const btn = document.getElementById('copyNomorBtn');
    btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
    btn.classList.add('copied');
    setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> Salin Nomor'; btn.classList.remove('copied'); }, 2500);
}

function copyJumlah() {
    navigator.clipboard.writeText(String(TOTAL)).catch(() => fallbackCopy(String(TOTAL)));
    const btn = document.getElementById('copyJumlahBtn');
    btn.innerHTML = '<i class="fas fa-check"></i> Tersalin!';
    btn.classList.add('copied');
    setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> Salin Jumlah'; btn.classList.remove('copied'); }, 2500);
}

function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text; document.body.appendChild(ta);
    ta.select(); document.execCommand('copy');
    document.body.removeChild(ta);
}

// ── PREVIEW BUKTI ──
function previewBukti(e) {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        alert('❌ Ukuran file terlalu besar! Maksimal 5MB');
        e.target.value = '';
        return;
    }

    if (!file.type.startsWith('image/')) {
        alert('❌ File harus berupa gambar (JPG/PNG)');
        e.target.value = '';
        return;
    }

    fileName = file.name;

    const reader = new FileReader();
    reader.onload = ev => {
        fileBase64 = ev.target.result; // data:image/jpeg;base64,...

        // Preview
        const img = document.getElementById('previewBukti');
        img.src = fileBase64;
        img.style.display = 'block';

        // Update upload box state
        document.getElementById('uploadBox').classList.add('has-file');
        document.getElementById('uploadIcon').textContent = '✅';
        document.getElementById('uploadText').textContent = 'Bukti terpilih';

        // File info
        const fi = document.getElementById('fileInfo');
        fi.style.display = 'flex';
        document.getElementById('fileInfoText').textContent =
            file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';

        // Enable button
        document.getElementById('btnPay').disabled = false;
        document.getElementById('btnText').innerHTML =
            '<i class="fas fa-paper-plane me-2"></i>Kirim Bukti Transfer';
    };
    reader.readAsDataURL(file);
}

// ── PROSES BAYAR ──
function proses() {
    if (!metode) { showToast('Pilih metode dulu!', 'error'); return; }

    if (metode === 'cash') {
        // Cash: langsung set metode, tampilkan konfirmasi, arahkan ke halaman tunggu kasir
        if (!confirm('Konfirmasi bayar tunai di kasir?\nTotal: ' + formatRp(TOTAL))) return;
        document.getElementById('loadingOverlay').classList.add('show');

        fetch('api/bayar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=set_cash&kode=' + encodeURIComponent(KODE)
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('loadingOverlay').classList.remove('show');
            if (data.success) {
                window.location.href = 'tunggu_kasir.php?kode=' + KODE + '&meja=' + MEJA;
            } else {
                showToast('❌ ' + (data.msg || 'Gagal'), 'error');
            }
        })
        .catch(() => {
            document.getElementById('loadingOverlay').classList.remove('show');
            showToast('❌ Kesalahan koneksi', 'error');
        });

    } else if (metode === 'qris') {
        // QRIS: validasi ada bukti
        if (!fileBase64) { showToast('Upload bukti transfer dulu!', 'error'); return; }
        if (!confirm('Kirim bukti transfer untuk diverifikasi admin?')) return;

        document.getElementById('loadingOverlay').classList.add('show');
        document.getElementById('loadingOverlay').querySelector('.loading-text').textContent = 'Mengunggah bukti...';

        // Ambil file asli dari input
        const fileInput = document.getElementById('buktiFoto');
        const file = fileInput.files[0];
        if (!file) { showToast('File tidak ditemukan!', 'error'); return; }

        const formData = new FormData();
        formData.append('action', 'upload_qris');
        formData.append('kode', KODE);
        formData.append('bukti_file', file, file.name);

        fetch('api/bayar.php', {
            method: 'POST',
            body: formData   // FormData - no Content-Type header, browser sets it with boundary
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('loadingOverlay').classList.remove('show');
            if (data.success) {
                window.location.href = 'menunggu_verifikasi.php?kode=' + KODE + '&meja=' + MEJA;
            } else {
                showToast('❌ ' + (data.msg || 'Gagal upload'), 'error');
            }
        })
        .catch(() => {
            document.getElementById('loadingOverlay').classList.remove('show');
            showToast('❌ Kesalahan koneksi', 'error');
        });
    }
}

function formatRp(n) { return 'Rp ' + parseInt(n).toLocaleString('id-ID'); }

function showToast(msg, type='success') {
    const colors = { success:'#1A1A2E', error:'#E84040', info:'#3B82F6' };
    const div = document.createElement('div');
    div.style.cssText = `position:fixed;top:16px;left:50%;transform:translateX(-50%);
        background:${colors[type]||colors.success};color:white;padding:12px 20px;
        border-radius:50px;font-size:13px;font-weight:700;z-index:99999;
        white-space:nowrap;box-shadow:0 8px 24px rgba(0,0,0,.25);
        font-family:'Plus Jakarta Sans',sans-serif;`;
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(() => { div.style.opacity='0'; div.style.transition='opacity .3s'; setTimeout(()=>div.remove(),300); }, 2800);
}
</script>
</body>
</html>
