<?php
require_once 'koneksi.php';
session_destroy();
redirect('/ayam-penyet/login.php');
?>
