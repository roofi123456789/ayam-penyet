<?php
require_once '../koneksi.php';
requireRole(['kitchen', 'admin']);

// Handle konfirmasi pesanan siap dari kitchen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);

    if ($_POST['action'] === 'siap' && $id > 0) {
        $stmt = $conn->prepare("UPDATE pesanan SET status='selesai' WHERE id=? AND status='diproses'");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Pesanan ditandai selesai!' : 'Gagal update']);
        exit;
    }

    if ($_POST['action'] === 'proses' && $id > 0) {
        $stmt = $conn->prepare("UPDATE pesanan SET status='diproses' WHERE id=? AND status='pending'");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Pesanan mulai diproses!' : 'Gagal update']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
    exit;
}

// GET: ambil pesanan aktif (pending + diproses)
function getPesananAktif($conn) {
    $list = [];
    $res = $conn->query("
        SELECT p.id, p.kode_pesanan, p.nomor_meja, p.tanggal, p.status, p.catatan, p.total_harga,
               p.nama_pelanggan, p.status_bayar
        FROM pesanan p
        WHERE p.status IN ('pending','diproses')
        AND DATE(p.tanggal) = CURDATE()
        ORDER BY FIELD(p.status,'pending','diproses'), p.tanggal ASC
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

// GET: ambil riwayat pesanan selesai hari ini
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

$pesanan_aktif  = getPesananAktif($conn);
$pesanan_selesai = getPesananSelesai($conn);
$stat = [
    'pending'  => (int)$conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='pending' AND DATE(tanggal)=CURDATE()")->fetch_assoc()['c'],
    'diproses' => (int)$conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='diproses' AND DATE(tanggal)=CURDATE()")->fetch_assoc()['c'],
    'selesai'  => (int)$conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='selesai' AND DATE(tanggal)=CURDATE()")->fetch_assoc()['c'],
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
            --pending: #F59E0B; --diproses: #3B82F6; --selesai: #22C55E;
            --dark: #0F172A; --surface: #1E293B; --card: #263548;
            --text: #F1F5F9; --muted: #94A3B8; --border: rgba(255,255,255,0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--dark); color: var(--text); min-height: 100vh; }

        /* TOPBAR */
        .kds-topbar {
            background: var(--surface); border-bottom: 1px solid var(--border);
            padding: 12px 24px; display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        .kds-brand { display: flex; align-items: center; gap: 12px; }
        .kds-brand .logo { width: 44px; height: 44px; background: linear-gradient(135deg,#22C55E,#16A34A); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .kds-brand h1 { font-size: 18px; font-weight: 800; color: var(--text); margin: 0; }
        .kds-brand p { font-size: 11px; color: var(--muted); margin: 0; }
        .kds-stats { display: flex; gap: 12px; }
        .kds-stat { text-align: center; background: rgba(255,255,255,0.05); padding: 8px 16px; border-radius: 10px; }
        .kds-stat-val { font-size: 22px; font-weight: 900; line-height: 1; }
        .kds-stat-lbl { font-size: 10px; color: var(--muted); font-weight: 600; text-transform: uppercase; }
        .kds-stat-val.pending { color: var(--pending); }
        .kds-stat-val.diproses { color: var(--diproses); }
        .kds-stat-val.selesai { color: var(--selesai); }
        .kds-time { font-size: 20px; font-weight: 800; color: var(--text); font-variant-numeric: tabular-nums; }
        .kds-logout { background: rgba(239,68,68,0.15); color: #FCA5A5; border: 1px solid rgba(239,68,68,0.3); padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; transition: all 0.2s; }
        .kds-logout:hover { background: rgba(239,68,68,0.3); color: white; }

        /* TABS */
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

        /* PANELS */
        .kds-panel { display: none; }
        .kds-panel.active { display: block; }

        /* GRID */
        .kds-grid { padding: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }

        /* ORDER CARDS */
        .order-card { background: var(--card); border-radius: 16px; overflow: hidden; border: 2px solid transparent; transition: all 0.3s; }
        .order-card.status-pending { border-color: var(--pending); }
        .order-card.status-diproses { border-color: var(--diproses); animation: pulse-blue 2s infinite; }
        .order-card.status-selesai { border-color: var(--selesai); opacity: 0.75; }
        @keyframes pulse-blue { 0%,100%{border-color: var(--diproses);} 50%{border-color: rgba(59,130,246,0.4);} }

        .order-header { padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; }
        .order-card.status-pending .order-header { background: rgba(245,158,11,0.15); }
        .order-card.status-diproses .order-header { background: rgba(59,130,246,0.15); }
        .order-card.status-selesai .order-header { background: rgba(34,197,94,0.12); }
        .order-meta { display: flex; align-items: center; gap: 10px; }
        .meja-badge { background: rgba(255,255,255,0.12); color: white; padding: 6px 14px; border-radius: 20px; font-size: 14px; font-weight: 800; }
        .order-code { font-size: 11px; color: var(--muted); font-weight: 600; }
        .order-pemesan { font-size: 12px; color: #93C5FD; font-weight: 700; margin-top: 2px; }
        .status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-pill.pending { background: rgba(245,158,11,0.2); color: var(--pending); border: 1px solid rgba(245,158,11,0.4); }
        .status-pill.diproses { background: rgba(59,130,246,0.2); color: var(--diproses); border: 1px solid rgba(59,130,246,0.4); }
        .status-pill.selesai { background: rgba(34,197,94,0.2); color: var(--selesai); border: 1px solid rgba(34,197,94,0.4); }

        /* Timer */
        .order-timer { display: flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; color: var(--muted); border-bottom: 1px solid var(--border); }
        .order-timer.urgent { color: #EF4444; }

        /* Items */
        .order-items { padding: 12px 16px; }
        .item-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .item-row:last-child { border-bottom: none; }
        .item-qty { width: 32px; height: 32px; background: rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 900; color: white; flex-shrink: 0; }
        .item-name { flex: 1; margin: 0 10px; font-size: 14px; font-weight: 600; color: var(--text); }

        /* Catatan */
        .order-note { padding: 8px 16px; background: rgba(245,158,11,0.1); border-top: 1px solid rgba(245,158,11,0.2); }
        .order-note span { font-size: 12px; color: #FCD34D; }

        /* Buttons */
        .order-actions { padding: 12px 16px; display: flex; gap: 8px; }
        .btn-proses {
            flex: 1; background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; border: none;
            border-radius: 10px; padding: 12px; font-size: 13px; font-weight: 800; cursor: pointer;
            transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-proses:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(59,130,246,0.4); }
        .btn-siap {
            flex: 1; background: linear-gradient(135deg, #22C55E, #16A34A); color: white; border: none;
            border-radius: 10px; padding: 12px; font-size: 13px; font-weight: 800; cursor: pointer;
            transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-siap:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(34,197,94,0.4); }
        .btn-siap:disabled, .btn-proses:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* Selesai label */
        .selesai-label {
            padding: 10px 16px; text-align: center; font-size: 13px; font-weight: 700; color: var(--selesai);
            background: rgba(34,197,94,0.08);
        }
        .selesai-bayar { font-size: 11px; color: var(--muted); margin-top: 2px; font-weight: 600; }

        /* RIWAYAT tabel list */
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
        .riwayat-bayar { font-size: 11px; color: var(--muted); margin-top: 2px; }

        /* Empty state */
        .empty-state { text-align: center; padding: 80px 20px; color: var(--muted); }
        .empty-state .icon { font-size: 64px; margin-bottom: 16px; opacity: 0.5; }
        .empty-state h3 { font-size: 20px; color: var(--selesai); margin-bottom: 8px; }

        /* Toast */
        .toast-container { position: fixed; top: 80px; right: 20px; z-index: 9999; }
        .toast-msg { background: #1E293B; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 14px 18px; margin-bottom: 8px; color: white; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.3); transform: translateX(120%); transition: transform 0.3s ease; }
        .toast-msg.show { transform: translateX(0); }
        .toast-msg.success { border-left: 3px solid #22C55E; }
        .toast-msg.error { border-left: 3px solid #EF4444; }

        @media (max-width: 600px) {
            .kds-stats { display: none; }
            .kds-grid { grid-template-columns: 1fr; padding: 12px; }
            .riwayat-list { padding: 12px; }
        }
    </style>
</head>
<body>

<!-- Toast container -->
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
            <div class="kds-stat-val pending" id="statPending"><?= $stat['pending'] ?></div>
            <div class="kds-stat-lbl">Pending</div>
        </div>
        <div class="kds-stat">
            <div class="kds-stat-val diproses" id="statDiproses"><?= $stat['diproses'] ?></div>
            <div class="kds-stat-lbl">Diproses</div>
        </div>
        <div class="kds-stat">
            <div class="kds-stat-val selesai" id="statSelesai"><?= $stat['selesai'] ?></div>
            <div class="kds-stat-lbl">Selesai</div>
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
        <i class="fas fa-history"></i> Riwayat Hari Ini
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
    <?php foreach ($pesanan_aktif as $p): ?>
        <?php
        $created = strtotime($p['tanggal']);
        $elapsed = time() - $created;
        $mins = floor($elapsed / 60);
        $urgent = $mins >= 15;
        $nm_raw = trim($p['nama_pelanggan'] ?? '');
        $nm_show = ($nm_raw && $nm_raw !== '0') ? $nm_raw : 'Pelanggan';
        ?>
        <div class="order-card status-<?= $p['status'] ?>" id="card-<?= $p['id'] ?>">
            <div class="order-header">
                <div class="order-meta">
                    <span class="meja-badge">Meja <?= $p['nomor_meja'] ?></span>
                    <div>
                        <div style="font-size:13px;font-weight:700"><?= htmlspecialchars($p['kode_pesanan']) ?></div>
                        <div class="order-code"><?= count($p['items']) ?> item · <?= formatRupiah($p['total_harga']) ?></div>
                        <div class="order-pemesan">👤 <?= htmlspecialchars($nm_show) ?></div>
                    </div>
                </div>
                <span class="status-pill <?= $p['status'] ?>">
                    <?= $p['status'] === 'pending' ? '⏳ Pending' : '🔥 Diproses' ?>
                </span>
            </div>

            <div class="order-timer <?= $urgent ? 'urgent' : '' ?>">
                <i class="fas fa-stopwatch"></i>
                <span><?= $mins > 0 ? "{$mins} menit yang lalu" : "Baru masuk" ?></span>
                <?php if ($urgent): ?><i class="fas fa-exclamation-circle ms-1 text-danger"></i><?php endif; ?>
            </div>

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
                <?php if ($p['status'] === 'pending'): ?>
                <button class="btn-proses" onclick="prosesOrder(<?= $p['id'] ?>, this)">
                    <i class="fas fa-fire me-1"></i> Mulai Proses
                </button>
                <?php else: ?>
                <button class="btn-siap" onclick="siapOrder(<?= $p['id'] ?>, this)">
                    <i class="fas fa-check-circle me-1"></i> Pesanan Siap!
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
            📅 Riwayat pesanan selesai — <?= date('d F Y') ?> (<?= count($pesanan_selesai) ?> pesanan)
        </div>
        <?php foreach ($pesanan_selesai as $p):
            $nm_raw = trim($p['nama_pelanggan'] ?? '');
            $nm_show = ($nm_raw && $nm_raw !== '0') ? $nm_raw : 'Pelanggan';
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
                <div class="riwayat-status">Selesai</div>
                <div class="riwayat-bayar">
                    <?php if ($sb === 'lunas'): ?>
                    <span style="color:#22C55E;font-weight:700;font-size:11px">✅ <?= $mb_label ?></span>
                    <?php else: ?>
                    <span style="color:#F59E0B;font-weight:700;font-size:11px">⏳ Belum Bayar</span>
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
// Tab switching
function switchTab(tab) {
    document.getElementById('panelAktif').classList.toggle('active', tab === 'aktif');
    document.getElementById('panelRiwayat').classList.toggle('active', tab === 'riwayat');
    document.getElementById('tabAktif').classList.toggle('active', tab === 'aktif');
    document.getElementById('tabRiwayat').classList.toggle('active', tab === 'riwayat');
}

// Clock
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('clockDisplay').textContent = `${h}:${m}:${s}`;
}
setInterval(updateClock, 1000);

// Toast
function showToast(msg, type='success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast-msg ${type}`;
    t.innerHTML = `<i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
    c.appendChild(t);
    setTimeout(() => t.classList.add('show'), 50);
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 400); }, 3000);
}

// Proses order
function prosesOrder(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Memproses...';
    fetch('', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=proses&id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Pesanan mulai diproses!', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-fire me-1"></i> Mulai Proses';
        }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-fire me-1"></i> Mulai Proses'; });
}

// Siap order
function siapOrder(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Mengirim...';
    fetch('', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=siap&id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Pesanan siap! Menunggu kasir.', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Pesanan Siap!';
        }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Pesanan Siap!'; });
}

// Auto refresh setiap 20 detik
setInterval(() => location.reload(), 20000);
</script>
</body>
</html>
