<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/auth.php';

// 1. PROTEKSI HAK AKSES KHUSUS ADMIN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
$role     = strtolower($_SESSION['role'] ?? '');
$username = $_SESSION['username'] ?? 'Admin';

if ($role !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// 2. AUTO-SETUP TABEL PIVOT GURU_MAPEL_KELAS & JADWAL (JIKA BELUM ADA)
mysqli_query($koneksi, "
    CREATE TABLE IF NOT EXISTS guru_mapel_kelas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guru_id INT NOT NULL,
        mapel_id INT NOT NULL,
        kelas VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_gmk (guru_id, mapel_id, kelas),
        INDEX idx_gmk_kelas (kelas),
        INDEX idx_gmk_guru (guru_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

mysqli_query($koneksi, "
    CREATE TABLE IF NOT EXISTS jadwal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kelas VARCHAR(50) NOT NULL,
        mapel_id INT NOT NULL,
        guru_id INT NOT NULL DEFAULT 0,
        hari VARCHAR(20) NOT NULL,
        jam_mulai TIME NOT NULL,
        jam_selesai TIME NOT NULL,
        INDEX idx_jadwal_kelas (kelas),
        INDEX idx_jadwal_guru (guru_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Cek kolom tabel jadwal secara dinamis agar kompatibel dengan struktur lama maupun baru
$jadwal_cols = [];
$q_jcols = mysqli_query($koneksi, "SHOW COLUMNS FROM jadwal");
if ($q_jcols) {
    while ($jc = mysqli_fetch_assoc($q_jcols)) {
        $jadwal_cols[$jc['Field']] = true;
    }
}
$jadwal_has_guru_id = isset($jadwal_cols['guru_id']);

// 3. DATA USER ADMIN UNTUK SIDEBAR
$q_user    = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id' LIMIT 1");
$user_data = ($q_user && mysqli_num_rows($q_user) > 0) ? mysqli_fetch_assoc($q_user) : [];
$foto_db   = $user_data['foto'] ?? '';
$gh_conn   = !empty($user_data['github_connected']) && $user_data['github_connected'] == 1;
$gh_avatar = $user_data['github_avatar'] ?? '';

$avatar_src = '';
if (!empty($foto_db)) {
    if (strpos($foto_db, 'data:image') === 0 || filter_var($foto_db, FILTER_VALIDATE_URL)) {
        $avatar_src = $foto_db;
    } elseif (file_exists(__DIR__ . '/uploads/avatars/' . $foto_db)) {
        $avatar_src = 'uploads/avatars/' . $foto_db;
    }
}
if (empty($avatar_src) && $gh_conn && !empty($gh_avatar)) {
    $avatar_src = $gh_avatar;
}

$msg_type     = '';
$msg_text     = '';
$active_kelas = trim((string) ($_GET['kelas'] ?? ''));

// 4. HANDLE SEMUA AKSI POST ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // A. BUAT KELAS BARU
    if (isset($_POST['buat_kelas_baru'])) {
        $nama_kelas_baru = trim((string) ($_POST['nama_kelas_baru'] ?? ''));
        $nama_kelas_baru = preg_replace('/\s+/', ' ', $nama_kelas_baru);

        if ($nama_kelas_baru === '') {
            $msg_type = 'error';
            $msg_text = 'Nama kelas baru tidak boleh kosong.';
        } else {
            $active_kelas = $nama_kelas_baru;
            $msg_type     = 'success';
            $msg_text     = "Kelas \"$nama_kelas_baru\" siap diatur. Silakan masukkan siswa dan guru pengajar ke kelas ini.";
        }
    }

    // B. UBAH NAMA KELAS (RENAME ROMBEL DI SEMUA TABEL TERKAIT)
    if (isset($_POST['rename_kelas'])) {
        $kelas_lama = trim((string) ($_POST['kelas_lama'] ?? ''));
        $kelas_baru = trim((string) ($_POST['kelas_baru'] ?? ''));
        $kelas_baru = preg_replace('/\s+/', ' ', $kelas_baru);

        if ($kelas_lama === '' || $kelas_baru === '') {
            $msg_type = 'error';
            $msg_text = 'Nama kelas lama dan nama kelas baru wajib diisi.';
        } else {
            $lama_esc = mysqli_real_escape_string($koneksi, $kelas_lama);
            $baru_esc = mysqli_real_escape_string($koneksi, $kelas_baru);

            mysqli_query($koneksi, "UPDATE siswa SET kelas = '$baru_esc' WHERE TRIM(kelas) = '$lama_esc'");
            mysqli_query($koneksi, "UPDATE IGNORE guru_mapel_kelas SET kelas = '$baru_esc' WHERE TRIM(kelas) = '$lama_esc'");
            mysqli_query($koneksi, "DELETE FROM guru_mapel_kelas WHERE TRIM(kelas) = '$lama_esc'");
            mysqli_query($koneksi, "UPDATE jadwal SET kelas = '$baru_esc' WHERE TRIM(kelas) = '$lama_esc'");

            $chk_tugas = mysqli_query($koneksi, "SHOW TABLES LIKE 'tugas'");
            if ($chk_tugas && mysqli_num_rows($chk_tugas) > 0) {
                mysqli_query($koneksi, "UPDATE tugas SET kelas = '$baru_esc' WHERE TRIM(kelas) = '$lama_esc'");
            }

            $active_kelas = $kelas_baru;
            $msg_type     = 'success';
            $msg_text     = "Nama kelas berhasil diubah dari \"$kelas_lama\" menjadi \"$kelas_baru\".";
        }
    }

    // C. MASUKKAN SISWA KE KELAS (MASSAL / CHECKLIST)
    if (isset($_POST['tambah_siswa_kelas'])) {
        $target_kelas = trim((string) ($_POST['target_kelas'] ?? ''));
        $siswa_ids    = $_POST['siswa_ids'] ?? [];

        if ($target_kelas === '') {
            $msg_type = 'error';
            $msg_text = 'Pilih atau buat kelas terlebih dahulu.';
        } elseif (!is_array($siswa_ids) || count($siswa_ids) === 0) {
            $active_kelas = $target_kelas;
            $msg_type     = 'error';
            $msg_text     = 'Pilih minimal satu siswa untuk dimasukkan ke kelas.';
        } else {
            $clean_ids = [];
            foreach ($siswa_ids as $sid) {
                $id_int = (int) $sid;
                if ($id_int > 0) {
                    $clean_ids[] = $id_int;
                }
            }

            if (!empty($clean_ids)) {
                $ids_in    = implode(',', $clean_ids);
                $kelas_esc = mysqli_real_escape_string($koneksi, $target_kelas);
                mysqli_query($koneksi, "UPDATE siswa SET kelas = '$kelas_esc' WHERE id IN ($ids_in)");

                $active_kelas = $target_kelas;
                $msg_type     = 'success';
                $msg_text     = count($clean_ids) . " siswa berhasil dimasukkan ke kelas $target_kelas.";
            }
        }
    }

    // D. KELUARKAN SATU SISWA DARI KELAS (SET KELAS JADI KOSONG)
    if (isset($_POST['keluarkan_siswa'])) {
        $sid          = (int) ($_POST['siswa_id'] ?? 0);
        $target_kelas = trim((string) ($_POST['target_kelas'] ?? ''));

        if ($sid > 0) {
            mysqli_query($koneksi, "UPDATE siswa SET kelas = '' WHERE id = '$sid'");
            $active_kelas = $target_kelas;
            $msg_type     = 'success';
            $msg_text     = 'Siswa berhasil dikeluarkan dari kelas (status: Belum Punya Kelas).';
        }
    }

    // E. PINDAHKAN SATU SISWA KE KELAS LAIN
    if (isset($_POST['pindah_siswa'])) {
        $sid          = (int) ($_POST['siswa_id'] ?? 0);
        $kelas_asal   = trim((string) ($_POST['kelas_asal'] ?? ''));
        $kelas_tujuan = trim((string) ($_POST['kelas_tujuan'] ?? ''));

        if ($sid > 0 && $kelas_tujuan !== '') {
            $tujuan_esc = mysqli_real_escape_string($koneksi, $kelas_tujuan);
            mysqli_query($koneksi, "UPDATE siswa SET kelas = '$tujuan_esc' WHERE id = '$sid'");
            $active_kelas = $kelas_asal !== '' ? $kelas_asal : $kelas_tujuan;
            $msg_type     = 'success';
            $msg_text     = "Siswa berhasil dipindahkan ke kelas $kelas_tujuan.";
        }
    }

    // F. PLOT GURU & MATA PELAJARAN (+ OPSIONAL JADWAL) KE KELAS
    if (isset($_POST['plot_guru_kelas'])) {
        $target_kelas = trim((string) ($_POST['target_kelas'] ?? ''));
        $mapel_id     = (int) ($_POST['mapel_id'] ?? 0);
        $guru_id      = (int) ($_POST['guru_id'] ?? 0);
        $tambah_jad   = isset($_POST['sekalian_jadwal']) && $_POST['sekalian_jadwal'] === '1';
        $hari         = trim((string) ($_POST['hari'] ?? ''));
        $jam_mulai    = trim((string) ($_POST['jam_mulai'] ?? ''));
        $jam_selesai  = trim((string) ($_POST['jam_selesai'] ?? ''));

        $active_kelas = $target_kelas;

        if ($target_kelas === '' || $mapel_id <= 0 || $guru_id <= 0) {
            $msg_type = 'error';
            $msg_text = 'Kelas, Mata Pelajaran, dan Guru Pengampu wajib dipilih.';
        } else {
            $kelas_esc = mysqli_real_escape_string($koneksi, $target_kelas);

            // Simpan relasi guru - mapel - kelas
            $ins_gmk = mysqli_query($koneksi, "
                INSERT INTO guru_mapel_kelas (guru_id, mapel_id, kelas)
                VALUES ('$guru_id', '$mapel_id', '$kelas_esc')
                ON DUPLICATE KEY UPDATE guru_id = VALUES(guru_id)
            ");

            // Jika mapel belum punya guru default, isi otomatis agar rapi
            mysqli_query($koneksi, "UPDATE mapel SET guru_id = '$guru_id' WHERE id = '$mapel_id' AND (guru_id IS NULL OR guru_id = 0)");

            $jadwal_info = '';
            if ($tambah_jad && $hari !== '' && $jam_mulai !== '' && $jam_selesai !== '') {
                $hari_esc = mysqli_real_escape_string($koneksi, $hari);
                $jm_esc   = mysqli_real_escape_string($koneksi, $jam_mulai);
                $js_esc   = mysqli_real_escape_string($koneksi, $jam_selesai);

                if ($jadwal_has_guru_id) {
                    mysqli_query($koneksi, "
                        INSERT INTO jadwal (kelas, mapel_id, guru_id, hari, jam_mulai, jam_selesai)
                        VALUES ('$kelas_esc', '$mapel_id', '$guru_id', '$hari_esc', '$jm_esc', '$js_esc')
                    ");
                } else {
                    mysqli_query($koneksi, "
                        INSERT INTO jadwal (kelas, mapel_id, hari, jam_mulai, jam_selesai)
                        VALUES ('$kelas_esc', '$mapel_id', '$hari_esc', '$jm_esc', '$js_esc')
                    ");
                }
                $jadwal_info = " beserta jadwal hari $hari ($jam_mulai - $jam_selesai)";
            }

            if ($ins_gmk) {
                $msg_type = 'success';
                $msg_text = "Guru pengampu & mata pelajaran berhasil di-plotting ke kelas $target_kelas" . $jadwal_info . "!";
            } else {
                $msg_type = 'error';
                $msg_text = 'Gagal menyimpan plotting guru: ' . mysqli_error($koneksi);
            }
        }
    }

    // G. HAPUS PLOTTING GURU & MAPEL DARI KELAS
    if (isset($_POST['hapus_plotting'])) {
        $gmk_id       = (int) ($_POST['gmk_id'] ?? 0);
        $target_kelas = trim((string) ($_POST['target_kelas'] ?? ''));
        $hapus_jad    = isset($_POST['hapus_jadwal_terkait']) && $_POST['hapus_jadwal_terkait'] === '1';

        $active_kelas = $target_kelas;

        if ($gmk_id > 0) {
            $q_get = mysqli_query($koneksi, "SELECT * FROM guru_mapel_kelas WHERE id = '$gmk_id' LIMIT 1");
            if ($q_get && mysqli_num_rows($q_get) > 0) {
                $row_gmk = mysqli_fetch_assoc($q_get);
                mysqli_query($koneksi, "DELETE FROM guru_mapel_kelas WHERE id = '$gmk_id'");

                if ($hapus_jad) {
                    $k_esc = mysqli_real_escape_string($koneksi, $row_gmk['kelas']);
                    $m_id  = (int) $row_gmk['mapel_id'];
                    $g_id  = (int) $row_gmk['guru_id'];
                    if ($jadwal_has_guru_id) {
                        mysqli_query($koneksi, "DELETE FROM jadwal WHERE TRIM(kelas) = '$k_esc' AND mapel_id = '$m_id' AND guru_id = '$g_id'");
                    } else {
                        mysqli_query($koneksi, "DELETE FROM jadwal WHERE TRIM(kelas) = '$k_esc' AND mapel_id = '$m_id'");
                    }
                }

                $msg_type = 'success';
                $msg_text = 'Plotting guru & mata pelajaran berhasil dihapus dari kelas.';
            }
        }
    }

    // H. HAPUS SATU BARIS JADWAL PELAJARAN
    if (isset($_POST['hapus_jadwal'])) {
        $jadwal_id    = (int) ($_POST['jadwal_id'] ?? 0);
        $target_kelas = trim((string) ($_POST['target_kelas'] ?? ''));
        $active_kelas = $target_kelas;

        if ($jadwal_id > 0) {
            mysqli_query($koneksi, "DELETE FROM jadwal WHERE id = '$jadwal_id'");
            $msg_type = 'success';
            $msg_text = 'Sesi jadwal pelajaran berhasil dihapus.';
        }
    }
}

// 5. KUMPULKAN SEMUA DAFTAR KELAS UNIK & STATISTIK PER KELAS
$kelas_stats = [];

$q_k_siswa = mysqli_query($koneksi, "
    SELECT TRIM(kelas) AS nama_kelas, COUNT(*) AS jml_siswa
    FROM siswa
    WHERE kelas IS NOT NULL AND TRIM(kelas) <> ''
    GROUP BY TRIM(kelas)
");
if ($q_k_siswa) {
    while ($r = mysqli_fetch_assoc($q_k_siswa)) {
        $k = $r['nama_kelas'];
        if (!isset($kelas_stats[$k])) {
            $kelas_stats[$k] = ['siswa' => 0, 'mapel_guru' => 0, 'jadwal' => 0];
        }
        $kelas_stats[$k]['siswa'] = (int) $r['jml_siswa'];
    }
}

$q_k_gmk = mysqli_query($koneksi, "
    SELECT TRIM(kelas) AS nama_kelas, COUNT(*) AS jml_plot
    FROM guru_mapel_kelas
    WHERE kelas IS NOT NULL AND TRIM(kelas) <> ''
    GROUP BY TRIM(kelas)
");
if ($q_k_gmk) {
    while ($r = mysqli_fetch_assoc($q_k_gmk)) {
        $k = $r['nama_kelas'];
        if (!isset($kelas_stats[$k])) {
            $kelas_stats[$k] = ['siswa' => 0, 'mapel_guru' => 0, 'jadwal' => 0];
        }
        $kelas_stats[$k]['mapel_guru'] = (int) $r['jml_plot'];
    }
}

$q_k_jad = mysqli_query($koneksi, "
    SELECT TRIM(kelas) AS nama_kelas, COUNT(*) AS jml_jad
    FROM jadwal
    WHERE kelas IS NOT NULL AND TRIM(kelas) <> ''
    GROUP BY TRIM(kelas)
");
if ($q_k_jad) {
    while ($r = mysqli_fetch_assoc($q_k_jad)) {
        $k = $r['nama_kelas'];
        if (!isset($kelas_stats[$k])) {
            $kelas_stats[$k] = ['siswa' => 0, 'mapel_guru' => 0, 'jadwal' => 0];
        }
        $kelas_stats[$k]['jadwal'] = (int) $r['jml_jad'];
    }
}

if ($active_kelas !== '' && !isset($kelas_stats[$active_kelas])) {
    $kelas_stats[$active_kelas] = ['siswa' => 0, 'mapel_guru' => 0, 'jadwal' => 0];
}

ksort($kelas_stats, SORT_NATURAL | SORT_FLAG_CASE);

if ($active_kelas === '' && !empty($kelas_stats)) {
    $active_kelas = (string) array_key_first($kelas_stats);
}

// Statistik Global
$q_tot_siswa     = mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM siswa");
$tot_siswa_all   = $q_tot_siswa ? (int) mysqli_fetch_assoc($q_tot_siswa)['c'] : 0;

$q_tanpa_kelas   = mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM siswa WHERE kelas IS NULL OR TRIM(kelas) = ''");
$tot_tanpa_kelas = $q_tanpa_kelas ? (int) mysqli_fetch_assoc($q_tanpa_kelas)['c'] : 0;

$q_tot_plot      = mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM guru_mapel_kelas");
$tot_plot_all    = $q_tot_plot ? (int) mysqli_fetch_assoc($q_tot_plot)['c'] : 0;

// 6. MASTER LIST GURU & MAPEL UNTUK FORM PLOTTING
$list_guru_all = [];
$q_guru_all = mysqli_query($koneksi, "SELECT id, nip, nama FROM guru ORDER BY nama ASC");
if ($q_guru_all) {
    while ($g = mysqli_fetch_assoc($q_guru_all)) {
        $list_guru_all[] = $g;
    }
}

$list_mapel_all = [];
$q_mapel_all = mysqli_query($koneksi, "SELECT id, kode_mapel, nama_mapel, guru_id FROM mapel ORDER BY nama_mapel ASC");
if ($q_mapel_all) {
    while ($m = mysqli_fetch_assoc($q_mapel_all)) {
        $list_mapel_all[] = $m;
    }
}

// 7. DATA DETAIL UNTUK KELAS AKTIF
$anggota_siswa    = [];
$kandidat_siswa   = [];
$plotting_kelas   = [];
$jadwal_kelas     = [];

if ($active_kelas !== '') {
    $k_esc = mysqli_real_escape_string($koneksi, $active_kelas);

    // Siswa di dalam kelas aktif
    $q_ang = mysqli_query($koneksi, "
        SELECT id, nis, nama, kelas
        FROM siswa
        WHERE TRIM(kelas) = '$k_esc'
        ORDER BY nama ASC
    ");
    if ($q_ang) {
        while ($s = mysqli_fetch_assoc($q_ang)) {
            $anggota_siswa[] = $s;
        }
    }

    // Siswa yang belum punya kelas + siswa kelas lain
    $q_kand = mysqli_query($koneksi, "
        SELECT id, nis, nama, TRIM(IFNULL(kelas, '')) AS kelas_sekarang
        FROM siswa
        WHERE TRIM(IFNULL(kelas, '')) <> '$k_esc'
        ORDER BY (TRIM(IFNULL(kelas, '')) = '') DESC, kelas ASC, nama ASC
    ");
    if ($q_kand) {
        while ($ks = mysqli_fetch_assoc($q_kand)) {
            $kandidat_siswa[] = $ks;
        }
    }

    // Guru & Mapel yang mengajar di kelas aktif
    $q_plot = mysqli_query($koneksi, "
        SELECT gmk.id AS gmk_id, gmk.guru_id, gmk.mapel_id, gmk.kelas,
               g.nama AS nama_guru, g.nip,
               m.nama_mapel, m.kode_mapel
        FROM guru_mapel_kelas gmk
        JOIN guru g ON gmk.guru_id = g.id
        JOIN mapel m ON gmk.mapel_id = m.id
        WHERE TRIM(gmk.kelas) = '$k_esc'
        ORDER BY m.nama_mapel ASC, g.nama ASC
    ");
    if ($q_plot) {
        while ($pl = mysqli_fetch_assoc($q_plot)) {
            $plotting_kelas[] = $pl;
        }
    }

    // Jadwal pelajaran di kelas aktif
    $sql_jad = $jadwal_has_guru_id
        ? "SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, m.nama_mapel, m.kode_mapel, COALESCE(g.nama, g2.nama) AS nama_guru
           FROM jadwal j
           JOIN mapel m ON j.mapel_id = m.id
           LEFT JOIN guru g ON j.guru_id = g.id
           LEFT JOIN guru g2 ON m.guru_id = g2.id
           WHERE TRIM(j.kelas) = '$k_esc'
           ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), j.jam_mulai ASC"
        : "SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, m.nama_mapel, m.kode_mapel, g2.nama AS nama_guru
           FROM jadwal j
           JOIN mapel m ON j.mapel_id = m.id
           LEFT JOIN guru g2 ON m.guru_id = g2.id
           WHERE TRIM(j.kelas) = '$k_esc'
           ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), j.jam_mulai ASC";

    $q_jad = mysqli_query($koneksi, $sql_jad);
    if ($q_jad) {
        while ($jd = mysqli_fetch_assoc($q_jad)) {
            $jadwal_kelas[] = $jd;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kelas &amp; Plotting Guru - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    <style>
        /* Dot warna menu Kelola Kelas di sidebar */
        .sidebar-menu .submenu .dot.kelas {
            background: #161616;
        }

        /* Skeleton Loader Khusus Halaman Manajemen Kelas */
        .mk-skel-wrap {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .mk-skel-wrap.is-hidden {
            display: none !important;
        }
        .mk-real-content.is-loading {
            display: none !important;
        }
        .sk-shimmer {
            background: linear-gradient(90deg, #ecece4 25%, #f7f7f2 50%, #ecece4 75%);
            background-size: 200% 100%;
            animation: mkShimmer 1.25s infinite linear;
            border-radius: 10px;
        }
        .sk-card-light .sk-shimmer {
            background: linear-gradient(90deg, #f1f5f9 25%, #f8fafc 50%, #f1f5f9 75%);
            background-size: 200% 100%;
        }
        @keyframes mkShimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .mk-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Hero Krem Selaras Design System SIPN */
        .mk-hero {
            background: #efefe5;
            border-radius: 22px;
            padding: 26px 28px;
            border: 1px solid rgba(22, 22, 22, 0.08);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .mk-eyebrow {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .mk-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid rgba(22, 22, 22, 0.09);
            font-size: 12px;
            font-weight: 600;
            color: #161616;
        }
        .mk-pill.warn {
            background: #fef3c7;
            border-color: #fde68a;
            color: #92400e;
        }
        .mk-hero-title {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #161616;
            margin: 0 0 6px;
        }
        .mk-hero-title span {
            color: #6f6f68;
            font-weight: 500;
        }
        .mk-hero-sub {
            margin: 0;
            font-size: 13.5px;
            color: #575750;
            max-width: 650px;
            line-height: 1.5;
        }
        .mk-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-ink {
            background: #161616;
            color: #ffffff;
            border: none;
            padding: 11px 18px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: opacity 0.15s ease, transform 0.15s ease;
        }
        .btn-ink:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .btn-cream {
            background: #ffffff;
            color: #161616;
            border: 1px solid rgba(22, 22, 22, 0.15);
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
        }
        .btn-cream:hover {
            background: #f6f6f2;
        }

        /* Alert */
        .alert-box {
            padding: 13px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #eaf4ed; color: #1e5b34; border: 1px solid #b7dfc4; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        /* Card Box */
        .card-box {
            background: #ffffff;
            border-radius: 18px;
            padding: 22px 24px;
            border: 1px solid rgba(22, 22, 22, 0.08);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.025);
        }
        .card-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .card-head h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #161616;
        }
        .card-head p {
            margin: 3px 0 0;
            font-size: 12.5px;
            color: #6f6f68;
        }

        /* Selector Tabs Kelas */
        .kelas-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .kelas-tab {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            border-radius: 14px;
            background: #f6f6f2;
            border: 1.5px solid rgba(22, 22, 22, 0.08);
            color: #161616;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.15s ease;
        }
        .kelas-tab:hover {
            background: #efefe5;
            border-color: rgba(22, 22, 22, 0.22);
        }
        .kelas-tab.active {
            background: #161616;
            color: #ffffff;
            border-color: #161616;
        }
        .kelas-tab .k-meta {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 999px;
            background: rgba(22, 22, 22, 0.08);
            color: #575750;
        }
        .kelas-tab.active .k-meta {
            background: rgba(255, 255, 255, 0.18);
            color: #ffffff;
        }

        /* Grid Workspace 2 Kolom */
        .workspace-grid {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .workspace-grid { grid-template-columns: 1fr; }
        }

        /* Form Elements */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }
        .form-control {
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            font-family: inherit;
            outline: none;
            background: #f8fafc;
            color: #161616;
        }
        .form-control:focus {
            border-color: #161616;
            background: #ffffff;
        }

        .inline-plot-box {
            background: #f6f6f2;
            border: 1px solid rgba(22, 22, 22, 0.08);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 18px;
        }
        .plot-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .plot-row-3 {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr;
            gap: 10px;
        }
        @media (max-width: 600px) {
            .plot-row-2, .plot-row-3 { grid-template-columns: 1fr; }
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }
        .data-table th {
            background: #f8fafc;
            color: #475569;
            padding: 11px 12px;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 700;
            white-space: nowrap;
        }
        .data-table td {
            padding: 11px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .data-table tr:hover td {
            background: #fafaf7;
        }

        .badge-code {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            background: #efefe5;
            color: #161616;
            font-size: 11px;
            font-weight: 700;
        }
        .btn-icon-danger {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: 1px solid #fecaca;
            background: #fff5f5;
            color: #b91c1c;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .btn-icon-danger:hover {
            background: #fee2e2;
        }

        /* Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(3px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
        }
        .modal-backdrop.is-open { display: flex; }
        .modal-card {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 580px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px 26px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.22);
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .modal-header h3 { margin: 0; font-size: 17px; color: #161616; }
        .modal-close {
            background: #f1f5f9;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
        }

        /* Checklist Siswa di Modal */
        .siswa-checklist {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
        .siswa-check-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
        }
        .siswa-check-item:hover {
            background: #f8fafc;
        }
        .siswa-check-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .siswa-check-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #161616;
        }
    </style>
    <!-- Vercel Speed Insights -->
    <script>
      window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
    <!-- Vercel Web Analytics -->
    <script>
      window.va = window.va || function () { (window.vaq = window.vaq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/analytics/script.js"></script>
</head>
<body>
<?php include __DIR__ . '/lenis_scroll.php'; ?>
<?php require __DIR__ . "/loader.php"; ?>
    <div class="dashboard-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo"><i class="home"></i></div>
                <h2>SIPN - SMK SAKUCI</h2>
            </div>

            <div class="user-profile-mini">
                <?php if (!empty($avatar_src)): ?>
                    <img src="<?= htmlspecialchars($avatar_src); ?>" class="user-avatar-initial" style="object-fit: cover;" referrerpolicy="no-referrer">
                <?php else: ?>
                    <div class="user-avatar-initial"><?= strtoupper(substr($username, 0, 1)); ?></div>
                <?php endif; ?>
                <div class="user-meta-mini">
                    <span class="user-name"><?= htmlspecialchars($username); ?></span>
                    <span class="user-role-badge"><?= htmlspecialchars($role); ?></span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="dashboard"></i> <span class="menu-label">Dashboard</span></a></li>

                <li class="has-submenu">
                    <a href="javascript:void(0)"><i class="master"></i> <span class="menu-label">Master Data</span></a>
                    <ul class="submenu">
                        <li><a href="daftar_user.php"><span class="dot user"></span> <span class="menu-label">Kelola User</span></a></li>
                        <li><a href="daftar_siswa.php"><span class="dot siswa"></span> <span class="menu-label">Kelola Siswa</span></a></li>
                        <li><a href="daftar_guru.php"><span class="dot guru"></span> <span class="menu-label">Kelola Guru</span></a></li>
                        <li><a href="daftar_mapel.php"><span class="dot mapel"></span> <span class="menu-label">Mata Pelajaran</span></a></li>
                        <li><a href="manajemen_kelas.php" class="active"><span class="dot kelas"></span> <span class="menu-label">Kelola Kelas</span></a></li>
                    </ul>
                </li>

                <li><a href="profile.php"><i class="profile"></i> <span class="menu-label">Profile Settings</span></a></li>
            </ul>

            <div class="sidebar-footer">
                <a href="logout.php" class="add-files-box" style="text-decoration: none; color: inherit;">
                    <div class="icon-plus"><i class="logout"></i></div>
                    <div class="add-text">
                        <strong>System Session</strong>
                        <span>Logout Account</span>
                    </div>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title">
                    <h1>Manajemen Kelas &amp; Plotting</h1>
                    <span class="storage-badge">Role: ADMIN</span>
                </div>
                <div class="topbar-actions">
                    <button type="button" class="btn-ink" onclick="openModal('modalBuatKelas')">
                        <i class="fa-solid fa-plus"></i> Buat Kelas Baru
                    </button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/settings.webp"/></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="page-container">

                <!-- SKELETON LOADER (OTOMATIS AKTIF SAAT LOADER.PHP TIDAK MERENDER OVERLAY DI HALAMAN INI) -->
                <div id="mkSkeletonLoader" class="mk-skel-wrap" aria-hidden="true">
                    <div class="mk-hero">
                        <div style="flex:1; min-width:260px;">
                            <div style="display:flex; gap:8px; margin-bottom:12px;">
                                <div class="sk-shimmer" style="width:110px; height:26px; border-radius:999px;"></div>
                                <div class="sk-shimmer" style="width:110px; height:26px; border-radius:999px;"></div>
                                <div class="sk-shimmer" style="width:120px; height:26px; border-radius:999px;"></div>
                            </div>
                            <div class="sk-shimmer" style="width:300px; height:32px; margin-bottom:10px;"></div>
                            <div class="sk-shimmer" style="width:85%; max-width:520px; height:16px;"></div>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <div class="sk-shimmer" style="width:155px; height:40px; border-radius:999px;"></div>
                            <div class="sk-shimmer" style="width:145px; height:40px; border-radius:999px;"></div>
                        </div>
                    </div>

                    <div class="card-box sk-card-light">
                        <div class="sk-shimmer" style="width:240px; height:20px; margin-bottom:8px;"></div>
                        <div class="sk-shimmer" style="width:360px; height:14px; margin-bottom:16px;"></div>
                        <div style="display:flex; flex-wrap:wrap; gap:10px;">
                            <div class="sk-shimmer" style="width:185px; height:42px; border-radius:14px;"></div>
                            <div class="sk-shimmer" style="width:185px; height:42px; border-radius:14px;"></div>
                            <div class="sk-shimmer" style="width:185px; height:42px; border-radius:14px;"></div>
                            <div class="sk-shimmer" style="width:185px; height:42px; border-radius:14px;"></div>
                        </div>
                    </div>

                    <div class="workspace-grid">
                        <div class="card-box sk-card-light">
                            <div class="sk-shimmer" style="width:230px; height:20px; margin-bottom:14px;"></div>
                            <div class="sk-shimmer" style="width:100%; height:110px; border-radius:14px; margin-bottom:16px;"></div>
                            <div class="sk-shimmer" style="width:100%; height:38px; margin-bottom:10px;"></div>
                            <div class="sk-shimmer" style="width:100%; height:38px; margin-bottom:10px;"></div>
                            <div class="sk-shimmer" style="width:100%; height:38px;"></div>
                        </div>
                        <div class="card-box sk-card-light">
                            <div class="sk-shimmer" style="width:220px; height:20px; margin-bottom:14px;"></div>
                            <div class="sk-shimmer" style="width:100%; height:38px; margin-bottom:14px;"></div>
                            <div class="sk-shimmer" style="width:100%; height:38px; margin-bottom:10px;"></div>
                            <div class="sk-shimmer" style="width:100%; height:38px; margin-bottom:10px;"></div>
                            <div class="sk-shimmer" style="width:100%; height:38px; margin-bottom:10px;"></div>
                            <div class="sk-shimmer" style="width:100%; height:38px;"></div>
                        </div>
                    </div>
                </div>

                <!-- KONTEN UTAMA HALAMAN -->
                <div id="mkRealContent" class="mk-container mk-real-content is-loading">

                    <?php if ($msg_text !== ''): ?>
                        <div class="alert-box <?= $msg_type === 'success' ? 'alert-success' : 'alert-error'; ?>">
                            <i class="fa-solid <?= $msg_type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                            <span><?= htmlspecialchars($msg_text); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- HERO BANNER -->
                    <section class="mk-hero">
                        <div>
                            <div class="mk-eyebrow">
                                <span class="mk-pill"><i class="fa-solid fa-school"></i> <?= count($kelas_stats); ?> Rombel Kelas</span>
                                <span class="mk-pill"><i class="fa-solid fa-user-graduate"></i> <?= $tot_siswa_all; ?> Total Siswa</span>
                                <span class="mk-pill"><i class="fa-solid fa-chalkboard-user"></i> <?= $tot_plot_all; ?> Plotting Guru</span>
                                <?php if ($tot_tanpa_kelas > 0): ?>
                                    <span class="mk-pill warn"><i class="fa-solid fa-triangle-exclamation"></i> <?= $tot_tanpa_kelas; ?> Siswa Belum Punya Kelas</span>
                                <?php endif; ?>
                            </div>
                            <h2 class="mk-hero-title">Plotting Kelas <span>Siswa &amp; Guru</span></h2>
                            <p class="mk-hero-sub">
                                Pilih kelas di bawah untuk mengatur daftar siswa anggota rombel sekaligus menentukan guru dan mata pelajaran yang mengajar di kelas tersebut.
                            </p>
                        </div>
                        <div class="mk-hero-actions">
                            <button type="button" class="btn-ink" onclick="openModal('modalBuatKelas')">
                                <i class="fa-solid fa-folder-plus"></i> Buat Kelas Baru
                            </button>
                            <?php if ($active_kelas !== ''): ?>
                                <button type="button" class="btn-cream" onclick="openModal('modalRenameKelas')">
                                    <i class="fa-solid fa-pen"></i> Ganti Nama Kelas
                                </button>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- PILIH ROMBEL KELAS -->
                    <div class="card-box">
                        <div class="card-head">
                            <div>
                                <h3>Pilih Kelas (Rombongan Belajar)</h3>
                                <p>Klik salah satu kelas untuk mengelola anggota siswa, guru pengampu, dan jadwal pelajarannya.</p>
                            </div>
                        </div>

                        <?php if (!empty($kelas_stats)): ?>
                            <div class="kelas-strip">
                                <?php foreach ($kelas_stats as $k_nama => $st):
                                    $is_act = ($k_nama === $active_kelas);
                                ?>
                                    <a href="manajemen_kelas.php?kelas=<?= urlencode($k_nama); ?>"
                                       class="kelas-tab <?= $is_act ? 'active' : ''; ?>">
                                        <span><?= htmlspecialchars($k_nama); ?></span>
                                        <span class="k-meta"><?= $st['siswa']; ?> Siswa &middot; <?= $st['mapel_guru']; ?> Mapel</span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align:center; padding: 28px; background:#f8fafc; border-radius:14px; border:1px dashed #cbd5e1;">
                                <p style="margin:0 0 12px; color:#64748b;">Belum ada data kelas. Buat nama kelas pertama (misalnya <strong>XI RPL 1</strong>) untuk mulai plotting.</p>
                                <button type="button" class="btn-ink" onclick="openModal('modalBuatKelas')">
                                    <i class="fa-solid fa-plus"></i> Buat Kelas Pertama
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($active_kelas !== ''): ?>
                    <!-- WORKSPACE 2 KOLOM UNTUK KELAS AKTIF -->
                    <div class="workspace-grid">

                        <!-- KOLOM KIRI: PLOTTING GURU, MAPEL & JADWAL -->
                        <div style="display:flex; flex-direction:column; gap:20px;">
                            <div class="card-box">
                                <div class="card-head">
                                    <div>
                                        <h3>Guru Pengajar Kelas <?= htmlspecialchars($active_kelas); ?> (<?= count($plotting_kelas); ?>)</h3>
                                        <p>Guru yang di-plotting di sini otomatis mendapatkan akses kelas ini di halaman tugas &amp; nilai guru.</p>
                                    </div>
                                </div>

                                <!-- Form Tambah Plotting Guru + Mapel -->
                                <form method="POST" action="manajemen_kelas.php?kelas=<?= urlencode($active_kelas); ?>" class="inline-plot-box">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="target_kelas" value="<?= htmlspecialchars($active_kelas); ?>">

                                    <div class="plot-row-2" style="margin-bottom: 12px;">
                                        <div class="form-group">
                                            <label>Mata Pelajaran *</label>
                                            <select name="mapel_id" id="selectMapelPlot" class="form-control" required onchange="autoSelectDefaultGuru(this)">
                                                <option value="">-- Pilih Mapel --</option>
                                                <?php foreach ($list_mapel_all as $m): ?>
                                                    <option value="<?= (int) $m['id']; ?>" data-default-guru="<?= (int) ($m['guru_id'] ?? 0); ?>">
                                                        <?= htmlspecialchars($m['nama_mapel']); ?><?= !empty($m['kode_mapel']) ? ' (' . htmlspecialchars($m['kode_mapel']) . ')' : ''; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label>Guru Pengampu *</label>
                                            <select name="guru_id" id="selectGuruPlot" class="form-control" required>
                                                <option value="">-- Pilih Guru --</option>
                                                <?php foreach ($list_guru_all as $g): ?>
                                                    <option value="<?= (int) $g['id']; ?>">
                                                        <?= htmlspecialchars($g['nama']); ?><?= !empty($g['nip']) ? ' (NIP: ' . htmlspecialchars($g['nip']) . ')' : ''; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div style="margin-bottom: 10px;">
                                        <label style="display:inline-flex; align-items:center; gap:8px; font-size:12.5px; font-weight:600; color:#161616; cursor:pointer;">
                                            <input type="checkbox" name="sekalian_jadwal" value="1" id="chkSekalianJadwal" onchange="toggleJadwalFields(this.checked)" style="accent-color:#161616;">
                                            Sekalian tambahkan ke Jadwal Pelajaran kelas ini
                                        </label>
                                    </div>

                                    <div id="wrapJadwalFields" class="plot-row-3" style="display:none; margin-bottom:12px;">
                                        <div class="form-group">
                                            <label>Hari</label>
                                            <select name="hari" class="form-control">
                                                <option value="Senin">Senin</option>
                                                <option value="Selasa">Selasa</option>
                                                <option value="Rabu">Rabu</option>
                                                <option value="Kamis">Kamis</option>
                                                <option value="Jumat">Jumat</option>
                                                <option value="Sabtu">Sabtu</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Jam Mulai</label>
                                            <input type="time" name="jam_mulai" class="form-control" value="07:00">
                                        </div>
                                        <div class="form-group">
                                            <label>Jam Selesai</label>
                                            <input type="time" name="jam_selesai" class="form-control" value="09:00">
                                        </div>
                                    </div>

                                    <button type="submit" name="plot_guru_kelas" class="btn-ink" style="border-radius:10px; padding:9px 16px;">
                                        <i class="fa-solid fa-user-plus"></i> Tambahkan Guru ke Kelas <?= htmlspecialchars($active_kelas); ?>
                                    </button>
                                </form>

                                <!-- Tabel Guru & Mapel Terdaftar -->
                                <div style="overflow-x:auto;">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th style="width:40px; text-align:center;">No</th>
                                                <th>Mata Pelajaran</th>
                                                <th>Guru Pengampu</th>
                                                <th style="text-align:center;">Hapus</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($plotting_kelas)): ?>
                                                <?php foreach ($plotting_kelas as $idx => $pl): ?>
                                                    <tr>
                                                        <td style="text-align:center; font-weight:600;"><?= $idx + 1; ?></td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($pl['nama_mapel']); ?></strong>
                                                            <?php if (!empty($pl['kode_mapel'])): ?>
                                                                <span class="badge-code"><?= htmlspecialchars($pl['kode_mapel']); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?= htmlspecialchars($pl['nama_guru']); ?><br>
                                                            <small style="color:#64748b;">NIP: <?= htmlspecialchars($pl['nip'] ?? '-'); ?></small>
                                                        </td>
                                                        <td style="text-align:center;">
                                                            <form method="POST" action="manajemen_kelas.php?kelas=<?= urlencode($active_kelas); ?>"
                                                                  onsubmit="return confirm('Hapus plotting guru <?= htmlspecialchars($pl['nama_guru']); ?> dari mata pelajaran ini?');">
                                                                <?= csrf_field(); ?>
                                                                <input type="hidden" name="gmk_id" value="<?= (int) $pl['gmk_id']; ?>">
                                                                <input type="hidden" name="target_kelas" value="<?= htmlspecialchars($active_kelas); ?>">
                                                                <input type="hidden" name="hapus_jadwal_terkait" value="1">
                                                                <button type="submit" name="hapus_plotting" class="btn-icon-danger" title="Hapus Plotting">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" style="text-align:center; padding:22px; color:#64748b;">
                                                        Belum ada guru yang di-plotting ke kelas <strong><?= htmlspecialchars($active_kelas); ?></strong>.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- CARD JADWAL PELAJARAN KELAS -->
                            <div class="card-box">
                                <div class="card-head">
                                    <div>
                                        <h3>Jadwal Pelajaran Kelas <?= htmlspecialchars($active_kelas); ?> (<?= count($jadwal_kelas); ?> Sesi)</h3>
                                        <p>Jadwal ini langsung tampil di dashboard siswa kelas <?= htmlspecialchars($active_kelas); ?> maupun dashboard guru pengajar.</p>
                                    </div>
                                </div>

                                <div style="overflow-x:auto;">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Hari</th>
                                                <th>Waktu</th>
                                                <th>Mata Pelajaran &amp; Guru</th>
                                                <th style="text-align:center;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($jadwal_kelas)): ?>
                                                <?php foreach ($jadwal_kelas as $jd): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($jd['hari']); ?></strong></td>
                                                        <td style="white-space:nowrap;">
                                                            <?= htmlspecialchars(substr($jd['jam_mulai'], 0, 5)); ?> – <?= htmlspecialchars(substr($jd['jam_selesai'], 0, 5)); ?>
                                                        </td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($jd['nama_mapel']); ?></strong><br>
                                                            <small style="color:#64748b;"><?= htmlspecialchars($jd['nama_guru'] ?? 'Belum ditentukan'); ?></small>
                                                        </td>
                                                        <td style="text-align:center;">
                                                            <form method="POST" action="manajemen_kelas.php?kelas=<?= urlencode($active_kelas); ?>"
                                                                  onsubmit="return confirm('Hapus sesi jadwal ini?');">
                                                                <?= csrf_field(); ?>
                                                                <input type="hidden" name="jadwal_id" value="<?= (int) $jd['id']; ?>">
                                                                <input type="hidden" name="target_kelas" value="<?= htmlspecialchars($active_kelas); ?>">
                                                                <button type="submit" name="hapus_jadwal" class="btn-icon-danger" title="Hapus Jadwal">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" style="text-align:center; padding:20px; color:#64748b;">
                                                        Belum ada jadwal pelajaran untuk kelas ini. Centang opsi jadwal saat menambah guru di atas.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: ANGGOTA SISWA DI KELAS AKTIF -->
                        <div class="card-box">
                            <div class="card-head">
                                <div>
                                    <h3>Anggota Siswa Kelas <?= htmlspecialchars($active_kelas); ?> (<?= count($anggota_siswa); ?>)</h3>
                                    <p>Siswa dalam daftar ini otomatis menerima tempat pengumpulan tugas kelas <?= htmlspecialchars($active_kelas); ?>.</p>
                                </div>
                                <button type="button" class="btn-ink" style="padding:9px 15px; font-size:12.5px;" onclick="openModal('modalTambahSiswa')">
                                    <i class="fa-solid fa-user-plus"></i> Masukkan Siswa
                                </button>
                            </div>

                            <div style="margin-bottom: 12px;">
                                <input type="text" id="filterAnggotaKelas" class="form-control" placeholder="Cari nama atau NIS siswa di kelas ini..." style="width:100%;">
                            </div>

                            <div style="overflow-x:auto;">
                                <table class="data-table" id="tabelAnggotaSiswa">
                                    <thead>
                                        <tr>
                                            <th style="width:40px; text-align:center;">No</th>
                                            <th>NIS</th>
                                            <th>Nama Siswa</th>
                                            <th style="text-align:center;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($anggota_siswa)): ?>
                                            <?php foreach ($anggota_siswa as $idx => $sw): ?>
                                                <tr class="anggota-row" data-search="<?= strtolower(htmlspecialchars($sw['nama'] . ' ' . $sw['nis'])); ?>">
                                                    <td style="text-align:center; font-weight:600;"><?= $idx + 1; ?></td>
                                                    <td><?= htmlspecialchars($sw['nis']); ?></td>
                                                    <td><strong><?= htmlspecialchars($sw['nama']); ?></strong></td>
                                                    <td style="text-align:center; white-space:nowrap;">
                                                        <button type="button"
                                                                class="btn-cream"
                                                                style="padding:5px 10px; font-size:11.5px;"
                                                                data-id="<?= (int) $sw['id']; ?>"
                                                                data-nama="<?= htmlspecialchars($sw['nama']); ?>"
                                                                onclick="bukaModalPindah(this)"
                                                                title="Pindahkan ke kelas lain">
                                                            Pindah
                                                        </button>
                                                        <form method="POST" action="manajemen_kelas.php?kelas=<?= urlencode($active_kelas); ?>"
                                                              style="display:inline;"
                                                              onsubmit="return confirm('Keluarkan <?= htmlspecialchars($sw['nama']); ?> dari kelas <?= htmlspecialchars($active_kelas); ?>?');">
                                                            <?= csrf_field(); ?>
                                                            <input type="hidden" name="siswa_id" value="<?= (int) $sw['id']; ?>">
                                                            <input type="hidden" name="target_kelas" value="<?= htmlspecialchars($active_kelas); ?>">
                                                            <button type="submit" name="keluarkan_siswa" class="btn-icon-danger" title="Keluarkan dari kelas">
                                                                <i class="fa-solid fa-xmark"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" style="text-align:center; padding:26px; color:#64748b;">
                                                    Belum ada siswa di kelas <strong><?= htmlspecialchars($active_kelas); ?></strong>.<br>
                                                    Klik tombol <strong>Masukkan Siswa</strong> untuk menambahkan siswa secara massal.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                    <?php endif; ?>

                </div><!-- /#mkRealContent -->

            </div>
        </main>
    </div>

    <!-- MODAL 1: BUAT KELAS BARU -->
    <div class="modal-backdrop" id="modalBuatKelas">
        <div class="modal-card" style="max-width:440px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-folder-plus"></i> Buat Rombel Kelas Baru</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalBuatKelas')">&times;</button>
            </div>
            <form method="POST" action="manajemen_kelas.php" style="display:flex; flex-direction:column; gap:14px;">
                <?= csrf_field(); ?>
                <div class="form-group">
                    <label>Nama Kelas *</label>
                    <input type="text" name="nama_kelas_baru" class="form-control" placeholder="Contoh: XI RPL 1 atau XII RPL 2" required>
                    <small style="color:#64748b; font-size:11.5px;">Gunakan format penamaan yang seragam (misal: X RPL 1, XI RPL 1).</small>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn-cream" onclick="closeModal('modalBuatKelas')">Batal</button>
                    <button type="submit" name="buat_kelas_baru" class="btn-ink">Buat &amp; Atur Kelas</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: GANTI NAMA KELAS -->
    <?php if ($active_kelas !== ''): ?>
    <div class="modal-backdrop" id="modalRenameKelas">
        <div class="modal-card" style="max-width:440px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pen"></i> Ganti Nama Kelas</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalRenameKelas')">&times;</button>
            </div>
            <form method="POST" action="manajemen_kelas.php?kelas=<?= urlencode($active_kelas); ?>" style="display:flex; flex-direction:column; gap:14px;">
                <?= csrf_field(); ?>
                <input type="hidden" name="kelas_lama" value="<?= htmlspecialchars($active_kelas); ?>">
                <div class="form-group">
                    <label>Nama Kelas Saat Ini</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($active_kelas); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Nama Kelas Baru *</label>
                    <input type="text" name="kelas_baru" class="form-control" value="<?= htmlspecialchars($active_kelas); ?>" required>
                    <small style="color:#64748b; font-size:11.5px;">Seluruh siswa, plotting guru, jadwal, dan tugas di kelas ini akan ikut diperbarui.</small>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn-cream" onclick="closeModal('modalRenameKelas')">Batal</button>
                    <button type="submit" name="rename_kelas" class="btn-ink">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: TAMBAH / MASUKKAN SISWA MASSAL KE KELAS AKTIF -->
    <div class="modal-backdrop" id="modalTambahSiswa">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-plus"></i> Masukkan Siswa ke <?= htmlspecialchars($active_kelas); ?></h3>
                <button type="button" class="modal-close" onclick="closeModal('modalTambahSiswa')">&times;</button>
            </div>

            <form method="POST" action="manajemen_kelas.php?kelas=<?= urlencode($active_kelas); ?>" style="display:flex; flex-direction:column; gap:12px;">
                <?= csrf_field(); ?>
                <input type="hidden" name="target_kelas" value="<?= htmlspecialchars($active_kelas); ?>">

                <div style="display:flex; flex-wrap:wrap; gap:8px; justify-content:space-between; align-items:center;">
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="btn-ink" id="btnFilterUnassigned" style="padding:6px 12px; font-size:12px;" onclick="filterKandidatMode('unassigned')">
                            Belum Punya Kelas (<?= $tot_tanpa_kelas; ?>)
                        </button>
                        <button type="button" class="btn-cream" id="btnFilterAllKandidat" style="padding:6px 12px; font-size:12px;" onclick="filterKandidatMode('all')">
                            Semua Siswa Lain (<?= count($kandidat_siswa); ?>)
                        </button>
                    </div>
                    <label style="font-size:12px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        <input type="checkbox" id="chkSelectAllKandidat" onchange="toggleSelectAllKandidat(this.checked)" style="accent-color:#161616;">
                        Pilih Semua Tampil
                    </label>
                </div>

                <input type="text" id="searchKandidatInput" class="form-control" placeholder="Cari nama atau NIS siswa...">

                <div class="siswa-checklist" id="listKandidatBox">
                    <?php if (!empty($kandidat_siswa)): ?>
                        <?php foreach ($kandidat_siswa as $ks):
                            $is_unassigned = ($ks['kelas_sekarang'] === '');
                        ?>
                            <label class="siswa-check-item kandidat-row"
                                   data-unassigned="<?= $is_unassigned ? '1' : '0'; ?>"
                                   data-search="<?= strtolower(htmlspecialchars($ks['nama'] . ' ' . $ks['nis'] . ' ' . $ks['kelas_sekarang'])); ?>">
                                <div class="siswa-check-left">
                                    <input type="checkbox" name="siswa_ids[]" value="<?= (int) $ks['id']; ?>" class="chk-kandidat">
                                    <div>
                                        <strong style="font-size:13px; color:#161616;"><?= htmlspecialchars($ks['nama']); ?></strong><br>
                                        <small style="color:#64748b;">NIS: <?= htmlspecialchars($ks['nis']); ?></small>
                                    </div>
                                </div>
                                <span class="badge-code" style="<?= $is_unassigned ? 'background:#fef3c7; color:#92400e;' : ''; ?>">
                                    <?= $is_unassigned ? 'Belum ada kelas' : 'Kelas: ' . htmlspecialchars($ks['kelas_sekarang']); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding:20px; text-align:center; color:#64748b; font-size:13px;">
                            Semua siswa sudah terdaftar di kelas <strong><?= htmlspecialchars($active_kelas); ?></strong>.
                        </div>
                    <?php endif; ?>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:4px;">
                    <button type="button" class="btn-cream" onclick="closeModal('modalTambahSiswa')">Batal</button>
                    <button type="submit" name="tambah_siswa_kelas" class="btn-ink">
                        <i class="fa-solid fa-check"></i> Masukkan ke <?= htmlspecialchars($active_kelas); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: PINDAH SATU SISWA KE KELAS LAIN -->
    <div class="modal-backdrop" id="modalPindahSiswa">
        <div class="modal-card" style="max-width:440px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-right-left"></i> Pindahkan Siswa</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalPindahSiswa')">&times;</button>
            </div>
            <form method="POST" action="manajemen_kelas.php?kelas=<?= urlencode($active_kelas); ?>" style="display:flex; flex-direction:column; gap:14px;">
                <?= csrf_field(); ?>
                <input type="hidden" name="siswa_id" id="pindahSiswaId">
                <input type="hidden" name="kelas_asal" value="<?= htmlspecialchars($active_kelas); ?>">

                <div class="form-group">
                    <label>Nama Siswa</label>
                    <input type="text" id="pindahSiswaNama" class="form-control" disabled>
                </div>

                <div class="form-group">
                    <label>Pilih Kelas Tujuan *</label>
                    <select name="kelas_tujuan" class="form-control" required>
                        <?php foreach ($kelas_stats as $k_opt => $st):
                            if ($k_opt === $active_kelas) continue;
                        ?>
                            <option value="<?= htmlspecialchars($k_opt); ?>"><?= htmlspecialchars($k_opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn-cream" onclick="closeModal('modalPindahSiswa')">Batal</button>
                    <button type="submit" name="pindah_siswa" class="btn-ink">Pindahkan Siswa</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

<script>
// Sinkronisasi Skeleton Loader dengan loader.php
(function () {
    var skelEl = document.getElementById('mkSkeletonLoader');
    var realEl = document.getElementById('mkRealContent');
    if (!skelEl || !realEl) return;

    function revealContent() {
        skelEl.classList.add('is-hidden');
        realEl.classList.remove('is-loading');
    }

    // Jika loader.php sudah merender skeleton/overlay sendiri, langsung tampilkan real content di belakangnya
    var globalLoader = document.querySelector('#skeleton-loader, #page-loader, .skeleton-overlay, .page-skeleton');
    if (globalLoader && globalLoader.offsetParent !== null) {
        revealContent();
    } else {
        window.addEventListener('load', function () {
            setTimeout(revealContent, 260);
        });
        // Fallback batas maksimal 900ms agar tidak pernah tertahan
        setTimeout(revealContent, 900);
    }

    // Tampilkan skeleton saat pindah tab kelas
    document.querySelectorAll('.kelas-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            skelEl.classList.remove('is-hidden');
            realEl.classList.add('is-loading');
        });
    });
})();

function openModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('is-open');
}
function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('is-open');
}
document.querySelectorAll('.modal-backdrop').forEach(function (bg) {
    bg.addEventListener('click', function (e) {
        if (e.target === bg) bg.classList.remove('is-open');
    });
});

// Auto-pilih guru default saat memilih mapel (tetap bisa diganti manual)
function autoSelectDefaultGuru(sel) {
    var opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    var defGuru = opt.getAttribute('data-default-guru');
    var guruSel = document.getElementById('selectGuruPlot');
    if (guruSel && defGuru && defGuru !== '0') {
        guruSel.value = defGuru;
    }
}

function toggleJadwalFields(show) {
    var wrap = document.getElementById('wrapJadwalFields');
    if (wrap) wrap.style.display = show ? 'grid' : 'none';
}

function bukaModalPindah(btn) {
    document.getElementById('pindahSiswaId').value   = btn.getAttribute('data-id');
    document.getElementById('pindahSiswaNama').value = btn.getAttribute('data-nama');
    openModal('modalPindahSiswa');
}

// Pencarian siswa anggota kelas aktif
(function () {
    var inp = document.getElementById('filterAnggotaKelas');
    var rows = document.querySelectorAll('#tabelAnggotaSiswa tbody .anggota-row');
    if (inp) {
        inp.addEventListener('input', function () {
            var q = inp.value.trim().toLowerCase();
            rows.forEach(function (r) {
                var hay = r.getAttribute('data-search') || '';
                r.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }
})();

// Filter & Select All pada Modal Masukkan Siswa
var kandidatMode = 'unassigned';
function applyKandidatFilter() {
    var searchEl = document.getElementById('searchKandidatInput');
    var q = searchEl ? searchEl.value.trim().toLowerCase() : '';
    var items = document.querySelectorAll('#listKandidatBox .kandidat-row');

    items.forEach(function (item) {
        var isUnassigned = item.getAttribute('data-unassigned') === '1';
        var hay = item.getAttribute('data-search') || '';
        var okMode = (kandidatMode === 'all') ? true : isUnassigned;
        var okText = (q === '' || hay.indexOf(q) !== -1);
        item.style.display = (okMode && okText) ? 'flex' : 'none';
    });
}

function filterKandidatMode(mode) {
    kandidatMode = mode;
    var btnU = document.getElementById('btnFilterUnassigned');
    var btnA = document.getElementById('btnFilterAllKandidat');
    if (btnU && btnA) {
        btnU.className = (mode === 'unassigned') ? 'btn-ink' : 'btn-cream';
        btnA.className = (mode === 'all') ? 'btn-ink' : 'btn-cream';
    }
    var chkAll = document.getElementById('chkSelectAllKandidat');
    if (chkAll) chkAll.checked = false;
    applyKandidatFilter();
}

function toggleSelectAllKandidat(checked) {
    document.querySelectorAll('#listKandidatBox .kandidat-row').forEach(function (row) {
        if (row.style.display !== 'none') {
            var cb = row.querySelector('.chk-kandidat');
            if (cb) cb.checked = checked;
        }
    });
}

(function () {
    var searchEl = document.getElementById('searchKandidatInput');
    if (searchEl) {
        searchEl.addEventListener('input', applyKandidatFilter);
    }
    var unassignedCount = document.querySelectorAll('#listKandidatBox .kandidat-row[data-unassigned="1"]').length;
    filterKandidatMode(unassignedCount > 0 ? 'unassigned' : 'all');
})();
</script>
</body>
</html>