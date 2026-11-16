# SIPN

**Sistem Informasi Penilaian & Nilai Siswa**

Aplikasi web sekolah untuk mengelola data siswa, guru, mata pelajaran, kelas, jadwal, tugas, sampai nilai rapor, dalam satu tempat. Dibuat dengan PHP native + MySQL, di-deploy ke Vercel dengan database di Aiven.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Aiven-4479A1?logo=mysql&logoColor=white)
![Vercel](https://img.shields.io/badge/Deploy-Vercel-000000?logo=vercel&logoColor=white)
![Status](https://img.shields.io/badge/status-active-success)

---

## Daftar Isi

- [Tentang Proyek](#tentang-proyek)
- [Fitur](#fitur)
- [Role & Hak Akses](#role--hak-akses)
- [Tech Stack](#tech-stack)
- [Struktur Folder](#struktur-folder)
- [Daftar Halaman](#daftar-halaman)
- [Database](#database)
- [Instalasi Lokal](#instalasi-lokal)
- [Konfigurasi `.env`](#konfigurasi-env)
- [Setup OAuth (GitHub & Google)](#setup-oauth-github--google)
- [Setup Database di Aiven](#setup-database-di-aiven)
- [Mengelola Database dengan Beekeeper Studio](#mengelola-database-dengan-beekeeper-studio)
- [Deploy ke Vercel](#deploy-ke-vercel)
- [Keamanan](#keamanan)
- [Upload File](#upload-file)
- [Troubleshooting](#troubleshooting)
- [Catatan & Keterbatasan](#catatan--keterbatasan)
- [Kontribusi](#kontribusi)
- [Lisensi](#lisensi)

---

## Tentang Proyek

SIPN dibuat untuk memudahkan alur akademik di sekolah:

- **Admin** mengelola seluruh data master (user, siswa, guru, mapel, kelas).
- **Guru** membuat tugas, menilai pengumpulan, dan mengisi nilai rapor untuk kelas yang diampu.
- **Siswa** melihat jadwal, mengumpulkan tugas, dan melihat rapor serta nilai tugasnya.

Semua halaman dilayani lewat **satu router** (`api/index.php`) supaya cocok dengan model serverless Vercel yang hanya memakai satu function.

---

## Fitur

### Autentikasi
- Login dengan username & password.
- Login / daftar dengan **Google OAuth** dan **GitHub OAuth**.
- Halaman *Complete Setup* untuk melengkapi data setelah daftar lewat Google/GitHub.
- Hubungkan / lepas akun GitHub dari halaman profil.
- Registrasi siswa mandiri (NIS, nama, kelas, jenis kelamin).
- Logout yang membersihkan session dan cookie.

### Dashboard
- Tampilan dashboard menyesuaikan role (admin, guru, siswa).
- Ringkasan data dan akses cepat ke menu yang relevan.
- Loader / skeleton screen supaya perpindahan halaman terasa halus.
- Smooth scroll memakai Lenis.

### Manajemen Data (Admin)
- **Kelola User**: tambah, ubah, hapus akun dan role.
- **Kelola Siswa**: data NIS, nama, kelas, jenis kelamin.
- **Kelola Guru**: data NIP, nama, jenis kelamin.
- **Mata Pelajaran**: kode mapel, nama mapel, guru pengampu.
- **Manajemen Kelas & Plotting Guru**: menentukan guru mana mengajar mapel apa di kelas mana, plus jadwal pelajaran (hari, jam mulai, jam selesai).

### Tugas & Nilai
- Guru membuat tugas per kelas, mapel, dan semester, lengkap dengan deskripsi, kategori, deadline, status, dan lampiran soal.
- Tipe pengumpulan tugas fleksibel: **file**, **link**, atau **jawaban teks**.
- Siswa mengumpulkan tugas dan bisa menambahkan catatan.
- Guru memberi nilai dan catatan, baik satuan maupun massal.
- **Nilai rapor** per semester: KKM, nilai pengetahuan, nilai keterampilan, dan keterangan.
- Siswa melihat rapor dan nilai tugasnya sendiri di halaman *View Report Card & Tugas*.

### Profil
- Ubah username, password, dan foto profil.
- Status koneksi akun GitHub / Google.

---

## Role & Hak Akses

| Role | Akses utama |
| --- | --- |
| `admin` | Semua menu manajemen: user, siswa, guru, mapel, kelas, jadwal, nilai |
| `guru` | Tugas, penilaian, dan nilai rapor untuk kelas & mapel yang diampu, plus profil |
| `siswa` | Dashboard, jadwal, pengumpulan tugas, lihat rapor & nilai, plus profil |

Pengecekan role dilakukan di sisi server lewat helper `require_login()` dan `require_role()` di `api/auth.php`.

---

## Tech Stack

| Bagian | Teknologi |
| --- | --- |
| Backend | PHP (native, tanpa framework & tanpa Composer) |
| Database | MySQL lewat `mysqli` (di production memakai **Aiven for MySQL**) |
| Hosting | **Vercel** (serverless) |
| DB Client | **Beekeeper Studio** |
| Frontend | HTML, CSS, JavaScript |
| UI | Font Awesome, font Plus Jakarta Sans, Lenis (smooth scroll) |
| Auth | Session PHP, Google OAuth 2.0, GitHub OAuth |

---

## Struktur Folder

```
SIPN/
├── .env                      # Konfigurasi lokal (JANGAN di-push ke GitHub)
├── .env.example              # Template konfigurasi
├── .gitignore
├── README.md
├── vercel.json               # Konfigurasi deploy Vercel
└── api/
    ├── index.php             # Router tunggal (entry point)
    ├── env.php               # Loader .env + helper env()
    ├── koneksi.php           # Koneksi DB + inisialisasi session
    ├── auth.php              # Helper login, role, CSRF
    ├── session_handler.php   # Session di tabel MySQL (SESSION_DRIVER=db)
    ├── config_github.php     # Konfigurasi GitHub OAuth
    ├── config_google.php     # Konfigurasi Google OAuth
    │
    ├── login.php             # Halaman login
    ├── register.php          # Registrasi siswa
    ├── logout.php            # Logout
    ├── github-login.php      # Mulai alur OAuth GitHub
    ├── github-callback.php   # Callback OAuth GitHub
    ├── github-disconnect.php # Lepas koneksi GitHub
    ├── google-login.php      # Mulai alur OAuth Google
    ├── google-callback.php   # Callback OAuth Google
    ├── complete-github-reg.php
    ├── complete-google-reg.php
    │
    ├── dashboard.php         # Dashboard sesuai role
    ├── profile.php           # Profil & pengaturan akun
    ├── daftar_user.php       # Kelola user
    ├── daftar_siswa.php      # Kelola siswa
    ├── daftar_guru.php       # Kelola guru
    ├── daftar_mapel.php      # Kelola mata pelajaran
    ├── manajemen_kelas.php   # Kelas, plotting guru, jadwal
    ├── daftar_nilai.php      # Tugas & nilai (sisi guru/admin)
    ├── lihat_nilai.php       # Rapor & tugas (sisi siswa)
    │
    ├── loader.php            # Komponen loader / skeleton
    ├── loader_nilai.php      # Loader khusus halaman nilai
    ├── lenis_scroll.php      # Smooth scroll
    ├── ca.pem                # (Opsional) sertifikat CA Aiven
    └── uploads/
        ├── pengumpulan_tugas/  # File tugas dari siswa
        └── tugas_soal/         # Lampiran soal dari guru
```

---

## Daftar Halaman

URL bisa diakses dengan atau tanpa `.php` (misalnya `/login` atau `/login.php`).

| URL | Fungsi | Akses |
| --- | --- | --- |
| `/` | Dashboard | Login |
| `/login` | Masuk | Publik |
| `/register` | Daftar akun siswa | Publik |
| `/logout` | Keluar | Login |
| `/profile` | Profil & koneksi akun | Login |
| `/daftar_user` | Kelola user | Admin |
| `/daftar_siswa` | Kelola siswa | Admin |
| `/daftar_guru` | Kelola guru | Admin |
| `/daftar_mapel` | Kelola mata pelajaran | Admin |
| `/manajemen_kelas` | Kelas, plotting guru, jadwal | Admin |
| `/daftar_nilai` | Kelola tugas & nilai | Guru / Admin |
| `/lihat_nilai` | Lihat rapor & tugas | Siswa |

File pendukung seperti `env`, `koneksi`, `auth`, `session_handler`, `config_github`, `config_google`, `loader`, dan `loader_nilai` **diblokir** oleh router dan tidak bisa dibuka lewat URL (akan menghasilkan 404).

---

## Database

Aplikasi memakai MySQL (`utf8mb4`). Tabel utama yang dipakai oleh kode:

| Tabel | Isi |
| --- | --- |
| `users` | Akun login: `username`, `password` (hash), `role`, `foto`, `email`, serta kolom koneksi `github_id`, `github_username`, `github_avatar`, `github_connected`, `google_id`, `google_email` |
| `siswa` | `nis`, `nama`, `kelas`, `jenis_kelamin`, `user_id` |
| `guru` | `nip`, `nama`, `jenis_kelamin`, `user_id` |
| `mapel` | `kode_mapel`, `nama_mapel`, `guru_id` |
| `guru_mapel_kelas` | Plotting: `guru_id`, `mapel_id`, `kelas` |
| `jadwal` | `kelas`, `mapel_id`, `guru_id`, `hari`, `jam_mulai`, `jam_selesai` |
| `tugas` | `guru_id`, `mapel_id`, `kelas`, `semester`, `judul`, `deskripsi`, `kategori`, `tipe_pengumpulan`, `file_lampiran`, `deadline`, `status` |
| `pengumpulan_tugas` | `tugas_id`, `siswa_id`, `file_tugas`, `nama_file_asli`, `link_tugas`, `jawaban_teks`, `catatan_siswa`, `nilai`, `catatan_guru`, `submitted_at`, `graded_at` |
| `nilai_rapor` | `siswa_id`, `mapel_id`, `semester`, `nilai_kkm`, `nilai_pengetahuan`, `nilai_keterampilan`, `keterangan` |
| `sessions` | Dibuat **otomatis** saat `SESSION_DRIVER=db` |

> **Catatan:** Skema tabel di atas disusun dari query yang ada di kode. Kalau kamu punya file `.sql` hasil export dari database, taruh di folder `database/` dan tambahkan link-nya di sini supaya orang lain bisa langsung import.

---

## Instalasi Lokal

### Prasyarat

- PHP 8.x dengan ekstensi `mysqli`, `curl`, dan `openssl`
- XAMPP / Laragon (atau `php -S`) untuk server lokal
- MySQL lokal **atau** akses ke database Aiven
- Git

### Langkah

1. **Clone repository**

   ```bash
   git clone https://github.com/<username>/SIPN.git
   cd SIPN
   ```

   Kalau pakai XAMPP, taruh foldernya di `htdocs`. Contoh path default proyek ini:

   ```
   C:\xampp\htdocs\School_Project\assignment\SIPN
   ```

2. **Salin file environment**

   ```bash
   cp .env.example .env
   ```

   Di Windows (CMD): `copy .env.example .env`

3. **Isi `.env`** (lihat bagian [Konfigurasi `.env`](#konfigurasi-env)).

4. **Siapkan database** (lokal atau Aiven), lalu import skema tabel.

5. **Jalankan aplikasi**

   - **XAMPP:** start Apache + MySQL, lalu buka
     `http://localhost/School_Project/assignment/SIPN/api/`
   - **PHP built-in server** (opsional):

     ```bash
     php -S localhost:8000 -t api
     ```

     Lalu buka `http://localhost:8000`. Kalau memakai cara ini, kosongkan `LOCAL_BASE_PATH` atau sesuaikan.

---

## Konfigurasi `.env`

File `.env` dibaca oleh `api/env.php` dari **root proyek** (satu level di atas folder `api/`). Di Vercel, file ini tidak dipakai, nilainya diambil dari **Environment Variables**.

> **Penting:** File `.env` berisi rahasia. Pastikan `.env` ada di `.gitignore` dan **tidak pernah** di-push ke GitHub. Yang di-push hanya `.env.example`.

```env
# Database (Aiven atau MySQL lokal)
DB_HOST=
DB_USER=
DB_PASS=
DB_NAME=
DB_PORT=3306

# GitHub OAuth (app LOCAL untuk .env lokal, app PRODUCTION untuk Vercel)
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

# Path proyek di localhost (tanpa /api di belakang)
LOCAL_BASE_PATH=/School_Project/assignment/SIPN

# file = session di disk (lokal), db = session di tabel MySQL (isi db di Vercel)
SESSION_DRIVER=file
```

### Penjelasan variabel

| Variabel | Wajib | Keterangan |
| --- | :---: | --- |
| `DB_HOST` | Ya | Host database (contoh: host dari Aiven, atau `localhost`) |
| `DB_USER` | Ya | Username database |
| `DB_PASS` | Ya | Password database |
| `DB_NAME` | Ya | Nama database |
| `DB_PORT` | Tidak | Port database, default `3306` (Aiven biasanya memakai port custom, cek di dashboard) |
| `DB_SSL` | Tidak | `true` / `false`. Default: `false` untuk DB lokal, `true` untuk DB remote (Aiven wajib SSL) |
| `GITHUB_CLIENT_ID` | Untuk login GitHub | Client ID dari GitHub OAuth App |
| `GITHUB_CLIENT_SECRET` | Untuk login GitHub | Client secret dari GitHub OAuth App |
| `GOOGLE_CLIENT_ID` | Untuk login Google | Client ID dari Google Cloud Console |
| `GOOGLE_CLIENT_SECRET` | Untuk login Google | Client secret dari Google Cloud Console |
| `LOCAL_BASE_PATH` | Lokal | Path folder proyek di localhost, **tanpa** `/api` |
| `SESSION_DRIVER` | Tidak | `file` (lokal) atau `db` (Vercel). Default `file` |

Kalau `DB_HOST`, `DB_USER`, atau `DB_NAME` kosong, aplikasi akan berhenti dengan pesan *"Konfigurasi server belum lengkap."*

---

## Setup OAuth (GitHub & Google)

Login sosial hanya berfungsi kalau Client ID dan Secret sudah diisi, dan **Redirect URI** di dashboard provider sama persis dengan yang dipakai aplikasi.

### GitHub

1. Buka **GitHub → Settings → Developer settings → OAuth Apps → New OAuth App**.
2. Buat **dua app terpisah**: satu untuk lokal, satu untuk production.
3. Isi *Authorization callback URL*:

   | Lingkungan | Callback URL |
   | --- | --- |
   | Lokal | `http://localhost<LOCAL_BASE_PATH>/api/github-callback.php` |
   | Production | `https://<domain-kamu>/api/github-callback.php` |

4. Salin Client ID & Client Secret:
   - app **lokal** → ke file `.env`
   - app **production** → ke Environment Variables Vercel

Scope yang diminta: `read:user user:email`.

### Google

1. Buka **Google Cloud Console → APIs & Services → Credentials → Create Credentials → OAuth client ID** (tipe *Web application*).
2. Tambahkan *Authorized redirect URIs*:

   | Lingkungan | Redirect URI |
   | --- | --- |
   | Lokal | `http://localhost<LOCAL_BASE_PATH>/api/google-callback.php` |
   | Production | `https://<domain-kamu>/api/google-callback.php` |

3. Salin Client ID & Client Secret ke `.env` / Vercel.

Scope yang diminta: `openid email profile`.

> **Catatan:** Redirect URI production untuk GitHub saat ini ditulis langsung (hardcode) di `api/config_github.php`. Kalau domain berubah, edit file tersebut atau pindahkan nilainya ke variabel environment.

---

## Setup Database di Aiven

1. Buat akun di [Aiven](https://aiven.io) lalu buat service **MySQL**.
2. Dari halaman *Overview* service, ambil **Host**, **Port**, **User**, **Password**, dan nama database (default `defaultdb`, atau buat database baru).
3. Isi nilai tersebut ke `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`.
4. Aiven mewajibkan koneksi **SSL** (`ssl-mode=REQUIRED`). Aplikasi sudah menanganinya otomatis untuk host non-lokal.
5. *(Opsional, disarankan)* Unduh **CA certificate** dari halaman service Aiven, simpan sebagai `api/ca.pem`. Kalau file ini ada, koneksi memverifikasi sertifikat server. Kalau tidak ada, koneksi tetap memakai SSL tetapi **tanpa verifikasi sertifikat**.
6. Pastikan IP yang dipakai boleh mengakses service (cek pengaturan *Allowed IP addresses* di Aiven).

> Jangan commit `ca.pem` kalau repository kamu publik dan kamu tidak ingin membagikannya. Sertifikat CA bukan rahasia, tapi tetap rapikan sesuai kebijakan proyek.

---

## Mengelola Database dengan Beekeeper Studio

[Beekeeper Studio](https://www.beekeeperstudio.io) dipakai untuk melihat dan mengelola database (import skema, cek data, jalanin query) tanpa lewat phpMyAdmin.

### Koneksi ke Aiven

1. Buka Beekeeper Studio → **New Connection** → pilih **MySQL**.
2. Isi:

   | Field | Nilai |
   | --- | --- |
   | Host | `DB_HOST` dari Aiven |
   | Port | `DB_PORT` dari Aiven |
   | User | `DB_USER` |
   | Password | `DB_PASS` |
   | Default Database | `DB_NAME` |

3. Aktifkan **SSL**. Kalau kamu sudah download CA certificate dari Aiven, pilih file tersebut sebagai *CA Cert*.
4. Klik **Test**, kalau berhasil klik **Connect**.

### Kegunaan

- Import file `.sql` untuk membuat tabel.
- Cek isi tabel `users`, `siswa`, `guru`, dst.
- Membuat akun admin pertama secara manual (lihat di bawah).
- Membersihkan session lama di tabel `sessions`.

### Membuat akun admin pertama

Registrasi lewat web hanya membuat akun dengan role `siswa`. Untuk admin pertama, buat lewat Beekeeper Studio:

1. Buat hash password lewat PHP:

   ```bash
   php -r "echo password_hash('password_kamu', PASSWORD_DEFAULT);"
   ```

2. Insert ke tabel `users`:

   ```sql
   INSERT INTO users (username, password, role)
   VALUES ('admin', '<hasil_hash_di_atas>', 'admin');
   ```

3. Login dengan username `admin` dan password yang tadi kamu pakai.

Ganti password default segera setelah login pertama.

---

## Deploy ke Vercel

SIPN memakai **satu entry point** (`api/index.php`), sehingga semua URL dialihkan ke router tersebut.

### Langkah

1. **Push** kode ke GitHub (pastikan `.env` tidak ikut).
2. Buka [Vercel](https://vercel.com) → **Add New → Project** → import repository SIPN.
3. Pastikan konfigurasi `vercel.json` di root repo mengarahkan semua request ke `api/index.php` dan memakai runtime PHP untuk Vercel.
4. Buka **Settings → Environment Variables**, lalu isi semua variabel berikut:

   | Variabel | Nilai di Vercel |
   | --- | --- |
   | `DB_HOST` | Host Aiven |
   | `DB_PORT` | Port Aiven |
   | `DB_USER` | User Aiven |
   | `DB_PASS` | Password Aiven |
   | `DB_NAME` | Nama database |
   | `GITHUB_CLIENT_ID` | Client ID app GitHub **production** |
   | `GITHUB_CLIENT_SECRET` | Client secret app GitHub **production** |
   | `GOOGLE_CLIENT_ID` | Client ID Google |
   | `GOOGLE_CLIENT_SECRET` | Client secret Google |
   | `SESSION_DRIVER` | **`db`** |

   `LOCAL_BASE_PATH` tidak perlu diisi di Vercel.

5. Klik **Deploy**.
6. Setelah dapat domain, daftarkan callback URL production di GitHub dan Google (lihat [Setup OAuth](#setup-oauth-github--google)).

### Kenapa `SESSION_DRIVER=db` di Vercel?

Vercel berjalan serverless, tiap request bisa ditangani instance berbeda dan filesystem-nya tidak persisten. Session berbasis file akan hilang antar request, sehingga user "ter-logout sendiri" dan alur OAuth gagal. Dengan `SESSION_DRIVER=db`, session disimpan di tabel `sessions` pada MySQL (dibuat otomatis) dan konsisten di semua instance.

---

## Keamanan

Beberapa praktik yang sudah diterapkan di kode:

- **Password di-hash** dengan `password_hash()` dan diverifikasi dengan `password_verify()`.
- **Prepared statements** (`mysqli`) untuk query yang membawa input user, mencegah SQL injection.
- **Proteksi CSRF**: token per session, divalidasi dengan `hash_equals()`.
- **Session fixation**: `session_regenerate_id(true)` dipanggil saat login.
- **OAuth `state`** acak untuk mencegah CSRF di alur login Google/GitHub.
- **Cookie session** dengan flag `HttpOnly`, `SameSite=Lax`, dan `Secure` saat HTTPS.
- **Escape output** dengan helper `e()` (`htmlspecialchars`) untuk mencegah XSS.
- **Router whitelist**: hanya nama halaman `[A-Za-z0-9_-]` yang valid, file pendukung diblokir.
- **Pesan error DB** hanya detail di localhost; di production hanya menampilkan kode umum.
- **Kredensial** dibaca dari environment, tidak ditulis di kode.

### Checklist sebelum go-live

- [ ] `.env` masuk `.gitignore`
- [ ] Semua secret (DB, OAuth) hanya ada di Environment Variables Vercel
- [ ] Password admin default sudah diganti
- [ ] `api/ca.pem` terpasang agar verifikasi sertifikat DB aktif
- [ ] Callback URL OAuth production sudah benar
- [ ] Allowed IP di Aiven sudah disesuaikan

> Kalau pernah tidak sengaja meng-commit secret ke GitHub, anggap secret itu bocor: **ganti (rotate)** password DB dan Client Secret OAuth-nya, jangan cuma menghapus file dari commit terbaru.

---

## Upload File

| Jenis | Folder | Batas ukuran |
| --- | --- | --- |
| Lampiran soal dari guru | `api/uploads/tugas_soal/` | 15 MB |
| File tugas dari siswa | `api/uploads/pengumpulan_tugas/` | 20 MB |

Format file yang diizinkan:

`pdf`, `doc`, `docx`, `ppt`, `pptx`, `xls`, `xlsx`, `zip`, `rar`, `txt`, `jpg`, `jpeg`, `png`, `webp`

Nama file disimpan dengan pola unik (contoh: `tugas_1_siswa_14_20261116_143039_535416.docx`) dan nama aslinya dicatat di database.

---

## Troubleshooting

**`Konfigurasi server belum lengkap.`**
`DB_HOST`, `DB_USER`, atau `DB_NAME` belum terisi. Cek `.env` (lokal) atau Environment Variables (Vercel).

**`Layanan sedang bermasalah (kode: ...)`**
Koneksi database gagal. Cek host, port, user, password, dan pastikan IP kamu diizinkan di Aiven. Di localhost, pesan error detail akan ditampilkan.

**Koneksi SSL ke Aiven gagal**
Pastikan port dan host benar. Coba taruh `ca.pem` di folder `api/`. Untuk DB lokal tanpa SSL, set `DB_SSL=false`.

**Login Google/GitHub error `redirect_uri_mismatch`**
Redirect URI di dashboard provider tidak sama persis dengan yang dipakai aplikasi. Cek huruf besar-kecil, `http` vs `https`, dan path-nya.

**Di Vercel selalu ter-logout / OAuth gagal**
`SESSION_DRIVER` belum diset `db`.

**Halaman lokal 404 atau path salah**
Sesuaikan `LOCAL_BASE_PATH` dengan lokasi folder proyek di `htdocs` (tanpa `/api`).

**`Sesi form kedaluwarsa` (HTTP 419)**
Token CSRF tidak cocok. Muat ulang halaman form lalu kirim ulang.

**Upload gagal**
Cek ukuran file (maks 15/20 MB), format file, dan batas `upload_max_filesize` serta `post_max_size` di `php.ini`.

---

## Catatan & Keterbatasan

- **Upload di Vercel tidak persisten.** Filesystem serverless bersifat sementara, jadi file di `api/uploads/` bisa hilang. Untuk penggunaan serius, pindahkan penyimpanan file ke layanan object storage (misalnya Vercel Blob, Cloudflare R2, atau S3).
- Skema database belum disertakan sebagai file `.sql` di repository.
- Domain production untuk callback GitHub masih ditulis langsung di `config_github.php`.

### Rencana pengembangan (ide)

- [ ] Export rapor ke PDF
- [ ] Absensi siswa
- [ ] Pengumuman sekolah
- [ ] Notifikasi deadline tugas
- [ ] Pindah storage upload ke cloud
- [ ] Skema database + seeder di folder `database/`

---

## Kontribusi

Kontribusi sangat terbuka.

1. Fork repository ini
2. Buat branch fitur: `git checkout -b fitur/nama-fitur`
3. Commit perubahan: `git commit -m "feat: tambah fitur X"`
4. Push ke branch: `git push origin fitur/nama-fitur`
5. Buka Pull Request

Pastikan tidak ada secret (password, token, client secret) yang ikut ter-commit.

---

## Lisensi

Proyek ini dilisensikan di bawah skema multi-lisensi (*multi-licensed*). Anda bebas memilih salah satu dari lisensi berikut sesuai kebutuhan Anda:

* **MIT License** — lihat file [LICENSE-MIT](LICENSE-MIT)
* **Apache License 2.0** — lihat file [LICENSE-APACHE](LICENSE-APACHE)
* **GNU General Public License v3.0 (GPL-3.0)** — lihat file [LICENSE-GPL](LICENSE-GPL)
