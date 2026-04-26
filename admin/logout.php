<?php
require_once '../koneksi.php';
session_destroy();
header('Location: /ayam-penyet/admin/login.php');
exit;
?>
