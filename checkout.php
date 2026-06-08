<?php
// ============================================
// checkout.php - Process Order
// ============================================
require_once 'koneksi.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => 'Method tidak valid']);
    exit;
}

$nomor_meja      = (int)($_POST['nomor_meja'] ?? 0);
$catatan         = trim($_POST['catatan'] ?? '');
$nama_pelanggan  = trim($_POST['nama_pelanggan'] ?? 'Pelanggan');
if (empty($nama_pelanggan)) $nama_pelanggan = 'Pelanggan';
// Sanitize nama - hanya huruf, spasi, titik
$nama_pelanggan = preg_replace("/[^a-zA-Z0-9 .'\-]/", "", $nama_pelanggan);
$nama_pelanggan = substr($nama_pelanggan, 0, 50);

if ($nomor_meja <= 0) {
    echo json_encode(['success' => false, 'msg' => 'Nomor meja tidak valid']);
    exit;
}

$keranjang = getKeranjang();
if (empty($keranjang)) {
    echo json_encode(['success' => false, 'msg' => 'Keranjang kosong']);
    exit;
}

// Hitung total
$total = getTotalKeranjang();

// Generate kode pesanan
$kode = generateKodePesanan();

// Mulai transaksi
$conn->begin_transaction();

try {
    // Insert pesanan
    $stmt = $conn->prepare("INSERT INTO pesanan (kode_pesanan, nomor_meja, nama_pelanggan, catatan, total_harga, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param('sissi', $kode, $nomor_meja, $nama_pelanggan, $catatan, $total);
    $stmt->execute();
    $id_pesanan = $conn->insert_id;
    $stmt->close();

    // Insert detail pesanan
    $stmt_detail = $conn->prepare("INSERT INTO detail_pesanan (id_pesanan, id_menu, nama_menu, harga, jumlah, subtotal) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($keranjang as $id_menu => $item) {
        $id_menu_int = (int)$id_menu;
        $nama  = $item['nama'];
        $harga = (int)$item['harga'];
        $jumlah = (int)$item['jumlah'];
        $subtotal = $harga * $jumlah;
        $stmt_detail->bind_param('iisiii', $id_pesanan, $id_menu_int, $nama, $harga, $jumlah, $subtotal);
        $stmt_detail->execute();
    }

    $stmt_detail->close();
    $conn->commit();

    // Kosongkan keranjang
    $_SESSION['keranjang'] = [];

    echo json_encode([
        'success' => true,
        'kode'    => $kode,
        'id'      => $id_pesanan,
        'total'   => $total
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'msg' => 'Gagal menyimpan pesanan: ' . $e->getMessage()]);
}
?>
