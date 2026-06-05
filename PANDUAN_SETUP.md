# 🍗 Panduan Setup Sistem Multi-Role
## Ayam Penyet Bendungan Batusangkar

---

## 📋 Langkah Setup

### 1. Import Database
1. Import `db_ayam_penyet.sql` ke phpMyAdmin seperti biasa
2. Kemudian jalankan **`db_ayam_penyet_update.sql`** untuk menambah kolom `role` dan user kitchen

### 2. Akun Login

| Role | Username | Password | Akses |
|------|----------|----------|-------|
| 👑 **Admin** | `admin` | `password` | Dashboard monitor + Laporan + Kelola Pengguna |
| 💼 **Kasir** | `kasir` | `password` | Dashboard + Kelola Menu + Kategori + Konfirmasi Bayar + QR Code |
| 🍳 **Kitchen** | `kitchen` | `password` | Lihat pesanan masuk + Konfirmasi pesanan siap |

---

## 🌐 URL Halaman

| Halaman | URL |
|---------|-----|
| **Login** | `/ayam-penyet/login.php` |
| **Admin Dashboard** | `/ayam-penyet/admin/dashboard.php` |
| **Admin Laporan** | `/ayam-penyet/admin/laporan.php` |
| **Kasir Dashboard** | `/ayam-penyet/kasir/dashboard.php` |
| **Kitchen Display** | `/ayam-penyet/kitchen/index.php` |

---

## 🏗️ Struktur Folder

```
ayam-penyet/
├── login.php              ← Login terpadu (semua role)
├── logout.php             ← Logout
├── koneksi.php            ← Database + auth functions
├── db_ayam_penyet_update.sql  ← Jalankan setelah import DB!
│
├── admin/                 ← Panel Admin
│   ├── dashboard.php      ← Monitor pesanan real-time
│   ├── laporan.php        ← Laporan harian/mingguan/bulanan/tahunan
│   └── admin_user.php     ← Kelola pengguna (tambah kasir/kitchen)
│
├── kasir/                 ← Panel Kasir
│   ├── dashboard.php      ← Dashboard pesanan
│   ├── menu.php           ← Kelola menu
│   ├── kategori.php       ← Kelola kategori
│   ├── konfirmasi_bayar.php ← Konfirmasi pembayaran cash & QRIS
│   └── qrcode.php         ← QR Code per meja
│
└── kitchen/               ← Panel Kitchen
    └── index.php          ← Tampilan pesanan masuk + tombol siap
```

---

## ⚙️ Alur Kerja

```
Pelanggan scan QR → Pesan menu → 
  Kasir (konfirmasi bayar) ↔ Kitchen (proses & konfirmasi siap) ← 
  Admin (pantau semua + laporan)
```

---

## 🔧 Ganti Password Default
Setelah setup, masuk sebagai Admin → **Kelola Pengguna** → ganti semua password!
