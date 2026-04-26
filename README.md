# 🍗 Ayam Penyet Bendungan Batusangkar
## Sistem Pemesanan Menu Berbasis Web (QR Code + LAN)

---

# KELOMPOK 8
1. IBNU ROOFI NIM 2330407014
2. RAHMA NADYA NIM 2330407020
3. MUHAMMAD ZAKI FAUZI NIM 2330407017

## 📦 DAFTAR FILE LENGKAP

```
/ayam-penyet/
│
├── index.php           ← Halaman menu utama (customer)
├── keranjang.php       ← Keranjang belanja
├── checkout.php        ← Proses checkout (API)
├── sukses.php          ← Halaman sukses setelah pesan
├── status.php          ← Cek status pesanan real-time
├── koneksi.php         ← Koneksi database + helper functions
├── setup.php           ← Setup awal & manajemen admin
├── 404.php             ← Halaman error 404
├── .htaccess           ← Konfigurasi Apache
├── database.sql        ← File SQL database
│
├── api/
│   └── keranjang.php   ← API AJAX keranjang
│
└── admin/
    ├── login.php        ← Login admin
    ├── logout.php       ← Logout
    ├── dashboard.php    ← Dashboard utama + kelola pesanan
    ├── menu.php         ← Daftar & kelola menu
    ├── tambah_menu.php  ← Tambah menu baru
    ├── edit_menu.php    ← Edit menu
    ├── laporan.php      ← Laporan penjualan
    ├── qrcode.php       ← Generate QR Code meja
    ├── meja.php         ← Manajemen meja
    ├── api_admin.php    ← API AJAX admin
    └── assets/
        ├── css/
        │   └── style.css
        ├── js/
        │   └── app.js
        └── images/      ← Upload foto menu disini
```

---

## 🚀 PANDUAN INSTALASI LENGKAP

### STEP 1 — Install XAMPP

1. Download XAMPP dari: **https://www.apachefriends.org/**
2. Pilih versi untuk Windows (PHP 8.x)
3. Install dengan setting default
4. Buka **XAMPP Control Panel**
5. Klik **Start** pada **Apache** dan **MySQL**
6. Pastikan keduanya berstatus **Running** (hijau)

---

### STEP 2 — Copy Project

1. Buka folder: `C:\xampp\htdocs\`
2. Copy folder **`ayam-penyet`** ke sana
3. Hasilnya: `C:\xampp\htdocs\ayam-penyet\`

---

### STEP 3 — Import Database

**Cara A — Via phpMyAdmin (Direkomendasikan):**

1. Buka browser → ketik: `http://localhost/phpmyadmin`
2. Klik **"New"** di panel kiri
3. Isi nama database: `db_ayam_penyet`
4. Klik **Create**
5. Klik tab **Import**
6. Klik **Choose File** → pilih file `database.sql`
7. Klik **Go / Import**
8. Tunggu sampai muncul pesan sukses ✅

**Cara B — Via MySQL CLI:**
```bash
mysql -u root -p
CREATE DATABASE db_ayam_penyet;
USE db_ayam_penyet;
SOURCE C:/xampp/htdocs/ayam-penyet/database.sql;
```

---

### STEP 4 — Konfigurasi Koneksi Database

Buka file: `C:\xampp\htdocs\ayam-penyet\koneksi.php`

Sesuaikan jika perlu:
```php
define('DB_HOST', 'localhost');  // Biasanya tidak perlu diubah
define('DB_USER', 'root');       // Username MySQL (default: root)
define('DB_PASS', '');           // Password MySQL (default: kosong)
define('DB_NAME', 'db_ayam_penyet');
```

---

### STEP 5 — Setup Password Admin

1. Buka browser → `http://localhost/ayam-penyet/setup.php`
2. Di bagian "Update Password Admin", masukkan:
   - Admin: `admin`
   - Nama: `Administrator`
   - Password baru: (isi password yang Anda inginkan)
3. Klik **Update Password**
4. **PENTING:** Setelah selesai, hapus atau rename file `setup.php`!

**Default login admin:**
- Username: `admin` / Password: `password`
- Username: `kasir` / Password: `password`

---

### STEP 6 — Test di Localhost

Buka browser dan akses:
- **Menu Customer:** `http://localhost/ayam-penyet/?meja=1`
- **Login Admin:**   `http://localhost/ayam-penyet/admin/login.php`

---

## 📱 CARA AKSES VIA HP (JARINGAN LOKAL)

### Cari IP Komputer Server:

**Windows:**
1. Tekan `Win + R` → ketik `cmd` → Enter
2. Ketik: `ipconfig`
3. Cari baris: **"IPv4 Address"**
4. Contoh: `192.168.1.10`

**Mac/Linux:**
```bash
ifconfig | grep "inet " | grep -v 127.0.0.1
```

### Pastikan HP & Komputer 1 Jaringan:
- Hubungkan HP ke WiFi yang sama dengan komputer server
- Atau buat **Hotspot** dari komputer, sambungkan HP ke hotspot tersebut

### Akses dari HP:
```
http://192.168.1.10/ayam-penyet/?meja=1
http://192.168.1.10/ayam-penyet/?meja=2
http://192.168.1.10/ayam-penyet/admin/login.php
```

> ⚠️ **Ganti** `192.168.1.10` dengan IP komputer Anda yang sebenarnya!

---

## 📲 CARA MEMBUAT QR CODE PER MEJA

### Metode 1 — Otomatis via Sistem (DIREKOMENDASIKAN):

1. Login admin → `http://IP_SERVER/ayam-penyet/admin/login.php`
2. Klik menu **"QR Code Meja"** di sidebar
3. Masukkan IP server Anda di kolom yang tersedia
4. Klik **"Generate Ulang"**
5. QR Code untuk semua meja akan otomatis dibuat
6. Klik **"Download QR"** untuk setiap meja
7. Atau klik **"Cetak Semua"** untuk mencetak sekaligus

### Metode 2 — Manual via Website Gratis:

Buka salah satu situs:
- **qr-code-generator.com**
- **qrcode-monkey.com**

Buat QR dengan URL:
```
http://192.168.1.10/ayam-penyet/?meja=1
http://192.168.1.10/ayam-penyet/?meja=2
http://192.168.1.10/ayam-penyet/?meja=3
... dst untuk setiap meja
```

### Tips Mencetak & Memasang QR Code:
1. Print QR Code ukuran minimal **5cm × 5cm**
2. Laminating agar tahan lama dan tahan air
3. Tempel di sudut meja atau taruh di holder akrilik
4. Test scan sebelum dipasang permanen

---

## 🎯 ALUR PENGGUNAAN SISTEM

### Alur Customer:
```
Scan QR Code → Lihat Menu → Tambah ke Keranjang
→ Checkout → Pesanan Masuk → Tunggu → Bayar di Kasir
```

### Alur Admin/Kasir:
```
Login Admin → Dashboard → Lihat Pesanan Masuk (Pending)
→ Ubah ke "Diproses" (sambil disiapkan dapur)
→ Ubah ke "Selesai" (setelah disajikan)
→ Customer bayar di kasir
```

### Alur Dapur:
```
Lihat Dashboard → Status "Diproses" → Masak Pesanan
→ Setelah selesai, kasir ubah status ke "Selesai"
```

---

## 🔐 AKUN DEFAULT

| Role | Username | Password |
|------|----------|----------|
| Administrator | admin | password |
| Kasir | kasir | password |

> ⚠️ **Segera ganti password** melalui `setup.php` setelah install!

---

## ⚙️ FITUR LENGKAP SISTEM

### 👤 Customer (User):
- [x] Lihat menu dengan kategori & harga
- [x] Search/cari menu
- [x] Tambah ke keranjang (tanpa reload halaman)
- [x] Atur jumlah item di keranjang
- [x] Tambah catatan pesanan
- [x] Checkout & kirim pesanan
- [x] Halaman sukses dengan kode pesanan
- [x] Cek status pesanan real-time (auto-refresh 10 detik)
- [x] Nomor meja otomatis dari QR Code
- [x] **Bayar Tunai** → pergi ke kasir, kasir yang konfirmasi
- [x] **Bayar QRIS/GoPay** → transfer ke 083803293430, upload bukti
- [x] Halaman tunggu kasir (polling otomatis)
- [x] Halaman tunggu verifikasi QRIS (auto-redirect saat lunas)
- [x] Download struk digital (PNG) atau print
- [x] Riwayat semua pesanan hari ini per meja

### 👨‍💼 Admin/Kasir:
- [x] Login aman dengan password hash (bcrypt)
- [x] Dashboard dengan statistik real-time
- [x] **Panel Konfirmasi Pembayaran** (`admin/konfirmasi_bayar.php`)
  - Cash: kasir input nominal uang, hitung kembalian otomatis
  - QRIS: lihat bukti foto, terima atau tolak verifikasi
- [x] Badge notif jumlah pembayaran menunggu di sidebar
- [x] Lihat semua pesanan masuk
- [x] Update status pesanan (pending → diproses → selesai)
- [x] Filter pesanan berdasarkan status
- [x] Lihat detail pesanan via modal popup
- [x] Auto-refresh dashboard (30 detik)
- [x] CRUD Menu lengkap (tambah, edit, hapus)
- [x] Upload foto menu
- [x] Toggle ketersediaan menu (tanpa reload)
- [x] Laporan penjualan per tanggal
- [x] Menu terlaris dengan ranking
- [x] Generate & Download QR Code per meja
- [x] Manajemen meja (tambah, status, hapus)
- [x] Print laporan

---

## 🔧 TROUBLESHOOTING

### ❌ "Koneksi Database Gagal"
- Pastikan MySQL Running di XAMPP Control Panel
- Cek nama database: `db_ayam_penyet`
- Cek username/password di `koneksi.php`

### ❌ HP Tidak Bisa Akses
- Pastikan HP & PC 1 jaringan WiFi
- Cek IP di CMD: `ipconfig`
- Coba disable Windows Firewall sementara
- Pastikan Apache sudah Running

### ❌ QR Code Tidak Berfungsi
- Update IP di halaman QR Code admin
- Pastikan format URL benar
- Test URL di browser PC dulu sebelum di HP

### ❌ Gambar Menu Tidak Muncul
- Pastikan folder `admin/assets/images/` bisa ditulis (writeable)
- Ukuran gambar maksimal 2MB
- Format: JPG, PNG, atau WEBP

### ❌ Session Keranjang Hilang
- Cek setting session di PHP
- Pastikan `session_start()` dipanggil di `koneksi.php`

---

## 📞 INFORMASI TEKNIS

- **Server:** XAMPP (Apache + MySQL)
- **PHP:** 7.4+ atau 8.x
- **Database:** MySQL 5.7+
- **Framework CSS:** Bootstrap 5.3
- **Font:** Plus Jakarta Sans (Google Fonts)
- **Icons:** Font Awesome 6.4
- **QR Library:** qrcodejs

---

*Dibuat untuk: Ayam Penyet Bendungan Batusangkar*
*Versi: 1.0.0*


---

## 💳 ALUR SISTEM PEMBAYARAN

### Bayar Tunai (Cash):
```
Customer pilih "Tunai" → Klik Konfirmasi ke Kasir
→ Halaman tunggu kasir (tunjukkan kode pesanan)
→ Kasir buka admin/konfirmasi_bayar.php
→ Kasir input nominal uang yang diterima
→ Sistem hitung kembalian otomatis
→ Klik Konfirmasi → Status lunas
→ Struk digital otomatis muncul di HP customer
```

### Bayar QRIS / GoPay:
```
Customer pilih "QRIS/GoPay"
→ Tampil nomor tujuan: 083803293430 (a.n. Ayam Penyet)
→ Customer transfer via GoPay/OVO/DANA/ShopeePay
→ Screenshot bukti transfer → upload di halaman payment
→ Halaman menunggu verifikasi (polling setiap 10 detik)
→ Admin buka admin/konfirmasi_bayar.php tab "Verifikasi QRIS"
→ Admin lihat foto bukti → Klik Terima / Tolak
→ Jika diterima: status lunas, struk muncul otomatis
→ Jika ditolak: customer diminta upload ulang
```

### Kode Voucher (di halaman payment):
| Kode | Diskon |
|------|--------|
| HEMAT10 | 10% |
| GRATIS5K | Rp 5.000 |
| SPESIAL | Rp 10.000 |
| WEEKEND | 15% |

---