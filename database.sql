-- ============================================
-- DATABASE: db_ayam_penyet
-- Ayam Penyet Bendungan Batusangkar
-- ============================================

CREATE DATABASE IF NOT EXISTS `db_ayam_penyet` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_ayam_penyet`;

-- ============================================
-- TABEL: admin
-- ============================================
CREATE TABLE IF NOT EXISTS `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin (password: admin123)
INSERT INTO `admin` (`username`, `password`, `nama`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator'),
('kasir', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kasir');

-- ============================================
-- TABEL: kategori
-- ============================================
CREATE TABLE IF NOT EXISTS `kategori` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kategori` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `kategori` (`nama_kategori`) VALUES
('Makanan Utama'),
('Lauk Pauk'),
('Minuman'),
('Snack & Gorengan');

-- ============================================
-- TABEL: menu
-- ============================================
CREATE TABLE IF NOT EXISTS `menu` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_kategori` INT DEFAULT 1,
  `nama_menu` VARCHAR(150) NOT NULL,
  `deskripsi` TEXT,
  `harga` INT NOT NULL,
  `gambar` VARCHAR(255) DEFAULT 'default.jpg',
  `tersedia` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_kategori`) REFERENCES `kategori`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `menu` (`id_kategori`, `nama_menu`, `deskripsi`, `harga`, `gambar`, `tersedia`) VALUES
(1, 'Ayam Penyet Original', 'Ayam goreng crispy dipenyet dengan sambal terasi khas Batusangkar, disajikan dengan lalapan segar', 25000, 'ayam-penyet.jpg', 1),
(1, 'Ayam Penyet Pedas Mampus', 'Level pedas tertinggi! Cocok untuk pecinta pedas sejati dengan sambal rawit merah', 27000, 'ayam-penyet-pedas.jpg', 1),
(1, 'Ayam Bakar Kecap', 'Ayam bakar bumbu kecap manis dengan aroma rempah pilihan', 28000, 'ayam-bakar.jpg', 1),
(1, 'Nasi Goreng Kampung', 'Nasi goreng dengan telur mata sapi, kecap, dan bumbu rahasia chef', 18000, 'nasi-goreng.jpg', 1),
(2, 'Tahu Tempe Goreng', 'Tahu dan tempe goreng crispy dengan sambal kecap', 8000, 'tahu-tempe.jpg', 1),
(2, 'Telur Dadar Spesial', 'Telur dadar tebal dengan irisan bawang dan cabe hijau', 7000, 'telur-dadar.jpg', 1),
(2, 'Ikan Goreng', 'Ikan segar goreng crispy dengan sambal terasi', 20000, 'ikan-goreng.jpg', 1),
(3, 'Es Teh Manis', 'Teh manis segar dengan es batu pilihan', 5000, 'es-teh.jpg', 1),
(3, 'Es Jeruk Peras', 'Jeruk peras segar dengan es batu, tanpa pengawet', 8000, 'es-jeruk.jpg', 1),
(3, 'Air Mineral', 'Air mineral botol 600ml', 4000, 'air-mineral.jpg', 1),
(3, 'Jus Alpukat', 'Jus alpukat segar dengan susu kental manis', 12000, 'jus-alpukat.jpg', 1),
(4, 'Tempe Mendoan', 'Tempe mendoan crispy dengan kecap dan cabe rawit', 6000, 'mendoan.jpg', 1),
(4, 'Pisang Goreng', 'Pisang kepok goreng crispy dengan taburan gula', 7000, 'pisang-goreng.jpg', 1);

-- ============================================
-- TABEL: pesanan
-- ============================================
CREATE TABLE IF NOT EXISTS `pesanan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_pesanan` VARCHAR(20) NOT NULL UNIQUE,
  `nomor_meja` INT NOT NULL,
  `nama_pelanggan` VARCHAR(100) DEFAULT 'Pelanggan',
  `catatan` TEXT,
  `total_harga` INT DEFAULT 0,
  `tanggal` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('pending','diproses','selesai','dibatalkan') DEFAULT 'pending',
  `metode_bayar` ENUM('cash','qris','transfer') DEFAULT NULL,
  `status_bayar` ENUM('belum_bayar','lunas') DEFAULT 'belum_bayar',
  `jumlah_bayar` INT DEFAULT 0,
  `kembalian` INT DEFAULT 0,
  `waktu_bayar` DATETIME DEFAULT NULL,
  `bukti_qris` VARCHAR(255) DEFAULT NULL,
  `status_verifikasi` ENUM('menunggu','terverifikasi','ditolak') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: detail_pesanan
-- ============================================
CREATE TABLE IF NOT EXISTS `detail_pesanan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_pesanan` INT NOT NULL,
  `id_menu` INT NOT NULL,
  `nama_menu` VARCHAR(150) NOT NULL,
  `harga` INT NOT NULL,
  `jumlah` INT NOT NULL,
  `subtotal` INT NOT NULL,
  FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_menu`) REFERENCES `menu`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TABEL: meja
-- ============================================
CREATE TABLE IF NOT EXISTS `meja` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nomor_meja` INT NOT NULL UNIQUE,
  `nama_meja` VARCHAR(50),
  `kapasitas` INT DEFAULT 4,
  `status` ENUM('tersedia','terisi','reserved') DEFAULT 'tersedia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `meja` (`nomor_meja`, `nama_meja`, `kapasitas`) VALUES
(1, 'Meja 1 - Dalam', 4),
(2, 'Meja 2 - Dalam', 4),
(3, 'Meja 3 - Dalam', 2),
(4, 'Meja 4 - Teras', 4),
(5, 'Meja 5 - Teras', 4),
(6, 'Meja 6 - Teras', 6),
(7, 'Meja 7 - VIP', 6),
(8, 'Meja 8 - VIP', 8),
(9, 'Meja 9 - Lesehan', 8),
(10, 'Meja 10 - Lesehan', 8);

-- ============================================
-- ============================================
-- UNTUK DATABASE YANG SUDAH ADA (v1/v2):
-- Jalankan query ALTER TABLE di bawah ini jika
-- sebelumnya sudah import database.sql yang lama
-- ============================================
-- ALTER TABLE `pesanan`
--   ADD COLUMN IF NOT EXISTS `metode_bayar` ENUM('cash','qris','transfer') DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS `status_bayar` ENUM('belum_bayar','lunas') DEFAULT 'belum_bayar',
--   ADD COLUMN IF NOT EXISTS `jumlah_bayar` INT DEFAULT 0,
--   ADD COLUMN IF NOT EXISTS `kembalian` INT DEFAULT 0,
--   ADD COLUMN IF NOT EXISTS `waktu_bayar` DATETIME DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS `bukti_qris` VARCHAR(255) DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS `status_verifikasi` ENUM('menunggu','terverifikasi','ditolak') DEFAULT NULL;

-- UNTUK DATABASE YANG SUDAH ADA:
-- Jalankan ALTER berikut jika tabel pesanan sudah ada
-- tapi belum punya kolom payment
-- ============================================
-- ALTER TABLE `pesanan` 
--   ADD COLUMN IF NOT EXISTS `metode_bayar` ENUM('cash','qris','transfer') DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS `status_bayar` ENUM('belum_bayar','lunas') DEFAULT 'belum_bayar',
--   ADD COLUMN IF NOT EXISTS `jumlah_bayar` INT DEFAULT 0,
--   ADD COLUMN IF NOT EXISTS `kembalian` INT DEFAULT 0,
--   ADD COLUMN IF NOT EXISTS `waktu_bayar` DATETIME DEFAULT NULL;

-- ============================================
-- MIGRATION: Tambah kolom payment (aman dijalankan ulang)
-- Jalankan ini jika database sudah ada dari versi sebelumnya
-- ============================================
SET @dbname = DATABASE();

-- Tambah metode_bayar jika belum ada
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='pesanan' AND COLUMN_NAME='metode_bayar') = 0,
    'ALTER TABLE `pesanan` ADD COLUMN `metode_bayar` ENUM(''cash'',''qris'',''transfer'') DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tambah status_bayar jika belum ada
SET @query2 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='pesanan' AND COLUMN_NAME='status_bayar') = 0,
    'ALTER TABLE `pesanan` ADD COLUMN `status_bayar` ENUM(''belum_bayar'',''lunas'') DEFAULT ''belum_bayar''',
    'SELECT 1'
);
PREPARE stmt FROM @query2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tambah kolom lainnya
SET @q3 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='pesanan' AND COLUMN_NAME='jumlah_bayar')=0,'ALTER TABLE `pesanan` ADD COLUMN `jumlah_bayar` INT DEFAULT 0','SELECT 1');
PREPARE stmt FROM @q3; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q4 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='pesanan' AND COLUMN_NAME='kembalian')=0,'ALTER TABLE `pesanan` ADD COLUMN `kembalian` INT DEFAULT 0','SELECT 1');
PREPARE stmt FROM @q4; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q5 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='pesanan' AND COLUMN_NAME='waktu_bayar')=0,'ALTER TABLE `pesanan` ADD COLUMN `waktu_bayar` DATETIME DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @q5; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q6 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='pesanan' AND COLUMN_NAME='bukti_qris')=0,'ALTER TABLE `pesanan` ADD COLUMN `bukti_qris` VARCHAR(255) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @q6; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q7 = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME='pesanan' AND COLUMN_NAME='status_verifikasi')=0,'ALTER TABLE `pesanan` ADD COLUMN `status_verifikasi` ENUM(''menunggu'',''terverifikasi'',''ditolak'') DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @q7; EXECUTE stmt; DEALLOCATE PREPARE stmt;
