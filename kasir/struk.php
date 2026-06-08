<?php
// ============================================
// admin/struk_admin.php
// Kasir cetak/print struk untuk pelanggan
// ============================================
require_once '../koneksi.php';
requireKasirLogin();
// Safe query - handle missing columns gracefully
$stat_pending_pay = 0;
try {
    $r = $conn->query("SELECT COUNT(*) as c FROM pesanan WHERE (metode_bayar='cash' OR metode_bayar='qris') AND status_verifikasi='menunggu' AND (status_bayar='belum_bayar' OR status_bayar IS NULL)");
    if ($r) $stat_pending_pay = (int)$r->fetch_assoc()['c'];
} catch (Exception $e) { $stat_pending_pay = 0; }

$kode = sanitize($_GET['kode'] ?? '');
$auto_print = isset($_GET['print']);

if (!$kode) {
    setFlash('error', 'Kode pesanan tidak valid');
    redirect('/ayam-penyet/kasir/dashboard.php');
}

$stmt = $conn->prepare("SELECT * FROM pesanan WHERE kode_pesanan = ?");
$stmt->bind_param('s', $kode);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    setFlash('error', 'Pesanan tidak ditemukan');
    redirect('/ayam-penyet/kasir/dashboard.php');
}

$details = [];
$stmt2 = $conn->prepare("SELECT * FROM detail_pesanan WHERE id_pesanan = ? ORDER BY id ASC");
$stmt2->bind_param('i', $pesanan['id']);
$stmt2->execute();
$r = $stmt2->get_result();
while ($row = $r->fetch_assoc()) $details[] = $row;
$stmt2->close();

$metode_label = ['cash' => '💵 Tunai', 'qris' => '📱 QRIS', 'transfer' => '🏦 Transfer Bank'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Admin · <?= htmlspecialchars($kode) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        :root{--primary:#E84040;--dark:#1A1A2E;}
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:#F0F2F5;margin:0;padding-bottom:80px;}

        /* ACTION BAR */
        .abar{background:var(--dark);padding:12px 20px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:100;}
        .abar a{color:rgba(255,255,255,0.7);text-decoration:none;font-size:15px;width:34px;height:34px;background:rgba(255,255,255,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;}
        .abar a:hover{background:rgba(255,255,255,0.2);color:white;}
        .abar-title{color:white;font-size:15px;font-weight:800;flex:1;}
        .abtn{background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:white;border-radius:8px;padding:8px 14px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px;transition:all 0.2s;}
        .abtn:hover{background:rgba(255,255,255,0.22);}
        .abtn.red{background:var(--primary);border-color:var(--primary);}
        .abtn.red:hover{background:#C42E2E;}

        /* STRUK */
        .wrap{display:flex;justify-content:center;padding:20px 16px;}
        .struk{background:white;width:100%;max-width:340px;border-radius:2px;box-shadow:0 8px 32px rgba(0,0,0,0.12);overflow:hidden;font-family:'Courier Prime','Courier New',monospace;}

        .sh{background:var(--dark);color:white;text-align:center;padding:22px 16px 18px;position:relative;overflow:hidden;}
        .sh::after{content:'';position:absolute;bottom:-10px;left:0;right:0;height:20px;background:white;border-radius:50% 50% 0 0/100% 100% 0 0;}
        .sh-logo{font-size:28px;margin-bottom:6px;}
        .sh-name{font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;letter-spacing:.5px;}
        .sh-sub{font-size:10px;color:rgba(255,255,255,0.55);margin-top:3px;}

        .sb{padding:20px 16px 10px;}
        .srow{display:flex;justify-content:space-between;font-size:11px;color:#555;margin-bottom:3px;}
        .srow span:first-child{color:#999;}
        .srow span:last-child{font-weight:700;}
        .sdiv{border:none;border-top:1.5px dashed #CCC;margin:10px 0;}

        .sitem{display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;gap:8px;align-items:flex-start;}
        .sin{flex:1;}
        .sin-name{font-weight:700;color:#222;}
        .sin-sub{color:#888;font-size:10px;margin-top:1px;}
        .sin-price{white-space:nowrap;font-weight:700;color:#222;font-size:12px;}

        .stbox{background:var(--dark);color:white;border-radius:8px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;margin:10px 0;}
        .stbox-l{font-size:11px;font-family:'Plus Jakarta Sans',sans-serif;opacity:.7;}
        .stbox-r{font-size:18px;font-weight:800;font-family:'Plus Jakarta Sans',sans-serif;}

        .smetode{background:#F8F9FA;border-radius:6px;padding:8px 12px;display:flex;justify-content:space-between;font-size:11px;margin-bottom:8px;}
        .smetode span:last-child{font-weight:800;}
        .skembalian{background:#ECFDF5;border:1px solid #A7F3D0;border-radius:8px;padding:9px 12px;display:flex;justify-content:space-between;font-size:12px;font-weight:800;color:#16A34A;margin-bottom:8px;}
        .stamp{text-align:center;margin:8px 0;}
        .stamp-badge{display:inline-block;border:3px solid #16A34A;color:#16A34A;border-radius:6px;padding:5px 18px;font-size:20px;font-weight:800;letter-spacing:4px;font-family:'Plus Jakarta Sans',sans-serif;transform:rotate(-3deg);}
        .stamp-blm{border-color:#F59E0B;color:#D97706;}

        .sf{text-align:center;padding:14px 16px 20px;border-top:1.5px dashed #DDD;}
        .sf-ty{font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:800;color:var(--dark);}
        .sf-sub{font-size:10px;color:#888;font-family:'Plus Jakarta Sans',sans-serif;margin-top:3px;}
        .sf-dt{font-size:9px;color:#CCC;font-family:'Plus Jakarta Sans',sans-serif;margin-top:8px;}
        .perf{height:12px;background:repeating-linear-gradient(90deg,transparent,transparent 7px,#F0F2F5 7px,#F0F2F5 14px);}

        /* FLOAT */
        .fbar{position:fixed;bottom:0;left:0;right:0;background:white;padding:12px 16px;box-shadow:0 -4px 20px rgba(0,0,0,0.1);display:flex;gap:10px;z-index:999;}
        .fbtn{flex:1;border:none;border-radius:50px;padding:14px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;}
        .fbtn-dl{background:var(--dark);color:white;}
        .fbtn-pr{background:white;color:var(--dark);border:2px solid #E5E7EB;}
        .fbtn-pr:hover{border-color:var(--dark);}
        .fbtn-bk{background:var(--primary);color:white;}

        @media print{
            .abar,.fbar{display:none!important;}
            body{background:white;padding:0;}
            .wrap{padding:0;justify-content:center;}
            .struk{box-shadow:none;max-width:280px;}
            @page{margin:0;size:80mm auto;}
        }
    </style>
</head>
<body>

<!-- Action Bar -->
<div class="abar">
    <a href="dashboard.php"><i class="fas fa-arrow-left"></i></a>
    <div class="abar-title">🧾 Struk Kasir · Meja <?= $pesanan['nomor_meja'] ?></div>
    <?php if(($pesanan['status_bayar']??'') !== 'lunas'): ?>
    <a href="konfirmasi_bayar.php" class="abtn red">
        <i class="fas fa-cash-register"></i> Konfirmasi Bayar
    </a>
    <?php endif; ?>
    <button class="abtn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    <button class="abtn" onclick="downloadStruk()"><i class="fas fa-download"></i> Simpan</button>
</div>

<!-- STRUK -->
<div class="wrap">
    <div class="struk" id="strukEl">
        <!-- Header -->
        <div class="sh">
            <div class="sh-logo">🍗</div>
            <div class="sh-name">AYAM PENYET</div>
            <div class="sh-sub">Bendungan Batusangkar · Sumatera Barat</div>
            <div class="sh-sub">Telp: 0812-3456-7890</div>
        </div>

        <div class="sb">
            <!-- Meta -->
            <div class="srow"><span>No. Struk</span><span><?= htmlspecialchars($pesanan['kode_pesanan']) ?></span></div>
            <div class="srow"><span>Tanggal</span><span><?= date('d/m/Y', strtotime($pesanan['tanggal'])) ?></span></div>
            <div class="srow"><span>Jam</span><span><?= date('H:i', strtotime($pesanan['tanggal'])) ?></span></div>
            <div class="srow"><span>Meja</span><span>Meja <?= $pesanan['nomor_meja'] ?></span></div>
            <?php
                $nm_raw = $pesanan['nama_pelanggan'] ?? '';
                $nm_display = (!empty(trim($nm_raw)) && trim($nm_raw) !== '0') ? trim($nm_raw) : 'Pelanggan';
            ?>
            <div class="srow"><span>Pemesan</span><span style="color:#2563EB;font-weight:800">👤 <?= htmlspecialchars($nm_display) ?></span></div>
            <div class="srow"><span>Kasir</span><span><?= htmlspecialchars($_SESSION['user_nama'] ?? 'Kasir') ?></span></div>
            <hr class="sdiv">

            <!-- Items -->
            <?php foreach($details as $d): ?>
            <div class="sitem">
                <div class="sin">
                    <div class="sin-name"><?= htmlspecialchars($d['nama_menu']) ?></div>
                    <div class="sin-sub"><?= $d['jumlah'] ?> × <?= formatRupiah($d['harga']) ?></div>
                </div>
                <div class="sin-price"><?= formatRupiah($d['subtotal']) ?></div>
            </div>
            <?php endforeach; ?>

            <?php if($pesanan['catatan']): ?>
            <div style="background:#FFF7ED;border-radius:5px;padding:6px 10px;font-size:10px;color:#92400E;margin-bottom:6px">
                📝 <?= htmlspecialchars($pesanan['catatan']) ?>
            </div>
            <?php endif; ?>

            <hr class="sdiv">
            <div class="srow"><span>Subtotal</span><span><?= formatRupiah($pesanan['total_harga']) ?></span></div>
            <div class="srow"><span>Diskon</span><span>Rp 0</span></div>

            <!-- Total Box -->
            <div class="stbox">
                <span class="stbox-l">TOTAL</span>
                <span class="stbox-r"><?= formatRupiah($pesanan['total_harga']) ?></span>
            </div>

            <!-- Metode & Kembalian -->
            <?php if(($pesanan['status_bayar']??'') === 'lunas'): ?>
            <div class="smetode">
                <span>Metode Bayar</span>
                <span><?= $metode_label[$pesanan['metode_bayar']] ?? '-' ?></span>
            </div>
            <?php if($pesanan['metode_bayar']==='cash' && ($pesanan['jumlah_bayar']??0) > 0): ?>
            <div class="srow"><span>Uang Diterima</span><span style="font-weight:700"><?= formatRupiah($pesanan['jumlah_bayar']) ?></span></div>
            <div class="skembalian">
                <span>💰 Kembalian</span>
                <span><?= formatRupiah($pesanan['kembalian'] ?? 0) ?></span>
            </div>
            <?php endif; ?>
            <?php if($pesanan['metode_bayar']==='qris'): ?>
            <div style="background:#EFF6FF;border-radius:7px;padding:7px 10px;margin-bottom:7px;font-size:10px;color:#1D4ED8;font-family:'Plus Jakarta Sans',sans-serif">
                📱 Transfer via GoPay ke <strong>083803293430</strong>
            </div>
            <?php endif; ?>
            <?php if($pesanan['waktu_bayar']): ?>
            <div class="srow"><span>Dibayar</span><span><?= date('H:i, d/m/Y', strtotime($pesanan['waktu_bayar'])) ?></span></div>
            <?php endif; ?>
            <div class="stamp"><div class="stamp-badge">✓ LUNAS</div></div>
            <?php else: ?>
            <div class="stamp"><div class="stamp-badge stamp-blm">⚠ BELUM LUNAS</div></div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="sf">
            <div class="sf-ty">Terima Kasih! 🙏</div>
            <div class="sf-sub">Selamat menikmati hidangan kami</div>
            <div class="sf-dt">Dicetak: <?= date('d/m/Y H:i:s') ?> · <?= APP_NAME ?></div>
        </div>
        <div class="perf"></div>
    </div>
</div>

<!-- Float Bar -->
<div class="fbar">
    <button class="fbtn fbtn-dl" onclick="downloadStruk()"><i class="fas fa-download"></i> Simpan PNG</button>
    <button class="fbtn fbtn-pr" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    <a href="dashboard.php" class="fbtn fbtn-bk" style="text-decoration:none"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<script>
function downloadStruk(){
    const btn = event.target.closest('button');
    const orig = btn.innerHTML;
    btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    btn.disabled=true;
    html2canvas(document.getElementById('strukEl'),{scale:3,backgroundColor:'#FFFFFF',logging:false}).then(canvas=>{
        const a=document.createElement('a');
        a.download='Struk-<?= htmlspecialchars($kode) ?>-Meja<?= $pesanan['nomor_meja'] ?>.png';
        a.href=canvas.toDataURL('image/png',1.0);
        a.click();
        btn.innerHTML='<i class="fas fa-check"></i> Tersimpan!';
        setTimeout(()=>{btn.innerHTML=orig;btn.disabled=false;},2000);
    }).catch(()=>{btn.innerHTML=orig;btn.disabled=false;});
}
<?php if($auto_print): ?>
window.onload=()=>setTimeout(()=>window.print(),600);
<?php endif; ?>
</script>
</body>
</html>
