<?php
// ============================================
// api/bayar.php  — Payment API
// action=set_cash   : tandai pesanan akan bayar tunai di kasir
// action=upload_qris: simpan bukti transfer QRIS
// action=konfirmasi_cash  : ADMIN only – konfirmasi cash dengan nominal
// action=verifikasi_qris  : ADMIN only – terima/tolak bukti QRIS
// ============================================
require_once '../koneksi.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => 'Invalid method']); exit;
}

$action = $_POST['action'] ?? '';

// ─────────────────────────────────────────────
//  CUSTOMER: tandai pesanan mau bayar cash
// ─────────────────────────────────────────────
if ($action === 'set_cash') {
    $kode = sanitize($_POST['kode'] ?? '');
    if (!$kode) { echo json_encode(['success' => false, 'msg' => 'Kode kosong']); exit; }

    $stmt = $conn->prepare("SELECT id, status_bayar FROM pesanan WHERE kode_pesanan = ?");
    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) { echo json_encode(['success' => false, 'msg' => 'Pesanan tidak ditemukan']); exit; }
    if ($row['status_bayar'] === 'lunas') { echo json_encode(['success' => true, 'msg' => 'Sudah lunas']); exit; }

    // Set metode = cash, status = menunggu kasir
    $stmt2 = $conn->prepare("UPDATE pesanan SET metode_bayar='cash', status_verifikasi='menunggu' WHERE kode_pesanan=?");
    $stmt2->bind_param('s', $kode);
    $ok = $stmt2->execute();
    $stmt2->close();

    echo json_encode(['success' => $ok, 'msg' => $ok ? 'OK' : $conn->error]);
    exit;
}

// ─────────────────────────────────────────────
//  CUSTOMER: upload bukti transfer QRIS
// ─────────────────────────────────────────────
if ($action === 'upload_qris') {
    $kode = sanitize($_POST['kode'] ?? '');

    if (!$kode) {
        echo json_encode(['success' => false, 'msg' => 'Kode pesanan kosong']); exit;
    }

    // Terima file via FormData ($_FILES)
    if (empty($_FILES['bukti_file']) || $_FILES['bukti_file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['bukti_file']['error'] ?? -1;
        $errMsg  = [
            UPLOAD_ERR_INI_SIZE   => 'File terlalu besar (limit server)',
            UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar (limit form)',
            UPLOAD_ERR_PARTIAL    => 'Upload tidak lengkap, coba lagi',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dikirim',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temp tidak ada',
            UPLOAD_ERR_CANT_WRITE => 'Tidak bisa tulis ke disk',
        ][$errCode] ?? 'Upload gagal (kode: ' . $errCode . ')';
        echo json_encode(['success' => false, 'msg' => $errMsg]); exit;
    }

    $file     = $_FILES['bukti_file'];
    $tmpPath  = $file['tmp_name'];
    $origName = basename($file['name']);

    // Validasi tipe file via MIME (lebih aman dari ekstensi)
    $mime    = mime_content_type($tmpPath);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed)) {
        echo json_encode(['success' => false, 'msg' => 'Format file harus JPG/PNG/WEBP']); exit;
    }

    // Validasi ukuran (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'msg' => 'Ukuran file maksimal 5MB']); exit;
    }

    // Ekstensi dari MIME
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    $ext    = $extMap[$mime] ?? 'jpg';

    // Buat folder jika belum ada
    $uploadDir = __DIR__ . '/../admin/assets/bukti_qris/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'msg' => 'Gagal membuat folder upload']); exit;
        }
    }

    // Nama file unik
    $saveName = 'qris_' . preg_replace('/[^a-zA-Z0-9]/', '_', $kode) . '_' . time() . '.' . $ext;
    $savePath = $uploadDir . $saveName;

    // Pindahkan file dari temp
    if (!move_uploaded_file($tmpPath, $savePath)) {
        echo json_encode(['success' => false, 'msg' => 'Gagal menyimpan file ke server']); exit;
    }

    // Hapus file lama jika ada
    $old = $conn->query("SELECT bukti_qris FROM pesanan WHERE kode_pesanan='" . $conn->real_escape_string($kode) . "'")->fetch_assoc();
    if ($old && $old['bukti_qris'] && $old['bukti_qris'] !== $saveName) {
        $oldPath = $uploadDir . $old['bukti_qris'];
        if (file_exists($oldPath)) @unlink($oldPath);
    }

    // Update DB
    $stmt = $conn->prepare("UPDATE pesanan SET 
        metode_bayar='qris', 
        bukti_qris=?,
        status_verifikasi='menunggu'
        WHERE kode_pesanan=?");
    $stmt->bind_param('ss', $saveName, $kode);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success'  => $ok,
        'filename' => $saveName,
        'msg'      => $ok ? 'Bukti berhasil dikirim dan menunggu verifikasi' : $conn->error
    ]);
    exit;
}

// ─────────────────────────────────────────────
//  ADMIN: konfirmasi cash – input nominal uang
// ─────────────────────────────────────────────
if ($action === 'konfirmasi_cash') {
    if (!isAdminLoggedIn()) { echo json_encode(['success' => false, 'msg' => 'Unauthorized']); exit; }

    $kode         = sanitize($_POST['kode'] ?? '');
    $jumlah_bayar = (int)($_POST['jumlah_bayar'] ?? 0);

    $stmt = $conn->prepare("SELECT total_harga, status_bayar FROM pesanan WHERE kode_pesanan=?");
    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$p) { echo json_encode(['success' => false, 'msg' => 'Pesanan tidak ditemukan']); exit; }
    if ($p['status_bayar'] === 'lunas') { echo json_encode(['success' => true, 'already' => true]); exit; }
    if ($jumlah_bayar < $p['total_harga']) {
        echo json_encode(['success' => false, 'msg' => 'Uang kurang! Total: ' . $p['total_harga']]); exit;
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

    echo json_encode([
        'success'   => $ok,
        'kembalian' => $kembalian,
        'msg'       => $ok ? 'Pembayaran cash dikonfirmasi!' : $conn->error
    ]);
    exit;
}

// ─────────────────────────────────────────────
//  ADMIN: verifikasi QRIS – terima / tolak
// ─────────────────────────────────────────────
if ($action === 'verifikasi_qris') {
    if (!isAdminLoggedIn()) { echo json_encode(['success' => false, 'msg' => 'Unauthorized']); exit; }

    $kode     = sanitize($_POST['kode'] ?? '');
    $keputusan = sanitize($_POST['keputusan'] ?? ''); // 'terima' | 'tolak'

    if (!in_array($keputusan, ['terima', 'tolak'])) {
        echo json_encode(['success' => false, 'msg' => 'Keputusan tidak valid']); exit;
    }

    $stmt = $conn->prepare("SELECT total_harga, status_bayar FROM pesanan WHERE kode_pesanan=?");
    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$p) { echo json_encode(['success' => false, 'msg' => 'Pesanan tidak ditemukan']); exit; }

    if ($keputusan === 'terima') {
        $waktu_bayar = date('Y-m-d H:i:s');
        $stmt2 = $conn->prepare("UPDATE pesanan SET 
            status_bayar='lunas', jumlah_bayar=total_harga, kembalian=0,
            waktu_bayar=?, status='selesai', status_verifikasi='terverifikasi'
            WHERE kode_pesanan=?");
        $stmt2->bind_param('ss', $waktu_bayar, $kode);
        $ok = $stmt2->execute();
        $stmt2->close();
        echo json_encode(['success' => $ok, 'msg' => $ok ? 'QRIS terverifikasi!' : $conn->error]);
    } else {
        // Tolak: reset supaya customer bisa coba lagi
        $stmt2 = $conn->prepare("UPDATE pesanan SET 
            metode_bayar=NULL, bukti_qris=NULL, status_verifikasi='ditolak'
            WHERE kode_pesanan=?");
        $stmt2->bind_param('s', $kode);
        $ok = $stmt2->execute();
        $stmt2->close();
        echo json_encode(['success' => $ok, 'msg' => 'Bukti ditolak. Pelanggan diminta upload ulang.']);
    }
    exit;
}

// ─────────────────────────────────────────────
//  Cek status verifikasi (polling oleh customer)
// ─────────────────────────────────────────────
if ($action === 'cek_status') {
    $kode = sanitize($_POST['kode'] ?? '');
    $stmt = $conn->prepare("SELECT status_bayar, status_verifikasi, metode_bayar FROM pesanan WHERE kode_pesanan=?");
    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode(['success' => (bool)$row, 'data' => $row]);
    exit;
}

// ─────────────────────────────────────────────
//  ADMIN: Serve bukti image securely
// ─────────────────────────────────────────────
if ($action === 'get_bukti') {
    // Keamanan: cek session admin ATAU token valid
    // img tag di browser mengirim session cookie secara otomatis
    $filename = basename($_GET['file'] ?? '');

    // Validasi nama file - hanya izinkan format qris_*
    if (!$filename || !preg_match('/^qris_[a-zA-Z0-9_]+\.(jpg|jpeg|png|webp|gif)$/i', $filename)) {
        http_response_code(400);
        header('Content-Type: text/plain');
        echo 'File tidak valid'; exit;
    }

    // Cek apakah admin sudah login (via session cookie dari browser)
    if (!isAdminLoggedIn()) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Login admin diperlukan'; exit;
    }

    $path = __DIR__ . '/../admin/assets/bukti_qris/' . $filename;
    if (!file_exists($path)) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'File tidak ditemukan: ' . $filename; exit;
    }

    $mime = mime_content_type($path);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=1800');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Action tidak dikenal: ' . $action]);
?>
