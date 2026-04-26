<?php
// ============================================
// api/notif.php - Real-time notification API
// Dipanggil setiap 15 detik dari admin dashboard
// ============================================
require_once '../koneksi.php';

// Bisa diakses admin saja
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$last_id   = (int)($_GET['last_id'] ?? 0);
$last_check = sanitize($_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 minute')));

// Pesanan baru sejak last_id
$new_orders = [];
if ($last_id > 0) {
    $res = $conn->query("SELECT id, kode_pesanan, nomor_meja, nama_pelanggan, total_harga, tanggal, status
        FROM pesanan WHERE id > $last_id AND status = 'pending'
        ORDER BY id ASC LIMIT 10");
    while ($row = $res->fetch_assoc()) $new_orders[] = $row;
}

// Pesanan yang belum bayar setelah selesai
$unpaid = $conn->query("SELECT COUNT(*) as c FROM pesanan 
    WHERE status='selesai' AND (status_bayar='belum_bayar' OR status_bayar IS NULL)")->fetch_assoc()['c'];

// Pending count
$pending = $conn->query("SELECT COUNT(*) as c FROM pesanan WHERE status='pending'")->fetch_assoc()['c'];

// ID pesanan terbaru
$r_lid = $conn->query("SELECT COALESCE(MAX(id),0) as id FROM pesanan");
$latest_id = $r_lid ? (int)$r_lid->fetch_assoc()['id'] : 0;

// Total omzet hari ini
$omzet_hari = $conn->query("SELECT COALESCE(SUM(total_harga),0) as t FROM pesanan WHERE DATE(tanggal)=CURDATE() AND status_bayar='lunas'")->fetch_assoc()['t'];

echo json_encode([
    'success'     => true,
    'new_orders'  => $new_orders,
    'new_count'   => count($new_orders),
    'pending'     => (int)$pending,
    'unpaid'      => (int)$unpaid,
    'latest_id'   => (int)$latest_id,
    'omzet_hari'  => (int)$omzet_hari,
    'server_time' => date('H:i:s'),
]);
?>
