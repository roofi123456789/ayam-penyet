<?php
// ============================================
// admin/konfirmasi_bayar.php
// Panel admin: konfirmasi cash & verifikasi QRIS
// ============================================
require_once '../koneksi.php';
requireKasirLogin();

// Pending payment badge for sidebar
$stat_pending_pay = 0;

// Check if payment columns exist
$col_check = $conn->query("SHOW COLUMNS FROM pesanan LIKE 'status_verifikasi'");
$columns_ok = ($col_check && $col_check->num_rows > 0);

// Check if nama_pelanggan column exists
$nama_col_check = $conn->query("SHOW COLUMNS FROM pesanan LIKE 'nama_pelanggan'");
$nama_col_ok = ($nama_col_check && $nama_col_check->num_rows > 0);
// Auto-add nama_pelanggan column if missing
if (!$nama_col_ok) {
    $conn->query("ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS nama_pelanggan VARCHAR(100) DEFAULT 'Pelanggan'");
}

$cash_list = [];
$qris_list = [];

if ($columns_ok) {
    $rc = $conn->query("SELECT p.*,
        (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan=p.id) as jml_item
        FROM pesanan p
        WHERE p.metode_bayar='cash'
        AND (p.status_bayar='belum_bayar' OR p.status_bayar IS NULL)
        AND p.status_verifikasi='menunggu'
        ORDER BY p.tanggal ASC");
    if ($rc) while ($row = $rc->fetch_assoc()) $cash_list[] = $row;

    $rq = $conn->query("SELECT p.*,
        (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan=p.id) as jml_item
        FROM pesanan p
        WHERE p.metode_bayar='qris'
        AND p.status_verifikasi='menunggu'
        AND p.bukti_qris IS NOT NULL
        ORDER BY p.tanggal ASC");
    if ($rq) while ($row = $rq->fetch_assoc()) $qris_list[] = $row;

    $stat_pending_pay = count($cash_list) + count($qris_list);
}

$total_menunggu = count($cash_list) + count($qris_list);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root{--primary:#E84040;--dark:#1A1A2E;--bg:#F0F2F5;--border:#E5E7EB;--radius:14px;--shadow:0 2px 16px rgba(0,0,0,0.07);--green:#16A34A;}
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);margin:0;}
        .sidebar{position:fixed;top:0;left:0;width:240px;height:100vh;background:#1A1A2E;z-index:1000;display:flex;flex-direction:column;overflow-y:auto;}
        .sidebar-logo{padding:24px 20px;border-bottom:1px solid rgba(255,255,255,0.08);}
        .sidebar-logo h2{font-size:15px;font-weight:800;color:white;margin:0;}
        .sidebar-logo p{font-size:11px;color:rgba(255,255,255,0.45);margin:3px 0 0;}
        .nav-section{padding:16px 12px 8px;}
        .nav-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.3);padding:0 8px;margin-bottom:6px;}
        .nav-item a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;color:rgba(255,255,255,0.6);transition:all 0.2s;margin-bottom:2px;}
        .nav-item a:hover{background:rgba(255,255,255,0.08);color:white;}
        .nav-item a.active{background:var(--primary);color:white;}
        .nav-item a i{width:18px;text-align:center;}
        .nav-badge{margin-left:auto;background:var(--primary);color:white;border-radius:50px;padding:2px 8px;font-size:11px;font-weight:700;}
        .nav-item a.active .nav-badge{background:rgba(255,255,255,0.25);}
        .sidebar-footer{margin-top:auto;padding:16px 12px;border-top:1px solid rgba(255,255,255,0.08);}
        .btn-logout{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;color:rgba(255,255,255,0.5);text-decoration:none;font-size:12px;font-weight:600;}
        .btn-logout:hover{background:rgba(255,255,255,0.08);color:#FF8A8A;}
        .main-content{margin-left:240px;}
        .topbar{background:white;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 4px rgba(0,0,0,0.06);position:sticky;top:0;z-index:100;}
        .topbar h1{font-size:20px;font-weight:800;color:var(--dark);margin:0;}
        .page-body{padding:24px;}

        .flash{border-radius:12px;padding:13px 18px;margin-bottom:20px;font-size:14px;font-weight:600;display:flex;align-items:center;gap:10px;}
        .flash-success{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;}
        .flash-error{background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;}

        /* TABS */
        .tabs{display:flex;gap:4px;background:white;border-radius:12px;padding:6px;box-shadow:var(--shadow);margin-bottom:20px;}
        .tab{flex:1;padding:10px;border-radius:8px;text-align:center;cursor:pointer;font-size:14px;font-weight:700;color:#888;transition:all .2s;border:none;background:none;font-family:inherit;}
        .tab.active{background:var(--primary);color:white;}
        .tab-badge{display:inline-block;background:rgba(232,64,64,0.15);color:var(--primary);border-radius:50px;padding:1px 8px;font-size:11px;font-weight:800;margin-left:5px;}
        .tab.active .tab-badge{background:rgba(255,255,255,0.25);color:white;}
        .tab-panel{display:none;}
        .tab-panel.show{display:block;}

        /* PAYMENT CARD */
        .pcard{background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:14px;border-left:4px solid var(--border);}
        .pcard.cash-card{border-color:#F59E0B;}
        .pcard.qris-card{border-color:#3B82F6;}
        .pcard-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;}
        .pcard-kode{font-size:14px;font-weight:800;color:var(--primary);font-family:monospace;}
        .pcard-time{font-size:12px;color:#888;margin-top:2px;}
        .meja-pill{background:var(--dark);color:white;border-radius:50px;padding:4px 12px;font-size:12px;font-weight:700;}
        .pcard-info{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;}
        .info-item-sm{font-size:12px;color:#888;}
        .info-item-sm strong{display:block;font-size:14px;color:var(--dark);margin-top:2px;}

        /* CASH NOMINAL */
        .nominal-section{background:#FFF7ED;border-radius:12px;padding:14px 16px;margin-bottom:12px;}
        .nominal-title{font-size:13px;font-weight:800;color:#92400E;margin:0 0 10px;}
        .nominal-quick{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
        .nq-btn{background:white;border:1.5px solid #FCD34D;border-radius:50px;padding:5px 12px;font-size:12px;font-weight:700;color:#92400E;cursor:pointer;font-family:inherit;transition:all .15s;}
        .nq-btn:hover,.nq-btn.active{background:#F59E0B;border-color:#F59E0B;color:white;}
        .nominal-inp-wrap{display:flex;gap:8px;align-items:center;}
        .nominal-prefix{background:#F8F9FA;border:1.5px solid var(--border);border-right:none;border-radius:10px 0 0 10px;padding:10px 14px;font-size:13px;font-weight:700;color:#666;}
        .nominal-inp{flex:1;border:1.5px solid var(--border);border-left:none;border-radius:0 10px 10px 0;padding:10px 14px;font-size:16px;font-weight:800;outline:none;font-family:inherit;color:var(--dark);}
        .nominal-inp:focus{border-color:#F59E0B;}
        .kembalian-display{background:#ECFDF5;border:1.5px solid #A7F3D0;border-radius:10px;padding:10px 14px;margin-top:8px;display:flex;justify-content:space-between;align-items:center;}
        .kembalian-display.minus{background:#FEF2F2;border-color:#FCA5A5;}
        .kdl{font-size:12px;font-weight:700;color:var(--green);}
        .kdl.minus{color:#DC2626;}
        .kdv{font-size:16px;font-weight:800;color:var(--green);}
        .kdv.minus{color:#DC2626;}

        /* BUKTI QRIS */
        .bukti-wrap{margin-bottom:14px;}
        .bukti-container{background:#F0F0F0;border-radius:12px;overflow:hidden;min-height:120px;display:flex;align-items:center;justify-content:center;position:relative;}
        .bukti-loading{font-size:13px;color:#888;text-align:center;padding:20px;}
        .bukti-img{width:100%;max-height:280px;object-fit:contain;display:block;cursor:pointer;border-radius:12px;}
        .bukti-error{padding:16px;text-align:center;font-size:13px;color:#DC2626;}
        .bukti-fullscreen{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:9999;align-items:center;justify-content:center;}
        .bukti-fullscreen.show{display:flex;}
        .bukti-fullscreen img{max-width:95vw;max-height:95vh;object-fit:contain;border-radius:8px;}
        .bukti-close{position:absolute;top:16px;right:16px;background:white;border:none;border-radius:50%;width:38px;height:38px;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center;font-weight:700;}

        /* ACTION BUTTONS */
        .pcard-actions{display:flex;gap:8px;}
        .btn-confirm{flex:1;background:var(--green);color:white;border:none;border-radius:10px;padding:11px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;}
        .btn-confirm:hover{background:#15803D;}
        .btn-confirm:disabled{background:#ccc;cursor:not-allowed;}
        .btn-reject{background:#FEF2F2;color:#DC2626;border:1.5px solid #FCA5A5;border-radius:10px;padding:11px 16px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;}
        .btn-reject:hover{background:#DC2626;color:white;border-color:#DC2626;}
        .btn-struk-link{background:#EFF6FF;color:#2563EB;border:none;border-radius:10px;padding:11px 14px;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;display:flex;align-items:center;gap:5px;transition:all .2s;}
        .btn-struk-link:hover{background:#2563EB;color:white;}
        .total-pill{background:var(--primary);color:white;border-radius:50px;padding:4px 12px;font-size:13px;font-weight:800;}

        /* EMPTY */
        .empty-box{text-align:center;padding:50px 20px;background:white;border-radius:var(--radius);box-shadow:var(--shadow);}
        .empty-icon{font-size:48px;opacity:.4;margin-bottom:12px;}

        /* AUTO REFRESH */
        .auto-bar{background:white;border-radius:10px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;box-shadow:var(--shadow);font-size:13px;color:#888;}
        .pulse-dot{width:8px;height:8px;border-radius:50%;background:#22C55E;animation:pd 1.5s ease-in-out infinite;}
        @keyframes pd{0%,100%{opacity:1;}50%{opacity:.3;}}
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-logo">
        <div style="font-size:28px;margin-bottom:8px">🍗</div>
        <h2>Ayam Penyet</h2>
        <p>Bendungan Batusangkar</p>
    </div>
            <div class="nav-section">
            <div class="nav-section-label">Menu Kasir</div>
            <div class="nav-item">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="menu.php">
                    <i class="fas fa-utensils"></i> Kelola Menu
                </a>
            </div>
            <div class="nav-item">
                <a href="kategori.php">
                    <i class="fas fa-tags"></i> Kategori
                </a>
            </div>
            <div class="nav-item">
                <a href="konfirmasi_bayar.php" class="active">
                    <i class="fas fa-cash-register"></i> Konfirmasi Bayar
                </a>
            </div>
            <div class="nav-item">
                <a href="qrcode.php">
                    <i class="fas fa-qrcode"></i> QR Code Meja
                </a>
            </div>
        </div>
        <div class="sidebar-footer">
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>
</div>

<!-- MAIN -->
<div class="main-content">
    <div class="topbar">
        <h1>💰 Konfirmasi Pembayaran</h1>
        <button onclick="location.reload()"
                style="background:#F0F2F5;border:1.5px solid var(--border);border-radius:8px;padding:8px 14px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>

    <div class="page-body">

        <?php if (!$columns_ok): ?>
        <!-- Warning: DB belum diupdate -->
        <div style="background:#FEF2F2;border:1.5px solid #FCA5A5;border-radius:12px;padding:16px 18px;margin-bottom:20px">
            <div style="font-size:14px;font-weight:800;color:#991B1B;margin-bottom:8px">
                ⚠️ Database Perlu Diupdate
            </div>
            <div style="font-size:13px;color:#B91C1C;margin-bottom:10px">
                Jalankan query berikut di phpMyAdmin untuk mengaktifkan fitur pembayaran:
            </div>
            <code style="display:block;background:#FFF;border:1px solid #FCA5A5;padding:12px;border-radius:8px;font-size:11px;line-height:1.8;color:#333">
                ALTER TABLE `pesanan`<br>
                &nbsp;ADD COLUMN IF NOT EXISTS `metode_bayar` ENUM('cash','qris','transfer') DEFAULT NULL,<br>
                &nbsp;ADD COLUMN IF NOT EXISTS `status_bayar` ENUM('belum_bayar','lunas') DEFAULT 'belum_bayar',<br>
                &nbsp;ADD COLUMN IF NOT EXISTS `jumlah_bayar` INT DEFAULT 0,<br>
                &nbsp;ADD COLUMN IF NOT EXISTS `kembalian` INT DEFAULT 0,<br>
                &nbsp;ADD COLUMN IF NOT EXISTS `waktu_bayar` DATETIME DEFAULT NULL,<br>
                &nbsp;ADD COLUMN IF NOT EXISTS `bukti_qris` VARCHAR(255) DEFAULT NULL,<br>
                &nbsp;ADD COLUMN IF NOT EXISTS `status_verifikasi` ENUM('menunggu','terverifikasi','ditolak') DEFAULT NULL,<br>
                &nbsp;ADD COLUMN IF NOT EXISTS `nama_pelanggan` VARCHAR(100) DEFAULT 'Pelanggan';
            </code>
        </div>
        <?php endif; ?>

        <?php if ($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
            <i class="fas <?= $flash['type']==='success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Auto refresh bar -->
        <div class="auto-bar">
            <div class="pulse-dot"></div>
            <span>Auto refresh setiap 20 detik</span>
            <span style="margin-left:auto;font-weight:700;font-family:monospace" id="cdEl">20s</span>
        </div>

        <!-- TABS -->
        <div class="tabs">
            <button class="tab active" id="tab-btn-cash" onclick="switchTab('cash')">
                💵 Tunai / Cash
                <span class="tab-badge"><?= count($cash_list) ?></span>
            </button>
            <button class="tab" id="tab-btn-qris" onclick="switchTab('qris')">
                📱 Verifikasi QRIS
                <span class="tab-badge"><?= count($qris_list) ?></span>
            </button>
        </div>

        <!-- ═══ TAB CASH ═══ -->
        <div class="tab-panel show" id="tab-cash">
            <?php if (empty($cash_list)): ?>
            <div class="empty-box">
                <div class="empty-icon">✅</div>
                <p style="font-size:14px;color:#888;margin:0">Tidak ada pembayaran tunai yang menunggu</p>
            </div>
            <?php else: ?>
            <?php foreach ($cash_list as $p):
                $total_p = (int)$p['total_harga'];
                // Nominal suggestions
                $noms = [];
                foreach ([5000,10000,20000,50000,100000] as $b) {
                    $v = (int)(ceil($total_p/$b)*$b);
                    if (!in_array($v,$noms)) $noms[] = $v;
                }
                if (!in_array($total_p,$noms)) array_unshift($noms,$total_p);
                sort($noms);
                $noms = array_slice($noms,0,5);
            ?>
            <div class="pcard cash-card" id="pcard-<?= $p['id'] ?>">
                <div class="pcard-header">
                    <div>
                        <div class="pcard-kode"><?= htmlspecialchars($p['kode_pesanan']) ?></div>
                        <div class="pcard-time">
                            <i class="fas fa-clock me-1"></i><?= date('H:i, d M', strtotime($p['tanggal'])) ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="meja-pill">Meja <?= $p['nomor_meja'] ?></span>
                        <span class="total-pill"><?= formatRupiah($p['total_harga']) ?></span>
                    </div>
                </div>

                <div class="pcard-info">
                    <?php
                        $nm_k = trim($p['nama_pelanggan'] ?? '');
                        $nm_k = ($nm_k && $nm_k !== '0') ? $nm_k : 'Pelanggan';
                    ?>
                    <div class="info-item-sm" style="grid-column:1/-1;background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:10px;padding:10px 14px;display:flex;align-items:center;gap:10px">
                        <span style="font-size:20px">👤</span>
                        <div>
                            <div style="font-size:11px;color:#6B7280;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Nama Pemesan</div>
                            <div style="font-size:16px;font-weight:800;color:#1D4ED8"><?= htmlspecialchars($nm_k) ?></div>
                        </div>
                    </div>
                    <div class="info-item-sm">Items <strong><?= $p['jml_item'] ?> item</strong></div>
                    <div class="info-item-sm">Total <strong style="color:var(--primary)"><?= formatRupiah($p['total_harga']) ?></strong></div>
                </div>

                <!-- Input Nominal -->
                <div class="nominal-section">
                    <div class="nominal-title">💵 Masukkan uang yang diterima:</div>
                    <div class="nominal-quick" id="nq-<?= $p['id'] ?>">
                        <?php foreach ($noms as $nom): ?>
                        <button class="nq-btn <?= $nom === $total_p ? 'active' : '' ?>"
                                onclick="setNominal(<?= $p['id'] ?>, <?= $nom ?>, <?= $total_p ?>, this)">
                            <?= $nom === $total_p ? '✓ Pas ' : '' ?><?= formatRupiah($nom) ?>
                        </button>
                        <?php endforeach; ?>
                        <button class="nq-btn" style="border-color:#aaa;color:#666"
                                onclick="setNominal(<?= $p['id'] ?>, 0, <?= $total_p ?>, this)">Lainnya</button>
                    </div>
                    <div class="nominal-inp-wrap">
                        <span class="nominal-prefix">Rp</span>
                        <input type="number" class="nominal-inp" id="inp-<?= $p['id'] ?>"
                               value="<?= $total_p ?>" min="<?= $total_p ?>"
                               oninput="hitungKembalian(<?= $p['id'] ?>, <?= $total_p ?>)">
                    </div>
                    <div class="kembalian-display" id="kb-<?= $p['id'] ?>">
                        <span class="kdl" id="kbl-<?= $p['id'] ?>">💰 Kembalian</span>
                        <span class="kdv" id="kbv-<?= $p['id'] ?>">Rp 0</span>
                    </div>
                </div>

                <div class="pcard-actions">
                    <button class="btn-confirm" id="btnConfirm-<?= $p['id'] ?>"
                            onclick="konfirmasiCash('<?= htmlspecialchars($p['kode_pesanan'], ENT_QUOTES) ?>', <?= $p['id'] ?>)">
                        <i class="fas fa-check-circle"></i> Konfirmasi & Terima Bayar
                    </button>
                    <a href="struk.php?kode=<?= urlencode($p['kode_pesanan']) ?>"
                       target="_blank" class="btn-struk-link">
                        <i class="fas fa-receipt"></i> Struk
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ═══ TAB QRIS ═══ -->
        <div class="tab-panel" id="tab-qris">
            <?php if (empty($qris_list)): ?>
            <div class="empty-box">
                <div class="empty-icon">✅</div>
                <p style="font-size:14px;color:#888;margin:0">Tidak ada bukti QRIS yang menunggu verifikasi</p>
            </div>
            <?php else: ?>
            <?php foreach ($qris_list as $p):
                $bukti_url = '../api/bayar.php?action=get_bukti&file=' . urlencode($p['bukti_qris']);
                $struk_url = 'struk_admin.php?kode=' . urlencode($p['kode_pesanan']);
            ?>
            <div class="pcard qris-card" id="pcard-q-<?= $p['id'] ?>">
                <div class="pcard-header">
                    <div>
                        <div class="pcard-kode"><?= htmlspecialchars($p['kode_pesanan']) ?></div>
                        <div class="pcard-time">
                            <i class="fas fa-clock me-1"></i><?= date('H:i, d M', strtotime($p['tanggal'])) ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="meja-pill">Meja <?= $p['nomor_meja'] ?></span>
                        <span class="total-pill"><?= formatRupiah($p['total_harga']) ?></span>
                    </div>
                </div>

                <div class="pcard-info">
                    <?php
                        $nm_k = trim($p['nama_pelanggan'] ?? '');
                        $nm_k = ($nm_k && $nm_k !== '0') ? $nm_k : 'Pelanggan';
                    ?>
                    <div class="info-item-sm" style="grid-column:1/-1;background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:10px;padding:10px 14px;display:flex;align-items:center;gap:10px">
                        <span style="font-size:20px">👤</span>
                        <div>
                            <div style="font-size:11px;color:#6B7280;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Nama Pemesan</div>
                            <div style="font-size:16px;font-weight:800;color:#1D4ED8"><?= htmlspecialchars($nm_k) ?></div>
                        </div>
                    </div>
                    <div class="info-item-sm">Transfer <strong style="color:var(--primary)"><?= formatRupiah($p['total_harga']) ?></strong></div>
                    <div class="info-item-sm">Tujuan <strong>083803293430</strong></div>
                </div>

                <!-- Bukti Transfer -->
                <?php if ($p['bukti_qris']): ?>
                <div class="bukti-wrap">
                    <div style="font-size:13px;font-weight:700;color:var(--dark);margin-bottom:8px">
                        📸 Bukti Transfer:
                    </div>
                    <div class="bukti-container" id="buktiBox-<?= $p['id'] ?>">
                        <div class="bukti-loading" id="buktiLoad-<?= $p['id'] ?>">
                            <i class="fas fa-spinner fa-spin me-1"></i> Memuat gambar...
                        </div>
                    </div>
                    <div style="font-size:11px;color:#888;margin-top:6px;text-align:center">
                        <i class="fas fa-search-plus me-1"></i>Klik gambar untuk perbesar
                    </div>
                    <!-- Load bukti via JS to avoid PHP escaping issues in onerror -->
                    <script>
                    (function() {
                        var img = new Image();
                        var url = <?= json_encode($bukti_url) ?>;
                        var boxId = 'buktiBox-<?= $p['id'] ?>';
                        var loadId = 'buktiLoad-<?= $p['id'] ?>';
                        img.className = 'bukti-img';
                        img.alt = 'Bukti Transfer';
                        img.onload = function() {
                            document.getElementById(loadId).style.display = 'none';
                            img.onclick = function() { lihatBukti(url); };
                            document.getElementById(boxId).appendChild(img);
                        };
                        img.onerror = function() {
                            document.getElementById(boxId).innerHTML =
                                '<div class="bukti-error">' +
                                '<i class="fas fa-exclamation-triangle"></i> Gambar tidak dapat dimuat.<br>' +
                                '<a href="' + url + '" target="_blank" style="color:#2563EB;font-weight:700">' +
                                'Buka di tab baru</a></div>';
                        };
                        img.src = url;
                    })();
                    </script>
                </div>
                <?php else: ?>
                <div style="background:#FEF2F2;border-radius:10px;padding:12px;text-align:center;font-size:13px;color:#DC2626;margin-bottom:12px">
                    ⚠️ Belum ada bukti transfer diunggah
                </div>
                <?php endif; ?>

                <!-- Info verifikasi -->
                <div style="background:#F0FDF4;border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#166534">
                    <i class="fas fa-info-circle me-1"></i>
                    Pastikan transfer <strong><?= formatRupiah($p['total_harga']) ?></strong>
                    ke <strong>083803293430</strong> sudah benar
                </div>

                <div class="pcard-actions">
                    <button class="btn-confirm"
                            onclick="verifikasiQris('<?= htmlspecialchars($p['kode_pesanan'], ENT_QUOTES) ?>', 'terima', <?= $p['id'] ?>)">
                        <i class="fas fa-check-circle"></i> Terima & Verifikasi
                    </button>
                    <button class="btn-reject"
                            onclick="verifikasiQris('<?= htmlspecialchars($p['kode_pesanan'], ENT_QUOTES) ?>', 'tolak', <?= $p['id'] ?>)">
                        <i class="fas fa-times"></i> Tolak
                    </button>
                    <a href="<?= htmlspecialchars($struk_url) ?>" target="_blank" class="btn-struk-link">
                        <i class="fas fa-receipt"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /page-body -->
</div><!-- /main-content -->

<!-- Fullscreen bukti viewer -->
<div class="bukti-fullscreen" id="buktiFullscreen" onclick="tutupBukti()">
    <button class="bukti-close" onclick="tutupBukti()">✕</button>
    <img id="buktiFullImg" src="" alt="Bukti Transfer" style="opacity:0;transition:opacity .3s">
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Tab switch
function switchTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('show'));
    document.getElementById('tab-btn-' + tab).classList.add('active');
    document.getElementById('tab-' + tab).classList.add('show');
}

// ── Set nominal cepat
function setNominal(id, val, total, btn) {
    if (val === 0) {
        document.getElementById('inp-' + id).value = '';
        document.getElementById('inp-' + id).focus();
    } else {
        document.getElementById('inp-' + id).value = val;
    }
    document.querySelectorAll('#nq-' + id + ' .nq-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    hitungKembalian(id, total);
}

// ── Hitung kembalian
function hitungKembalian(id, total) {
    const uang = parseInt(document.getElementById('inp-' + id).value) || 0;
    const kem  = uang - total;
    const box  = document.getElementById('kb-' + id);
    const lbl  = document.getElementById('kbl-' + id);
    const val  = document.getElementById('kbv-' + id);
    const btn  = document.getElementById('btnConfirm-' + id);
    if (!box) return;

    if (kem < 0) {
        box.classList.add('minus'); lbl.classList.add('minus'); val.classList.add('minus');
        lbl.textContent = '⚠️ Uang Kurang';
        val.textContent = '- Rp ' + Math.abs(kem).toLocaleString('id-ID');
        if (btn) btn.disabled = true;
    } else {
        box.classList.remove('minus'); lbl.classList.remove('minus'); val.classList.remove('minus');
        lbl.textContent = '💰 Kembalian';
        val.textContent = 'Rp ' + kem.toLocaleString('id-ID');
        if (btn) btn.disabled = false;
    }
}

// Init kembalian
document.querySelectorAll('[id^="inp-"]').forEach(inp => {
    const id    = inp.id.replace('inp-', '');
    const total = parseInt(inp.min) || 0;
    if (total > 0) hitungKembalian(id, total);
});

// ── Konfirmasi Cash
function konfirmasiCash(kode, id) {
    const jumlah = parseInt(document.getElementById('inp-' + id).value) || 0;
    const total  = parseInt(document.getElementById('inp-' + id).min) || 0;
    if (jumlah < total) {
        Swal.fire({
            icon: 'warning',
            title: 'Uang Tidak Cukup!',
            text: 'Jumlah uang yang diterima kurang dari total tagihan.',
            confirmButtonColor: '#E84040',
            confirmButtonText: 'OK'
        });
        return;
    }

    const kem = jumlah - total;
    Swal.fire({
        title: 'Konfirmasi Pembayaran TUNAI',
        html:
            '<div style="text-align:left;font-family:\'Plus Jakarta Sans\',sans-serif;font-size:14px">' +
            '<div style="background:#F9FAFB;border-radius:10px;padding:14px 16px;margin-bottom:8px">' +
            '<div style="display:flex;justify-content:space-between;margin-bottom:6px"><span style="color:#6B7280">Total Tagihan</span><strong>Rp ' + total.toLocaleString('id-ID') + '</strong></div>' +
            '<div style="display:flex;justify-content:space-between;margin-bottom:6px"><span style="color:#6B7280">Uang Diterima</span><strong style="color:#16A34A">Rp ' + jumlah.toLocaleString('id-ID') + '</strong></div>' +
            '<hr style="margin:8px 0;border-color:#E5E7EB">' +
            '<div style="display:flex;justify-content:space-between"><span style="color:#6B7280">Kembalian</span><strong style="color:#2563EB;font-size:16px">Rp ' + kem.toLocaleString('id-ID') + '</strong></div>' +
            '</div>' +
            '</div>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16A34A',
        cancelButtonColor: '#6B7280',
        confirmButtonText: '<i class="fas fa-check-circle"></i> Ya, Konfirmasi!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (!result.isConfirmed) return;

        const btn = document.getElementById('btnConfirm-' + id);
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

        fetch('api_kasir.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=konfirmasi_cash&kode=' + encodeURIComponent(kode) + '&jumlah_bayar=' + jumlah
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Pembayaran Dikonfirmasi!',
                    html: '<p style="font-size:15px">Kembalian ke pelanggan:</p>' +
                          '<div style="font-size:28px;font-weight:800;color:#16A34A">Rp ' + data.kembalian.toLocaleString('id-ID') + '</div>',
                    confirmButtonColor: '#16A34A',
                    confirmButtonText: 'Selesai',
                    timer: 4000,
                    timerProgressBar: true
                }).then(() => {
                    const card = document.getElementById('pcard-' + id);
                    if (card) card.remove();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: data.msg || 'Terjadi kesalahan, coba lagi.',
                    confirmButtonColor: '#E84040'
                });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Konfirmasi & Terima Bayar';
            }
        })
        .catch(() => {
            Swal.fire({
                icon: 'error',
                title: 'Koneksi Error',
                text: 'Tidak dapat terhubung ke server, periksa koneksi Anda.',
                confirmButtonColor: '#E84040'
            });
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Konfirmasi & Terima Bayar';
        });
    });
}

// ── Verifikasi QRIS
function verifikasiQris(kode, keputusan, id) {
    const isOk = keputusan === 'terima';

    Swal.fire({
        title: isOk ? 'Terima Pembayaran QRIS?' : 'Tolak Bukti Transfer?',
        text: isOk
            ? 'Bukti transfer akan diverifikasi dan pesanan dikonfirmasi lunas.'
            : 'Pelanggan akan diminta untuk mengupload ulang bukti transfer.',
        icon: isOk ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: isOk ? '#16A34A' : '#E84040',
        cancelButtonColor: '#6B7280',
        confirmButtonText: isOk ? '<i class="fas fa-check-circle"></i> Ya, Terima!' : '<i class="fas fa-times"></i> Ya, Tolak!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (!result.isConfirmed) return;

        fetch('api_kasir.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=verifikasi_qris&kode=' + encodeURIComponent(kode) + '&keputusan=' + keputusan
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: isOk ? 'success' : 'info',
                    title: isOk ? 'Pembayaran Terverifikasi!' : 'Bukti Ditolak',
                    text: isOk ? 'Pesanan telah dikonfirmasi lunas.' : 'Pelanggan akan diminta upload ulang.',
                    confirmButtonColor: isOk ? '#16A34A' : '#E84040',
                    timer: 3000,
                    timerProgressBar: true
                }).then(() => {
                    const card = document.getElementById('pcard-q-' + id);
                    if (card) card.remove();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: data.msg || 'Terjadi kesalahan, coba lagi.',
                    confirmButtonColor: '#E84040'
                });
            }
        })
        .catch(() => {
            Swal.fire({
                icon: 'error',
                title: 'Koneksi Error',
                text: 'Tidak dapat terhubung ke server.',
                confirmButtonColor: '#E84040'
            });
        });
    });
}

// ── Fullscreen viewer
function lihatBukti(src) {
    const img = document.getElementById('buktiFullImg');
    img.style.opacity = '0';
    img.src = src;
    img.onload  = () => { img.style.opacity = '1'; };
    document.getElementById('buktiFullscreen').classList.add('show');
}
function tutupBukti() {
    document.getElementById('buktiFullscreen').classList.remove('show');
}

// ── Auto refresh countdown
let cd = 20;
setInterval(() => {
    cd--;
    const el = document.getElementById('cdEl');
    if (el) el.textContent = cd + 's';
    if (cd <= 0) location.reload();
}, 1000);
</script>
</body>
</html>