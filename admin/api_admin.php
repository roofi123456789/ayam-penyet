<?php
// ============================================
// admin/api_admin.php - Admin API
// ============================================
require_once '../koneksi.php';
requireAdminLogin();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'update_status':
        $id     = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $valid  = ['pending','diproses','selesai','dibatalkan'];

        if (!in_array($status, $valid) || $id <= 0) {
            echo json_encode(['success'=>false,'msg'=>'Data tidak valid']); exit;
        }

        $stmt = $conn->prepare("UPDATE pesanan SET status=? WHERE id=?");
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $ok]);
        break;

    case 'get_detail':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false]); exit; }

        $stmt = $conn->prepare("SELECT * FROM pesanan WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $pesanan = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pesanan) { echo json_encode(['success'=>false,'msg'=>'Tidak ditemukan']); exit; }

        $stmt2 = $conn->prepare("SELECT * FROM detail_pesanan WHERE id_pesanan=?");
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $details = [];
        while ($row = $result->fetch_assoc()) $details[] = $row;
        $stmt2->close();

        echo json_encode(['success'=>true, 'pesanan'=>$pesanan, 'details'=>$details]);
        break;

    case 'hapus_pesanan':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false]); exit; }

        $stmt = $conn->prepare("DELETE FROM pesanan WHERE id=?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $ok]);
        break;

    case 'toggle_tersedia':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE menu SET tersedia = 1 - tersedia WHERE id=?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        // Get new value
        $stmt2 = $conn->prepare("SELECT tersedia FROM menu WHERE id=?");
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $row = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        echo json_encode(['success'=>$ok, 'tersedia'=>$row['tersedia']]);
        break;

    // ── ADMIN: Konfirmasi pembayaran cash (nominal diinput kasir)
    case 'konfirmasi_cash':
        $kode         = sanitize($_POST['kode'] ?? '');
        $jumlah_bayar = (int)($_POST['jumlah_bayar'] ?? 0);

        if (!$kode || $jumlah_bayar <= 0) {
            echo json_encode(['success'=>false,'msg'=>'Data tidak lengkap']); exit;
        }

        $stmt = $conn->prepare("SELECT total_harga, status_bayar FROM pesanan WHERE kode_pesanan=?");
        $stmt->bind_param('s', $kode);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$p) { echo json_encode(['success'=>false,'msg'=>'Pesanan tidak ditemukan']); exit; }
        if ($p['status_bayar'] === 'lunas') { echo json_encode(['success'=>true,'already'=>true,'kembalian'=>0]); exit; }
        if ($jumlah_bayar < $p['total_harga']) {
            echo json_encode(['success'=>false,'msg'=>'Uang kurang! Perlu: Rp '.number_format($p['total_harga'],0,',','.')]); exit;
        }

        $kembalian   = $jumlah_bayar - $p['total_harga'];
        $waktu_bayar = date('Y-m-d H:i:s');
        $stmt2 = $conn->prepare("UPDATE pesanan SET
            status_bayar='lunas', jumlah_bayar=?, kembalian=?,
            waktu_bayar=?, status='selesai', status_verifikasi='terverifikasi'
            WHERE kode_pesanan=?");
        $stmt2->bind_param('iiss', $jumlah_bayar, $kembalian, $waktu_bayar, $kode);
        $ok = $stmt2->execute();
        $stmt2->close();

        echo json_encode(['success'=>$ok, 'kembalian'=>$kembalian, 'msg'=> $ok?'OK':$conn->error]);
        break;

    // ── ADMIN: Verifikasi bukti QRIS (terima / tolak)
    case 'verifikasi_qris':
        $kode      = sanitize($_POST['kode'] ?? '');
        $keputusan = sanitize($_POST['keputusan'] ?? '');

        if (!in_array($keputusan, ['terima','tolak']) || !$kode) {
            echo json_encode(['success'=>false,'msg'=>'Data tidak valid']); exit;
        }

        if ($keputusan === 'terima') {
            $waktu_bayar = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE pesanan SET
                status_bayar='lunas', jumlah_bayar=total_harga, kembalian=0,
                waktu_bayar=?, status='selesai', status_verifikasi='terverifikasi'
                WHERE kode_pesanan=?");
            $stmt->bind_param('ss', $waktu_bayar, $kode);
        } else {
            // Tolak – reset agar customer bisa upload ulang
            $stmt = $conn->prepare("UPDATE pesanan SET
                metode_bayar=NULL, bukti_qris=NULL, status_verifikasi='ditolak'
                WHERE kode_pesanan=?");
            $stmt->bind_param('s', $kode);
        }
        $ok = $stmt->execute();
        $stmt->close();

        echo json_encode(['success'=>$ok, 'msg'=> $ok ? ($keputusan==='terima'?'Terverifikasi!':'Ditolak.') : $conn->error]);
        break;

    default:
        echo json_encode(['success'=>false,'msg'=>'Action tidak dikenal']);
}
?>
