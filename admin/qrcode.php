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

$meja_list = [];
$res = $conn->query("SELECT * FROM meja ORDER BY nomor_meja ASC");
while ($row = $res->fetch_assoc()) $meja_list[] = $row;

// Ambil base URL - support ngrok & lokal
// Cek apakah ada URL ngrok yang disimpan di file konfigurasi
$config_file = __DIR__ . '/../ngrok_url.txt';
$saved_url = '';
if (file_exists($config_file)) {
    $saved_url = trim(file_get_contents($config_file));
}

// Deteksi otomatis: kalau diakses via ngrok, pakai URL ngrok
$current_host = $_SERVER['HTTP_HOST'] ?? '';
$current_scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$auto_base_url = $current_scheme . '://' . $current_host;

// Prioritas: saved ngrok URL > auto detect > fallback lokal
if (!empty($saved_url)) {
    $base_url = rtrim($saved_url, '/');
} else {
    $base_url = $auto_base_url;
}

// Untuk kompatibilitas lama
$server_ip = $current_host;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <!-- QR Code library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root { --primary: #E84040; --primary-dark: #C42E2E; --dark: #1A1A2E; --bg: #F0F2F5; --border: #E5E7EB; --radius: 14px; --shadow: 0 2px 16px rgba(0,0,0,0.07); }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); margin: 0; }
        .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: #1A1A2E; z-index: 1000; display: flex; flex-direction: column; overflow-y: auto; }
        .sidebar-logo { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar-logo h2 { font-size: 15px; font-weight: 800; color: white; margin: 0; }
        .sidebar-logo p { font-size: 11px; color: rgba(255,255,255,0.45); margin: 3px 0 0; }
        .nav-section { padding: 16px 12px 8px; }
        .nav-section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.3); padding: 0 8px; margin-bottom: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.6); transition: all 0.2s; margin-bottom: 2px; }
        .nav-item a:hover { background: rgba(255,255,255,0.08); color: white; }
        .nav-item a.active { background: var(--primary); color: white; }
        .nav-item a i { width: 18px; text-align: center; }
        .sidebar-footer { margin-top: auto; padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.08); }
        .btn-logout { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; color: rgba(255,255,255,0.5); text-decoration: none; font-size: 12px; font-weight: 600; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(255,255,255,0.08); color: #FF8A8A; }
        .main-content { margin-left: 240px; }
        .topbar { background: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .topbar h1 { font-size: 20px; font-weight: 800; color: var(--dark); margin: 0; }
        .page-body { padding: 24px; }

        .ip-config-card {
            background: #1A1A2E;
            border-radius: var(--radius);
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .ip-config-card label { color: rgba(255,255,255,0.6); font-size: 13px; font-weight: 600; white-space: nowrap; }
        .ip-input { background: rgba(255,255,255,0.1); border: 1.5px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 9px 14px; font-size: 14px; color: white; font-family: 'Plus Jakarta Sans', sans-serif; outline: none; width: 200px; }
        .ip-input:focus { border-color: var(--primary); }
        .btn-apply { background: var(--primary); color: white; border: none; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif; }
        .btn-apply:hover { background: var(--primary-dark); }
        .current-url { color: rgba(255,255,255,0.45); font-size: 12px; font-family: monospace; }

        .qr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }

        .qr-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }
        .qr-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,0.12); }

        .qr-card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 14px;
            text-align: center;
        }
        .qr-card-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            color: white;
            margin: 0 0 2px;
        }
        .qr-card-header p { font-size: 11px; color: rgba(255,255,255,0.7); margin: 0; }

        .qr-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .qr-container {
            background: white;
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: 10px;
            margin-bottom: 12px;
        }
        .qr-container canvas { display: block !important; }
        .qr-container img { display: none !important; }
        .qr-url { font-size: 10px; color: #888; text-align: center; word-break: break-all; margin-bottom: 12px; font-family: monospace; }
        
        .btn-download {
            width: 100%;
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--dark);
        }
        .btn-download:hover { background: var(--primary); border-color: var(--primary); color: white; }

        /* Print styles */
        @media print {
            .sidebar, .topbar, .ip-config-card, .btn-download { display: none; }
            .main-content { margin: 0; }
            .page-body { padding: 0; }
            .qr-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .qr-card { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-logo">
        <div style="font-size:28px;margin-bottom:8px">🍗</div>
        <h2>Ayam Penyet</h2>
        <p>Bendungan Batusangkar</p>
    </div>
    <div class="nav-section">
        <div class="nav-section-label">Menu Utama</div>
        <div class="nav-item"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-item"><a href="konfirmasi_bayar.php"><i class="fas fa-cash-register"></i> Konfirmasi Bayar</a></div>
        <div class="nav-item"><a href="menu.php"><i class="fas fa-utensils"></i> Kelola Menu</a></div>
        <div class="nav-item"><a href="kategori.php"><i class="fas fa-tags"></i> Kategori</a></div>
        <div class="nav-item"><a href="kitchen.php"><i class="fas fa-tv"></i> Kitchen Display</a></div>
        <div class="nav-item"><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></div>
        <div class="nav-item"><a href="qrcode.php" class="active"><i class="fas fa-qrcode"></i> QR Code</a></div>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <h1>📱 QR Code</h1>
        <button onclick="window.print()" style="background:#1A1A2E;color:white;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px">
            <i class="fas fa-print"></i> Cetak Semua
        </button>
    </div>

    <div class="page-body">
        <!-- URL Config -->
        <div class="ip-config-card" style="flex-direction:column;align-items:flex-start;gap:12px">
            <div style="display:flex;align-items:center;gap:10px;width:100%;flex-wrap:wrap">
                <label><i class="fas fa-globe me-2"></i>Base URL (Ngrok / Lokal):</label>
                <input type="text" class="ip-input" id="urlInput" value="<?= htmlspecialchars($base_url) ?>" placeholder="https://xxx.ngrok-free.app" style="width:320px">
                <button class="btn-apply" onclick="applyURL()">
                    <i class="fas fa-sync me-1"></i>Generate Ulang
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="background:rgba(255,255,255,0.1);border-radius:6px;padding:4px 10px;font-size:11px;color:rgba(255,255,255,0.5)">📋 URL ngrok kamu:</span>
                <code style="color:#4ade80;font-size:12px" id="urlPreview"><?= htmlspecialchars($base_url) ?>/ayam-penyet/?meja=N</code>
                <button onclick="simpanURL()" style="background:#4ade80;color:#1A1A2E;border:none;border-radius:6px;padding:4px 12px;font-size:11px;font-weight:700;cursor:pointer">💾 Simpan</button>
            </div>
        </div>

        <!-- QR Grid -->
        <div class="qr-grid" id="qrGrid">
            <?php foreach ($meja_list as $meja): ?>
            <div class="qr-card">
                <div class="qr-card-header">
                    <h3>🍗 Meja <?= $meja['nomor_meja'] ?></h3>
                    <p><?= htmlspecialchars($meja['nama_meja'] ?? '') ?> · <?= $meja['kapasitas'] ?> orang</p>
                </div>
                <div class="qr-body">
                    <div class="qr-container" id="qr-<?= $meja['nomor_meja'] ?>"></div>
                    <p class="qr-url" id="url-<?= $meja['nomor_meja'] ?>">Loading...</p>
                    <button class="btn-download" onclick="downloadQR(<?= $meja['nomor_meja'] ?>)">
                        <i class="fas fa-download"></i> Download QR
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="background:white;border-radius:var(--radius);padding:20px 24px;margin-top:24px;box-shadow:var(--shadow)">
            <h3 style="font-size:15px;font-weight:800;margin:0 0 14px">📖 Cara Menggunakan QR Code via Internet (Ngrok)</h3>
            <ol style="font-size:13px;color:#555;line-height:1.8;margin:0;padding-left:20px">
                <li>Pastikan <strong>XAMPP</strong> (Apache + MySQL) sudah nyala</li>
                <li>Pastikan <strong>ngrok</strong> sudah jalan: <code>ngrok http 80</code></li>
                <li>Copy URL ngrok dari CMD (contoh: <code>https://overrate-lather-tarnish.ngrok-free.app</code>)</li>
                <li>Paste URL ngrok di kolom atas, klik <strong>"Generate Ulang"</strong>, lalu klik <strong>"Simpan"</strong></li>
                <li>Download QR Code tiap meja lalu cetak & laminating</li>
                <li>Pelanggan scan QR dari HP mana saja → langsung ke menu online! 🎉</li>
            </ol>
            <div style="background:#fff8e1;border-radius:8px;padding:12px 16px;margin-top:12px;font-size:12px;color:#b45309">
                ⚠️ <strong>Penting:</strong> URL ngrok berubah setiap kali ngrok di-restart. Jika URL berubah, ulangi langkah 3-5 dan cetak ulang QR Code.
            </div>
        </div>
    </div>
</div>

<script>
    let currentBaseURL = '<?= htmlspecialchars($base_url) ?>';
    const mejaNums = <?= json_encode(array_column($meja_list, 'nomor_meja')) ?>;

    function generateQR(meja, baseUrl) {
        const url = `${baseUrl}/ayam-penyet/?meja=${meja}`;
        const container = document.getElementById(`qr-${meja}`);
        const urlEl = document.getElementById(`url-${meja}`);
        if (!container) return;
        container.innerHTML = '';
        new QRCode(container, {
            text: url,
            width: 150,
            height: 150,
            colorDark: '#1A1A2E',
            colorLight: '#FFFFFF',
            correctLevel: QRCode.CorrectLevel.H
        });
        urlEl.textContent = url;
    }

    function applyURL() {
        const url = document.getElementById('urlInput').value.trim().replace(/\/$/, '');
        if (!url) { alert('Masukkan URL yang valid!'); return; }
        currentBaseURL = url;
        document.getElementById('urlPreview').textContent = `${url}/ayam-penyet/?meja=N`;
        mejaNums.forEach(n => generateQR(n, url));
    }

    function simpanURL() {
        const url = document.getElementById('urlInput').value.trim().replace(/\/$/, '');
        fetch('save_ngrok_url.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'url=' + encodeURIComponent(url)
        }).then(r => r.text()).then(res => {
            if (res === 'ok') {
                alert('✅ URL berhasil disimpan! QR Code akan tetap pakai URL ini.');
            } else {
                alert('⚠️ Gagal simpan, tapi QR Code sudah ter-generate dengan URL baru.');
            }
        }).catch(() => alert('⚠️ Gagal simpan, tapi QR Code sudah ter-generate.'));
    }

    function downloadQR(meja) {
        setTimeout(() => {
            const container = document.getElementById(`qr-${meja}`);
            const canvas = container.querySelector('canvas');
            const img = container.querySelector('img');
            
            if (canvas) {
                const link = document.createElement('a');
                link.download = `QR-Meja-${meja}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
            } else if (img) {
                const link = document.createElement('a');
                link.download = `QR-Meja-${meja}.png`;
                link.href = img.src;
                link.click();
            } else {
                alert('QR Code belum selesai di-generate. Coba lagi!');
            }
        }, 100);
    }

    // Generate semua QR saat load
    window.onload = () => {
        mejaNums.forEach(n => generateQR(n, currentBaseURL));
    };
</script>
</body>
</html>
