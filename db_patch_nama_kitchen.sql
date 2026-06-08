-- ============================================================
-- PATCH: Pastikan kolom nama_pelanggan ada di tabel pesanan
-- Jalankan script ini di phpMyAdmin jika nama pemesan
-- tidak muncul di halaman konfirmasi bayar kasir.
-- ============================================================

ALTER TABLE `pesanan`
  ADD COLUMN IF NOT EXISTS `nama_pelanggan` VARCHAR(100) DEFAULT 'Pelanggan';

-- Update baris lama yang NULL menjadi 'Pelanggan'
UPDATE `pesanan` SET `nama_pelanggan` = 'Pelanggan'
WHERE `nama_pelanggan` IS NULL OR `nama_pelanggan` = '';

-- Verifikasi
SELECT id, kode_pesanan, nama_pelanggan FROM pesanan ORDER BY id DESC LIMIT 10;
