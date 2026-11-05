# SIPN: Sistem Informasi Penilaian Sekolah

Aplikasi web modular untuk mengelola data akademik sekolah: pengguna, siswa, guru, mata pelajaran, dan nilai rapor.

![PHP](https://img.shields.io/badge/PHP-7.4%20%7C%208.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Aiven%20%7C%20Local-4479A1?logo=mysql&logoColor=white)
![OAuth](https://img.shields.io/badge/OAuth-GitHub%20%7C%20Google-black)
![License](https://img.shields.io/badge/License-Apache%202.0-blue)

## Fitur

- **Autentikasi:** login lokal, GitHub OAuth, dan Google OAuth 2.0. User OAuth baru melengkapi data siswa lewat `complete-github-reg.php` / `complete-google-reg.php`.
- **Role:** `admin`, `guru`, `siswa`.
- **Admin:** CRUD user, siswa, guru, dan mata pelajaran.
- **Guru/Admin:** input nilai per mapel, kelas, dan semester (`daftar_nilai.php`). Guru hanya bisa mengisi mapel yang diajarnya.
- **Siswa:** melihat rapor (`lihat_nilai.php`).
- **Profil:** ubah username, password, foto, dan tautkan/lepas akun GitHub (`profile.php`).
- **UI:** dashboard Bento Grid (`dashboard-bento.css`), halaman auth (`auth-style3.css`), dan loader (`loader.php`, `loader_nilai.php`).

### Rumus nilai

- Nilai akhir = (nilai pengetahuan + nilai keterampilan) / 2
- Predikat: A (>= 90), B (>= 80), C (>= 70), D (di bawah 70)
- Tuntas jika nilai akhir >= KKM (default 75, bisa diubah guru per baris)

## Teknologi

| Komponen | Teknologi |
| :-- | :-- |
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP 7.4 / 8.x (mysqli, cURL) |
| Database | MySQL / MariaDB (Aiven untuk cloud) |
| Server | Apache (XAMPP/LAMPP) lokal, Vercel (`vercel-php`) produksi |

## Instalasi lokal

1. Taruh proyek di web root, misalnya `C:/xampp/htdocs/School_Project/assignment/SIPN` atau `/opt/lampp/htdocs/...`.
2. Jalankan Apache dan MySQL.
3. Salin `.env.example` menjadi `.env`, lalu isi:

   ```env
   DB_HOST=
   DB_USER=
   DB_PASS=
   DB_NAME=
   DB_PORT=3306
   GITHUB_CLIENT_ID=
   GITHUB_CLIENT_SECRET=
   GOOGLE_CLIENT_ID=
   GOOGLE_CLIENT_SECRET=
   LOCAL_BASE_PATH=/School_Project/assignment/SIPN
   SESSION_DRIVER=file
   ```

   `LOCAL_BASE_PATH` adalah path proyek di localhost tanpa `/api`. **Jangan pernah commit `.env`.**
4. Buka `http://localhost/School_Project/assignment/SIPN/api/login.php`.

### Daftar OAuth

- **GitHub:** Settings, Developer settings, OAuth Apps. Callback: `http://localhost/<LOCAL_BASE_PATH>/api/github-callback.php` (lokal) dan `https://domain-kamu/api/github-callback.php` (produksi). Pakai dua app terpisah.
- **Google:** Cloud Console, APIs & Services, Credentials. Authorized redirect URI: `.../api/google-callback.php`.

## Deploy ke Vercel

1. Isi **Settings, Environment Variables** dengan nama yang sama seperti `.env` (pakai secret app *production*).
2. Tambahkan `SESSION_DRIVER=db` supaya session disimpan di tabel MySQL (tabel `sessions` dibuat otomatis).
3. Redeploy setelah mengubah variabel.

## Keamanan

- Semua secret dibaca dari `.env` / Environment Variables, tidak ada yang hardcode.
- Setiap halaman terproteksi memanggil `auth.php` (cek login, cek role, token CSRF).
- Session ID diganti saat login, cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` di HTTPS).
- `display_errors` mati; error dicatat di log server.
- File pendukung (`koneksi`, `auth`, `env`, dst.) tidak bisa dibuka lewat router `index.php`.
- Backup database rutin minimal seminggu sekali.

## Struktur singkat

| Path | Fungsi |
| :-- | :-- |
| `api/index.php` | Router tunggal (Vercel) |
| `api/env.php`, `api/koneksi.php` | Loader `.env`, koneksi DB, session |
| `api/auth.php` | Cek login/role, CSRF, `login_user()` |
| `api/session_handler.php` | Session di database (`SESSION_DRIVER=db`) |
| `api/login.php`, `register.php`, `logout.php` | Autentikasi lokal |
| `api/github-*.php`, `google-*.php`, `complete-*-reg.php` | Alur OAuth |
| `api/daftar_user/siswa/guru/mapel/nilai.php` | Halaman admin dan guru |
| `api/lihat_nilai.php`, `profile.php`, `dashboard.php` | Halaman umum |

## Lisensi

Apache License 2.0.
