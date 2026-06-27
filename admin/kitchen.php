<?php
// ============================================
// admin/kitchen.php
// Kitchen Display System (KDS)
// Layar monitor di dapur untuk melihat pesanan
// ============================================
require_once '../koneksi.php';
requireAdminLogin();

// Ambil pesanan aktif (pending + diproses)
$pesanan_aktif = [];
$res = $conn->query("
    SELECT p.id, p.kode_pesanan, p.nomor_meja, p.tanggal, p.status, p.catatan,
           p.total_harga, p.status_bayar
    FROM pesanan p
    WHERE p.status IN ('pending','diproses')
    AND DATE(p.tanggal) = CURDATE()
    ORDER BY FIELD(p.status,'pending','diproses'), p.tanggal ASC
    LIMIT 20
");
while ($row = $res->fetch_assoc()) {
    // Ambil detail
    $s = $conn->prepare("SELECT nama_menu, jumlah FROM detail_pesanan WHERE id_pesanan=?");
    $s->bind_param('i', $row['id']);
    $s->execute();
    $r = $s->get_result();
    $row['items'] = [];
    while ($item = $r->fetch_assoc()) $row['items'][] = $item;
    $s->close();
    $pesanan_aktif[] = $row;
}

// Stats singkat
$stat = [
    'pending'  => $conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='pending' AND DATE(tanggal)=CURDATE()")->fetch_assoc()['c'],
    'diproses' => $conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='diproses' AND DATE(tanggal)=CURDATE()")->fetch_assoc()['c'],
    'selesai'  => $conn->query("SELECT COUNT(*) c FROM pesanan WHERE status='selesai' AND DATE(tanggal)=CURDATE()")->fetch_assoc()['c'],
];

$now = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>Kitchen Display - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --pending:  #F59E0B;
            --diproses: #3B82F6;
            --selesai:  #22C55E;
            --danger:   #EF4444;
            --dark:     #0F172A;
            --surface:  #1E293B;
            --text:     #F1F5F9;
            --muted:    #94A3B8;
            --border:   rgba(255,255,255,0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--dark);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── TOP BAR ── */
        .kds-topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        .kds-logo { font-size: 16px; font-weight: 900; display: flex; align-items: center; gap: 10px; }
        .kds-clock { font-size: 22px; font-weight: 900; font-family: monospace; color: var(--pending); }
        .kds-nav { display: flex; gap: 8px; }
        .kds-nav a {
            background: rgba(255,255,255,0.07); color: var(--muted);
            border-radius: 8px; padding: 7px 14px; text-decoration: none;
            font-size: 12px; font-weight: 700; transition: all .2s;
            border: 1px solid var(--border);
        }
        .kds-nav a:hover { background: rgba(255,255,255,0.12); color: white; }

        /* ── STATS ── */
        .stats-row {
            display: flex; gap: 12px; padding: 16px 20px;
            background: rgba(255,255,255,0.02);
            border-bottom: 1px solid var(--border);
        }
        .stat-chip {
            display: flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.06); border-radius: 10px;
            padding: 10px 16px; border: 1px solid var(--border);
        }
        .stat-chip .s-icon { font-size: 18px; }
        .stat-chip .s-num { font-size: 22px; font-weight: 900; }
        .stat-chip .s-lbl { font-size: 11px; color: var(--muted); }
        .stat-chip.pending  .s-num { color: var(--pending); }
        .stat-chip.diproses .s-num { color: var(--diproses); }
        .stat-chip.selesai  .s-num { color: var(--selesai); }

        /* ── ORDER GRID ── */
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 14px;
            padding: 16px 20px 80px;
        }

        /* ── ORDER CARD ── */
        .order-card {
            background: var(--surface);
            border-radius: 14px;
            border: 2px solid var(--border);
            overflow: hidden;
            transition: transform .2s;
            position: relative;
        }
        .order-card:hover { transform: translateY(-2px); }
        .order-card.pending  { border-color: var(--pending); }
        .order-card.diproses { border-color: var(--diproses); }

        /* urgency pulse for old orders */
        .order-card.urgent { animation: urgentPulse 2s ease-in-out infinite; }
        @keyframes urgentPulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
            50%      { box-shadow: 0 0 0 6px rgba(239,68,68,0.3); }
        }

        .order-header {
            padding: 12px 16px 10px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .order-meja {
            font-size: 28px; font-weight: 900;
            line-height: 1; display: flex; flex-direction: column;
        }
        .meja-lbl { font-size: 10px; font-weight: 600; color: var(--muted); letter-spacing: 1px; text-transform: uppercase; }
        .meja-num { color: white; }

        .order-meta { text-align: right; }
        .order-kode { font-size: 11px; font-family: monospace; color: var(--muted); }
        .order-time { font-size: 14px; font-weight: 700; color: var(--pending); margin-top: 2px; }
        .order-age { font-size: 11px; color: var(--muted); }

        .order-status-badge {
            position: absolute; top: 10px; left: 50%; transform: translateX(-50%);
            border-radius: 50px; padding: 3px 12px; font-size: 11px; font-weight: 800;
        }
        .status-pending  { background: rgba(245,158,11,0.2); color: var(--pending); border: 1px solid rgba(245,158,11,0.3); }
        .status-diproses { background: rgba(59,130,246,0.2); color: var(--diproses); border: 1px solid rgba(59,130,246,0.3); }

        .divider { border: none; border-top: 1px solid var(--border); margin: 0; }

        .order-items { padding: 12px 16px; }
        .order-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 0; border-bottom: 1px solid var(--border);
        }
        .order-item:last-child { border: none; }
        .item-qty {
            width: 32px; height: 32px; border-radius: 8px;
            background: rgba(255,255,255,0.08);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 900; flex-shrink: 0;
            color: white;
        }
        .item-name { font-size: 14px; font-weight: 700; flex: 1; color: var(--text); }

        .order-note {
            margin: 0 16px 12px;
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.2);
            border-radius: 8px; padding: 8px 12px;
            font-size: 12px; color: var(--pending);
            display: flex; gap: 6px;
        }

        /* Action buttons */
        .order-actions {
            display: flex; gap: 8px; padding: 12px 16px;
            background: rgba(255,255,255,0.02);
            border-top: 1px solid var(--border);
        }
        .btn-action {
            flex: 1; border: none; border-radius: 8px; padding: 10px;
            font-size: 13px; font-weight: 700; cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all .2s; display: flex; align-items: center;
            justify-content: center; gap: 6px;
        }
        .btn-proses  { background: rgba(59,130,246,0.2); color: var(--diproses); border: 1px solid rgba(59,130,246,0.3); }
        .btn-proses:hover  { background: var(--diproses); color: white; }
        .btn-selesai { background: rgba(34,197,94,0.2);  color: var(--selesai);  border: 1px solid rgba(34,197,94,0.3); }
        .btn-selesai:hover { background: var(--selesai);  color: white; }
        .btn-action:disabled { opacity: .4; cursor: not-allowed; }

        /* ── EMPTY STATE ── */
        .empty-kds {
            grid-column: 1 / -1;
            text-align: center; padding: 80px 20px;
        }
        .empty-kds .icon { font-size: 80px; opacity: .25; margin-bottom: 20px; }
        .empty-kds h3 { font-size: 22px; font-weight: 800; color: var(--muted); }
        .empty-kds p  { font-size: 14px; color: rgba(148,163,184,.6); margin-top: 8px; }

        /* ── BOTTOM BAR ── */
        .bottom-bar {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: var(--surface); border-top: 1px solid var(--border);
            padding: 10px 20px; display: flex; align-items: center; gap: 12px;
            font-size: 12px; color: var(--muted);
        }
        .refresh-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--selesai); animation: pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.3;} }
        .bb-timer { margin-left: auto; font-family: monospace; font-weight: 700; color: var(--pending); }

        /* Toast */
        #kds-toast {
            position: fixed; top: 80px; right: 20px; z-index: 9999;
            display: flex; flex-direction: column; gap: 8px; pointer-events: none;
        }
        .toast-item {
            background: var(--surface); border: 1px solid var(--border);
            border-left: 4px solid var(--selesai);
            border-radius: 12px; padding: 12px 16px;
            font-size: 13px; font-weight: 700; color: white;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            animation: slideInRight .3s ease;
            max-width: 280px;
        }
        @keyframes slideInRight { from{opacity:0;transform:translateX(20px);} to{opacity:1;transform:translateX(0);} }

        /* Fullscreen toggle */
        .btn-fullscreen {
            background: rgba(255,255,255,0.07); border: 1px solid var(--border);
            color: var(--muted); border-radius: 8px; padding: 7px 12px;
            cursor: pointer; font-size: 13px; transition: all .2s;
        }
        .btn-fullscreen:hover { background: rgba(255,255,255,0.12); color: white; }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="kds-topbar">
    <div class="kds-logo">
        🍗 <span>Kitchen Display</span>
    </div>
    <div class="kds-clock" id="kds-clock"><?= $now ?></div>
    <div class="kds-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
        <a href="konfirmasi_bayar.php"><i class="fas fa-cash-register me-1"></i>Kasir</a>
        <button class="btn-fullscreen" onclick="toggleFullscreen()"><i class="fas fa-expand"></i></button>
    </div>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-chip pending">
        <span class="s-icon">⏳</span>
        <div>
            <div class="s-num" id="statPending"><?= $stat['pending'] ?></div>
            <div class="s-lbl">Pending</div>
        </div>
    </div>
    <div class="stat-chip diproses">
        <span class="s-icon">🔥</span>
        <div>
            <div class="s-num" id="statDiproses"><?= $stat['diproses'] ?></div>
            <div class="s-lbl">Dimasak</div>
        </div>
    </div>
    <div class="stat-chip selesai">
        <span class="s-icon">✅</span>
        <div>
            <div class="s-num" id="statSelesai"><?= $stat['selesai'] ?></div>
            <div class="s-lbl">Selesai Hari Ini</div>
        </div>
    </div>
    <div style="margin-left:auto;display:flex;align-items:center;gap:8px;color:var(--muted);font-size:12px">
        <div class="refresh-dot"></div>
        Auto-refresh setiap 15 detik
    </div>
</div>

<!-- Toast container -->
<div id="kds-toast"></div>

<!-- Order Grid -->
<div class="orders-grid" id="ordersGrid">
    <?php if (empty($pesanan_aktif)): ?>
    <div class="empty-kds">
        <div class="icon">🍽️</div>
        <h3>Dapur Tenang</h3>
        <p>Belum ada pesanan aktif saat ini</p>
    </div>
    <?php else: ?>
    <?php
    foreach ($pesanan_aktif as $p):
        $age_minutes = round((time() - strtotime($p['tanggal'])) / 60);
        $urgent = ($p['status'] === 'pending' && $age_minutes >= 10) || ($p['status'] === 'diproses' && $age_minutes >= 20);
    ?>
    <div class="order-card <?= $p['status'] ?> <?= $urgent ? 'urgent' : '' ?>" id="card-<?= $p['id'] ?>">
        <div class="order-header">
            <div class="order-meja">
                <span class="meja-lbl">Meja</span>
                <span class="meja-num"><?= $p['nomor_meja'] ?></span>
            </div>
            <div class="order-status-badge status-<?= $p['status'] ?>">
                <?= $p['status'] === 'pending' ? '⏳ Baru Masuk' : '🔥 Dimasak' ?>
            </div>
            <div class="order-meta">
                <div class="order-kode"><?= htmlspecialchars(substr($p['kode_pesanan'], -8)) ?></div>
                <div class="order-time"><?= date('H:i', strtotime($p['tanggal'])) ?></div>
                <div class="order-age" style="<?= $urgent ? 'color:#EF4444;font-weight:700' : '' ?>">
                    <?= $age_minutes ?> mnt lalu<?= $urgent ? ' ⚠️' : '' ?>
                </div>
            </div>
        </div>
        <hr class="divider">

        <div class="order-items">
            <?php foreach ($p['items'] as $item): ?>
            <div class="order-item">
                <div class="item-qty"><?= $item['jumlah'] ?></div>
                <div class="item-name"><?= htmlspecialchars($item['nama_menu']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($p['catatan']): ?>
        <div class="order-note">
            <i class="fas fa-sticky-note" style="margin-top:1px;flex-shrink:0"></i>
            <span><?= htmlspecialchars($p['catatan']) ?></span>
        </div>
        <?php endif; ?>

        <div class="order-actions">
            <?php if ($p['status'] === 'pending'): ?>
            <button class="btn-action btn-proses" onclick="ubahStatus(<?= $p['id'] ?>, 'diproses')">
                <i class="fas fa-fire"></i> Mulai Masak
            </button>
            <?php else: ?>
            <button class="btn-action btn-selesai" onclick="ubahStatus(<?= $p['id'] ?>, 'selesai')">
                <i class="fas fa-check"></i> Selesai Disajikan
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Bottom Bar -->
<div class="bottom-bar">
    <div class="refresh-dot"></div>
    <span>Kitchen Display System — <?= APP_NAME ?></span>
    <span class="bb-timer" id="bbTimer">15s</span>
</div>

<script>
let latestOrderId = 0;
let refreshCountdown = 15;

// Clock
setInterval(() => {
    const now = new Date();
    document.getElementById('kds-clock').textContent =
        now.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}, 1000);

// Countdown
setInterval(() => {
    refreshCountdown--;
    document.getElementById('bbTimer').textContent = refreshCountdown + 's';
    if (refreshCountdown <= 0) {
        refreshCountdown = 15;
        fetchOrders();
    }
}, 1000);

// Ubah status pesanan
function ubahStatus(id, status) {
    const card = document.getElementById('card-' + id);
    if (card) {
        card.style.opacity = '.5';
        card.style.pointerEvents = 'none';
    }
    fetch('api_admin.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=update_status&id=${id}&status=${status}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (status === 'selesai') {
                card?.remove();
                showToast('✅ Pesanan selesai disajikan!');
                if (!document.querySelector('.order-card')) {
                    document.getElementById('ordersGrid').innerHTML =
                        `<div class="empty-kds">
                            <div class="icon">🍽️</div>
                            <h3>Dapur Tenang</h3>
                            <p>Semua pesanan sudah selesai</p>
                        </div>`;
                }
            } else {
                // Refresh card
                fetchOrders();
            }
        } else {
            if (card) { card.style.opacity='1'; card.style.pointerEvents=''; }
            showToast('❌ Gagal update status');
        }
    })
    .catch(() => {
        if (card) { card.style.opacity='1'; card.style.pointerEvents=''; }
    });
}

// Poll orders dari server
function fetchOrders() {
    fetch('api/notif.php?last_id=' + latestOrderId, {credentials:'include'})
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;

            // Update stats
            const ps = document.getElementById('statPending');
            const dp = document.getElementById('statDiproses');
            if (ps) ps.textContent = data.pending;

            // New orders? reload page for full update
            if (data.new_count > 0) {
                latestOrderId = data.latest_id;
                showToast('🔔 ' + data.new_count + ' pesanan baru masuk!');
                playBeep();
                setTimeout(() => location.reload(), 2000);
            }
        })
        .catch(() => {});
}

// Beep sound
function playBeep() {
    try {
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        osc.connect(g); g.connect(ctx.destination);
        osc.type = 'square';
        osc.frequency.setValueAtTime(660, ctx.currentTime);
        osc.frequency.setValueAtTime(880, ctx.currentTime + .15);
        osc.frequency.setValueAtTime(660, ctx.currentTime + .3);
        g.gain.setValueAtTime(.4, ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + .5);
        osc.start(); osc.stop(ctx.currentTime + .5);
    } catch(e) {}
}

// Toast
function showToast(msg) {
    const container = document.getElementById('kds-toast');
    const t = document.createElement('div');
    t.className = 'toast-item';
    t.textContent = msg;
    container.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .4s'; setTimeout(()=>t.remove(),400); }, 4000);
}

// Fullscreen
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.();
    } else {
        document.exitFullscreen?.();
    }
}

// Start polling
fetchOrders();
</script>
</body>
</html>
