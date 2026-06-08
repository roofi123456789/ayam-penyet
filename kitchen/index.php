<?php
require_once '../koneksi.php';
requireRole(['kitchen', 'admin']);

// Handle POST actions dari kitchen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);

    if ($_POST['action'] === 'proses' && $id > 0) {
        // Hanya bisa proses jika kasir sudah konfirmasi pembayaran
        $cek = $conn->prepare("SELECT id, status_verifikasi, status_bayar FROM pesanan WHERE id=? AND status='pending'");
        $cek->bind_param('i', $id);
        $cek->execute();
        $row = $cek->get_result()->fetch_assoc();
        $cek->close();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan atau sudah diproses']);
            exit;
        }
        if ($row['status_verifikasi'] !== 'terverifikasi' && $row['status_bayar'] !== 'lunas') {
            echo json_encode(['success' => false, 'message' => '⚠️ Belum bisa diproses! Kasir belum mengkonfirmasi pembayaran.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE pesanan SET status='diproses' WHERE id=? AND status='pending'");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Pesanan mulai dimasak!' : 'Gagal update']);
        exit;
    }

    if ($_POST['action'] === 'siap' && $id > 0) {
        $stmt = $conn->prepare("UPDATE pesanan SET status='selesai' WHERE id=? AND status='diproses'");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Pesanan sudah selesai dimasak!' : 'Gagal update']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
    exit;
}

// GET: ambil pesanan aktif (pending + diproses)
// Pending: terbagi 2 — belum bayar (dikunci) & sudah bayar (bisa diproses)
function getPesananAktif($conn) {
    $list = [];
    $res = $conn->query("
        SELECT p.id, p.kode_pesanan, p.nomor_meja, p.tanggal, p.status, p.catatan, p.total_harga,
               p.nama_pelanggan, p.status_bayar, p.status_verifikasi, p.metode_bayar
        FROM pesanan p
        WHERE p.status IN ('pending','diproses')
        AND DATE(p.tanggal) = CURDATE()
        ORDER BY FIELD(p.status,'diproses','pending'), p.tanggal ASC
        LIMIT 30
    ");
    while ($row = $res->fetch_assoc()) {
        $s = $conn->prepare("SELECT nama_menu, jumlah FROM detail_pesanan WHERE id_pesanan=?");
        $s->bind_param('i', $row['id']);
        $s->execute();
        $r = $s->get_result();
        $row['items'] = [];
        while ($item = $r->fetch_assoc()) $row['items'][] = $item;
        $s->close();
        $list[] = $row;
    }
    return $list;
}

function getPesananSelesai($conn) {
    $list = [];
    $res = $conn->query("
        SELECT p.id, p.kode_pesanan, p.nomor_meja, p.tanggal, p.status, p.catatan, p.total_harga,
               p.nama_pelanggan, p.status_bayar, p.metode_bayar, p.waktu_bayar
        FROM pesanan p
        WHERE p.status = 'selesai'
        AND DATE(p.tanggal) = CURDATE()
        ORDER BY p.tanggal DESC
        LIMIT 50
    ");
    while ($row = $res->fetch_assoc()) {
        $s = $conn->prepare("SELECT nama_menu, jumlah FROM detail_pesanan WHERE id_pesanan=?");
        $s->bind_param('i', $row['id']);
        $s->execute();
        $r = $s->get_result();
        $row['items'] = [];
        while ($item = $r->fetch_assoc()) $row['items'][] = $item;
        $s->close();
        $list[] = $row;
    }
    return $list;
}

$pesanan_aktif   = getPesananAktif($conn);
$pesanan_selesai = getPesananSelesai($conn);

// Hitung stat — pending dibagi: menunggu_bayar vs siap_diproses
$stat_menunggu_bayar = 0;
$stat_siap_diproses  = 0;
foreach ($pesanan_aktif as $p) {
    if ($p['status'] === 'pending') {
        if ($p['status_verifikasi'] === 'terverifikasi' || $p['status_bayar'] === 'lunas') {
            $stat_siap_diproses++;
        } else {
            $stat_menunggu_bayar++;
        }
    }
}
$stat = [
    'menunggu_bayar' => $stat_menunggu_bayar,
    'siap_diproses'  => $stat_siap_diproses,
    'diproses'       => (int)$conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='diproses' AND DATE(tanggal)=CURDATE()")->fetch_assoc()['c'],
    'selesai'        => (int)$conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='selesai' AND DATE(tanggal)=CURDATE()")->fetch_assoc()['c'],
];
$now = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Display - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --pending-locked: #6B7280; --pending-ready: #F59E0B;
            --diproses: #3B82F6; --selesai: #22C55E;
            --dark: #0F172A; --surface: #1E293B; --card: #263548;
            --text: #F1F5F9; --muted: #94A3B8; --border: rgba(255,255,255,0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--dark); color: var(--text); min-height: 100vh; }

        .kds-topbar {
            background: var(--surface); border-bottom: 1px solid var(--border);
            padding: 12px 24px; display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        .kds-brand { display: flex; align-items: center; gap: 12px; }
        .kds-brand .logo { width: 44px; height: 44px; background: linear-gradient(135deg,#22C55E,#16A34A); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .kds-brand h1 { font-size: 18px; font-weight: 800; color: var(--text); margin: 0; }
        .kds-brand p { font-size: 11px; color: var(--muted); margin: 0; }
        .kds-stats { display: flex; gap: 10px; }
        .kds-stat { text-align: center; background: rgba(255,255,255,0.05); padding: 8px 14px; border-radius: 10px; }
        .kds-stat-val { font-size: 20px; font-weight: 900; line-height: 1; }
        .kds-stat-lbl { font-size: 10px; color: var(--muted); font-weight: 600; text-transform: uppercase; }
        .kds-stat-val.locked { color: var(--pending-locked); }
        .kds-stat-val.ready { color: var(--pending-ready); }
        .kds-stat-val.diproses { color: var(--diproses); }
        .kds-stat-val.selesai { color: var(--selesai); }
        .kds-time { font-size: 20px; font-weight: 800; color: var(--text); font-variant-numeric: tabular-nums; }
        .kds-logout { background: rgba(239,68,68,0.15); color: #FCA5A5; border: 1px solid rgba(239,68,68,0.3); padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; transition: all 0.2s; }
        .kds-logout:hover { background: rgba(239,68,68,0.3); color: white; }

        .kds-tabs {
            background: var(--surface); border-bottom: 1px solid var(--border);
            padding: 0 24px; display: flex; gap: 4px;
        }
        .kds-tab {
            padding: 12px 20px; font-size: 13px; font-weight: 700; cursor: pointer;
            color: var(--muted); border: none; background: none; border-bottom: 3px solid transparent;
            transition: all 0.2s; font-family: inherit; display: flex; align-items: center; gap: 8px;
        }
        .kds-tab:hover { color: var(--text); }
        .kds-tab.active { color: white; border-bottom-color: var(--selesai); }
        .kds-tab .tab-cnt {
            background: rgba(255,255,255,0.15); color: white; border-radius: 50px;
            padding: 2px 8px; font-size: 11px; font-weight: 800; min-width: 22px; text-align: center;
        }
        .kds-tab.active .tab-cnt { background: var(--selesai); }
        .kds-tab.tab-riwayat.active { border-bottom-color: #8B5CF6; }
        .kds-tab.tab-riwayat.active .tab-cnt { background: #8B5CF6; }

        .kds-panel { display: none; }
        .kds-panel.active { display: block; }

        .kds-grid { padding: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }

        /* Order Cards */
        .order-card { background: var(--card); border-radius: 16px; overflow: hidden; border: 2px solid transparent; transition: all 0.3s; }
        .order-card.status-pending-locked { border-color: var(--pending-locked); opacity: 0.7; }
        .order-card.status-pending-ready  { border-color: var(--pending-ready); }
        .order-card.status-diproses       { border-color: var(--diproses); animation: pulse-blue 2s infinite; }
        .order-card.status-selesai        { border-color: var(--selesai); opacity: 0.75; }
        @keyframes pulse-blue { 0%,100%{border-color: var(--diproses);} 50%{border-color: rgba(59,130,246,0.4);} }

        .order-header { padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; }
        .order-card.status-pending-locked .order-header { background: rgba(107,114,128,0.15); }
        .order-card.status-pending-ready  .order-header { background: rgba(245,158,11,0.15); }
        .order-card.status-diproses       .order-header { background: rgba(59,130,246,0.15); }
        .order-card.status-selesai        .order-header { background: rgba(34,197,94,0.12); }

        .order-meta { display: flex; align-items: center; gap: 10px; }
        .meja-badge { background: rgba(255,255,255,0.12); color: white; padding: 6px 14px; border-radius: 20px; font-size: 14px; font-weight: 800; }
        .order-code { font-size: 11px; color: var(--muted); font-weight: 600; }
        .order-pemesan { font-size: 12px; color: #93C5FD; font-weight: 700; margin-top: 2px; }

        .status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-pill.locked  { background: rgba(107,114,128,0.2); color: #9CA3AF; border: 1px solid rgba(107,114,128,0.4); }
        .status-pill.ready   { background: rgba(245,158,11,0.2); color: var(--pending-ready); border: 1px solid rgba(245,158,11,0.4); }
        .status-pill.diproses{ background: rgba(59,130,246,0.2); color: var(--diproses); border: 1px solid rgba(59,130,246,0.4); }
        .status-pill.selesai { background: rgba(34,197,94,0.2); color: var(--selesai); border: 1px solid rgba(34,197,94,0.4); }

        /* Locked badge */
        .locked-banner {
            background: rgba(107,114,128,0.2); border-top: 1px solid rgba(107,114,128,0.3);
            padding: 10px 16px; display: flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 700; color: #9CA3AF;
        }
        .locked-banner i { color: #EF4444; }

        .order-timer { display: flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; color: var(--muted); border-bottom: 1px solid var(--border); }
        .order-timer.urgent { color: #EF4444; }

        .order-items { padding: 12px 16px; }
        .item-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .item-row:last-child { border-bottom: none; }
        .item-qty { width: 32px; height: 32px; background: rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 900; color: white; flex-shrink: 0; }
        .item-name { flex: 1; margin: 0 10px; font-size: 14px; font-weight: 600; color: var(--text); }

        .order-note { padding: 8px 16px; background: rgba(245,158,11,0.1); border-top: 1px solid rgba(245,158,11,0.2); }
        .order-note span { font-size: 12px; color: #FCD34D; }

        .order-actions { padding: 12px 16px; display: flex; gap: 8px; }
        .btn-proses {
            flex: 1; background: linear-gradient(135deg, #F59E0B, #D97706); color: white; border: none;
            border-radius: 10px; padding: 12px; font-size: 13px; font-weight: 800; cursor: pointer;
            transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-proses:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(245,158,11,0.4); }
        .btn-masak {
            flex: 1; background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; border: none;
            border-radius: 10px; padding: 12px; font-size: 13px; font-weight: 800; cursor: pointer;
            transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-masak:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(59,130,246,0.4); }
        .btn-siap {
            flex: 1; background: linear-gradient(135deg, #22C55E, #16A34A); color: white; border: none;
            border-radius: 10px; padding: 12px; font-size: 13px; font-weight: 800; cursor: pointer;
            transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-siap:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(34,197,94,0.4); }
        .btn-siap:disabled, .btn-proses:disabled, .btn-masak:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .selesai-label { padding: 10px 16px; text-align: center; font-size: 13px; font-weight: 700; color: var(--selesai); background: rgba(34,197,94,0.08); }

        /* Progress bar for diproses */
        .cooking-progress {
            padding: 8px 16px; background: rgba(59,130,246,0.1); border-top: 1px solid rgba(59,130,246,0.2);
            display: flex; align-items: center; gap: 8px; font-size: 12px; color: #93C5FD; font-weight: 700;
        }
        .cooking-progress .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--diproses); animation: blink 1s ease-in-out infinite; }
        @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:.2;} }

        /* RIWAYAT */
        .riwayat-list { padding: 20px; }
        .riwayat-card {
            background: var(--card); border-radius: 12px; padding: 14px 18px;
            margin-bottom: 10px; display: flex; align-items: center; gap: 14px;
            border-left: 4px solid var(--selesai);
        }
        .riwayat-icon { width: 40px; height: 40px; background: rgba(34,197,94,0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .riwayat-info { flex: 1; }
        .riwayat-kode { font-size: 13px; font-weight: 800; color: #93C5FD; }
        .riwayat-meta { font-size: 12px; color: var(--muted); margin-top: 3px; }
        .riwayat-items { font-size: 12px; color: var(--text); margin-top: 4px; font-weight: 600; }
        .riwayat-time { text-align: right; }
        .riwayat-waktu { font-size: 12px; color: var(--muted); font-weight: 600; }
        .riwayat-status { font-size: 11px; padding: 3px 10px; border-radius: 50px; background: rgba(34,197,94,0.2); color: var(--selesai); font-weight: 800; margin-top: 4px; display: inline-block; }

        .empty-state { text-align: center; padding: 80px 20px; color: var(--muted); }
        .empty-state .icon { font-size: 64px; margin-bottom: 16px; opacity: 0.5; }
        .empty-state h3 { font-size: 20px; color: var(--selesai); margin-bottom: 8px; }

        .toast-container { position: fixed; top: 80px; right: 20px; z-index: 9999; }
        .toast-msg { background: #1E293B; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 14px 18px; margin-bottom: 8px; color: white; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.3); transform: translateX(120%); transition: transform 0.3s ease; }
        .toast-msg.show { transform: translateX(0); }
        .toast-msg.success { border-left: 3px solid #22C55E; }
        .toast-msg.error { border-left: 3px solid #EF4444; }
        .toast-msg.warn { border-left: 3px solid #F59E0B; }

        @media (max-width: 600px) {
            .kds-stats { display: none; }
            .kds-grid { grid-template-columns: 1fr; padding: 12px; }
            .riwayat-list { padding: 12px; }
        }
    </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<!-- Topbar -->
<div class="kds-topbar">
    <div class="kds-brand">
        <div class="logo">🍳</div>
        <div>
            <h1>Kitchen Display</h1>
            <p><?= APP_NAME ?></p>
        </div>
    </div>
    <div class="kds-stats">
        <div class="kds-stat">
            <div class="kds-stat-val locked" id="statLocked"><?= $stat['menunggu_bayar'] ?></div>
            <div class="kds-stat-lbl">🔒 Menunggu Bayar</div>
        </div>
        <div class="kds-stat">
            <div class="kds-stat-val ready" id="statReady"><?= $stat['siap_diproses'] ?></div>
            <div class="kds-stat-lbl">✅ Siap Diproses</div>
        </div>
        <div class="kds-stat">
            <div class="kds-stat-val diproses" id="statDiproses"><?= $stat['diproses'] ?></div>
            <div class="kds-stat-lbl">🔥 Dimasak</div>
        </div>
        <div class="kds-stat">
            <div class="kds-stat-val selesai" id="statSelesai"><?= $stat['selesai'] ?></div>
            <div class="kds-stat-lbl">✅ Selesai</div>
        </div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="kds-time" id="clockDisplay"><?= $now ?></div>
        <a href="../logout.php" class="kds-logout"><i class="fas fa-sign-out-alt me-1"></i>Keluar</a>
    </div>
</div>

<!-- Tabs -->
<div class="kds-tabs">
    <button class="kds-tab active" id="tabAktif" onclick="switchTab('aktif')">
        <i class="fas fa-fire"></i> Pesanan Aktif
        <span class="tab-cnt"><?= count($pesanan_aktif) ?></span>
    </button>
    <button class="kds-tab tab-riwayat" id="tabRiwayat" onclick="switchTab('riwayat')">
        <i class="fas fa-history"></i> Sudah Selesai Hari Ini
        <span class="tab-cnt"><?= count($pesanan_selesai) ?></span>
    </button>
</div>

<!-- Panel Aktif -->
<div class="kds-panel active" id="panelAktif">
    <div class="kds-grid" id="orderGrid">
    <?php if (empty($pesanan_aktif)): ?>
        <div class="empty-state" style="grid-column:1/-1">
            <div class="icon">✅</div>
            <h3>Semua Pesanan Selesai!</h3>
            <p>Tidak ada pesanan yang perlu diproses saat ini.</p>
        </div>
    <?php else: ?>
    <?php foreach ($pesanan_aktif as $p):
        $created  = strtotime($p['tanggal']);
        $elapsed  = time() - $created;
        $mins     = floor($elapsed / 60);
        $urgent   = $mins >= 15;
        $nm_raw   = trim($p['nama_pelanggan'] ?? '');
        $nm_show  = ($nm_raw && $nm_raw !== '0') ? $nm_raw : 'Pelanggan';

        // Tentukan card state
        $sudah_bayar = ($p['status_verifikasi'] === 'terverifikasi' || $p['status_bayar'] === 'lunas');
        if ($p['status'] === 'pending') {
            $card_class  = $sudah_bayar ? 'status-pending-ready' : 'status-pending-locked';
            $pill_class  = $sudah_bayar ? 'ready' : 'locked';
            $pill_text   = $sudah_bayar ? '✅ Siap Diproses' : '🔒 Menunggu Bayar';
        } else {
            $card_class  = 'status-diproses';
            $pill_class  = 'diproses';
            $pill_text   = '🔥 Sedang Dimasak';
        }
    ?>
        <div class="order-card <?= $card_class ?>" id="card-<?= $p['id'] ?>">
            <div class="order-header">
                <div class="order-meta">
                    <span class="meja-badge">Meja <?= $p['nomor_meja'] ?></span>
                    <div>
                        <div style="font-size:13px;font-weight:700"><?= htmlspecialchars($p['kode_pesanan']) ?></div>
                        <div class="order-code"><?= count($p['items']) ?> item · <?= formatRupiah($p['total_harga']) ?></div>
                        <div class="order-pemesan">👤 <?= htmlspecialchars($nm_show) ?></div>
                    </div>
                </div>
                <span class="status-pill <?= $pill_class ?>"><?= $pill_text ?></span>
            </div>

            <div class="order-timer <?= $urgent ? 'urgent' : '' ?>">
                <i class="fas fa-stopwatch"></i>
                <span><?= $mins > 0 ? "{$mins} menit yang lalu" : "Baru masuk" ?></span>
                <?php if ($urgent): ?><i class="fas fa-exclamation-circle ms-1 text-danger"></i><?php endif; ?>
            </div>

            <?php if ($p['status'] === 'diproses'): ?>
            <div class="cooking-progress">
                <div class="dot"></div>
                <span>Sedang dimasak oleh kitchen...</span>
            </div>
            <?php endif; ?>

            <?php if (!$sudah_bayar && $p['status'] === 'pending'): ?>
            <div class="locked-banner">
                <i class="fas fa-lock"></i>
                <span>Menunggu konfirmasi pembayaran kasir</span>
            </div>
            <?php endif; ?>

            <div class="order-items">
                <?php foreach ($p['items'] as $item): ?>
                <div class="item-row">
                    <div class="item-qty"><?= $item['jumlah'] ?></div>
                    <div class="item-name"><?= htmlspecialchars($item['nama_menu']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($p['catatan'])): ?>
            <div class="order-note">
                <span><i class="fas fa-sticky-note me-1"></i><?= htmlspecialchars($p['catatan']) ?></span>
            </div>
            <?php endif; ?>

            <div class="order-actions">
                <?php if ($p['status'] === 'pending' && $sudah_bayar): ?>
                <button class="btn-proses" onclick="prosesOrder(<?= $p['id'] ?>, this)">
                    <i class="fas fa-fire me-1"></i> Mulai Masak
                </button>
                <?php elseif ($p['status'] === 'pending' && !$sudah_bayar): ?>
                <button class="btn-proses" disabled style="background:#374151;cursor:not-allowed">
                    <i class="fas fa-lock me-1"></i> Tunggu Konfirmasi Kasir
                </button>
                <?php elseif ($p['status'] === 'diproses'): ?>
                <button class="btn-siap" onclick="siapOrder(<?= $p['id'] ?>, this)">
                    <i class="fas fa-check-circle me-1"></i> Selesai Dimasak!
                </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<!-- Panel Riwayat -->
<div class="kds-panel" id="panelRiwayat">
    <div class="riwayat-list">
        <?php if (empty($pesanan_selesai)): ?>
        <div class="empty-state">
            <div class="icon">📋</div>
            <h3>Belum Ada Riwayat</h3>
            <p>Pesanan yang selesai hari ini akan muncul di sini.</p>
        </div>
        <?php else: ?>
        <div style="font-size:13px;color:var(--muted);margin-bottom:14px;font-weight:600">
            📅 Pesanan selesai hari ini — <?= date('d F Y') ?> (<?= count($pesanan_selesai) ?> pesanan)
        </div>
        <?php foreach ($pesanan_selesai as $p):
            $nm_raw   = trim($p['nama_pelanggan'] ?? '');
            $nm_show  = ($nm_raw && $nm_raw !== '0') ? $nm_raw : 'Pelanggan';
            $item_names = array_map(fn($i) => $i['jumlah'].'× '.$i['nama_menu'], $p['items']);
            $metode_label = ['cash'=>'💵 Tunai','qris'=>'📱 QRIS','transfer'=>'🏦 Transfer'];
            $mb_label = $metode_label[$p['metode_bayar'] ?? ''] ?? '—';
            $sb = $p['status_bayar'] ?? 'belum_bayar';
        ?>
        <div class="riwayat-card">
            <div class="riwayat-icon">✅</div>
            <div class="riwayat-info">
                <div class="riwayat-kode"><?= htmlspecialchars($p['kode_pesanan']) ?></div>
                <div class="riwayat-meta">
                    🪑 Meja <?= $p['nomor_meja'] ?> &nbsp;·&nbsp; 👤 <?= htmlspecialchars($nm_show) ?>
                </div>
                <div class="riwayat-items"><?= htmlspecialchars(implode(', ', array_slice($item_names, 0, 4))) ?><?= count($item_names) > 4 ? '...' : '' ?></div>
                <?php if (!empty($p['catatan'])): ?>
                <div style="font-size:11px;color:#FCD34D;margin-top:3px">📝 <?= htmlspecialchars($p['catatan']) ?></div>
                <?php endif; ?>
            </div>
            <div class="riwayat-time">
                <div class="riwayat-waktu"><?= date('H:i', strtotime($p['tanggal'])) ?></div>
                <div class="riwayat-status">🍽️ Selesai Dimasak</div>
                <div style="font-size:11px;margin-top:3px">
                    <?php if ($sb === 'lunas'): ?>
                    <span style="color:#22C55E;font-weight:700">✅ <?= $mb_label ?></span>
                    <?php else: ?>
                    <span style="color:#F59E0B;font-weight:700">⏳ Belum Bayar</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;font-weight:800;color:white;margin-top:2px"><?= formatRupiah($p['total_harga']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('panelAktif').classList.toggle('active', tab === 'aktif');
    document.getElementById('panelRiwayat').classList.toggle('active', tab === 'riwayat');
    document.getElementById('tabAktif').classList.toggle('active', tab === 'aktif');
    document.getElementById('tabRiwayat').classList.toggle('active', tab === 'riwayat');
}

function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('clockDisplay').textContent = `${h}:${m}:${s}`;
}
setInterval(updateClock, 1000);

function showToast(msg, type='success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast-msg ${type}`;
    t.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':type==='warn'?'exclamation-triangle':'exclamation-circle'}"></i> ${msg}`;
    c.appendChild(t);
    setTimeout(() => t.classList.add('show'), 50);
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 400); }, 4000);
}

function prosesOrder(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Memulai...';
    fetch('', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=proses&id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('🔥 Pesanan mulai dimasak!', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-fire me-1"></i> Mulai Masak';
        }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-fire me-1"></i> Mulai Masak'; });
}

function siapOrder(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...';
    fetch('', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=siap&id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Pesanan selesai dimasak!', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Selesai Dimasak!';
        }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Selesai Dimasak!'; });
}

// Auto refresh 20 detik
setInterval(() => location.reload(), 20000);
</script>
</body>
</html>
