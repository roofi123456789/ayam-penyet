-- ============================================================
-- JALANKAN SCRIPT INI SETELAH IMPORT db_ayam_penyet.sql
-- Tambah sistem role: admin, kasir, kitchen
-- ============================================================

-- 1. Tambah kolom role ke tabel admin
ALTER TABLE `admin` 
  ADD COLUMN IF NOT EXISTS `role` ENUM('admin','kasir','kitchen') NOT NULL DEFAULT 'kasir' AFTER `username`;

-- 2. Set role untuk user yang sudah ada
UPDATE `admin` SET `role` = 'admin' WHERE `username` = 'admin';
UPDATE `admin` SET `role` = 'kasir' WHERE `username` = 'kasir';

-- 3. Tambah user kitchen (password: password)
INSERT INTO `admin` (`username`, `role`, `password`, `nama`) 
VALUES ('kitchen', 'kitchen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kitchen Staff')
ON DUPLICATE KEY UPDATE `role` = 'kitchen';

-- Verifikasi
SELECT id, username, role, nama FROM admin;
