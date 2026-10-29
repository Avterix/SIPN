# SIPN — Sistem Informasi Penilaian Sekolah

Dokumentasi resmi proyek & panduan penggunaan modular.

![PHP](https://img.shields.io/badge/PHP-7.4%20%7C%208.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Aiven%20%7C%20Local-4479A1?logo=mysql&logoColor=white)
![OAuth](https://img.shields.io/badge/OAuth-GitHub%20%7C%20Google-black)
![License](https://img.shields.io/badge/License-Apache%202.0-blue)

## Ringkasan Proyek

| Item | Keterangan |
| :-- | :-- |
| Nama Proyek | SIPN (Sistem Informasi Penilaian Sekolah) |
| Lingkungan | PHP 7.4 / 8.x, MySQL (Aiven & Local Apache/LAMPP), HTML5, CSS3, JS |
| Integrasi OAuth | GitHub OAuth API & Google OAuth 2.0 API |
| Antarmuka | Responsive Bento Grid, Glassmorphism Auth UI, Async Page Loaders |
| Lisensi | Apache License 2.0 |

## 1. Tentang Proyek

SIPN adalah aplikasi web modular untuk mengelola data akademik sekolah secara terpusat, terstruktur, aman, dan efisien. Cakupannya meliputi otorisasi pengguna, direktori siswa dan guru, manajemen kurikulum mata pelajaran, hingga pencatatan dan evaluasi nilai per semester.

### Pembaruan Utama

- **Dual OAuth:** Google OAuth 2.0 ditambahkan di samping GitHub OAuth untuk login sekali klik.
- **Pendaftaran OAuth Terpandu:** `complete-github-reg.php` dan `complete-google-reg.php` untuk melengkapi data peran (Siswa/Guru) saat login pertama.
- **Bento Grid Dashboard:** Redesain dashboard dengan `dashboard-bento.css` yang simetris dan responsif.
- **Async Loading State:** `loader.php` dan `loader_nilai.php` sebagai indikator pemuatan saat kalkulasi nilai atau data berat.
- **Auth UI Refresh:** Tampilan autentikasi baru (`auth-style3.css`) dengan proteksi sesi lebih ketat.

## 2. Fitur Utama

### 2.1 Autentikasi & Otorisasi

- **Akun lokal:** Registrasi (`register.php`) dan login (`login.php`) dengan kredensial terenkripsi.
- **GitHub OAuth:** `github-login.php`, `github-callback.php`, `github-disconnect.php`.
- **Google OAuth 2.0:** `google-login.php`, `google-callback.php`, `config_google.php`, `complete-google-reg.php`.
- **Profil mandiri:** Ubah data pribadi, foto profil, dan kelola tautan akun OAuth (`profile.php`).

### 2.2 Manajemen Pengguna

- **CRUD pengguna:** `daftar_user.php`, `form_user.php`, `edit_user.php`, `aksi_tambah_user.php`, `aksi_edit_user.php`.
- **Direktori siswa:** `daftar_siswa.php` (NISN, kelas, status keaktifan).
- **Direktori guru:** `daftar_guru.php` (NIP, mapel yang diampu, kontak).

### 2.3 Manajemen Mata Pelajaran

- Tambah, ubah, tinjau, dan hapus mapel: `daftar_mapel.php`, `form_mapel.php`, `edit_mapel.php`, `aksi_tambah_mapel.php`, `aksi_edit_mapel.php`, `aksi_hapus_mapel.php`.

### 2.4 Nilai Akademik

- **Pencatatan nilai:** Tugas, UTS, UAS, dan nilai akhir per semester (`daftar_nilai.php`).
- **Transkrip & rekap:** Rata-rata kumulatif dan verifikasi kelulusan (`lihat_nilai.php`).
- **Loader visual:** Progres pemrosesan nilai skala besar (`loader_nilai.php`).

### 2.5 Dashboard & UI

- **Bento Grid:** `dashboard.php` + `dashboard-bento.css` dengan kartu statistik siswa, guru, mapel, dan nilai.
- **Komponen modular:** Header, `navbar.php`, footer, dan indikator status.
- **Ikon kustom:** `icons/dashboard`, `icons/report`, `icons/profile`, `icons/admin`, `icons/login`.

## 3. Arsitektur & Teknologi

| Komponen | Teknologi | Peran |
| :-- | :-- | :-- |
| Frontend | HTML5, CSS3, JS, Vercel | Struktur UI, styling (Bento/Auth), interaktivitas |
| Backend | PHP 7.4 / 8.x | Logika bisnis, autentikasi, handler API |
| Database | MySQL / Beekeeper Studio | Data relasional (User, Mapel, Nilai) |
| Web Server | Apache HTTP Server | Lokal/LAMPP (`/opt/lampp/htdocs/SIPN`) |
| Cloud | Aiven Managed Database | MySQL terkelola di cloud |
| OAuth | GitHub REST API & Google OAuth 2.0 | Verifikasi identitas pihak ketiga |

## 4. Struktur Direktori

| Path | Fungsi |
| :-- | :-- |
| `api/aksi_edit_mapel.php` | Proses pembaruan data mapel |
| `api/aksi_edit_user.php` | Proses pembaruan data pengguna |
| `api/aksi_hapus_mapel.php` | Proses penghapusan mapel |
| `api/aksi_tambah_mapel.php` | Proses penambahan mapel |
| `api/aksi_tambah_user.php` | Proses penambahan pengguna oleh admin |
| `api/complete-github-reg.php` | Pelengkapan profil setelah login pertama via GitHub |
| `api/complete-google-reg.php` | Pelengkapan profil setelah login pertama via Google |
| `api/config_github.php` | Konfigurasi Client ID, Secret, Redirect URL GitHub |
| `api/config_google.php` | Konfigurasi Client ID, Secret, Redirect URL Google |
| `api/dashboard.php` | Dashboard Bento Grid |
| `api/daftar_guru.php` | Direktori guru |
| `api/daftar_mapel.php` | Daftar kurikulum mapel |
| `api/daftar_nilai.php` | Input dan pengolahan nilai |
| `api/daftar_siswa.php` | Direktori siswa aktif |
| `api/daftar_user.php` | Manajemen pengguna dan hak akses |
| `api/edit_mapel.php` | Form ubah mapel |
| `api/form_mapel.php` | Form tambah mapel |
| `api/form_user.php` | Form tambah pengguna |
| `api/github-callback.php` | Callback token GitHub |
| `api/github-disconnect.php` | Putus tautan akun GitHub |
| `api/github-login.php` | Inisiasi login/registrasi GitHub |
| `api/google-callback.php` | Callback token Google |
| `api/google-login.php` | Inisiasi login/registrasi Google |
| `api/index.php` | Landing page |
| `api/koneksi.php` | Koneksi database MySQL / Aiven |
| `api/lihat_nilai.php` | Detail dan rekap transkrip nilai |
| `api/loader.php` | Loader halaman umum |
| `api/loader_nilai.php` | Loader kalkulasi nilai |
| `api/login.php` | Login akun lokal |
| `api/logout.php` | Hancurkan sesi (logout) |
| `api/navbar.php` | Navbar terintegrasi |
| `api/profile.php` | Profil pribadi dan tautan OAuth |
| `api/register.php` | Registrasi pengguna lokal |
| `dashboard-bento.css` | Styling layout Bento Grid |
| `auth-style3.css` | Styling halaman login & register |

## 5. Memulai Proyek

### 5.1 Persyaratan Sistem

- **Web server:** Apache (XAMPP / LAMPP / WAMP)
- **Database:** MySQL 5.7+ / MariaDB 10.4+ atau Aiven
- **PHP:** 7.4 atau 8.x dengan ekstensi cURL dan PDO/mysqli aktif
- **Browser:** Chrome, Firefox, Edge, atau Safari versi terbaru

### 5.2 Instalasi

**1. Tempatkan berkas proyek** di web root:

- Linux (LAMPP): `/opt/lampp/htdocs/SIPN/`
- Windows (XAMPP): `C:/xampp/htdocs/SIPN/`

**2. Jalankan server.** Start modul Apache dan MySQL dari Control Panel XAMPP/LAMPP.

**3. Inisialisasi database.**

1. Buka `http://localhost/phpmyadmin`
2. Buat database baru bernama `db_sipn`
3. Impor skema tabel lewat tab **Import**

**4. Konfigurasi koneksi database** di `api/koneksi.php`:

```php
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_sipn";

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
```

**5. Konfigurasi OAuth.**

GitHub (`api/config_github.php`), daftarkan aplikasi di GitHub Developer Settings:

```php
define("GITHUB_CLIENT_ID", "CLIENT_ID_GITHUB_ANDA");
define("GITHUB_CLIENT_SECRET", "CLIENT_SECRET_GITHUB_ANDA");
define("REDIRECT_URL", "http://localhost/SIPN/api/github-callback.php");
```

Google (`api/config_google.php`), daftarkan proyek di Google Cloud Console:

```php
define("GOOGLE_CLIENT_ID", "CLIENT_ID_GOOGLE_ANDA");
define("GOOGLE_CLIENT_SECRET", "CLIENT_SECRET_GOOGLE_ANDA");
define("GOOGLE_REDIRECT_URL", "http://localhost/SIPN/api/google-callback.php");
```

**6. Akses aplikasi.**

- Portal login: <http://localhost/SIPN/api/login.php>
- Landing page: <http://localhost/SIPN/api/index.php>

## 6. Keamanan & Pemeliharaan

- **Validasi sesi ketat:** Setiap halaman terproteksi wajib memeriksa `session_start()` dan peran pengguna sebelum merender konten sensitif.
- **Rahasiakan API key:** Jangan commit Client Secret di `config_github.php` dan `config_google.php` ke repositori publik. Gunakan file `.env` di produksi.
- **Backup rutin:** Cadangkan skema dan data MySQL minimal seminggu sekali.

## 7. Lisensi

Didistribusikan di bawah [Apache License 2.0](LICENSE). Anda boleh menggunakan, memodifikasi, dan mendistribusikan ulang kode sumber sesuai ketentuan lisensi.
