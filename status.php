<?php
// ============================================
// status.php - Cek Status Pesanan
// Hanya berguna setelah sudah bayar:
//   - Menampilkan status pembayaran
//   - Link ke struk jika sudah lunas
//   - Link bayar jika belum
// ============================================
require_once 'koneksi.php';

$kode = sanitize($_GET['kode'] ?? '');
$meja = (int)($_GET['meja'] ?? getNomorMeja());

// ── API mode (polling dari tunggu_kasir / menunggu_verifikasi) ──
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    if (!$kode) { echo json_encode(['success' => false]); exit; }

    $stmt = $conn->prepare("SELECT status, status_bayar, status_verifikasi, metode_bayar FROM pesanan WHERE kode_pesanan = ?");
    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success'           => (bool)$row,
        'status'            => $row['status']            ?? null,
        'status_bayar'      => $row['status_bayar']      ?? null,
        'status_verifikasi' => $row['status_verifikasi'] ?? null,
        'metode_bayar'      => $row['metode_bayar']      ?? null,
    ]);
    exit;
}

// ── Halaman normal ──
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

// Jika belum ada kode, tampilkan form pencarian
$sb = $pesanan['status_bayar']      ?? 'belum_bayar';
$mb = $pesanan['metode_bayar']      ?? null;
$sv = $pesanan['status_verifikasi'] ?? null;
$metode_label = ['cash' => '💵 Tunai', 'qris' => '📱 QRIS / GoPay', 'transfer' => '🏦 Transfer'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pembayaran - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #E84040; --primary-dark: #C42E2E;
            --dark: #1A1A2E; --bg: #F5F5F5; --green: #16A34A;
            --border: #E5E7EB;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); margin: 0; padding-bottom: 32px; }

        /* HEADER */
        .page-header { background: var(--dark); padding: 16px 20px; display: flex; align-items: center; gap: 14px; }
        .back-btn { width: 36px; height: 36px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; font-size: 16px; }
        .back-btn:hover { background: rgba(255,255,255,0.2); color: white; }
        .header-title { color: white; font-size: 17px; font-weight: 800; margin: 0; flex: 1; }
        .meja-chip { background: rgba(255,255,255,0.15); color: white; border-radius: 50px; padding: 5px 12px; font-size: 12px; font-weight: 700; }

        /* STATUS HERO - changes color based on payment status */
        .status-hero {
            padding: 32px 24px 28px; text-align: center;
        }
        .status-hero.lunas     { background: linear-gradient(135deg, #16A34A, #15803D); }
        .status-hero.menunggu  { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
        .status-hero.belum     { background: linear-gradient(135deg, #D97706, #B45309); }
        .status-hero.ditolak   { background: linear-gradient(135deg, #DC2626, #B91C1C); }

        .status-icon-circle {
            width: 80px; height: 80px; border-radius: 50%;
            background: rgba(255,255,255,0.2); border: 3px solid rgba(255,255,255,0.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 36px; margin: 0 auto 16px;
        }
        .status-hero h2 { font-size: 22px; font-weight: 800; color: white; margin: 0 0 6px; }
        .status-hero p  { font-size: 13px; color: rgba(255,255,255,0.8); margin: 0; }

        /* CARDS */
        .card-box { background: white; border-radius: 16px; margin: 14px 16px; padding: 18px 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .card-title { font-size: 14px; font-weight: 800; color: var(--dark); margin: 0 0 14px; }
        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid #F5F5F5; font-size: 14px; }
        .info-row:last-child { border: none; padding-bottom: 0; }
        .info-lbl { color: #888; }
        .info-val { font-weight: 700; color: var(--dark); }

        /* Payment status badge */
        .pay-badge { border-radius: 50px; padding: 4px 12px; font-size: 12px; font-weight: 700; }
        .pay-lunas    { background: #ECFDF5; color: #16A34A; }
        .pay-menunggu { background: #EFF6FF; color: #2563EB; }
        .pay-belum    { background: #FEF2F2; color: #DC2626; }
        .pay-ditolak  { background: #FEF2F2; color: #DC2626; }

        /* Detail items */
        .detail-row { display: flex; justify-content: space-between; font-size: 13px; color: #444; margin-bottom: 9px; }
        .detail-div { border: none; border-top: 1.5px dashed var(--border); margin: 10px 0; }
        .detail-total { display: flex; justify-content: space-between; font-size: 15px; font-weight: 800; color: var(--dark); }
        .detail-total span:last-child { color: var(--primary); }

        /* Action area */
        .action-area { margin: 0 16px 24px; display: flex; flex-direction: column; gap: 10px; }

        .btn-struk {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            background: linear-gradient(135deg, var(--green), #15803D);
            color: white; border-radius: 50px; padding: 17px;
            font-size: 16px; font-weight: 800; text-decoration: none;
            box-shadow: 0 6px 20px rgba(22,163,74,0.35); transition: all .2s;
        }
        .btn-struk:hover { color: white; transform: translateY(-2px); }

        .btn-bayar {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; border-radius: 50px; padding: 17px;
            font-size: 16px; font-weight: 800; text-decoration: none;
            box-shadow: 0 6px 20px rgba(232,64,64,0.35); transition: all .2s;
        }
        .btn-bayar:hover { color: white; transform: translateY(-2px); }

        .btn-tambah {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            background: white; color: var(--dark); border: 2px solid var(--border);
            border-radius: 50px; padding: 14px;
            font-size: 15px; font-weight: 700; text-decoration: none; transition: all .2s;
        }
        .btn-tambah:hover { border-color: var(--dark); color: var(--dark); }

        /* Search form (when no kode) */
        .search-card { background: white; border-radius: 16px; margin: 20px 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); text-align: center; }
        .search-card .icon { font-size: 56px; margin-bottom: 14px; }
        .search-card h3 { font-size: 18px; font-weight: 800; color: var(--dark); margin: 0 0 6px; }
        .search-card p  { font-size: 13px; color: #888; margin: 0 0 20px; }
        .search-inp { width: 100%; border: 1.5px solid var(--border); border-radius: 10px; padding: 12px 16px; font-size: 14px; font-family: inherit; outline: none; margin-bottom: 10px; letter-spacing: 1px; transition: border .2s; }
        .search-inp:focus { border-color: var(--primary); }
        .btn-cari { width: 100%; background: var(--primary); color: white; border: none; border-radius: 50px; padding: 14px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-cari:hover { background: var(--primary-dark); }

        /* Notice boxes */
        .notice { border-radius: 12px; padding: 14px 16px; margin: 0 16px 14px; display: flex; gap: 10px; font-size: 13px; }
        .notice-warn  { background: #FFF7ED; border: 1px solid #FDE68A; color: #92400E; }
        .notice-info  { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E40AF; }
        .notice-green { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .notice i { flex-shrink: 0; margin-top: 1px; }
    </style>
</head>
<body>

<!-- Header -->
<div class="page-header">
    <a href="index.php?meja=<?= $meja ?>" class="back-btn"><i class="fas fa-arrow-left"></i></a>
    <h1 class="header-title">💳 Status Pembayaran</h1>
    <?php if ($meja > 0): ?>
    <span class="meja-chip">Meja <?= $meja ?></span>
    <?php endif; ?>
</div>

<?php if (!$kode): ?>
<!-- ── FORM CARI KODE ── -->
<div class="search-card">
    <div class="icon">🔍</div>
    <h3>Cek Status Pembayaran</h3>
    <p>Masukkan kode pesanan Anda</p>
    <form method="GET">
        <?php if ($meja > 0): ?><input type="hidden" name="meja" value="<?= $meja ?>"><?php endif; ?>
        <input type="text" name="kode" class="search-inp"
               placeholder="Contoh: ORD-XXXXXX-..." required
               style="text-transform:uppercase">
        <button type="submit" class="btn-cari"><i class="fas fa-search me-2"></i>Cek Sekarang</button>
    </form>
</div>

<?php elseif (!$pesanan): ?>
<!-- ── TIDAK DITEMUKAN ── -->
<div class="search-card" style="margin-top:20px">
    <div class="icon">❓</div>
    <h3>Pesanan Tidak Ditemukan</h3>
    <p>Kode <strong><?= htmlspecialchars($kode) ?></strong> tidak ada di sistem.</p>
    <a href="status.php<?= $meja > 0 ? '?meja='.$meja : '' ?>"
       style="color:var(--primary);font-weight:700;font-size:14px;text-decoration:none">← Cari kode lain</a>
</div>

<?php else: ?>
<!-- ── DETAIL STATUS ── -->

<?php
// Determine hero style
if ($sb === 'lunas') {
    $heroClass = 'lunas';
    $heroIcon  = '✅';
    $heroTitle = 'Pembayaran Lunas!';
    $heroSub   = 'Terima kasih, struk siap diunduh';
} elseif ($sv === 'menunggu' && $mb === 'qris') {
    $heroClass = 'menunggu';
    $heroIcon  = '🔍';
    $heroTitle = 'Verifikasi QRIS';
    $heroSub   = 'Admin sedang mengecek bukti transfer Anda';
} elseif ($sv === 'menunggu' && $mb === 'cash') {
    $heroClass = 'menunggu';
    $heroIcon  = '🏪';
    $heroTitle = 'Menunggu Kasir';
    $heroSub   = 'Silakan tunjukkan kode ke kasir';
} elseif ($sv === 'ditolak') {
    $heroClass = 'ditolak';
    $heroIcon  = '❌';
    $heroTitle = 'Bukti Transfer Ditolak';
    $heroSub   = 'Silakan upload ulang bukti transfer';
} else {
    $heroClass = 'belum';
    $heroIcon  = '⏳';
    $heroTitle = 'Belum Dibayar';
    $heroSub   = 'Pesanan ' . ucfirst($pesanan['status']);
}
?>

<div class="status-hero <?= $heroClass ?>">
    <div class="status-icon-circle"><?= $heroIcon ?></div>
    <h2><?= $heroTitle ?></h2>
    <p><?= $heroSub ?></p>
</div>

<!-- Info Pesanan -->
<div class="card-box">
    <div class="card-title">📋 Info Pesanan</div>
    <div class="info-row">
        <span class="info-lbl">Kode Pesanan</span>
        <span class="info-val" style="font-family:monospace;color:var(--primary);font-size:13px">
            <?= htmlspecialchars($pesanan['kode_pesanan']) ?>
        </span>
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
        <span class="info-lbl">Status Masak</span>
        <?php
        $sc_map = ['pending'=>'⏳ Menunggu','diproses'=>'🔥 Dimasak','selesai'=>'✅ Selesai','dibatalkan'=>'❌ Batal'];
        $sc_col = ['pending'=>'color:#D97706','diproses'=>'color:#2563EB','selesai'=>'color:#16A34A','dibatalkan'=>'color:#888'];
        ?>
        <span class="info-val" style="<?= $sc_col[$pesanan['status']] ?? '' ?>">
            <?= $sc_map[$pesanan['status']] ?? $pesanan['status'] ?>
        </span>
    </div>
    <div class="info-row">
        <span class="info-lbl">Status Bayar</span>
        <?php
        $pay_cls = match(true) {
            $sb === 'lunas'                   => 'pay-lunas',
            $sv === 'ditolak'                 => 'pay-ditolak',
            $sv === 'menunggu'                => 'pay-menunggu',
            default                           => 'pay-belum',
        };
        $pay_text = match(true) {
            $sb === 'lunas'                   => '✅ LUNAS',
            $sv === 'ditolak'                 => '❌ Bukti Ditolak',
            $sv === 'menunggu' && $mb==='qris'=> '⏳ Verifikasi QRIS',
            $sv === 'menunggu' && $mb==='cash'=> '⏳ Menunggu Kasir',
            default                           => '⏳ Belum Bayar',
        };
        ?>
        <span class="pay-badge <?= $pay_cls ?>"><?= $pay_text ?></span>
    </div>
    <?php if ($mb && $sb === 'lunas'): ?>
    <div class="info-row">
        <span class="info-lbl">Metode Bayar</span>
        <span class="info-val"><?= $metode_label[$mb] ?? $mb ?></span>
    </div>
    <?php endif; ?>
    <?php if ($sb === 'lunas' && $pesanan['waktu_bayar']): ?>
    <div class="info-row">
        <span class="info-lbl">Waktu Bayar</span>
        <span class="info-val"><?= date('H:i · d M Y', strtotime($pesanan['waktu_bayar'])) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($mb === 'cash' && $sb === 'lunas' && ($pesanan['kembalian'] ?? 0) > 0): ?>
    <div class="info-row">
        <span class="info-lbl">Kembalian</span>
        <span class="info-val" style="color:#16A34A"><?= formatRupiah($pesanan['kembalian']) ?></span>
    </div>
    <?php endif; ?>
</div>

<!-- Detail Item -->
<div class="card-box">
    <div class="card-title">🍽️ Detail Pesanan</div>
    <?php foreach ($details as $d): ?>
    <div class="detail-row">
        <span><?= htmlspecialchars($d['nama_menu']) ?> <span style="color:#aaa">×<?= $d['jumlah'] ?></span></span>
        <span style="font-weight:600"><?= formatRupiah($d['subtotal']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php if ($pesanan['catatan']): ?>
    <div style="background:#FFF7ED;border-radius:8px;padding:8px 12px;font-size:12px;color:#92400E;margin:8px 0">
        📝 <?= htmlspecialchars($pesanan['catatan']) ?>
    </div>
    <?php endif; ?>
    <hr class="detail-div">
    <div class="detail-total">
        <span>Total</span>
        <span><?= formatRupiah($pesanan['total_harga']) ?></span>
    </div>
</div>

<!-- Notice contextual -->
<?php if ($sb === 'lunas'): ?>
<div class="notice notice-green">
    <i class="fas fa-check-circle"></i>
    <span>Pembayaran Anda <strong>sudah terkonfirmasi</strong>. Struk digital siap diunduh atau dicetak.</span>
</div>
<?php elseif ($sv === 'menunggu' && $mb === 'qris'): ?>
<div class="notice notice-info">
    <i class="fas fa-clock"></i>
    <span>Bukti transfer sedang diverifikasi admin. Biasanya 1–5 menit. Halaman ini akan otomatis update.</span>
</div>
<?php elseif ($sv === 'menunggu' && $mb === 'cash'): ?>
<div class="notice notice-warn">
    <i class="fas fa-store"></i>
    <span>Tunjukkan kode pesanan <strong><?= htmlspecialchars($pesanan['kode_pesanan']) ?></strong> ke kasir dan bayar tunai.</span>
</div>
<?php elseif ($sv === 'ditolak'): ?>
<div class="notice" style="background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B">
    <i class="fas fa-exclamation-circle"></i>
    <span>Bukti transfer Anda ditolak. Pastikan transfer ke <strong>083803293430</strong> dan nominal tepat, lalu upload ulang.</span>
</div>
<?php else: ?>
<div class="notice notice-warn">
    <i class="fas fa-info-circle"></i>
    <span>Pesanan Anda belum dibayar. Pilih metode pembayaran untuk melanjutkan.</span>
</div>
<?php endif; ?>

<!-- ── ACTION BUTTONS ── -->
<div class="action-area">
    <?php if ($sb === 'lunas'): ?>
    <!-- SUDAH BAYAR: tampilkan STRUK -->
    <a href="struk.php?kode=<?= urlencode($pesanan['kode_pesanan']) ?>&meja=<?= $meja ?>"
       class="btn-struk">
        <i class="fas fa-receipt"></i>
        Lihat & Download Struk
    </a>
    <a href="index.php?meja=<?= $meja ?>" class="btn-tambah">
        <i class="fas fa-plus-circle"></i> Tambah Pesanan
    </a>

    <?php elseif ($sv === 'ditolak'): ?>
    <!-- DITOLAK: upload ulang -->
    <a href="payment.php?kode=<?= urlencode($pesanan['kode_pesanan']) ?>&meja=<?= $meja ?>"
       class="btn-bayar">
        <i class="fas fa-upload"></i> Upload Ulang Bukti
    </a>
    <a href="index.php?meja=<?= $meja ?>" class="btn-tambah">
        <i class="fas fa-plus-circle"></i> Tambah Pesanan
    </a>

    <?php elseif ($sv === 'menunggu'): ?>
    <!-- MENUNGGU VERIFIKASI: info saja -->
    <a href="index.php?meja=<?= $meja ?>" class="btn-tambah">
        <i class="fas fa-plus-circle"></i> Tambah Pesanan
    </a>

    <?php else: ?>
    <!-- BELUM BAYAR: tombol bayar -->
    <a href="payment.php?kode=<?= urlencode($pesanan['kode_pesanan']) ?>&meja=<?= $meja ?>"
       class="btn-bayar">
        <i class="fas fa-credit-card"></i>
        Bayar Sekarang · <?= formatRupiah($pesanan['total_harga']) ?>
    </a>
    <a href="index.php?meja=<?= $meja ?>" class="btn-tambah">
        <i class="fas fa-plus-circle"></i> Tambah Pesanan
    </a>
    <?php endif; ?>
</div>

<?php if ($sb !== 'lunas'): ?>
<!-- Auto polling untuk status yang masih aktif -->
<script>
const KODE = '<?= htmlspecialchars($pesanan['kode_pesanan']) ?>';
const MEJA = <?= $meja ?>;

function cekStatus() {
    fetch(`status.php?api=1&kode=${encodeURIComponent(KODE)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const sb = data.status_bayar;
            const sv = data.status_verifikasi;
            // Jika sudah lunas atau ditolak → reload untuk update tampilan
            if (sb === 'lunas' || sv === 'ditolak') {
                location.reload();
            }
        })
        .catch(() => {});
}

// Poll setiap 10 detik
setInterval(cekStatus, 10000);
</script>
<?php endif; ?>

<?php endif; ?>

</body>
</html>
