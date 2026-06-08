<?php
// ============================================
// struk.php - Struk / Receipt
// ============================================
require_once 'koneksi.php';

$kode       = sanitize($_GET['kode'] ?? '');
$nomor_meja = (int)($_GET['meja'] ?? 0);
$print_mode = isset($_GET['print']); // mode cetak langsung

if (!$kode) redirect('index.php');

// Ambil data pesanan
$stmt = $conn->prepare("SELECT * FROM pesanan WHERE kode_pesanan = ?");
$stmt->bind_param('s', $kode);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) redirect('index.php');

// Ambil detail
$details = [];
$stmt2 = $conn->prepare("SELECT * FROM detail_pesanan WHERE id_pesanan = ? ORDER BY id ASC");
$stmt2->bind_param('i', $pesanan['id']);
$stmt2->execute();
$r = $stmt2->get_result();
while ($row = $r->fetch_assoc()) $details[] = $row;
$stmt2->close();

$metode_label = [
    'cash'     => '💵 Tunai',
    'qris'     => '📱 QRIS',
    'transfer' => '🏦 Transfer Bank',
];

$tanggal_fmt  = date('d/m/Y', strtotime($pesanan['tanggal']));
$waktu_fmt    = date('H:i', strtotime($pesanan['tanggal']));
$waktu_bayar  = $pesanan['waktu_bayar'] ? date('H:i, d/m/Y', strtotime($pesanan['waktu_bayar'])) : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran - <?= htmlspecialchars($kode) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">

    <!-- html2canvas for download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        :root {
            --primary: #E84040;
            --dark: #1A1A2E;
            --bg: #F0F2F5;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            margin: 0;
            padding-bottom: 120px;
        }

        /* ===== ACTIONS BAR (non-print) ===== */
        .actions-bar {
            background: var(--dark);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .back-link {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 16px;
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transition: background 0.2s;
        }
        .back-link:hover { background: rgba(255,255,255,0.2); color: white; }
        .actions-title { color: white; font-size: 16px; font-weight: 800; margin: 0; }
        .actions-right { margin-left: auto; display: flex; gap: 8px; }
        .action-btn {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.2s;
        }
        .action-btn:hover { background: rgba(255,255,255,0.22); }
        .action-btn.primary { background: var(--primary); border-color: var(--primary); }
        .action-btn.primary:hover { background: #C42E2E; }

        /* ===== STRUK WRAPPER ===== */
        .struk-wrapper {
            display: flex;
            justify-content: center;
            padding: 20px 16px;
        }

        /* ===== STRUK ===== */
        .struk {
            background: white;
            width: 100%;
            max-width: 380px;
            border-radius: 4px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            overflow: hidden;
            font-family: 'Courier Prime', 'Courier New', monospace;
        }

        /* Header struk */
        .struk-header {
            background: var(--dark);
            color: white;
            text-align: center;
            padding: 24px 20px 20px;
            position: relative;
            overflow: hidden;
        }
        .struk-header::after {
            content: '';
            position: absolute;
            bottom: -10px; left: 0; right: 0;
            height: 20px;
            background: white;
            border-radius: 50% 50% 0 0 / 100% 100% 0 0;
        }
        .struk-logo { font-size: 32px; margin-bottom: 8px; }
        .struk-nama {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin: 0 0 2px;
        }
        .struk-alamat {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 11px;
            color: rgba(255,255,255,0.65);
            margin: 0;
        }

        /* Body struk */
        .struk-body { padding: 24px 20px 12px; }

        /* Meta info */
        .struk-meta { margin-bottom: 12px; }
        .struk-meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #555;
            margin-bottom: 3px;
        }
        .struk-meta-row span:first-child { color: #999; }
        .struk-meta-row span:last-child { font-weight: 700; }

        /* Divider garis putus */
        .struk-divider {
            border: none;
            border-top: 1.5px dashed #CCC;
            margin: 12px 0;
        }

        /* Items */
        .struk-items { margin-bottom: 4px; }
        .struk-item {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 6px;
            align-items: flex-start;
            gap: 8px;
        }
        .item-left { flex: 1; }
        .item-nama { color: #222; font-weight: 700; font-size: 13px; }
        .item-sub { color: #888; font-size: 11px; margin-top: 1px; }
        .item-price {
            white-space: nowrap;
            font-weight: 700;
            font-size: 13px;
            color: #222;
        }

        /* Subtotal area */
        .struk-subtotal { margin-top: 10px; }
        .subtotal-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #555;
            margin-bottom: 5px;
        }
        .subtotal-row.bold {
            font-size: 15px;
            font-weight: 800;
            color: var(--dark);
        }
        .subtotal-row.primary {
            color: var(--primary);
            font-weight: 800;
            font-size: 13px;
        }
        .subtotal-row.kembalian {
            color: #16A34A;
            font-weight: 800;
            font-size: 14px;
            background: #ECFDF5;
            padding: 8px 10px;
            border-radius: 8px;
            margin: 6px 0;
        }

        /* Total box */
        .struk-total-box {
            background: var(--dark);
            color: white;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 12px 0;
        }
        .total-label-struk { font-size: 13px; font-family: 'Plus Jakarta Sans', sans-serif; opacity: 0.75; }
        .total-val-struk { font-size: 22px; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; }

        /* Metode bayar */
        .struk-metode {
            background: #F8F9FA;
            border-radius: 8px;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 12px;
        }
        .struk-metode-label { color: #888; }
        .struk-metode-val { font-weight: 800; color: var(--dark); }

        /* LUNAS stamp */
        .lunas-stamp {
            text-align: center;
            margin: 8px 0;
        }
        .lunas-badge {
            display: inline-block;
            border: 3px solid #16A34A;
            color: #16A34A;
            border-radius: 8px;
            padding: 6px 24px;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 4px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transform: rotate(-3deg);
        }

        /* Catatan */
        .struk-catatan {
            background: #FFF7ED;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 11px;
            color: #92400E;
            margin-bottom: 12px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* Footer struk */
        .struk-footer {
            text-align: center;
            padding: 16px 20px 24px;
            border-top: 1.5px dashed #DDD;
            position: relative;
        }
        .struk-footer::before {
            content: '';
            position: absolute;
            top: -10px; left: 0; right: 0;
            height: 20px;
            background: var(--dark);
            display: none; /* only in print */
        }
        .struk-thankyou {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 800;
            color: var(--dark);
            margin: 0 0 4px;
        }
        .struk-tagline {
            font-size: 11px;
            color: #888;
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .struk-wifi {
            margin-top: 10px;
            font-size: 11px;
            color: #aaa;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* Perforated bottom */
        .struk-perf {
            height: 14px;
            background: repeating-linear-gradient(
                90deg,
                transparent,
                transparent 8px,
                var(--bg) 8px,
                var(--bg) 16px
            );
        }

        /* ===== FLOATING BUTTONS ===== */
        .float-actions {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: white;
            padding: 12px 16px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
            display: flex;
            gap: 10px;
            z-index: 999;
        }
        .btn-download-struk {
            flex: 1;
            background: var(--dark);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.2s;
        }
        .btn-download-struk:hover { background: #0F3460; }

        .btn-print-struk {
            flex: 1;
            background: white;
            color: var(--dark);
            border: 2px solid #E5E7EB;
            border-radius: 50px;
            padding: 14px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.2s;
        }
        .btn-print-struk:hover { border-color: var(--dark); }

        .btn-selesai {
            flex: 1;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-selesai:hover { background: var(--primary-dark); color: white; }

        /* ===== PRINT STYLES ===== */
        @media print {
            body { background: white; padding: 0; }
            .actions-bar,
            .float-actions { display: none !important; }
            .struk-wrapper { padding: 0; justify-content: center; }
            .struk {
                box-shadow: none;
                max-width: 300px;
                border-radius: 0;
                border: none;
            }
            .struk-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .struk-total-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .struk-perf { display: none; }
            @page { margin: 0; size: 80mm auto; }
        }
    </style>
</head>
<body>

<!-- Actions Bar (hidden in print) -->
<div class="actions-bar">
    <a href="index.php?meja=<?= $nomor_meja ?>" class="back-link">
        <i class="fas fa-home"></i>
    </a>
    <h1 class="actions-title">🧾 Struk Pembayaran</h1>
    <div class="actions-right">
        <button class="action-btn" onclick="printStruk()">
            <i class="fas fa-print"></i> Print
        </button>
        <button class="action-btn primary" onclick="downloadStruk()">
            <i class="fas fa-download"></i> Simpan
        </button>
    </div>
</div>

<!-- Struk -->
<div class="struk-wrapper">
    <div class="struk" id="strukElement">

        <!-- Header -->
        <div class="struk-header">
            <div class="struk-logo">🍗</div>
            <div class="struk-nama">AYAM PENYET</div>
            <div class="struk-alamat">Bendungan Batusangkar · Sumatera Barat</div>
            <div class="struk-alamat" style="margin-top:4px">Telp: 0812-3456-7890</div>
        </div>

        <div class="struk-body">

            <!-- Meta -->
            <div class="struk-meta">
                <div class="struk-meta-row">
                    <span>No. Struk</span>
                    <span><?= htmlspecialchars($pesanan['kode_pesanan']) ?></span>
                </div>
                <div class="struk-meta-row">
                    <span>Tanggal</span>
                    <span><?= $tanggal_fmt ?></span>
                </div>
                <div class="struk-meta-row">
                    <span>Jam</span>
                    <span><?= $waktu_fmt ?></span>
                </div>
                <div class="struk-meta-row">
                    <span>Meja</span>
                    <span>Meja <?= $pesanan['nomor_meja'] ?></span>
                </div>
                <?php if ($pesanan['nama_pelanggan'] && $pesanan['nama_pelanggan'] !== 'Pelanggan'): ?>
                <div class="struk-meta-row">
                    <span>Pelanggan</span>
                    <span><?= htmlspecialchars($pesanan['nama_pelanggan']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <hr class="struk-divider">

            <!-- Item Pesanan -->
            <div class="struk-items">
                <?php foreach ($details as $d): ?>
                <div class="struk-item">
                    <div class="item-left">
                        <div class="item-nama"><?= htmlspecialchars($d['nama_menu']) ?></div>
                        <div class="item-sub"><?= $d['jumlah'] ?> × <?= formatRupiah($d['harga']) ?></div>
                    </div>
                    <div class="item-price"><?= formatRupiah($d['subtotal']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pesanan['catatan']): ?>
            <div class="struk-catatan">
                📝 Catatan: <?= htmlspecialchars($pesanan['catatan']) ?>
            </div>
            <?php endif; ?>

            <hr class="struk-divider">

            <!-- Subtotal / Total -->
            <div class="struk-subtotal">
                <div class="subtotal-row">
                    <span>Subtotal (<?= count($details) ?> item)</span>
                    <span><?= formatRupiah($pesanan['total_harga']) ?></span>
                </div>
                <div class="subtotal-row">
                    <span>Diskon</span>
                    <span>Rp 0</span>
                </div>
                <div class="subtotal-row">
                    <span>Pajak (0%)</span>
                    <span>Rp 0</span>
                </div>
            </div>

            <!-- Total Box -->
            <div class="struk-total-box">
                <span class="total-label-struk">TOTAL</span>
                <span class="total-val-struk"><?= formatRupiah($pesanan['total_harga']) ?></span>
            </div>

            <!-- Metode Bayar & Detail -->
            <?php if ($pesanan['status_bayar'] === 'lunas'): ?>
            <div class="struk-metode">
                <span class="struk-metode-label">Metode Bayar</span>
                <span class="struk-metode-val"><?= $metode_label[$pesanan['metode_bayar']] ?? '-' ?></span>
            </div>
            <?php if ($pesanan['metode_bayar'] === 'qris'): ?>
            <div style="background:#EFF6FF;border-radius:8px;padding:8px 12px;font-size:11px;color:#1D4ED8;margin-bottom:8px;font-family:'Plus Jakarta Sans',sans-serif">
                📱 Via GoPay/QRIS ke <strong>083803293430</strong>
            </div>
            <?php endif; ?>

            <?php if ($pesanan['metode_bayar'] === 'cash' && $pesanan['jumlah_bayar'] > 0): ?>
            <div class="struk-subtotal">
                <div class="subtotal-row">
                    <span>Uang Diterima</span>
                    <span style="font-weight:700"><?= formatRupiah($pesanan['jumlah_bayar']) ?></span>
                </div>
                <div class="subtotal-row kembalian">
                    <span>💰 Kembalian</span>
                    <span><?= formatRupiah($pesanan['kembalian']) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Waktu bayar -->
            <div class="struk-meta" style="margin-top:8px">
                <div class="struk-meta-row">
                    <span>Dibayar pada</span>
                    <span><?= $waktu_bayar ?></span>
                </div>
            </div>

            <!-- Stamp LUNAS -->
            <div class="lunas-stamp">
                <div class="lunas-badge">✓ LUNAS</div>
            </div>

            <?php else: ?>
            <!-- Belum bayar -->
            <div style="text-align:center;padding:12px;background:#FEF9C3;border-radius:10px;font-size:13px;color:#854D0E;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700">
                ⚠️ BELUM LUNAS — Harap segera bayar di kasir
            </div>
            <?php endif; ?>

        </div>

        <!-- Footer -->
        <div class="struk-footer">
            <div class="struk-thankyou">Terima Kasih! 🙏</div>
            <div class="struk-tagline">Selamat menikmati, sampai jumpa lagi!</div>
            <div class="struk-wifi">WiFi Password: AyamPenyet2024</div>
            <div style="margin-top:12px;font-size:10px;color:#bbb;font-family:'Plus Jakarta Sans',sans-serif">
                Struk ini adalah bukti pembayaran yang sah.<br>
                <?= date('d/m/Y H:i:s') ?>
            </div>
        </div>

        <!-- Perforated edge -->
        <div class="struk-perf"></div>

    </div><!-- end .struk -->
</div>

<!-- Floating Actions -->
<div class="float-actions">
    <?php if ($pesanan['status_bayar'] === 'lunas'): ?>
    <button class="btn-download-struk" onclick="downloadStruk()">
        <i class="fas fa-download"></i> Simpan Struk
    </button>
    <button class="btn-print-struk" onclick="printStruk()">
        <i class="fas fa-print"></i> Print
    </button>
    <?php else: ?>
    <a href="payment.php?kode=<?= urlencode($pesanan['kode_pesanan']) ?>&meja=<?= $nomor_meja ?>"
       class="btn-selesai" style="background:linear-gradient(135deg,#E84040,#C42E2E);flex:2">
        <i class="fas fa-credit-card"></i> Bayar Sekarang
    </a>
    <?php endif; ?>
    <a href="riwayat.php?meja=<?= $nomor_meja ?>" class="btn-print-struk" style="flex:1">
        <i class="fas fa-history"></i>
    </a>
    <a href="index.php?meja=<?= $nomor_meja ?>" class="btn-selesai" style="<?= $pesanan['status_bayar']==='lunas' ? 'flex:1' : 'display:none' ?>">
        <i class="fas fa-home"></i>
    </a>
</div>

<script>
    // ===== PRINT =====
    function printStruk() {
        window.print();
    }

    // ===== DOWNLOAD AS IMAGE =====
    function downloadStruk() {
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...';
        btn.disabled = true;

        const element = document.getElementById('strukElement');

        html2canvas(element, {
            scale: 3,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#FFFFFF',
            logging: false,
            width: element.offsetWidth,
            height: element.offsetHeight
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = `Struk-<?= htmlspecialchars($kode) ?>-Meja<?= $nomor_meja ?>.png`;
            link.href = canvas.toDataURL('image/png', 1.0);
            link.click();

            btn.innerHTML = '<i class="fas fa-check me-1"></i> Tersimpan!';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 2000);
        }).catch(err => {
            console.error(err);
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('Gagal menyimpan. Gunakan tombol Print untuk screenshot.');
        });
    }

    // Auto print jika mode print
    <?php if ($print_mode): ?>
    window.onload = () => setTimeout(printStruk, 500);
    <?php endif; ?>
</script>
</body>
</html>
