# **SIPN (Sistem Informasi Penilaian Sekolah)**

Dokumentasi Resmi Proyek & Panduan Penggunaan Modular

# **1\. Tentang Proyek**

SIPN (Sistem Informasi Penilaian) adalah aplikasi berbasis web yang dikembangkan menggunakan PHP dan MySQL untuk mengelola data akademik sekolah secara terpusat.

Aplikasi ini dirancang untuk memfasilitasi pengelolaan data pengguna (Siswa, Guru, Admin), data mata pelajaran (Mapel), serta pencatatan dan peninjauan rekapitulasi nilai siswa secara terstruktur, aman, dan efisien. Selain autentikasi berbasis akun lokal, aplikasi ini dilengkapi integrasi autentikasi pihak ketiga menggunakan **GitHub OAuth** untuk mempermudah aksesibilitas pengguna.

# **2\. Fitur Utama**

## **Autentikasi & Otorisasi Pengguna**

* **Manajemen Akun Lokal:** Fitur pendaftaran (Register) dan masuk (Login) akun berbasis kredensial database lokal.  
* **Integrasi GitHub OAuth:** Otorisasi dan login praktis menggunakan akun GitHub (`github-login.php`, `github-callback.php`, `github-disconnect.php`).  
* **Pengelolaan Profil:** Pengguna dapat memperbarui data pribadi dan foto profil secara mandiri (`profile.php`).

## **Manajemen Pengguna (User, Siswa, & Guru)**

* **Pengelolaan Akses Sistem:** Operasi CRUD (Create, Read, Update, Delete) untuk data pengguna/user sistem (`daftar_user.php`, `form_user.php`, `edit_user.php`).  
* **Direktori Akademik:** Penayangan terorganisir untuk daftar siswa aktif (`daftar_siswa.php`) dan daftar pengajar (`daftar_guru.php`).

## **Manajemen Mata Pelajaran (Mapel)**

* **Pengolahan Data Mapel:** Alur kerja lengkap untuk menambah, merubah, meninjau, dan menghapus kurikulum mata pelajaran (`daftar_mapel.php`, `form_mapel.php`, `edit_mapel.php`, `aksi_tambah_mapel.php`, `aksi_edit_mapel.php`, `aksi_hapus_mapel.php`).

## **Manajemen & Rekapitulasi Nilai**

* **Pencatatan & Evaluasi:** Entri nilai akademik per semester serta peninjauan rekapitulasi transkrip nilai siswa secara terstruktur (`daftar_nilai.php`, `lihat_nilai.php`).

## **Antarmuka & Dashboard**

* **UI/UX Modern:** Dashboard interaktif dengan visualisasi data yang ramah pengguna.  
* **Komponen Modular:** Penggunaan koleksi ikon kustom, komponen UI konsisten (`elements/`), navbar terintegrasi, serta indikator *loader* halaman.

# **3\. Arsitektur & Teknologi**

| Komponen | Teknologi / Lingkungan | Deskripsi |
| :---- | :---- | :---- |
| **Backend** | PHP 7.4 / 8.x | Bahasa pemrograman utama logika bisnis & API handler |
| **Database** | MySQL / MariaDB | Penyimpanan data relasional |
| **Web Server** | Apache | Server HTTP (Direktori Web Root: `/opt/lampp/htdocs/SIPN`) |
| **Frontend** | HTML5, CSS3, JavaScript | Struktur UI, styling kustom, dan interaktivitas client-side |
| **OAuth API** | GitHub REST API | Provider otorisasi dan identitas pihak ketiga |

# **4\. Struktur Direktori Proyek**

SIPN/

├── api/

│   ├── aksi\_edit\_mapel.php       \# Handler proses edit data mapel

│   ├── aksi\_edit\_user.php        \# Handler proses edit data user

│   ├── aksi\_hapus\_mapel.php      \# Handler hapus data mapel

│   ├── aksi\_tambah\_mapel.php     \# Handler tambah data mapel

│   ├── aksi\_tambah\_user.php      \# Handler tambah data user

│   ├── config\_github.php         \# Konfigurasi kredensial GitHub OAuth API

│   ├── dashboard.php             \# Tampilan halaman utama/dashboard

│   ├── daftar\_guru.php           \# Halaman daftar data guru

│   ├── daftar\_mapel.php          \# Halaman daftar mata pelajaran

│   ├── daftar\_nilai.php          \# Halaman pengolahan data nilai

│   ├── daftar\_siswa.php          \# Halaman daftar data siswa

│   ├── daftar\_user.php           \# Halaman manajemen pengguna sistem

│   ├── edit\_mapel.php            \# Form edit data mata pelajaran

│   ├── form\_mapel.php            \# Form tambah mata pelajaran

│   ├── form\_user.php             \# Form tambah pengguna baru

│   ├── github-callback.php       \# Endpoint callback otorisasi GitHub

│   ├── github-disconnect.php     \# Handler pemutusan tautan akun GitHub

│   ├── github-login.php          \# Handler inisiasi login GitHub OAuth

│   ├── index.php                 \# Halaman penunjuk awal / landing page

│   ├── koneksi.php               \# Konfigurasi koneksi database MySQL

│   ├── lihat\_nilai.php           \# Halaman detail/rekap nilai siswa

│   ├── login.php                 \# Halaman autentikasi/login lokal

│   ├── logout.php                \# Handler destruksi sesi/logout

│   ├── profile.php               \# Halaman pengelolaan profil user

│   └── register.php              \# Halaman pendaftaran pengguna baru

├── elements/                     \# Component template UI (Header, Navbar, Footer)

└── icons/                        \# Aset ikon dan grafis antarmuka

# **5\. Memulai Proyek (Getting Started)**

## **Persyaratan Sistem**

* **Web Server:** Apache (terintegrasi dalam XAMPP / LAMPP / WAMP)  
* **Database Engine:** MySQL v5.7+ atau MariaDB v10.4+  
* **PHP Engine:** PHP Versi 7.4 atau 8.x  
* **Peramban Web:** Google Chrome, Mozilla Firefox, Microsoft Edge, atau Safari versi terbaru

## **Langkah Instalasi & Konfigurasi**

1. **Penempatan Berkas Proyek**  
     
   Unduh atau salin direktori proyek `SIPN` ke direktori web root server Anda:  
     
   * **Linux (LAMPP):** `/opt/lampp/htdocs/SIPN/`  
   * **Windows (XAMPP):** `C:/xampp/htdocs/SIPN/`  
2. **Jalankan Layanan Server**  
     
   Buka Control Panel XAMPP/LAMPP dan jalankan modul **Apache** serta **MySQL**.  
     
3. **Inisialisasi Database**  
   * Buka peramban dan akses `http://localhost/phpmyadmin`  
   * Buat database baru (misal: `db_sipn`).  
   * Impor skema tabel basis data SIPN melalui tab **Import**.  
4. **Konfigurasi Koneksi Database**  
     
   Buka berkas `SIPN/api/koneksi.php` menggunakan editor teks, lalu sesuaikan parameter koneksi:\$host \= "localhost";  
     
   \$user \= "root";  
     
   \$pass \= "";  
     
   \$db   \= "db\_sipn";  
     
5. **Konfigurasi OAuth (Opsional)**  
     
   Jika Anda ingin mengaktifkan fitur *Login with GitHub*, daftarkan aplikasi Anda pada [GitHub Developer Settings](https://github.com/settings/developers) dan perbarui berkas `SIPN/api/config_github.php`:define('GITHUB\_CLIENT\_ID', 'CLIENT\_ID\_ANDA');  
     
   define('GITHUB\_CLIENT\_SECRET', 'CLIENT\_SECRET\_ANDA');  
     
   define('REDIRECT\_URL', 'http\://localhost/SIPN/api/github-callback.php');  
     
6. **Akses Aplikasi**  
     
   Buka web browser dan navigasi ke alamat berikut:  
     
   * `http://localhost/SIPN/api/login.php` (Halaman Login)  
   * `http://localhost/SIPN/api/index.php` (Landing Page)

# **6\. Lisensi**

Proyek ini didistribusikan dan dilisensikan di bawah ketentuan **Apache License 2.0**. Anda diperbolehkan untuk menggunakan, memodifikasi, dan mendistribusikan ulang kode sumber ini sesuai dengan ketentuan lisensi yang berlaku.