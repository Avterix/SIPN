# SIPN (Sistem Informasi Penilaian Sekolah)

Dokumentasi Resmi Proyek & Panduan Penggunaan Modular

> **RINGKASAN EKSEKUTIF PROYEK SIPN**
> - **Nama Proyek:** SIPN (Sistem Informasi Penilaian Sekolah)
> - **Lingkungan Pengembangan:** PHP 7.4 / 8.x, MySQL (Aiven & Local Apache/LAMPP), HTML5, CSS3, JS
> - **Integrasi OAuth:** Dual Provider (GitHub OAuth API & Google OAuth 2.0 API)
> - **Tampilan Antarmuka:** Responsive Bento Grid Layout, Custom Glassmorphism Auth UI, Async Page Loaders
> - **Lisensi:** Apache License 2.0

---

## 1. Tentang Proyek & Pembaruan Utama

SIPN (Sistem Informasi Penilaian Sekolah) adalah aplikasi berbasis web modular yang dikembangkan untuk mengelola data akademik sekolah secara terpusat, terstruktur, aman, dan efisien. Sistem ini menangani seluruh siklus pengelolaan akademik mulai dari otorisasi pengguna, administrasi direktori siswa dan pengajar, manajemen kurikulum mata pelajaran, hingga pencatatan dan evaluasi transkrip nilai per semester.

Pada versi SIPN ini, dilakukan beberapa peningkatan infrastruktur dan visual yang signifikan guna meningkatkan keamanan, kemudahan aksesibilitas, serta kenyamanan pengalaman pengguna (UI/UX). Beberapa pembaruan utama meliputi:

- **Integrasi Dual OAuth:** Penambahan Google OAuth 2.0 API sebagai pilihan otorisasi pihak ketiga di samping GitHub OAuth API, memungkinkan login sekali klik yang cepat dan aman tanpa mengetik kredensial lokal.
- **Pendaftaran OAuth Terpandu:** Halaman pendaftaran mandiri khusus bagi pengguna yang pertama kali masuk melalui OAuth (`complete-github-reg.php` dan `complete-google-reg.php`) untuk melengkapi data identitas peran (Siswa/Guru).
- **Antarmuka Bento Grid Modern:** Redesain dashboard utama menggunakan konsep Bento Grid Layout modern (`dashboard-bento.css`) yang mengelompokkan informasi secara simetris, intuitif, dan responsif.
- **Sistem Async Loading State:** Penerapan indikator pemuatan halaman interaktif (`loader.php` dan `loader_nilai.php`) untuk memberikan umpan balik visual saat memproses kalkulasi nilai akademik atau data server yang tebal.
- **Visual Auth UI Refresh:** Penyempurnaan tampilan halaman autentikasi (`auth-style3.css`) dengan gaya visual modern dan proteksi sesi yang lebih ketat.

---

## 2. Fitur Utama Sistem

### 2.1. Autentikasi & Otorisasi Pengguna

- **Manajemen Akun Lokal:** Mendukung pembuatan akun baru (`register.php`) dan proses masuk berbasis kredensial lokal (`login.php`) yang terenkripsi aman di basis data.
- **Integrasi GitHub OAuth:** Otorisasi praktis bagi pengembang atau staf menggunakan OAuth GitHub (`github-login.php`, `github-callback.php`, `github-disconnect.php`) dan registrasi otomatis.
- **Integrasi Google OAuth 2.0:** Integrasi identitas resmi akun Google (`google-login.php`, `google-callback.php`, `config_google.php`, `complete-google-reg.php`) untuk siswa dan pengajar.
- **Pengelolaan Profil Mandiri:** Pengguna dapat memperbarui data pribadi, foto profil, serta mengelola penautan/pemutusan akun OAuth secara mandiri (`profile.php`).

### 2.2. Manajemen Pengguna (User, Siswa, & Guru)

- **Pengelolaan Pengguna Sistem:** Operasi CRUD (Create, Read, Update, Delete) lengkap untuk mengelola akun pengguna sistem (`daftar_user.php`, `form_user.php`, `edit_user.php`, `aksi_tambah_user.php`, `aksi_edit_user.php`).
- **Direktori Siswa Akademik:** Penayangan terorganisir untuk direktori siswa aktif (`daftar_siswa.php`) lengkap dengan informasi NISN, kelas, dan status keaktifan.
- **Direktori Pengajar / Guru:** Penayangan direktori tenaga pengajar/guru (`daftar_guru.php`) yang mencantumkan NIP, mata pelajaran yang diampu, dan profil kontak.

### 2.3. Manajemen Mata Pelajaran (Mapel)

- **Pengolahan Kurikulum Mapel:** Alur kerja komprehensif untuk menambah, mengubah, meninjau, dan menghapus daftar mata pelajaran dalam kurikulum sekolah (`daftar_mapel.php`, `form_mapel.php`, `edit_mapel.php`, `aksi_tambah_mapel.php`, `aksi_edit_mapel.php`, `aksi_hapus_mapel.php`).

### 2.4. Manajemen & Rekapitulasi Nilai Akademik

- **Pencatatan Nilai Terstruktur:** Pencatatan nilai tugas, UTS, UAS, dan nilai akhir siswa per semester secara sistematis (`daftar_nilai.php`).
- **Evaluasi Transkrip & Rekap:** Peninjauan rekapitulasi transkrip nilai akademik siswa beserta kalkulasi rata-rata kumulatif dan verifikasi kelulusan (`lihat_nilai.php`).
- **Visual Progress Evaluation:** Tampilan penemu nilai berkecepatan tinggi dengan loader visual untuk pemrosesan nilai skala besar (`loader_nilai.php`).

### 2.5. Antarmuka & Dashboard Bento Grid

- **Layout Bento Grid:** Dashboard utama interaktif berbasis bento grid (`dashboard.php` & `dashboard-bento.css`) dengan kartu statistik cepat untuk jumlah siswa, guru, mapel, dan nilai.
- **Komponen Layout Modular:** Struktur UI terintegrasi menggunakan komponen modular header, navbar (`navbar.php`), footer, serta indikator status sistem.
- **Set Aset Ikon Kustom:** Penyediaan perpustakaan ikon kustom lengkap (`icons/dashboard`, `icons/report`, `icons/profile`, `icons/admin`, `icons/login`) untuk navigasi visual yang ramah pengguna.

---

## 3. Arsitektur & Spesifikasi Teknologi

Tabel berikut merinci arsitektur teknis dan komponen infrastruktur yang digunakan dalam proyek SIPN:

| Komponen | Teknologi / Lingkungan | Deskripsi & Peran |
| :--- | :--- | :--- |
| **Frontend** | HTML5, CSS3, JS, Vercel | Struktur UI, styling kustom (Bento/Auth), & interaktivitas client-side. |
| **Backend** | PHP 7.4 / 8.x | Bahasa pemrograman utama logika bisnis, autentikasi, & API handler. |
| **Database Engine** | MySQL / Beekeeper Studio | Penyimpanan data relasional terstruktur (User, Mapel, Nilai). |
| **Web Server** | Apache HTTP Server | Server HTTP lokal/LAMPP (Direktori Web Root: `/opt/lampp/htdocs/SIPN`). |
| **Cloud Infrastructure** | Aiven Managed Database | Layanan basis data MySQL terkelola di cloud untuk skalabilitas. |
| **OAuth Provider API** | GitHub REST API & Google OAuth 2.0 API | Penyedia otorisasi dan verifikasi identitas pihak ketiga. |

---

## 4. Struktur Direktori Proyek Lengkap

Berikut adalah struktur hirarki berkas dan direktori lengkap proyek SIPN beserta fungsi utama dari masing-masing berkas:

| Path / Nama Berkas | Fungsi & Deskripsi Berkas |
| :--- | :--- |
| `api/aksi_edit_mapel.php` | Handler pemrosesan pembaruan data mata pelajaran di database. |
| `api/aksi_edit_user.php` | Handler pemrosesan pembaruan data pengguna sistem. |
| `api/aksi_hapus_mapel.php` | Handler penghapusan data mata pelajaran dari kurikulum. |
| `api/aksi_tambah_mapel.php` | Handler penambahan data mata pelajaran baru. |
| `api/aksi_tambah_user.php` | Handler pendaftaran/penambahan pengguna baru oleh admin. |
| `api/complete-github-reg.php` | Form pelengkapan data profil setelah login pertama via GitHub. |
| `api/complete-google-reg.php` | Form pelengkapan data profil setelah login pertama via Google. |
| `api/config_github.php` | Konfigurasi kredensial Client ID, Secret, & Redirect URL GitHub OAuth. |
| `api/config_google.php` | Konfigurasi kredensial Client ID, Secret, & Redirect URL Google OAuth. |
| `api/dashboard.php` | Tampilan utama dashboard interaktif berbento grid. |
| `api/daftar_guru.php` | Halaman penayangan direktori daftar guru/pengajar. |
| `api/daftar_mapel.php` | Halaman pengolahan kurikulum dan daftar mata pelajaran. |
| `api/daftar_nilai.php` | Halaman input dan pengolahan data nilai siswa. |
| `api/daftar_siswa.php` | Halaman penayangan direktori data siswa aktif. |
| `api/daftar_user.php` | Halaman manajemen pengguna dan hak akses sistem. |
| `api/edit_mapel.php` | Form antarmuka untuk mengubah data mata pelajaran. |
| `api/form_mapel.php` | Form antarmuka untuk membuat mata pelajaran baru. |
| `api/form_user.php` | Form antarmuka untuk menambah pengguna sistem baru. |
| `api/github-callback.php` | Endpoint callback pengolahan token otorisasi dari GitHub. |
| `api/github-disconnect.php` | Handler pemutusan tautan otorisasi akun GitHub dari profil. |
| `api/github-login.php` | Handler inisiasi alur pendaftaran/login via GitHub OAuth. |
| `api/google-callback.php` | Endpoint callback pengolahan token otorisasi dari Google. |
| `api/google-login.php` | Handler inisiasi alur pendaftaran/login via Google OAuth 2.0. |
| `api/index.php` | Halaman penunjuk awal / landing page sistem. |
| `api/koneksi.php` | Konfigurasi koneksi utama ke database MySQL / Aiven. |
| `api/lihat_nilai.php` | Halaman detail dan rekapitulasi transkrip nilai siswa. |
| `api/loader.php` | Komponen animasi pemuatan (loader) halaman umum. |
| `api/loader_nilai.php` | Komponen animasi pemuatan khusus kalkulasi data nilai. |
| `api/login.php` | Halaman autentikasi/login berbasis akun lokal. |
| `api/logout.php` | Handler penghancuran sesi pengguna (logout). |
| `api/navbar.php` | Komponen navigasi atas (navbar) terintegrasi. |
| `api/profile.php` | Halaman pengelolaan profil pribadi dan tautan akun OAuth. |
| `api/register.php` | Halaman pendaftaran pengguna baru lokal. |
| `dashboard-bento.css` | Berkas CSS kustom untuk tampilan layout Bento Grid pada dashboard. |
| `auth-style3.css` | Berkas CSS kustom untuk penataan visual halaman login & register. |

---

## 5. Memulai Proyek (Getting Started)

### 5.1. Persyaratan Sistem

- **Web Server:** Apache Web Server (terintegrasi dalam XAMPP / LAMPP / WAMP)
- **Database Engine:** MySQL v5.7+ / MariaDB v10.4+ atau Layanan Managed Cloud DB (Aiven)
- **PHP Engine:** PHP Versi 7.4 atau 8.x (memiliki ekstensi cURL & PDO/mysqli aktif)
- **Peramban Web:** Google Chrome, Mozilla Firefox, Microsoft Edge, atau Safari versi terbaru

### 5.2. Langkah Instalasi & Konfigurasi

#### Langkah 1: Penempatan Berkas Proyek
Unduh atau salin seluruh direktori proyek SIPN ke dalam folder web root server:
- **Linux (LAMPP):** `/opt/lampp/htdocs/SIPN/`
- **Windows (XAMPP):** `C:/xampp/htdocs/SIPN/`

#### Langkah 2: Jalankan Layanan Server
Buka Control Panel XAMPP/LAMPP dan jalankan modul Apache serta MySQL.

#### Langkah 3: Inisialisasi Basis Data
- Akses phpMyAdmin di peramban web: `http://localhost/phpmyadmin`
- Buat database baru dengan nama `db_sipn`.
- Impor skema tabel basis data SIPN melalui tab **Import**.

#### Langkah 4: Konfigurasi Koneksi Database
Buka berkas `SIPN/api/koneksi.php` dan sesuaikan parameter koneksi berikut:

```php
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_sipn";

$koneksi = mysqli_connect($host,$user, $pass,$db);
if (!$koneksi) { 
    die("Koneksi gagal: " . mysqli_connect_error()); 
}
?>
