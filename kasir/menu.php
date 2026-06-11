<?php
require_once '../koneksi.php';
requireKasirLogin();
// Pending payment badge
// Safe query - handle missing columns gracefully
$stat_pending_pay = 0;
try {
    $r = $conn->query("SELECT COUNT(*) as c FROM pesanan WHERE (metode_bayar='cash' OR metode_bayar='qris') AND status_verifikasi='menunggu' AND (status_bayar='belum_bayar' OR status_bayar IS NULL)");
    if ($r) $stat_pending_pay = (int)$r->fetch_assoc()['c'];
} catch (Exception $e) { $stat_pending_pay = 0; }

// Handle DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        // Hapus gambar jika ada
        $stmt = $conn->prepare("SELECT gambar FROM menu WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && $row['gambar'] && $row['gambar'] !== 'default.jpg') {
            $imgPath = __DIR__ . '/../assets/images/' . $row['gambar'];
            if (file_exists($imgPath)) unlink($imgPath);
        }

        $stmt = $conn->prepare("DELETE FROM menu WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Menu berhasil dihapus!');
    }
    redirect('/ayam-penyet/kasir/menu.php');
}

// Ambil menu
$filter_kat = (int)($_GET['kat'] ?? 0);
$search     = sanitize($_GET['q'] ?? '');

$where = "WHERE 1=1";
if ($filter_kat > 0) $where .= " AND m.id_kategori = $filter_kat";
if ($search) $where .= " AND m.nama_menu LIKE '%$search%'";

$menu_list = [];
$result = $conn->query("SELECT m.*, k.nama_kategori FROM menu m LEFT JOIN kategori k ON m.id_kategori=k.id $where ORDER BY m.id_kategori, m.nama_menu");
while ($row = $result->fetch_assoc()) $menu_list[] = $row;

$kategori_list = [];
$res2 = $conn->query("SELECT * FROM kategori ORDER BY id");
while ($row = $res2->fetch_assoc()) $kategori_list[] = $row;

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #E84040; --primary-dark: #C42E2E;
            --dark: #1A1A2E; --bg: #F0F2F5;
            --surface: #fff; --text: #2D2D2D; --text-muted: #888;
            --border: #E5E7EB; --radius: 14px; --shadow: 0 2px 16px rgba(0,0,0,0.07);
        }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); margin: 0; }
        
        .sidebar {
            position: fixed; top: 0; left: 0; width: 240px; height: 100vh;
            background: #1A1A2E; z-index: 1000;
            display: flex; flex-direction: column;
        }
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
        .topbar { background: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 4px rgba(0,0,0,0.06); position: sticky; top: 0; z-index: 100; }
        .topbar h1 { font-size: 20px; font-weight: 800; color: var(--dark); margin: 0; }
        .btn-tambah { background: var(--primary); color: white; border: none; border-radius: 10px; padding: 10px 18px; font-size: 13px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif; cursor: pointer; }
        .btn-tambah:hover { background: var(--primary-dark); color: white; transform: translateY(-1px); }

        .page-body { padding: 24px; }
        .flash-alert { border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .flash-success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
        .flash-error   { background: #FEF2F2; border: 1px solid #FCA5A5; color: #991B1B; }

        .filter-bar { background: white; border-radius: var(--radius); padding: 16px 20px; margin-bottom: 20px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .search-input { border: 1.5px solid var(--border); border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; font-family: 'Plus Jakarta Sans', sans-serif; flex: 1; min-width: 200px; transition: border 0.2s; }
        .search-input:focus { border-color: var(--primary); }
        .filter-select { border: 1.5px solid var(--border); border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; font-family: 'Plus Jakarta Sans', sans-serif; cursor: pointer; background: white; }
        .btn-search { background: var(--primary); color: white; border: none; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif; }

        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
        .menu-card { background: white; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); transition: transform 0.2s; }
        .menu-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }

        .menu-card-img {
            height: 160px;
            background: linear-gradient(135deg, #FFE8E8, #FFD0D0);
            display: flex; align-items: center; justify-content: center;
            font-size: 54px;
            position: relative;
        }
        .menu-card-img img { width: 100%; height: 100%; object-fit: cover; }

        .availability-toggle {
            position: absolute; top: 10px; right: 10px;
            background: rgba(0,0,0,0.5); color: white;
            border: none; border-radius: 50px;
            padding: 4px 12px; font-size: 11px; font-weight: 700;
            cursor: pointer; backdrop-filter: blur(8px);
            transition: all 0.2s;
        }
        .availability-toggle.available { background: rgba(22,163,74,0.8); }
        .availability-toggle.unavailable { background: rgba(220,38,38,0.8); }

        .menu-card-body { padding: 14px 16px 16px; }
        .menu-kat { font-size: 11px; color: var(--primary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px; }
        .menu-nama { font-size: 15px; font-weight: 800; color: var(--dark); margin: 0 0 6px; }
        .menu-desc { font-size: 12px; color: var(--text-muted); margin: 0 0 10px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .menu-harga { font-size: 16px; font-weight: 800; color: var(--primary); margin: 0 0 12px; }

        .card-actions { display: flex; gap: 8px; }
        .btn-edit { flex: 1; padding: 8px; border-radius: 8px; background: #EFF6FF; border: none; color: #2563EB; font-size: 12px; font-weight: 700; text-decoration: none; text-align: center; transition: all 0.2s; cursor: pointer; }
        .btn-edit:hover { background: #2563EB; color: white; }
        .btn-hapus { flex: 1; padding: 8px; border-radius: 8px; background: #FEF2F2; border: none; color: #DC2626; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Plus Jakarta Sans', sans-serif; }
        .btn-hapus:hover { background: #DC2626; color: white; }

        .empty-state { text-align: center; padding: 80px 30px; color: var(--text-muted); }
        .empty-state i { font-size: 60px; opacity: 0.3; margin-bottom: 16px; }
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
            <div class="nav-section-label">Menu Kasir</div>
            <div class="nav-item">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="menu.php" class="active">
                    <i class="fas fa-utensils"></i> Kelola Menu
                </a>
            </div>
            <div class="nav-item">
                <a href="kategori.php">
                    <i class="fas fa-tags"></i> Kategori
                </a>
            </div>
            <div class="nav-item">
                <a href="konfirmasi_bayar.php">
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

<div class="main-content">
    <div class="topbar">
        <h1>🍽️ Kelola Menu</h1>
        <a href="tambah_menu.php" class="btn-tambah">
            <i class="fas fa-plus"></i> Tambah Menu
        </a>
    </div>

    <div class="page-body">
        <?php if ($flash): ?>
        <div class="flash-alert flash-<?= $flash['type'] ?>">
            <i class="fas <?= $flash['type']==='success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
        <?php endif; ?>

        <div class="filter-bar">
            <form method="GET" style="display:flex;gap:10px;flex:1;flex-wrap:wrap">
                <input type="text" name="q" class="search-input" placeholder="Cari nama menu..."
                       value="<?= htmlspecialchars($search) ?>">
                <select name="kat" class="filter-select">
                    <option value="0">Semua Kategori</option>
                    <?php foreach ($kategori_list as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $filter_kat==$k['id']?'selected':'' ?>>
                        <?= htmlspecialchars($k['nama_kategori']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-search"><i class="fas fa-search me-1"></i>Cari</button>
            </form>
            <span style="font-size:13px;color:var(--text-muted)"><?= count($menu_list) ?> menu</span>
        </div>

        <?php if (empty($menu_list)): ?>
        <div class="empty-state">
            <div><i class="fas fa-utensils"></i></div>
            <p>Belum ada menu. <a href="tambah_menu.php" style="color:var(--primary)">Tambah menu pertama!</a></p>
        </div>
        <?php else: ?>
        <div class="menu-grid">
            <?php
            $foodEmojis = ['🍗','🍖','🍳','🥘','🍜','🥤','🧃','🍟','🥙','🍱'];
            foreach ($menu_list as $i => $menu):
                $emoji = $foodEmojis[$menu['id'] % count($foodEmojis)];
            ?>
            <div class="menu-card" id="menuCard-<?= $menu['id'] ?>">
                <div class="menu-card-img">
                    <?php
                    $imgPath = __DIR__ . '/../assets/images/' . $menu['gambar'];
                    if ($menu['gambar'] && $menu['gambar'] !== 'default.jpg' && file_exists($imgPath)):
                    ?>
                    <img src="../assets/images/<?= htmlspecialchars($menu['gambar']) ?>" alt="<?= htmlspecialchars($menu['nama_menu']) ?>">
                    <?php else: ?>
                    <?= $emoji ?>
                    <?php endif; ?>

                    <button class="availability-toggle <?= $menu['tersedia'] ? 'available' : 'unavailable' ?>"
                            id="toggle-<?= $menu['id'] ?>"
                            onclick="toggleTersedia(<?= $menu['id'] ?>)">
                        <?= $menu['tersedia'] ? '✅ Tersedia' : '❌ Habis' ?>
                    </button>
                </div>

                <div class="menu-card-body">
                    <p class="menu-kat"><?= htmlspecialchars($menu['nama_kategori'] ?? '-') ?></p>
                    <p class="menu-nama"><?= htmlspecialchars($menu['nama_menu']) ?></p>
                    <?php if ($menu['deskripsi']): ?>
                    <p class="menu-desc"><?= htmlspecialchars($menu['deskripsi']) ?></p>
                    <?php endif; ?>
                    <p class="menu-harga"><?= formatRupiah($menu['harga']) ?></p>
                    <div class="card-actions">
                        <a href="edit_menu.php?id=<?= $menu['id'] ?>" class="btn-edit">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                        <form method="POST" style="flex:1" onsubmit="return confirm('Hapus menu ini?')">
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $menu['id'] ?>">
                            <button type="submit" class="btn-hapus" style="width:100%">
                                <i class="fas fa-trash me-1"></i>Hapus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleTersedia(id) {
    fetch('api_kasir.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=toggle_tersedia&id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const btn = document.getElementById('toggle-' + id);
            if (data.tersedia == 1) {
                btn.className = 'availability-toggle available';
                btn.textContent = '✅ Tersedia';
            } else {
                btn.className = 'availability-toggle unavailable';
                btn.textContent = '❌ Habis';
            }
        }
    });
}
</script>
</body>
</html>
