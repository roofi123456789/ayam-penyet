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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Error Koneksi</title>
    <style>body{font-family:Arial;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f8f9fa;}
    .e{background:white;padding:40px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);text-align:center;max-width:400px;}
    h2{color:#dc3545;}</style></head><body><div class="e"><h2>⚠️ Koneksi Database Gagal</h2>
    <p>Error: <code>' . $conn->connect_error . '</code></p>
    <p>Pastikan XAMPP sudah berjalan dan database <strong>db_ayam_penyet</strong> sudah diimport.</p>
    </div></body></html>');
}
$conn->set_charset('utf8mb4');

// ============================================
// HELPER FUNCTIONS
// ============================================

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

function generateKodePesanan() {
    return 'ORD-' . strtoupper(substr(uniqid(), -6)) . '-' . date('dmHi');
}

function redirect($url) {
    header("Location: $url");
    exit();
}

// ============================================
// AUTH FUNCTIONS - ROLE BASED
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function isAdmin() {
    return isLoggedIn() && getUserRole() === 'admin';
}

function isKasir() {
    return isLoggedIn() && getUserRole() === 'kasir';
}

function isKitchen() {
    return isLoggedIn() && getUserRole() === 'kitchen';
}

// Legacy support - dianggap admin
function isAdminLoggedIn() {
    return isAdmin();
}

function requireRole($role) {
    if (!isLoggedIn()) {
        redirect('/ayam-penyet/login.php');
    }
    $userRole = getUserRole();
    if (is_array($role)) {
        if (!in_array($userRole, $role)) {
            redirect('/ayam-penyet/login.php?error=akses');
        }
    } else {
        if ($userRole !== $role) {
            redirect('/ayam-penyet/login.php?error=akses');
        }
    }
}

function requireAdminLogin() {
    requireRole('admin');
}

function requireKasirLogin() {
    requireRole(['kasir', 'admin']);
}

function requireKitchenLogin() {
    requireRole(['kitchen', 'admin']);
}

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

function getNomorMeja() {
    if (isset($_GET['meja']) && is_numeric($_GET['meja'])) {
        $_SESSION['nomor_meja'] = (int)$_GET['meja'];
    }
    return isset($_SESSION['nomor_meja']) ? (int)$_SESSION['nomor_meja'] : 0;
}

function getKeranjang() {
    return isset($_SESSION['keranjang']) ? $_SESSION['keranjang'] : [];
}

function getTotalKeranjang() {
    $keranjang = getKeranjang();
    $total = 0;
    foreach ($keranjang as $item) {
        $total += $item['harga'] * $item['jumlah'];
    }
    return $total;
}

function getJumlahKeranjang() {
    $keranjang = getKeranjang();
    $jumlah = 0;
    foreach ($keranjang as $item) {
        $jumlah += $item['jumlah'];
    }
    return $jumlah;
}
?>
