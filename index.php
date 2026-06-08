<?php
require_once 'koneksi.php';

$nomor_meja = getNomorMeja();

// Ambil semua kategori
$query_kategori = "SELECT * FROM kategori ORDER BY id ASC";
$result_kategori = $conn->query($query_kategori);
$kategori_list = [];
while ($row = $result_kategori->fetch_assoc()) {
    $kategori_list[] = $row;
}

// Ambil semua menu yang tersedia
$query_menu = "SELECT m.*, k.nama_kategori FROM menu m 
               LEFT JOIN kategori k ON m.id_kategori = k.id 
               WHERE m.tersedia = 1 
               ORDER BY m.id_kategori, m.nama_menu ASC";
$result_menu = $conn->query($query_menu);
$menu_list = [];
while ($row = $result_menu->fetch_assoc()) {
    $menu_list[$row['id_kategori']][] = $row;
}

$keranjang = getKeranjang();
$total_keranjang = getTotalKeranjang();
$jumlah_keranjang = getJumlahKeranjang();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #E84040;
            --primary-dark: #C42E2E;
            --primary-light: #FF6B6B;
            --secondary: #FF8C00;
            --accent: #FFC107;
            --dark: #1A1A2E;
            --surface: #FFFFFF;
            --bg: #F5F5F5;
            --text: #2D2D2D;
            --text-muted: #888;
            --border: #EBEBEB;
            --shadow: 0 2px 20px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 40px rgba(0,0,0,0.12);
            --radius: 16px;
            --radius-sm: 10px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding-bottom: 100px;
            margin: 0;
        }

        /* ===== HEADER ===== */
        .app-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 60%, #8B1A1A 100%);
            padding: 20px 20px 60px;
            position: relative;
            overflow: hidden;
        }

        .app-header::before {
            content: '';
            position: absolute;
            top: -50px; right: -50px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .app-header::after {
            content: '';
            position: absolute;
            bottom: -30px; left: -30px;
            width: 150px; height: 150px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            position: relative; z-index: 1;
        }

        .restaurant-info h1 {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.2;
        }

        .restaurant-info p {
            font-size: 12px;
            color: rgba(255,255,255,0.75);
            margin: 2px 0 0;
        }

        .logo-circle {
            width: 46px; height: 46px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .meja-badge {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 50px;
            padding: 6px 14px;
            color: white;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .search-wrapper {
            position: relative; z-index: 1;
        }

        .search-input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            border: none;
            border-radius: 50px;
            font-size: 14px;
            background: rgba(255,255,255,0.15);
            color: white;
            backdrop-filter: blur(10px);
            outline: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .search-input::placeholder { color: rgba(255,255,255,0.65); }

        .search-icon {
            position: absolute;
            left: 18px; top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.7);
            font-size: 15px;
        }

        /* ===== KATEGORI TABS ===== */
        .category-section {
            margin-top: -28px;
            position: relative; z-index: 2;
            padding: 0 16px 8px;
        }

        .category-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 4px 2px 8px;
            scrollbar-width: none;
        }

        .category-scroll::-webkit-scrollbar { display: none; }

        .cat-pill {
            flex-shrink: 0;
            padding: 9px 18px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            border: 2px solid transparent;
        }

        .cat-pill.inactive {
            background: white;
            color: var(--text-muted);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .cat-pill.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 14px rgba(232,64,64,0.35);
        }

        .cat-pill:hover.inactive {
            background: #fff0f0;
            color: var(--primary);
            border-color: var(--primary);
        }

        /* ===== PROMO BANNER ===== */
        .promo-banner {
            margin: 0 16px 20px;
            background: linear-gradient(135deg, #FF8C00, var(--accent));
            border-radius: var(--radius);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .promo-banner .promo-icon { font-size: 32px; }

        .promo-banner h6 {
            margin: 0; color: white;
            font-weight: 800; font-size: 14px;
        }

        .promo-banner p {
            margin: 2px 0 0; color: rgba(255,255,255,0.85);
            font-size: 12px;
        }

        /* ===== SECTION TITLE ===== */
        .section-header {
            padding: 4px 16px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--dark);
            margin: 0;
        }

        .section-line {
            flex: 1;
            height: 2px;
            background: var(--border);
            border-radius: 2px;
        }

        /* ===== MENU CARDS ===== */
        .menu-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            padding: 0 16px;
            margin-bottom: 24px;
        }

        .menu-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }

        .menu-card:active { transform: scale(0.97); }

        .menu-card-img {
            width: 100%;
            height: 130px;
            object-fit: cover;
            display: block;
        }
        .menu-img-wrapper {
            width: 100%;
            height: 130px;
            overflow: hidden;
            background: linear-gradient(135deg, #FFE8E8, #FFD0D0);
        }

        .menu-img-placeholder {
            width: 100%;
            height: 130px;
            background: linear-gradient(135deg, #FFE8E8 0%, #FFD0D0 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }

        .menu-card-body {
            padding: 12px;
        }

        .menu-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 4px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .menu-price {
            font-size: 14px;
            font-weight: 800;
            color: var(--primary);
            margin: 0 0 10px;
        }

        .btn-add {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-add:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-add:active { transform: translateY(0); }

        .qty-control {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FFF5F5;
            border-radius: 50px;
            padding: 4px;
        }

        .qty-btn {
            width: 28px; height: 28px;
            border: none;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .qty-btn.minus { background: white; color: var(--text-muted); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        .qty-btn.plus { background: var(--primary); color: white; }
        .qty-btn.minus:hover { color: var(--primary); }
        .qty-btn.plus:hover { background: var(--primary-dark); }

        .qty-num {
            font-size: 14px;
            font-weight: 800;
            color: var(--primary);
            min-width: 24px;
            text-align: center;
        }

        .badge-new {
            position: absolute;
            top: 8px; left: 8px;
            background: var(--accent);
            color: var(--dark);
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 50px;
        }

        .badge-hot {
            position: absolute;
            top: 8px; left: 8px;
            background: var(--primary);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 50px;
        }

        /* ===== CART BUTTON (FLOATING) ===== */
        .cart-float {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 999;
            width: calc(100% - 32px);
            max-width: 500px;
        }

        .cart-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 50px;
            padding: 16px 24px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 8px 28px rgba(232,64,64,0.45);
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .cart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(232,64,64,0.5);
            color: white;
        }

        .cart-count-badge {
            background: rgba(255,255,255,0.25);
            border-radius: 50px;
            padding: 3px 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .cart-total {
            font-size: 14px;
            opacity: 0.9;
        }

        .cart-hidden { display: none !important; }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            color: var(--text-muted);
        }

        .empty-state i { font-size: 60px; margin-bottom: 16px; opacity: 0.3; }

        /* ===== TOAST NOTIFICATION ===== */
        .toast-container {
            position: fixed;
            top: 16px; left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            pointer-events: none;
        }

        .toast-item {
            background: var(--dark);
            color: white;
            padding: 12px 20px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            animation: toastIn 0.3s ease forwards;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        @keyframes toastIn {
            from { opacity: 0; transform: translateY(-10px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .no-meja-banner {
            margin: 16px;
            background: #FFF3CD;
            border: 1px solid #FFD700;
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #856404;
        }

        @media (max-width: 360px) {
            .menu-grid { grid-template-columns: 1fr; }
            .menu-card-img, .menu-img-placeholder { height: 160px; }
        }
    </style>
</head>
<body>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Header -->
<div class="app-header">
    <div class="header-top">
        <div class="restaurant-info">
            <h1>🍗 Ayam Penyet</h1>
            <p>Bendungan Batusangkar</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($nomor_meja > 0): ?>
                <a href="riwayat.php?meja=<?= $nomor_meja ?>"
                   style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:50px;padding:6px 12px;color:white;text-decoration:none;font-size:11px;font-weight:700;display:flex;align-items:center;gap:5px;">
                    <i class="fas fa-history"></i>Riwayat
                </a>
                <span class="meja-badge">
                    <i class="fas fa-chair me-1"></i>Meja <?= $nomor_meja ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="search-wrapper">
        <i class="fas fa-search search-icon"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Cari makanan atau minuman...">
    </div>
</div>

<!-- Warning jika tanpa nomor meja -->
<?php if ($nomor_meja <= 0): ?>
<div class="no-meja-banner">
    <i class="fas fa-exclamation-triangle"></i>
    <span>Nomor meja tidak terdeteksi. Silakan scan ulang QR Code atau tambahkan <strong>?meja=1</strong> di URL.</span>
</div>
<?php endif; ?>

<!-- Kategori Tabs -->
<div class="category-section">
    <div class="category-scroll" id="categoryScroll">
        <div class="cat-pill active" data-cat="all" onclick="filterKategori('all', this)">
            🍽️ Semua
        </div>
        <?php foreach ($kategori_list as $kat): ?>
        <div class="cat-pill inactive" data-cat="<?= $kat['id'] ?>" onclick="filterKategori('<?= $kat['id'] ?>', this)">
            <?php
                $icons = ['1' => '🍗', '2' => '🍳', '3' => '🥤', '4' => '🍟'];
                echo ($icons[$kat['id']] ?? '🍽️') . ' ' . $kat['nama_kategori'];
            ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Promo Banner -->
<div class="promo-banner">
    <div class="promo-icon">🔥</div>
    <div>
        <h6>Paket Hemat Hari Ini!</h6>
        <p>Ayam Penyet + Nasi + Es Teh cuma Rp 30.000</p>
    </div>
</div>

<!-- Menu Sections -->
<?php if (empty($menu_list)): ?>
    <div class="empty-state">
        <i class="fas fa-utensils"></i>
        <p>Menu belum tersedia</p>
    </div>
<?php else: ?>
    <?php foreach ($kategori_list as $kat): ?>
        <?php if (isset($menu_list[$kat['id']])): ?>
        <div class="menu-section" data-cat-section="<?= $kat['id'] ?>">
            <div class="section-header">
                <h2 class="section-title"><?= htmlspecialchars($kat['nama_kategori']) ?></h2>
                <div class="section-line"></div>
            </div>
            <div class="menu-grid">
                <?php foreach ($menu_list[$kat['id']] as $i => $menu): ?>
                <div class="menu-card" 
                     data-id="<?= $menu['id'] ?>" 
                     data-nama="<?= htmlspecialchars($menu['nama_menu']) ?>"
                     data-harga="<?= $menu['harga'] ?>"
                     data-cat="<?= $menu['id_kategori'] ?>">
                    <?php if ($i === 0): ?><span class="badge-hot">🔥 Terlaris</span><?php endif; ?>
                    <?php if ($i === 1): ?><span class="badge-new">⭐ Favorit</span><?php endif; ?>
                    
                    <?php
                    $foodEmojis = ['🍗','🍖','🍳','🥘','🍜','🥤','🧃','🍟','🥙'];
                    $emoji = $foodEmojis[$menu['id'] % count($foodEmojis)];
                    $hasImg = !empty($menu['gambar']) 
                              && $menu['gambar'] !== 'default.jpg'
                              && file_exists(__DIR__ . '/assets/images/' . $menu['gambar']);
                    ?>
                    <?php if ($hasImg): ?>
                    <div class="menu-img-wrapper" style="position:relative;">
                        <img src="assets/images/<?= htmlspecialchars($menu['gambar']) ?>"
                             alt="<?= htmlspecialchars($menu['nama_menu']) ?>"
                             class="menu-card-img">
                    </div>
                    <?php else: ?>
                    <div class="menu-img-placeholder">
                        <span><?= $emoji ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="menu-card-body">
                        <p class="menu-name"><?= htmlspecialchars($menu['nama_menu']) ?></p>
                        <p class="menu-price"><?= formatRupiah($menu['harga']) ?></p>
                        
                        <!-- QTY Control (hidden by default) -->
                        <?php
                        $qtyInCart = 0;
                        if (isset($keranjang[$menu['id']])) {
                            $qtyInCart = $keranjang[$menu['id']]['jumlah'];
                        }
                        ?>
                        
                        <?php if ($qtyInCart > 0): ?>
                        <div class="qty-control" id="qty-<?= $menu['id'] ?>">
                            <button class="qty-btn minus" onclick="updateKeranjang(<?= $menu['id'] ?>, -1)">−</button>
                            <span class="qty-num" id="qty-num-<?= $menu['id'] ?>"><?= $qtyInCart ?></span>
                            <button class="qty-btn plus" onclick="updateKeranjang(<?= $menu['id'] ?>, 1)">+</button>
                        </div>
                        <button class="btn-add" id="btn-add-<?= $menu['id'] ?>" style="display:none;" onclick="tambahKeranjang(<?= $menu['id'] ?>)">
                            <i class="fas fa-plus"></i> Tambah
                        </button>
                        <?php else: ?>
                        <div class="qty-control" id="qty-<?= $menu['id'] ?>" style="display:none;">
                            <button class="qty-btn minus" onclick="updateKeranjang(<?= $menu['id'] ?>, -1)">−</button>
                            <span class="qty-num" id="qty-num-<?= $menu['id'] ?>">0</span>
                            <button class="qty-btn plus" onclick="updateKeranjang(<?= $menu['id'] ?>, 1)">+</button>
                        </div>
                        <button class="btn-add" id="btn-add-<?= $menu['id'] ?>" onclick="tambahKeranjang(<?= $menu['id'] ?>)">
                            <i class="fas fa-plus"></i> Tambah
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Floating Cart Button -->
<div class="cart-float" id="cartFloat">
    <a href="keranjang.php<?= $nomor_meja > 0 ? '?meja='.$nomor_meja : '' ?>" class="cart-btn">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-shopping-bag"></i>
            <span>Lihat Keranjang</span>
            <span class="cart-count-badge" id="cartCountBadge"><?= $jumlah_keranjang ?></span>
        </div>
        <span class="cart-total" id="cartTotal"><?= formatRupiah($total_keranjang) ?></span>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ===== DATA KERANJANG =====
    let keranjang = <?= json_encode($keranjang) ?>;
    let totalKeranjang = <?= $total_keranjang ?>;
    let jumlahKeranjang = <?= $jumlah_keranjang ?>;

    // ===== TAMBAH KE KERANJANG =====
    function tambahKeranjang(idMenu) {
        fetch('api/keranjang.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=tambah&id_menu=${idMenu}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Show qty control
                document.getElementById('btn-add-' + idMenu).style.display = 'none';
                document.getElementById('qty-' + idMenu).style.display = 'flex';
                document.getElementById('qty-num-' + idMenu).textContent = data.qty;
                updateCartDisplay(data.total, data.jumlah);
                showToast('✅ Ditambahkan ke keranjang!');
            }
        })
        .catch(() => showToast('❌ Gagal menambahkan!'));
    }

    // ===== UPDATE KERANJANG =====
    function updateKeranjang(idMenu, delta) {
        fetch('api/keranjang.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update&id_menu=${idMenu}&delta=${delta}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.qty <= 0) {
                    document.getElementById('qty-' + idMenu).style.display = 'none';
                    document.getElementById('btn-add-' + idMenu).style.display = 'flex';
                } else {
                    document.getElementById('qty-num-' + idMenu).textContent = data.qty;
                }
                updateCartDisplay(data.total, data.jumlah);
            }
        });
    }

    // ===== UPDATE CART DISPLAY =====
    function updateCartDisplay(total, jumlah) {
        totalKeranjang = total;
        jumlahKeranjang = jumlah;

        const cartFloat = document.getElementById('cartFloat');
        const cartCount = document.getElementById('cartCountBadge');
        const cartTotal = document.getElementById('cartTotal');

        if (jumlah > 0) {
            cartFloat.classList.remove('cart-hidden');
            cartCount.textContent = jumlah;
            cartTotal.textContent = 'Rp ' + total.toLocaleString('id-ID');
        } else {
            cartFloat.classList.add('cart-hidden');
        }
    }

    // Init cart display
    <?php if ($jumlah_keranjang <= 0): ?>
    document.getElementById('cartFloat').classList.add('cart-hidden');
    <?php endif; ?>

    // ===== FILTER KATEGORI =====
    function filterKategori(cat, el) {
        // Update active pill
        document.querySelectorAll('.cat-pill').forEach(p => {
            p.classList.remove('active');
            p.classList.add('inactive');
        });
        el.classList.remove('inactive');
        el.classList.add('active');

        // Filter sections
        const sections = document.querySelectorAll('.menu-section');
        const cards = document.querySelectorAll('.menu-card');
        
        if (cat === 'all') {
            sections.forEach(s => s.style.display = '');
        } else {
            sections.forEach(s => {
                s.style.display = s.dataset.catSection === cat ? '' : 'none';
            });
        }

        // Scroll to top
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    // ===== SEARCH =====
    document.getElementById('searchInput').addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        const cards = document.querySelectorAll('.menu-card');
        const sections = document.querySelectorAll('.menu-section');

        if (!q) {
            cards.forEach(c => c.style.display = '');
            sections.forEach(s => s.style.display = '');
            return;
        }

        sections.forEach(section => {
            const sectionCards = section.querySelectorAll('.menu-card');
            let visible = 0;
            sectionCards.forEach(card => {
                const nama = card.dataset.nama.toLowerCase();
                if (nama.includes(q)) {
                    card.style.display = '';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });
            section.style.display = visible > 0 ? '' : 'none';
        });
    });

    // ===== TOAST =====
    function showToast(msg) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast-item';
        toast.textContent = msg;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }
</script>
</body>
</html>
