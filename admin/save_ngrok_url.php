<?php
require_once '../koneksi.php';
requireRole('admin');

$url = trim($_POST['url'] ?? '');

// Validasi: harus berupa URL yang valid
if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo 'invalid';
    exit;
}

// Simpan ke file
$config_file = __DIR__ . '/../ngrok_url.txt';
$result = file_put_contents($config_file, $url);

echo $result !== false ? 'ok' : 'error';
