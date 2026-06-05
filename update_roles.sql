-- ============================================
-- UPDATE: Tambah kolom role ke tabel admin
-- Jalankan sekali di phpMyAdmin atau terminal MySQL
-- ============================================

-- Tambah kolom role jika belum ada
ALTER TABLE `admin` ADD COLUMN IF NOT EXISTS `role` ENUM('admin','kasir','kitchen') NOT NULL DEFAULT 'kasir' AFTER `username`;

-- Update user yang sudah ada
UPDATE `admin` SET `role` = 'admin' WHERE `username` = 'admin';
UPDATE `admin` SET `role` = 'kasir' WHERE `username` = 'kasir';

-- Tambah user kitchen (password: password)
INSERT IGNORE INTO `admin` (`username`, `role`, `password`, `nama`) VALUES
('kitchen', 'kitchen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kitchen Staff');

-- Jika kolom role sudah ada sebelumnya (tanpa IF NOT EXISTS):
-- ALTER TABLE `admin` MODIFY COLUMN `role` ENUM('admin','kasir','kitchen') NOT NULL DEFAULT 'kasir';
