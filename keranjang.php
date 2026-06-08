<?php
require_once 'koneksi.php';
$nomor_meja = getNomorMeja();
$keranjang = getKeranjang();
$total = getTotalKeranjang();
$jumlah = getJumlahKeranjang();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #E84040;
            --primary-dark: #C42E2E;
            --dark: #1A1A2E;
            --bg: #F5F5F5;
            --surface: #FFFFFF;
            --text: #2D2D2D;
            --text-muted: #888;
            --border: #EBEBEB;
            --radius: 16px;
            --shadow: 0 2px 16px rgba(0,0,0,0.07);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding-bottom: 120px;
        }

        /* HEADER */
        .page-header {
            background: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            position: sticky;
            top: 0; z-index: 100;
        }
        .back-btn {
            width: 38px; height: 38px;
            background: var(--bg);
            border: none;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--dark);
            text-decoration: none;
            font-size: 16px;
            transition: background 0.2s;
        }
        .back-btn:hover { background: #eee; color: var(--dark); }
        .page-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--dark);
            margin: 0;
        }
        .meja-chip {
            margin-left: auto;
            background: #FFF0F0;
            color: var(--primary);
            border-radius: 50px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 700;
        }

        /* CART ITEMS */
        .cart-container { padding: 16px; }

        .cart-item {
            background: white;
            border-radius: var(--radius);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 10px;
            box-shadow: var(--shadow);
        }

        .item-emoji {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, #FFE8E8, #FFD0D0);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }

        .item-info { flex: 1; min-width: 0; }
        .item-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .item-price {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
        }
        .item-subtotal {
            font-size: 15px;
            font-weight: 800;
            color: var(--primary);
            white-space: nowrap;
        }

        .qty-control {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }
        .qty-btn {
            width: 30px; height: 30px;
            border: 1.5px solid var(--border);
            border-radius: 50%;
            background: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            color: var(--text);
            transition: all 0.15s;
        }
        .qty-btn:hover { border-color: var(--primary); color: var(--primary); }
        .qty-btn.minus:hover { background: #fff0f0; }
        .qty-btn.plus { border-color: var(--primary); color: var(--primary); }
        .qty-btn.plus:hover { background: var(--primary); color: white; }
        .qty-num {
            font-size: 16px;
            font-weight: 800;
            color: var(--dark);
            min-width: 24px;
            text-align: center;
        }
        .delete-btn {
            background: none;
            border: none;
            color: #ccc;
            font-size: 16px;
            cursor: pointer;
            padding: 4px;
            transition: color 0.2s;
        }
        .delete-btn:hover { color: var(--primary); }

        /* CATATAN */
        .note-section {
            margin: 0 16px 16px;
            background: white;
            border-radius: var(--radius);
            padding: 16px;
            box-shadow: var(--shadow);
        }
        .note-section label {
            font-size: 14px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
            display: block;
        }
        .note-section textarea,
        .note-section input[type="text"] {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            resize: none;
            outline: none;
            transition: border 0.2s;
        }
        .note-section textarea:focus,
        .note-section input[type="text"]:focus { border-color: var(--primary); }

        /* ORDER SUMMARY */
        .summary-section {
            margin: 0 16px 16px;
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .summary-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--dark);
            margin: 0 0 16px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .summary-divider {
            border: none;
            border-top: 1.5px dashed var(--border);
            margin: 14px 0;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-label { font-size: 16px; font-weight: 700; color: var(--dark); }
        .total-value { font-size: 20px; font-weight: 800; color: var(--primary); }

        /* CHECKOUT BUTTON */
        .checkout-float {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: white;
            padding: 16px 20px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
            z-index: 999;
        }
        .btn-checkout {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 50px;
            padding: 16px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-checkout:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(232,64,64,0.4); }
        .btn-checkout:disabled { background: #ccc; transform: none; box-shadow: none; }

        /* EMPTY CART */
        .empty-cart {
            text-align: center;
            padding: 80px 30px;
        }
        .empty-cart .empty-icon { font-size: 80px; margin-bottom: 16px; }
        .empty-cart h3 { font-weight: 800; color: var(--dark); }
        .empty-cart p { color: var(--text-muted); font-size: 14px; }
        .btn-back-menu {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px 32px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            margin-top: 16px;
            transition: all 0.2s;
        }
        .btn-back-menu:hover { background: var(--primary-dark); color: white; }
    </style>
</head>
<body>

<!-- Header -->
<div class="page-header">
    <a href="index.php<?= $nomor_meja > 0 ? '?meja='.$nomor_meja : '' ?>" class="back-btn">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="page-title">🛒 Keranjang</h1>
    <?php if ($nomor_meja > 0): ?>
    <a href="riwayat.php?meja=<?= $nomor_meja ?>"
       style="margin-left:auto;background:rgba(232,64,64,0.1);color:var(--primary);border-radius:50px;padding:5px 12px;font-size:11px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:4px">
        <i class="fas fa-history"></i>Riwayat
    </a>
    <span class="meja-chip">Meja <?= $nomor_meja ?></span>
    <?php endif; ?>
</div>

<?php if (empty($keranjang)): ?>
<!-- Empty Cart -->
<div class="empty-cart">
    <div class="empty-icon">🛒</div>
    <h3>Keranjang Kosong</h3>
    <p>Belum ada makanan yang dipilih.<br>Yuk, pilih menu dulu!</p>
    <a href="index.php<?= $nomor_meja > 0 ? '?meja='.$nomor_meja : '' ?>" class="btn-back-menu">
        <i class="fas fa-utensils me-2"></i>Lihat Menu
    </a>
</div>

<?php else: ?>
<!-- Cart Items -->
<div class="cart-container" id="cartContainer">
    <?php
    $foodEmojis = ['🍗','🍖','🍳','🥘','🍜','🥤','🧃','🍟','🥙','🍱'];
    $i = 0;
    foreach ($keranjang as $id_menu => $item):
        $emoji = $foodEmojis[$i % count($foodEmojis)];
        $i++;
    ?>
    <div class="cart-item" id="cartItem-<?= $id_menu ?>">
        <div class="item-emoji"><?= $emoji ?></div>
        <div class="item-info">
            <p class="item-name"><?= htmlspecialchars($item['nama']) ?></p>
            <p class="item-price"><?= formatRupiah($item['harga']) ?> /porsi</p>
            <div class="qty-control">
                <button class="qty-btn minus" onclick="updateItem(<?= $id_menu ?>, -1)">−</button>
                <span class="qty-num" id="qtyNum-<?= $id_menu ?>"><?= $item['jumlah'] ?></span>
                <button class="qty-btn plus" onclick="updateItem(<?= $id_menu ?>, 1)">+</button>
            </div>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <button class="delete-btn" onclick="hapusItem(<?= $id_menu ?>)" title="Hapus">
                <i class="fas fa-trash-alt"></i>
            </button>
            <span class="item-subtotal" id="subtotal-<?= $id_menu ?>">
                <?= formatRupiah($item['harga'] * $item['jumlah']) ?>
            </span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Nama Pelanggan -->
<div class="note-section">
    <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <i class="fas fa-user" style="color:var(--primary)"></i>
        <span>Nama Pemesan <span style="color:var(--primary);font-size:11px">*Wajib diisi</span></span>
    </label>
    <input type="text" id="namaPelanggan" 
           placeholder="Masukkan nama Anda..." 
           maxlength="50"
           style="width:100%;border:1.5px solid var(--border);border-radius:10px;padding:11px 14px;font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;outline:none;transition:border .2s"
           onfocus="this.style.borderColor='var(--primary)'"
           onblur="this.style.borderColor='var(--border)'">
    <div id="namaError" style="display:none;color:#E84040;font-size:12px;margin-top:5px;font-weight:600">
        <i class="fas fa-exclamation-circle me-1"></i>Nama pemesan wajib diisi
    </div>
</div>

<!-- Catatan -->
<div class="note-section">
    <label><i class="fas fa-sticky-note me-2 text-warning"></i>Catatan Pesanan (Opsional)</label>
    <textarea id="catatanPesanan" rows="2" placeholder="Contoh: Tidak pakai sambal, nasi setengah, dll..."></textarea>
</div>

<!-- Order Summary -->
<div class="summary-section" id="summarySection">
    <h3 class="summary-title">Ringkasan Pesanan</h3>
    <?php foreach ($keranjang as $id_menu => $item): ?>
    <div class="summary-row" id="summaryRow-<?= $id_menu ?>">
        <span><?= htmlspecialchars($item['nama']) ?> ×<?= $item['jumlah'] ?></span>
        <span><?= formatRupiah($item['harga'] * $item['jumlah']) ?></span>
    </div>
    <?php endforeach; ?>
    <hr class="summary-divider">
    <div class="summary-total">
        <span class="total-label">Total Pembayaran</span>
        <span class="total-value" id="grandTotal"><?= formatRupiah($total) ?></span>
    </div>
</div>

<!-- Checkout Button -->
<div class="checkout-float">
    <button class="btn-checkout" id="btnCheckout" onclick="prosesCheckout()">
        <i class="fas fa-paper-plane"></i>
        Pesan Sekarang · <?= formatRupiah($total) ?>
    </button>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let keranjang = <?= json_encode($keranjang) ?>;
    const nomorMeja = <?= $nomor_meja ?>;

    function formatRupiah(n) {
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    function updateItem(idMenu, delta) {
        fetch('api/keranjang.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update&id_menu=${idMenu}&delta=${delta}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.qty <= 0) {
                    document.getElementById('cartItem-' + idMenu)?.remove();
                    document.getElementById('summaryRow-' + idMenu)?.remove();
                    delete keranjang[idMenu];
                } else {
                    document.getElementById('qtyNum-' + idMenu).textContent = data.qty;
                    keranjang[idMenu].jumlah = data.qty;
                    const sub = keranjang[idMenu].harga * data.qty;
                    document.getElementById('subtotal-' + idMenu).textContent = formatRupiah(sub);
                    
                    // Update summary row
                    const summaryRow = document.getElementById('summaryRow-' + idMenu);
                    if (summaryRow) {
                        summaryRow.querySelector('span:first-child').textContent = keranjang[idMenu].nama + ' ×' + data.qty;
                        summaryRow.querySelector('span:last-child').textContent = formatRupiah(sub);
                    }
                }
                updateTotal(data.total, data.jumlah);
            }
        });
    }

    function hapusItem(idMenu) {
        if (!confirm('Hapus item ini dari keranjang?')) return;
        fetch('api/keranjang.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=hapus&id_menu=${idMenu}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('cartItem-' + idMenu)?.remove();
                document.getElementById('summaryRow-' + idMenu)?.remove();
                delete keranjang[idMenu];
                updateTotal(data.total, data.jumlah);
                if (data.jumlah === 0) location.reload();
            }
        });
    }

    function updateTotal(total, jumlah) {
        const el = document.getElementById('grandTotal');
        if (el) el.textContent = formatRupiah(total);
        const btn = document.getElementById('btnCheckout');
        if (btn) btn.innerHTML = `<i class="fas fa-paper-plane"></i> Pesan Sekarang · ${formatRupiah(total)}`;
    }

    function prosesCheckout() {
        // ── Ambil nilai dari form ──
        const namaInput = document.getElementById('namaPelanggan');
        const nama      = (namaInput?.value || '').trim();
        const catatan   = document.getElementById('catatanPesanan')?.value || '';
        const btn       = document.getElementById('btnCheckout');

        // ── Validasi nama ──
        if (!nama) {
            if (namaInput) {
                namaInput.style.borderColor = '#E84040';
                namaInput.focus();
            }
            const errEl = document.getElementById('namaError');
            if (errEl) errEl.style.display = 'block';
            return;  // Berhenti, TIDAK disable tombol
        }

        // ── Validasi nomor meja ──
        if (nomorMeja <= 0) {
            alert('⚠️ Nomor meja tidak terdeteksi!\nSilakan scan ulang QR Code meja Anda.');
            return;
        }

        // ── Disable tombol dan tampilkan loading ──
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';

        fetch('checkout.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'nomor_meja=' + nomorMeja
                + '&nama_pelanggan=' + encodeURIComponent(nama)
                + '&catatan='        + encodeURIComponent(catatan)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'sukses.php?kode=' + data.kode + '&meja=' + nomorMeja;
            } else {
                alert('❌ ' + (data.msg || 'Gagal memproses pesanan'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Pesan Sekarang';
            }
        })
        .catch(() => {
            alert('❌ Terjadi kesalahan koneksi. Coba lagi.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Pesan Sekarang';
        });
    }
</script>
</body>
</html>
