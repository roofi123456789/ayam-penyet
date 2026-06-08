-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 26, 2026 at 11:42 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_ayam_penyet`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `nama`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', '2026-04-13 09:34:07'),
(2, 'kasir', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kasir', '2026-04-13 09:34:07');

-- --------------------------------------------------------

--
-- Table structure for table `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `id_menu` int(11) NOT NULL,
  `nama_menu` varchar(150) NOT NULL,
  `harga` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `subtotal` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_pesanan`
--

INSERT INTO `detail_pesanan` (`id`, `id_pesanan`, `id_menu`, `nama_menu`, `harga`, `jumlah`, `subtotal`) VALUES
(1, 1, 3, 'Ayam Bakar Kecap', 28000, 1, 28000),
(2, 1, 4, 'Nasi Goreng Kampung', 18000, 1, 18000),
(3, 1, 7, 'Ikan Goreng', 20000, 1, 20000),
(4, 2, 1, 'Ayam Penyet Original', 25000, 1, 25000),
(5, 2, 3, 'Ayam Bakar Kecap', 28000, 1, 28000),
(6, 2, 2, 'Ayam Penyet Pedas Mampus', 27000, 1, 27000),
(7, 2, 6, 'Telur Dadar Spesial', 7000, 1, 7000),
(8, 3, 3, 'Ayam Bakar Kecap', 28000, 1, 28000),
(9, 4, 3, 'Ayam Bakar Kecap', 28000, 1, 28000),
(10, 4, 1, 'Ayam Penyet Original', 25000, 1, 25000);

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`) VALUES
(1, 'Makanan Utama'),
(2, 'Lauk Pauk'),
(3, 'Minuman'),
(4, 'Snack & Gorengan');

-- --------------------------------------------------------

--
-- Table structure for table `meja`
--

CREATE TABLE `meja` (
  `id` int(11) NOT NULL,
  `nomor_meja` int(11) NOT NULL,
  `nama_meja` varchar(50) DEFAULT NULL,
  `kapasitas` int(11) DEFAULT 4,
  `status` enum('tersedia','terisi','reserved') DEFAULT 'tersedia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meja`
--

INSERT INTO `meja` (`id`, `nomor_meja`, `nama_meja`, `kapasitas`, `status`) VALUES
(1, 1, 'Meja 1 - Dalam', 4, 'tersedia'),
(2, 2, 'Meja 2 - Dalam', 4, 'tersedia'),
(3, 3, 'Meja 3 - Dalam', 2, 'tersedia'),
(4, 4, 'Meja 4 - Teras', 4, 'tersedia'),
(5, 5, 'Meja 5 - Teras', 4, 'tersedia'),
(6, 6, 'Meja 6 - Teras', 6, 'tersedia'),
(7, 7, 'Meja 7 - VIP', 6, 'tersedia'),
(8, 8, 'Meja 8 - VIP', 8, 'tersedia'),
(9, 9, 'Meja 9 - Lesehan', 8, 'tersedia'),
(10, 10, 'Meja 10 - Lesehan', 8, 'tersedia');

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
  `id_kategori` int(11) DEFAULT 1,
  `nama_menu` varchar(150) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga` int(11) NOT NULL,
  `gambar` varchar(255) DEFAULT 'default.jpg',
  `tersedia` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`id`, `id_kategori`, `nama_menu`, `deskripsi`, `harga`, `gambar`, `tersedia`, `created_at`) VALUES
(1, 1, 'Ayam Penyet Original', 'Ayam goreng crispy dipenyet dengan sambal terasi khas Batusangkar, disajikan dengan lalapan segar', 25000, 'menu_1776048991_517.jpeg', 1, '2026-04-13 09:34:07'),
(2, 1, 'Ayam Penyet Pedas Mampus', 'Level pedas tertinggi! Cocok untuk pecinta pedas sejati dengan sambal rawit merah', 27000, 'menu_1776049006_693.jpg', 1, '2026-04-13 09:34:07'),
(3, 1, 'Ayam Bakar Kecap', 'Ayam bakar bumbu kecap manis dengan aroma rempah pilihan', 28000, 'menu_1776048971_478.jpg', 1, '2026-04-13 09:34:07'),
(4, 1, 'Nasi Goreng Kampung', 'Nasi goreng dengan telur mata sapi, kecap, dan bumbu rahasia chef', 18000, 'menu_1776049261_438.jpeg', 1, '2026-04-13 09:34:07'),
(5, 2, 'Tahu Tempe Goreng', 'Tahu dan tempe goreng crispy dengan sambal kecap', 8000, 'menu_1776049290_300.jpg', 1, '2026-04-13 09:34:07'),
(6, 2, 'Telur Dadar Spesial', 'Telur dadar tebal dengan irisan bawang dan cabe hijau', 7000, 'menu_1776049303_252.jpg', 1, '2026-04-13 09:34:07'),
(7, 2, 'Ikan Goreng', 'Ikan segar goreng crispy dengan sambal terasi', 20000, 'menu_1776049276_172.jpeg', 1, '2026-04-13 09:34:07'),
(8, 3, 'Es Teh Manis', 'Teh manis segar dengan es batu pilihan', 5000, 'menu_1776049959_408.jpg', 1, '2026-04-13 09:34:07'),
(9, 3, 'Es Jeruk Peras', 'Jeruk peras segar dengan es batu, tanpa pengawet', 8000, 'menu_1776049873_862.jpg', 1, '2026-04-13 09:34:07'),
(10, 3, 'Air Mineral', 'Air mineral botol 600ml', 4000, 'menu_1776050346_479.jpg', 1, '2026-04-13 09:34:07'),
(11, 3, 'Jus Alpukat', 'Jus alpukat segar dengan susu kental manis', 12000, 'menu_1776050082_323.jpg', 1, '2026-04-13 09:34:07'),
(12, 4, 'Tempe Mendoan', 'Tempe mendoan crispy dengan kecap dan cabe rawit', 6000, 'menu_1776050107_294.webp', 1, '2026-04-13 09:34:07'),
(13, 4, 'Pisang Goreng', 'Pisang kepok goreng crispy dengan taburan gula', 7000, 'menu_1776050094_449.jpg', 1, '2026-04-13 09:34:07');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL,
  `kode_pesanan` varchar(20) NOT NULL,
  `nomor_meja` int(11) NOT NULL,
  `nama_pelanggan` varchar(100) DEFAULT 'Pelanggan',
  `catatan` text DEFAULT NULL,
  `total_harga` int(11) DEFAULT 0,
  `tanggal` datetime DEFAULT current_timestamp(),
  `status` enum('pending','diproses','selesai','dibatalkan') DEFAULT 'pending',
  `metode_bayar` enum('cash','qris','transfer') DEFAULT NULL,
  `status_bayar` enum('belum_bayar','lunas') DEFAULT 'belum_bayar',
  `jumlah_bayar` int(11) DEFAULT 0,
  `kembalian` int(11) DEFAULT 0,
  `waktu_bayar` datetime DEFAULT NULL,
  `bukti_qris` varchar(255) DEFAULT NULL,
  `status_verifikasi` enum('menunggu','terverifikasi','ditolak') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`id`, `kode_pesanan`, `nomor_meja`, `nama_pelanggan`, `catatan`, `total_harga`, `tanggal`, `status`, `metode_bayar`, `status_bayar`, `jumlah_bayar`, `kembalian`, `waktu_bayar`, `bukti_qris`, `status_verifikasi`) VALUES
(1, 'ORD-841CF9-13040449', 1, '0', '', 66000, '2026-04-13 09:49:12', 'diproses', NULL, 'belum_bayar', 0, 0, NULL, NULL, NULL),
(2, 'ORD-E44812-13040459', 1, '0', 'Jan pakai ayam', 87000, '2026-04-13 09:59:10', 'selesai', 'qris', 'lunas', 87000, 0, '2026-04-13 05:03:45', 'qris_ORD_E44812_13040459_1776049408.jpg', 'terverifikasi'),
(3, 'ORD-DA7A36-13040546', 1, '0', '', 28000, '2026-04-13 10:46:37', 'selesai', 'cash', 'lunas', 50000, 22000, '2026-04-13 06:00:46', NULL, 'terverifikasi'),
(4, 'ORD-AD53E9-13040602', 3, '0', 'Cepat ya', 53000, '2026-04-13 11:02:02', 'selesai', 'cash', 'lunas', 53000, 0, '2026-04-13 06:17:06', NULL, 'terverifikasi');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `id_menu` (`id_menu`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meja`
--
ALTER TABLE `meja`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_meja` (`nomor_meja`);

--
-- Indexes for table `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pesanan` (`kode_pesanan`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `meja`
--
ALTER TABLE `meja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `detail_pesanan_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detail_pesanan_ibfk_2` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id`);

--
-- Constraints for table `menu`
--
ALTER TABLE `menu`
  ADD CONSTRAINT `menu_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
