<?php
// ============================================
// api/keranjang.php - Cart API (AJAX)
// ============================================
require_once '../koneksi.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'tambah':
        $id_menu = (int)($_POST['id_menu'] ?? 0);
        if ($id_menu <= 0) { echo json_encode(['success'=>false,'msg'=>'ID menu tidak valid']); exit; }
        
        // Ambil data menu dari DB
        $stmt = $conn->prepare("SELECT id, nama_menu, harga FROM menu WHERE id = ? AND tersedia = 1");
        $stmt->bind_param('i', $id_menu);
        $stmt->execute();
        $menu = $stmt->get_result()->fetch_assoc();
        
        if (!$menu) { echo json_encode(['success'=>false,'msg'=>'Menu tidak ditemukan']); exit; }
        
        if (!isset($_SESSION['keranjang'])) $_SESSION['keranjang'] = [];
        
        if (isset($_SESSION['keranjang'][$id_menu])) {
            $_SESSION['keranjang'][$id_menu]['jumlah']++;
        } else {
            $_SESSION['keranjang'][$id_menu] = [
                'id'        => $menu['id'],
                'nama'      => $menu['nama_menu'],
                'harga'     => $menu['harga'],
                'jumlah'    => 1
            ];
        }
        
        echo json_encode([
            'success' => true,
            'qty'     => $_SESSION['keranjang'][$id_menu]['jumlah'],
            'total'   => getTotalKeranjang(),
            'jumlah'  => getJumlahKeranjang()
        ]);
        break;

    case 'update':
        $id_menu = (int)($_POST['id_menu'] ?? 0);
        $delta   = (int)($_POST['delta'] ?? 0);
        
        if (!isset($_SESSION['keranjang'][$id_menu])) {
            echo json_encode(['success'=>false,'msg'=>'Item tidak ada di keranjang']); exit;
        }
        
        $_SESSION['keranjang'][$id_menu]['jumlah'] += $delta;
        
        if ($_SESSION['keranjang'][$id_menu]['jumlah'] <= 0) {
            unset($_SESSION['keranjang'][$id_menu]);
            $qty = 0;
        } else {
            $qty = $_SESSION['keranjang'][$id_menu]['jumlah'];
        }
        
        echo json_encode([
            'success' => true,
            'qty'     => $qty,
            'total'   => getTotalKeranjang(),
            'jumlah'  => getJumlahKeranjang()
        ]);
        break;

    case 'hapus':
        $id_menu = (int)($_POST['id_menu'] ?? 0);
        if (isset($_SESSION['keranjang'][$id_menu])) {
            unset($_SESSION['keranjang'][$id_menu]);
        }
        echo json_encode([
            'success' => true,
            'total'   => getTotalKeranjang(),
            'jumlah'  => getJumlahKeranjang()
        ]);
        break;

    case 'kosongkan':
        $_SESSION['keranjang'] = [];
        echo json_encode(['success' => true]);
        break;

    case 'get':
        echo json_encode([
            'success'  => true,
            'keranjang'=> getKeranjang(),
            'total'    => getTotalKeranjang(),
            'jumlah'   => getJumlahKeranjang()
        ]);
        break;

    default:
        echo json_encode(['success'=>false,'msg'=>'Action tidak valid']);
}
?>
