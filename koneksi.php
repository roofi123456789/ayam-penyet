<?php
// ============================================
// koneksi.php - Database Connection
// Ayam Penyet Bendungan Batusangkar
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_ayam_penyet');
define('APP_NAME', 'Ayam Penyet Bendungan Batusangkar');
define('APP_VERSION', '1.0.0');

// Mulai session jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Koneksi ke MySQL menggunakan mysqli
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    die('
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Error Koneksi</title>
        <style>
            body { font-family: Arial; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f8f9fa; }
            .error-box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
            h2 { color: #dc3545; }
            p { color: #666; }
            code { background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h2>⚠️ Koneksi Database Gagal</h2>
            <p>Tidak dapat terhubung ke database MySQL.</p>
            <p>Error: <code>' . $conn->connect_error . '</code></p>
            <p>Pastikan XAMPP sudah berjalan dan database <strong>db_ayam_penyet</strong> sudah diimport.</p>
        </div>
    </body>
    </html>');
}

// Set charset ke UTF-8
$conn->set_charset('utf8mb4');

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Format rupiah
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Sanitize input
 */
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

/**
 * Generate kode pesanan unik
 */
function generateKodePesanan() {
    return 'ORD-' . strtoupper(substr(uniqid(), -6)) . '-' . date('dmHi');
}

/**
 * Redirect
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Cek apakah admin sudah login
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Paksa login admin
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        redirect('/ayam-penyet/admin/login.php');
    }
}

/**
 * Flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Get nomor meja dari URL atau session
 */
function getNomorMeja() {
    if (isset($_GET['meja']) && is_numeric($_GET['meja'])) {
        $_SESSION['nomor_meja'] = (int)$_GET['meja'];
    }
    return isset($_SESSION['nomor_meja']) ? (int)$_SESSION['nomor_meja'] : 0;
}

/**
 * Get keranjang dari session
 */
function getKeranjang() {
    return isset($_SESSION['keranjang']) ? $_SESSION['keranjang'] : [];
}

/**
 * Hitung total keranjang
 */
function getTotalKeranjang() {
    $keranjang = getKeranjang();
    $total = 0;
    foreach ($keranjang as $item) {
        $total += $item['harga'] * $item['jumlah'];
    }
    return $total;
}

/**
 * Hitung jumlah item keranjang
 */
function getJumlahKeranjang() {
    $keranjang = getKeranjang();
    $jumlah = 0;
    foreach ($keranjang as $item) {
        $jumlah += $item['jumlah'];
    }
    return $jumlah;
}
?>
