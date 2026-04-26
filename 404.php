<?php
require_once 'koneksi.php';
$nomor_meja = getNomorMeja();
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Tidak Ditemukan</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F5F5F5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; text-align: center; padding: 24px; }
        .box { max-width: 340px; }
        .icon { font-size: 80px; margin-bottom: 16px; }
        h1 { font-size: 22px; font-weight: 800; color: #1A1A2E; margin: 0 0 8px; }
        p  { color: #888; font-size: 14px; margin: 0 0 24px; }
        a  { background: #E84040; color: white; text-decoration: none; border-radius: 50px; padding: 13px 28px; font-size: 15px; font-weight: 700; display: inline-block; transition: all 0.2s; }
        a:hover { background: #C42E2E; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">🍽️</div>
        <h1>Halaman Tidak Ditemukan</h1>
        <p>Maaf, halaman yang Anda cari tidak ada.</p>
        <a href="index.php<?= $nomor_meja > 0 ? '?meja='.$nomor_meja : '' ?>">← Kembali ke Menu</a>
    </div>
</body>
</html>
