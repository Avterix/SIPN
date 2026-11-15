<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/auth.php';

// 1. PROTEKSI HAK AKSES GURU & ADMIN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
$role     = strtolower($_SESSION['role'] ?? '');
$username = $_SESSION['username'] ?? 'User';

if ($role !== 'guru' && $role !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$msg_type = "";
$msg_text = "";

// 2. AUTO-SETUP TABEL TUGAS & PENGUMPULAN TUGAS (JIKA BELUM ADA)
mysqli_query($koneksi, "
    CREATE TABLE IF NOT EXISTS tugas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guru_id INT NOT NULL DEFAULT 0,
        mapel_id INT NOT NULL,
        kelas VARCHAR(50) NOT NULL,
        semester VARCHAR(10) NOT NULL DEFAULT '1',
        judul VARCHAR(150) NOT NULL,
        deskripsi TEXT NULL,
        kategori ENUM('pengetahuan', 'keterampilan') NOT NULL DEFAULT 'keterampilan',
        tipe_pengumpulan ENUM('semua', 'file', 'link', 'teks') NOT NULL DEFAULT 'semua',
        file_lampiran VARCHAR(255) NULL,
        deadline DATETIME NULL,
        status ENUM('buka', 'tutup') NOT NULL DEFAULT 'buka',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tugas_filter (mapel_id, kelas, semester),
        INDEX idx_tugas_guru (guru_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

mysqli_query($koneksi, "
    CREATE TABLE IF NOT EXISTS pengumpulan_tugas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tugas_id INT NOT NULL,
        siswa_id INT NOT NULL,
        file_tugas VARCHAR(255) NULL,
        nama_file_asli VARCHAR(255) NULL,
        link_tugas VARCHAR(500) NULL,
        jawaban_teks TEXT NULL,
        catatan_siswa VARCHAR(255) NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        nilai INT NULL,
        catatan_guru VARCHAR(255) NULL,
        graded_at DATETIME NULL,
        UNIQUE KEY uniq_tugas_siswa (tugas_id, siswa_id),
        INDEX idx_pt_tugas (tugas_id),
        INDEX idx_pt_siswa (siswa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Pastikan direktori upload tersedia
$dir_soal  = __DIR__ . '/uploads/tugas_soal';
$dir_kumpul = __DIR__ . '/uploads/pengumpulan_tugas';
if (!is_dir($dir_soal)) {
    @mkdir($dir_soal, 0755, true);
}
if (!is_dir($dir_kumpul)) {
    @mkdir($dir_kumpul, 0755, true);
}

// 3. AMBIL DATA USER & GURU
$q_user    = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id'");
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

$q_guru    = mysqli_query($koneksi, "SELECT id, nama, nip FROM guru WHERE user_id = '$user_id' LIMIT 1");
$guru_data = ($q_guru && mysqli_num_rows($q_guru) > 0) ? mysqli_fetch_assoc($q_guru) : [];
$guru_id   = (int) ($guru_data['id'] ?? 0);
$guru_nama = !empty($guru_data['nama']) ? $guru_data['nama'] : $username;

$chk_gmk = mysqli_query($koneksi, "SHOW TABLES LIKE 'guru_mapel_kelas'");
$has_gmk = ($chk_gmk && mysqli_num_rows($chk_gmk) > 0);

// Helper verifikasi kepemilikan mapel oleh guru
function guruBolehAksesMapel($koneksi, $role, $guru_id, $mapel_id, $has_gmk)
{
    if ($role === 'admin') {
        return true;
    }
    if ($guru_id <= 0 || $mapel_id <= 0) {
        return false;
    }
    $sql = $has_gmk
        ? "SELECT 1 FROM mapel WHERE id = '$mapel_id' AND guru_id = '$guru_id'
           UNION
           SELECT 1 FROM guru_mapel_kelas WHERE mapel_id = '$mapel_id' AND guru_id = '$guru_id'
           LIMIT 1"
        : "SELECT 1 FROM mapel WHERE id = '$mapel_id' AND guru_id = '$guru_id' LIMIT 1";
    $res = mysqli_query($koneksi, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}

// Helper sinkronisasi otomatis nilai tugas -> nilai_rapor (agar otomatis tampil di lihat_nilai.php)
function sinkronNilaiRapor($koneksi, $siswa_id, $mapel_id, $semester)
{
    $siswa_id = (int) $siswa_id;
    $mapel_id = (int) $mapel_id;
    $sem_esc  = mysqli_real_escape_string($koneksi, $semester);

    $q_avg = mysqli_query($koneksi, "
        SELECT t.kategori, ROUND(AVG(pt.nilai), 0) AS rata_nilai, COUNT(pt.id) AS jml
        FROM pengumpulan_tugas pt
        JOIN tugas t ON pt.tugas_id = t.id
        WHERE pt.siswa_id = '$siswa_id'
          AND t.mapel_id = '$mapel_id'
          AND t.semester = '$sem_esc'
          AND pt.nilai IS NOT NULL
        GROUP BY t.kategori
    ");

    $avg_pengetahuan  = null;
    $avg_keterampilan = null;

    if ($q_avg) {
        while ($r = mysqli_fetch_assoc($q_avg)) {
            if ($r['kategori'] === 'pengetahuan' && (int)$r['jml'] > 0) {
                $avg_pengetahuan = max(0, min(100, (int) $r['rata_nilai']));
            } elseif ($r['kategori'] === 'keterampilan' && (int)$r['jml'] > 0) {
                $avg_keterampilan = max(0, min(100, (int) $r['rata_nilai']));
            }
        }
    }

    if ($avg_pengetahuan === null && $avg_keterampilan === null) {
        return;
    }

    $q_cek = mysqli_query($koneksi, "
        SELECT id, nilai_pengetahuan, nilai_keterampilan
        FROM nilai_rapor
        WHERE siswa_id = '$siswa_id' AND mapel_id = '$mapel_id' AND semester = '$sem_esc'
        LIMIT 1
    ");

    if ($q_cek && mysqli_num_rows($q_cek) > 0) {
        $existing = mysqli_fetch_assoc($q_cek);
        $new_p = ($avg_pengetahuan !== null) ? $avg_pengetahuan : (int) $existing['nilai_pengetahuan'];
        $new_k = ($avg_keterampilan !== null) ? $avg_keterampilan : (int) $existing['nilai_keterampilan'];

        mysqli_query($koneksi, "
            UPDATE nilai_rapor
            SET nilai_pengetahuan = '$new_p',
                nilai_keterampilan = '$new_k'
            WHERE id = '" . (int)$existing['id'] . "'
        ");
    } else {
        $new_p = ($avg_pengetahuan !== null) ? $avg_pengetahuan : 0;
        $new_k = ($avg_keterampilan !== null) ? $avg_keterampilan : 0;

        mysqli_query($koneksi, "
            INSERT INTO nilai_rapor (siswa_id, mapel_id, semester, nilai_kkm, nilai_pengetahuan, nilai_keterampilan, keterangan)
            VALUES ('$siswa_id', '$mapel_id', '$sem_esc', 75, '$new_p', '$new_k', 'Baik')
        ");
    }
}

// 4. FILTER AKTIF DARI URL
$filter_kelas    = trim($_GET['kelas'] ?? '');
$filter_mapel_id = (int) ($_GET['mapel_id'] ?? 0);
$filter_semester = ($_GET['semester'] ?? '1') === '2' ? '2' : '1';
$selected_tugas  = (int) ($_GET['tugas_id'] ?? 0);

// 5. HANDLE SEMUA AKSI POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    // A. BUAT TEMPAT PENGUMPULAN TUGAS BARU
    if (isset($_POST['buat_tugas'])) {
        $t_mapel_id  = (int) ($_POST['mapel_id'] ?? 0);
        $t_kelas     = trim($_POST['kelas'] ?? '');
        $t_semester  = ($_POST['semester'] ?? '1') === '2' ? '2' : '1';
        $t_judul     = trim($_POST['judul'] ?? '');
        $t_deskripsi = trim($_POST['deskripsi'] ?? '');
        $t_kategori  = in_array($_POST['kategori'] ?? '', ['pengetahuan', 'keterampilan'], true)
            ? $_POST['kategori']
            : 'keterampilan';
        $t_tipe      = in_array($_POST['tipe_pengumpulan'] ?? '', ['semua', 'file', 'link', 'teks'], true)
            ? $_POST['tipe_pengumpulan']
            : 'semua';
        $t_deadline  = trim($_POST['deadline'] ?? '');

        if (!guruBolehAksesMapel($koneksi, $role, $guru_id, $t_mapel_id, $has_gmk)) {
            $msg_type = "error";
            $msg_text = "Kamu tidak memiliki akses untuk membuat tugas pada mata pelajaran ini.";
        } elseif ($t_judul === '' || $t_kelas === '') {
            $msg_type = "error";
            $msg_text = "Judul tugas dan kelas wajib diisi.";
        } else {
            $file_lampiran_db = null;
            $upload_ok = true;

            if (!empty($_FILES['file_lampiran']['name']) && $_FILES['file_lampiran']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['file_lampiran']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'rar', 'txt', 'jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $allowed_ext, true)) {
                    $upload_ok = false;
                    $msg_type  = "error";
                    $msg_text  = "Format file lampiran tidak didukung.";
                } elseif ($_FILES['file_lampiran']['size'] > 15 * 1024 * 1024) {
                    $upload_ok = false;
                    $msg_type  = "error";
                    $msg_text  = "Ukuran file lampiran maksimal 15 MB.";
                } else {
                    $new_name = 'soal_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['file_lampiran']['tmp_name'], $dir_soal . '/' . $new_name)) {
                        $file_lampiran_db = $new_name;
                    }
                }
            }

            if ($upload_ok) {
                $judul_esc  = mysqli_real_escape_string($koneksi, $t_judul);
                $desk_esc   = mysqli_real_escape_string($koneksi, $t_deskripsi);
                $kelas_esc  = mysqli_real_escape_string($koneksi, $t_kelas);
                $sem_esc    = mysqli_real_escape_string($koneksi, $t_semester);
                $kat_esc    = mysqli_real_escape_string($koneksi, $t_kategori);
                $tipe_esc   = mysqli_real_escape_string($koneksi, $t_tipe);
                $lamp_sql   = $file_lampiran_db ? "'" . mysqli_real_escape_string($koneksi, $file_lampiran_db) . "'" : "NULL";
                $dead_sql   = ($t_deadline !== '')
                    ? "'" . mysqli_real_escape_string($koneksi, date('Y-m-d H:i:s', strtotime($t_deadline))) . "'"
                    : "NULL";

                $ins = mysqli_query($koneksi, "
                    INSERT INTO tugas (guru_id, mapel_id, kelas, semester, judul, deskripsi, kategori, tipe_pengumpulan, file_lampiran, deadline, status)
                    VALUES ('$guru_id', '$t_mapel_id', '$kelas_esc', '$sem_esc', '$judul_esc', '$desk_esc', '$kat_esc', '$tipe_esc', $lamp_sql, $dead_sql, 'buka')
                ");

                if ($ins) {
                    $selected_tugas  = (int) mysqli_insert_id($koneksi);
                    $filter_mapel_id = $t_mapel_id;
                    $filter_kelas    = $t_kelas;
                    $filter_semester = $t_semester;
                    $msg_type = "success";
                    $msg_text = "Tempat pengumpulan tugas \"$t_judul\" berhasil dibuat! Siswa kelas $t_kelas kini bisa mengumpulkan tugas di halaman Lihat Nilai.";
                } else {
                    $msg_type = "error";
                    $msg_text = "Gagal membuat tempat tugas: " . mysqli_error($koneksi);
                }
            }
        }
    }

    // B. BUKA / TUTUP PENGUMPULAN TUGAS
    if (isset($_POST['toggle_status_tugas'])) {
        $tid = (int) ($_POST['tugas_id'] ?? 0);
        $q_t = mysqli_query($koneksi, "SELECT id, mapel_id, status FROM tugas WHERE id = '$tid' LIMIT 1");
        if ($q_t && mysqli_num_rows($q_t) > 0) {
            $t_row = mysqli_fetch_assoc($q_t);
            if (guruBolehAksesMapel($koneksi, $role, $guru_id, (int)$t_row['mapel_id'], $has_gmk)) {
                $next_status = ($t_row['status'] === 'buka') ? 'tutup' : 'buka';
                mysqli_query($koneksi, "UPDATE tugas SET status = '$next_status' WHERE id = '$tid'");
                $selected_tugas = $tid;
                $msg_type = "success";
                $msg_text = $next_status === 'buka'
                    ? "Pengumpulan tugas kembali dibuka untuk siswa."
                    : "Pengumpulan tugas ditutup.";
            }
        }
    }

    // C. HAPUS TEMPAT PENGUMPULAN TUGAS
    if (isset($_POST['hapus_tugas'])) {
        $tid = (int) ($_POST['tugas_id'] ?? 0);
        $q_t = mysqli_query($koneksi, "SELECT * FROM tugas WHERE id = '$tid' LIMIT 1");
        if ($q_t && mysqli_num_rows($q_t) > 0) {
            $t_row = mysqli_fetch_assoc($q_t);
            if (guruBolehAksesMapel($koneksi, $role, $guru_id, (int)$t_row['mapel_id'], $has_gmk)) {
                // Hapus file soal & file jawaban siswa
                if (!empty($t_row['file_lampiran']) && file_exists($dir_soal . '/' . $t_row['file_lampiran'])) {
                    @unlink($dir_soal . '/' . $t_row['file_lampiran']);
                }
                $q_sub_files = mysqli_query($koneksi, "SELECT siswa_id, file_tugas FROM pengumpulan_tugas WHERE tugas_id = '$tid'");
                $affected_students = [];
                if ($q_sub_files) {
                    while ($sf = mysqli_fetch_assoc($q_sub_files)) {
                        $affected_students[] = (int) $sf['siswa_id'];
                        if (!empty($sf['file_tugas']) && file_exists($dir_kumpul . '/' . $sf['file_tugas'])) {
                            @unlink($dir_kumpul . '/' . $sf['file_tugas']);
                        }
                    }
                }
                mysqli_query($koneksi, "DELETE FROM pengumpulan_tugas WHERE tugas_id = '$tid'");
                mysqli_query($koneksi, "DELETE FROM tugas WHERE id = '$tid'");

                foreach ($affected_students as $sid) {
                    sinkronNilaiRapor($koneksi, $sid, (int)$t_row['mapel_id'], $t_row['semester']);
                }

                if ($selected_tugas === $tid) {
                    $selected_tugas = 0;
                }
                $msg_type = "success";
                $msg_text = "Tempat pengumpulan tugas berhasil dihapus.";
            }
        }
    }

    // D. SIMPAN NILAI TUGAS PER SISWA (OTOMATIS MASUK KE LIHAT_NILAI.PHP)
    if (isset($_POST['simpan_nilai_tugas_single'])) {
        $tid       = (int) ($_POST['tugas_id'] ?? 0);
        $sid       = (int) ($_POST['siswa_id'] ?? 0);
        $raw_nilai = trim($_POST['nilai_tugas'] ?? '');
        $catatan_g = trim($_POST['catatan_guru'] ?? '');

        $q_t = mysqli_query($koneksi, "SELECT id, mapel_id, semester FROM tugas WHERE id = '$tid' LIMIT 1");
        if ($q_t && mysqli_num_rows($q_t) > 0) {
            $t_row = mysqli_fetch_assoc($q_t);
            if (!guruBolehAksesMapel($koneksi, $role, $guru_id, (int)$t_row['mapel_id'], $has_gmk)) {
                http_response_code(403);
                exit('Kamu tidak memiliki akses untuk menilai tugas ini.');
            }

            $selected_tugas = $tid;
            $cat_esc = mysqli_real_escape_string($koneksi, $catatan_g);

            if ($raw_nilai === '') {
                mysqli_query($koneksi, "
                    UPDATE pengumpulan_tugas
                    SET nilai = NULL, catatan_guru = '$cat_esc', graded_at = NULL
                    WHERE tugas_id = '$tid' AND siswa_id = '$sid'
                ");
            } else {
                $nilai_val = max(0, min(100, (int) $raw_nilai));
                mysqli_query($koneksi, "
                    INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, catatan_siswa, nilai, catatan_guru, graded_at)
                    VALUES ('$tid', '$sid', 'Dinilai langsung oleh guru', '$nilai_val', '$cat_esc', NOW())
                    ON DUPLICATE KEY UPDATE
                        nilai = '$nilai_val',
                        catatan_guru = '$cat_esc',
                        graded_at = NOW()
                ");
                sinkronNilaiRapor($koneksi, $sid, (int)$t_row['mapel_id'], $t_row['semester']);
            }

            $msg_type = "success";
            $msg_text = "Nilai tugas siswa berhasil disimpan & otomatis tersinkron ke rapor (Lihat Nilai)!";
        }
    }

    // E. SIMPAN SEMUA NILAI TUGAS SEKALIGUS (BATCH)
    if (isset($_POST['simpan_nilai_tugas_massal'])) {
        $tid         = (int) ($_POST['tugas_id'] ?? 0);
        $nilai_arr   = $_POST['nilai_tugas'] ?? [];
        $catatan_arr = $_POST['catatan_guru'] ?? [];

        $q_t = mysqli_query($koneksi, "SELECT id, mapel_id, semester FROM tugas WHERE id = '$tid' LIMIT 1");
        if ($q_t && mysqli_num_rows($q_t) > 0) {
            $t_row = mysqli_fetch_assoc($q_t);
            if (!guruBolehAksesMapel($koneksi, $role, $guru_id, (int)$t_row['mapel_id'], $has_gmk)) {
                http_response_code(403);
                exit('Kamu tidak memiliki akses untuk menilai tugas ini.');
            }

            $selected_tugas = $tid;
            $count_saved = 0;

            foreach ($nilai_arr as $sid_key => $raw_val) {
                $sid     = (int) $sid_key;
                $val_str = trim((string) $raw_val);
                $cat_str = trim((string) ($catatan_arr[$sid_key] ?? ''));
                $cat_esc = mysqli_real_escape_string($koneksi, $cat_str);

                if ($val_str === '') {
                    continue;
                }

                $nilai_val = max(0, min(100, (int) $val_str));
                mysqli_query($koneksi, "
                    INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, catatan_siswa, nilai, catatan_guru, graded_at)
                    VALUES ('$tid', '$sid', 'Dinilai langsung oleh guru', '$nilai_val', '$cat_esc', NOW())
                    ON DUPLICATE KEY UPDATE
                        nilai = '$nilai_val',
                        catatan_guru = '$cat_esc',
                        graded_at = NOW()
                ");
                sinkronNilaiRapor($koneksi, $sid, (int)$t_row['mapel_id'], $t_row['semester']);
                $count_saved++;
            }

            $msg_type = "success";
            $msg_text = "$count_saved nilai tugas siswa berhasil disimpan & otomatis masuk ke halaman Lihat Nilai siswa!";
        }
    }

    // F. PROSES INSERT / UPDATE NILAI RAPOR MANUAL (REKAP AKHIR)
    if (isset($_POST['simpan_nilai'])) {
        $siswa_id           = (int) $_POST['siswa_id'];
        $mapel_id           = (int) $_POST['mapel_id'];
        $semester           = mysqli_real_escape_string($koneksi, $_POST['semester']);
        $nilai_kkm          = max(0, min(100, (int) $_POST['nilai_kkm']));
        $nilai_pengetahuan  = max(0, min(100, (int) $_POST['nilai_pengetahuan']));
        $nilai_keterampilan = max(0, min(100, (int) $_POST['nilai_keterampilan']));
        $keterangan         = mysqli_real_escape_string($koneksi, $_POST['keterangan']);

        if (!guruBolehAksesMapel($koneksi, $role, $guru_id, $mapel_id, $has_gmk)) {
            http_response_code(403);
            exit('Kamu tidak mengajar mapel ini.');
        }

        $q_cek = mysqli_query($koneksi, "SELECT id FROM nilai_rapor WHERE siswa_id = '$siswa_id' AND mapel_id = '$mapel_id' AND semester = '$semester'");

        if ($q_cek && mysqli_num_rows($q_cek) > 0) {
            $query_save = "UPDATE nilai_rapor SET 
                           nilai_kkm = '$nilai_kkm', 
                           nilai_pengetahuan = '$nilai_pengetahuan', 
                           nilai_keterampilan = '$nilai_keterampilan', 
                           keterangan = '$keterangan' 
                           WHERE siswa_id = '$siswa_id' AND mapel_id = '$mapel_id' AND semester = '$semester'";
        } else {
            $query_save = "INSERT INTO nilai_rapor (siswa_id, mapel_id, semester, nilai_kkm, nilai_pengetahuan, nilai_keterampilan, keterangan) 
                           VALUES ('$siswa_id', '$mapel_id', '$semester', '$nilai_kkm', '$nilai_pengetahuan', '$nilai_keterampilan', '$keterangan')";
        }

        if (mysqli_query($koneksi, $query_save)) {
            $msg_type = "success";
            $msg_text = "Nilai rapor akhir siswa berhasil disimpan!";
        } else {
            $msg_type = "error";
            $msg_text = "Gagal menyimpan nilai: " . mysqli_error($koneksi);
        }
    }
}

// 6. AMBIL DAFTAR MAPEL & KELAS YANG DIAJAR GURU INI
if ($role === 'admin') {
    $q_mapel_guru = mysqli_query($koneksi, "
        SELECT m.id AS mapel_id, m.nama_mapel, s.kelas 
        FROM mapel m 
        CROSS JOIN (SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas <> '') s
        ORDER BY m.nama_mapel ASC, s.kelas ASC
    ");
} else {
    $sql_opsi = $has_gmk
        ? "SELECT m.id AS mapel_id, m.nama_mapel, gmk.kelas 
           FROM guru_mapel_kelas gmk
           JOIN mapel m ON gmk.mapel_id = m.id
           WHERE gmk.guru_id = '$guru_id'
           UNION
           SELECT m.id AS mapel_id, m.nama_mapel, s.kelas
           FROM mapel m
           CROSS JOIN (SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas <> '') s
           WHERE m.guru_id = '$guru_id'
           ORDER BY nama_mapel ASC, kelas ASC"
        : "SELECT m.id AS mapel_id, m.nama_mapel, s.kelas
           FROM mapel m
           CROSS JOIN (SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas <> '') s
           WHERE m.guru_id = '$guru_id'
           ORDER BY nama_mapel ASC, kelas ASC";
    $q_mapel_guru = mysqli_query($koneksi, $sql_opsi);
}

$opsi_ajar = [];
if ($q_mapel_guru) {
    while ($row = mysqli_fetch_assoc($q_mapel_guru)) {
        $opsi_ajar[] = $row;
    }
}

if (($filter_mapel_id <= 0 || $filter_kelas === '') && !empty($opsi_ajar)) {
    $filter_mapel_id = (int) $opsi_ajar[0]['mapel_id'];
    $filter_kelas    = $opsi_ajar[0]['kelas'];
}

$active_mapel_nama = 'Mata Pelajaran';
foreach ($opsi_ajar as $oa) {
    if ((int)$oa['mapel_id'] === $filter_mapel_id) {
        $active_mapel_nama = $oa['nama_mapel'];
        break;
    }
}

// 7. AMBIL DAFTAR TEMPAT PENGUMPULAN TUGAS UNTUK MAPEL + KELAS + SEMESTER AKTIF
$list_tugas = [];
if ($filter_mapel_id > 0 && $filter_kelas !== '') {
    $kelas_esc = mysqli_real_escape_string($koneksi, $filter_kelas);
    $sem_esc   = mysqli_real_escape_string($koneksi, $filter_semester);

    $q_tugas = mysqli_query($koneksi, "
        SELECT t.*,
               (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id = t.id AND (pt.file_tugas IS NOT NULL OR pt.link_tugas IS NOT NULL OR (pt.jawaban_teks IS NOT NULL AND pt.jawaban_teks <> ''))) AS jml_kumpul,
               (SELECT COUNT(*) FROM pengumpulan_tugas pt WHERE pt.tugas_id = t.id AND pt.nilai IS NOT NULL) AS jml_dinilai
        FROM tugas t
        WHERE t.mapel_id = '$filter_mapel_id'
          AND t.kelas = '$kelas_esc'
          AND t.semester = '$sem_esc'
        ORDER BY t.created_at DESC, t.id DESC
    ");

    if ($q_tugas) {
        while ($tr = mysqli_fetch_assoc($q_tugas)) {
            $list_tugas[] = $tr;
        }
    }
}

// Pilih otomatis tugas pertama jika belum ada yang dipilih
$active_tugas = null;
if (!empty($list_tugas)) {
    foreach ($list_tugas as $lt) {
        if ((int)$lt['id'] === $selected_tugas) {
            $active_tugas = $lt;
            break;
        }
    }
    if ($active_tugas === null) {
        $active_tugas   = $list_tugas[0];
        $selected_tugas = (int) $active_tugas['id'];
    }
}

// 8. FETCH DAFTAR SISWA & STATUS PENGUMPULAN TUGAS AKTIF + REKAP RAPOR
$list_siswa_tugas = [];
$list_siswa_nilai = [];
$stat_kumpul      = 0;
$stat_dinilai     = 0;
$stat_belum_nilai = 0;

if ($filter_kelas !== '') {
    $kelas_esc = mysqli_real_escape_string($koneksi, $filter_kelas);
    $sem_esc   = mysqli_real_escape_string($koneksi, $filter_semester);

    // Data submisi siswa untuk slot tugas yang dipilih
    if ($active_tugas !== null) {
        $tid_active = (int) $active_tugas['id'];
        $q_sub = mysqli_query($koneksi, "
            SELECT s.id AS siswa_id, s.nis, s.nama, s.kelas,
                   pt.id AS pengumpulan_id, pt.file_tugas, pt.nama_file_asli,
                   pt.link_tugas, pt.jawaban_teks, pt.catatan_siswa,
                   pt.submitted_at, pt.nilai, pt.catatan_guru, pt.graded_at
            FROM siswa s
            LEFT JOIN pengumpulan_tugas pt ON s.id = pt.siswa_id AND pt.tugas_id = '$tid_active'
            WHERE s.kelas = '$kelas_esc'
            ORDER BY s.nama ASC
        ");
        if ($q_sub) {
            while ($sr = mysqli_fetch_assoc($q_sub)) {
                $has_submission = !empty($sr['file_tugas']) || !empty($sr['link_tugas']) || trim((string)($sr['jawaban_teks'] ?? '')) !== '';
                $is_graded      = ($sr['nilai'] !== null);

                if ($has_submission) {
                    $stat_kumpul++;
                    if (!$is_graded) {
                        $stat_belum_nilai++;
                    }
                }
                if ($is_graded) {
                    $stat_dinilai++;
                }

                $sr['has_submission'] = $has_submission;
                $sr['is_graded']      = $is_graded;
                $list_siswa_tugas[]   = $sr;
            }
        }
    }

    // Data rekap nilai rapor akhir kelas
    $q_siswa_list = mysqli_query($koneksi, "
        SELECT s.id AS siswa_id, s.nis, s.nama, s.kelas,
               nr.id AS nilai_id, nr.nilai_kkm, nr.nilai_pengetahuan, nr.nilai_keterampilan, nr.keterangan
        FROM siswa s
        LEFT JOIN nilai_rapor nr ON s.id = nr.siswa_id AND nr.mapel_id = '$filter_mapel_id' AND nr.semester = '$sem_esc'
        WHERE s.kelas = '$kelas_esc'
        ORDER BY s.nama ASC
    ");
    if ($q_siswa_list) {
        while ($r = mysqli_fetch_assoc($q_siswa_list)) {
            $list_siswa_nilai[] = $r;
        }
    }
}

$total_siswa_kelas = count($list_siswa_nilai);

$label_tipe = [
    'semua' => 'File / Link / Teks',
    'file'  => 'Upload File',
    'link'  => 'Link / Tautan',
    'teks'  => 'Jawaban Teks'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tugas & Nilai Siswa - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    <style>
        .page-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            animation: fadeInUp 0.35s ease-out forwards;
        }

        /* Hero Krem Selaras Dashboard & Rapor */
        .gn-hero {
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
        .gn-eyebrow {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .gn-pill {
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
            text-decoration: none;
        }
        .gn-hero-title {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #161616;
            margin: 0 0 6px;
        }
        .gn-hero-title span {
            color: #6f6f68;
            font-weight: 500;
        }
        .gn-hero-sub {
            margin: 0;
            font-size: 13.5px;
            color: #575750;
            max-width: 640px;
            line-height: 1.5;
        }
        .gn-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn-dark-pill {
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
        .btn-dark-pill:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .btn-light-pill {
            background: #ffffff;
            color: #161616;
            border: 1px solid rgba(22, 22, 22, 0.14);
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
        .btn-light-pill:hover {
            background: #f6f6f2;
        }

        /* Card Umum */
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

        /* Filter Grid */
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 16px;
            align-items: end;
        }
        @media (max-width: 768px) {
            .filter-grid { grid-template-columns: 1fr; }
        }
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
            padding: 10px 12px;
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

        /* Grid Slot Tempat Pengumpulan Tugas */
        .tugas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 14px;
        }
        .tugas-slot {
            border-radius: 16px;
            padding: 16px 18px;
            background: #f6f6f2;
            border: 1.5px solid rgba(22, 22, 22, 0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 12px;
            transition: all 0.18s ease;
        }
        .tugas-slot:hover {
            border-color: rgba(22, 22, 22, 0.25);
            background: #f1f1eb;
        }
        .tugas-slot.is-selected {
            background: #ffffff;
            border-color: #161616;
            box-shadow: 0 6px 18px rgba(22, 22, 22, 0.08);
        }
        .tugas-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 8px;
        }
        .t-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 999px;
        }
        .t-badge.kat-keterampilan { background: #161616; color: #ffffff; }
        .t-badge.kat-pengetahuan { background: #e2e8f0; color: #1e293b; }
        .t-badge.tipe { background: #ffffff; color: #475569; border: 1px solid #cbd5e1; }
        .t-badge.st-buka { background: #dcfce7; color: #15803d; }
        .t-badge.st-tutup { background: #fee2e2; color: #b91c1c; }

        .tugas-title {
            font-size: 15px;
            font-weight: 700;
            color: #161616;
            margin: 0 0 4px;
        }
        .tugas-desc {
            font-size: 12.5px;
            color: #6f6f68;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .tugas-meta {
            font-size: 11.5px;
            color: #575750;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding-top: 8px;
            border-top: 1px dashed rgba(22, 22, 22, 0.12);
        }
        .tugas-progress-bar {
            width: 100%;
            height: 6px;
            background: #e2e2da;
            border-radius: 999px;
            overflow: hidden;
            margin-top: 4px;
        }
        .tugas-progress-fill {
            height: 100%;
            background: #161616;
            border-radius: 999px;
        }
        .tugas-slot-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 4px;
        }
        .btn-slot-open {
            flex: 1;
            text-align: center;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            background: #161616;
            color: #ffffff;
        }
        .tugas-slot:not(.is-selected) .btn-slot-open {
            background: #ffffff;
            color: #161616;
            border: 1px solid rgba(22, 22, 22, 0.16);
        }
        .tugas-slot:not(.is-selected) .btn-slot-open:hover {
            background: #161616;
            color: #ffffff;
        }
        .btn-icon-sm {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            border: 1px solid rgba(22, 22, 22, 0.12);
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .btn-icon-sm:hover { background: #f1f5f9; color: #161616; }
        .btn-icon-sm.danger:hover { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }

        /* Stat Strip & Filter Tab Submisi */
        .sub-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            padding: 12px 14px;
            background: #f6f6f2;
            border-radius: 12px;
        }
        .filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .chip-btn {
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid rgba(22, 22, 22, 0.12);
            background: #ffffff;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .chip-btn.active {
            background: #161616;
            color: #ffffff;
            border-color: #161616;
        }

        /* Tabel Penilaian */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }
        .data-table th {
            background: #f8fafc;
            color: #475569;
            padding: 12px 12px;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 700;
            white-space: nowrap;
        }
        .data-table td {
            padding: 12px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .data-table tr:hover td {
            background: #fafaf7;
        }
        .data-table input[type="number"] {
            width: 72px;
            padding: 7px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            text-align: center;
            font-weight: 700;
            font-size: 13px;
        }
        .data-table input[type="number"]:focus {
            border-color: #161616;
            outline: none;
        }
        .btn-save-sm {
            background: #161616;
            color: #ffffff;
            border: none;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .btn-save-sm:hover { opacity: 0.88; }
        .btn-save-green {
            background: #15803d;
            color: #ffffff;
            border: none;
            padding: 9px 16px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .btn-save-green:hover { background: #166534; }

        /* Badge Status & Lampiran Siswa */
        .st-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }
        .st-pill.ok { background: #dcfce7; color: #15803d; }
        .st-pill.late { background: #fef3c7; color: #b45309; }
        .st-pill.wait { background: #f1f5f9; color: #64748b; }
        .st-pill.graded { background: #161616; color: #ffffff; }

        .sub-artifact-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .artifact-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 8px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .artifact-btn:hover {
            background: #e2e8f0;
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
        .modal-backdrop.is-open {
            display: flex;
        }
        .modal-card {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px 26px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.2);
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
            font-size: 15px;
        }
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 540px) {
            .modal-grid { grid-template-columns: 1fr; }
        }
    </style>
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
                    <div class="user-avatar-initial"><?= strtoupper(substr($guru_nama, 0, 1)); ?></div>
                <?php endif; ?>
                <div class="user-meta-mini">
                    <span class="user-name"><?= htmlspecialchars($guru_nama); ?></span>
                    <span class="user-role-badge"><?= htmlspecialchars($role); ?></span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="dashboard"></i> <span class="menu-label">Dashboard</span></a></li>

                <?php if ($role === 'admin'): ?>
                <li class="has-submenu">
                    <a href="javascript:void(0)"><i class="master"></i> <span class="menu-label">Master Data</span></a>
                    <ul class="submenu">
                        <li><a href="daftar_user.php"><span class="dot user"></span> <span class="menu-label">Kelola User</span></a></li>
                        <li><a href="daftar_siswa.php"><span class="dot siswa"></span> <span class="menu-label">Kelola Siswa</span></a></li>
                        <li><a href="daftar_guru.php"><span class="dot guru"></span> <span class="menu-label">Kelola Guru</span></a></li>
                        <li><a href="daftar_mapel.php"><span class="dot mapel"></span> <span class="menu-label">Mata Pelajaran</span></a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($role === 'guru' || $role === 'admin'): ?>
                <li><a href="daftar_nilai.php" class="active"><i class="fa-solid fa-pen-to-square"></i> <span class="menu-label">Kelola Nilai</span></a></li>
                <?php endif; ?>

                <?php if ($role === 'siswa'): ?>
                <li><a href="lihat_nilai.php"><i class="report"></i> <span class="menu-label">Report Grades</span></a></li>
                <?php endif; ?>

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
                    <h1>Kelola Tugas &amp; Nilai Siswa</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <button type="button" class="btn-dark-pill" onclick="openModal('modalBuatTugas')">
                        <i class="fa-solid fa-folder-plus"></i> Buat Tempat Tugas
                    </button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><i class="fa-solid fa-user-gear"></i></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="page-container">

                <?php if (!empty($msg_text)): ?>
                    <div class="alert-box <?= ($msg_type === 'success') ? 'alert-success' : 'alert-error'; ?>">
                        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                        <span><?= htmlspecialchars($msg_text); ?></span>
                    </div>
                <?php endif; ?>

                <!-- HERO BANNER -->
                <section class="gn-hero">
                    <div>
                        <div class="gn-eyebrow">
                            <span class="gn-pill"><i class="fa-solid fa-book"></i> <?= htmlspecialchars($active_mapel_nama); ?></span>
                            <span class="gn-pill"><i class="fa-solid fa-users"></i> Kelas <?= htmlspecialchars($filter_kelas ?: '-'); ?></span>
                            <span class="gn-pill">Semester <?= htmlspecialchars($filter_semester); ?></span>
                        </div>
                        <h2 class="gn-hero-title">Pengumpulan Tugas <span>&amp; Penilaian</span></h2>
                        <p class="gn-hero-sub">
                            Buat wadah pengumpulan tugas untuk siswa (file, link, atau teks). Tugas yang dikirim siswa dari halaman <strong>Lihat Nilai</strong> otomatis masuk ke sini dan nilainya langsung tersinkron ke rapor siswa.
                        </p>
                    </div>
                    <div class="gn-hero-actions">
                        <button type="button" class="btn-dark-pill" onclick="openModal('modalBuatTugas')">
                            <i class="fa-solid fa-plus"></i> Buat Tempat Pengumpulan Tugas
                        </button>
                        <a href="#rekap-rapor" class="btn-light-pill">
                            <i class="fa-solid fa-table-list"></i> Rekap Nilai Rapor
                        </a>
                    </div>
                </section>

                <!-- CARD 1: FILTER MAPEL & KELAS -->
                <div class="card-box">
                    <form method="GET" action="daftar_nilai.php" class="filter-grid">
                        <div class="form-group">
                            <label>Mata Pelajaran &amp; Kelas Ajar</label>
                            <select name="mapel_kelas" class="form-control" onchange="
                                let val = this.value.split('|');
                                document.getElementById('mapel_id_input').value = val[0];
                                document.getElementById('kelas_input').value = val[1];
                            ">
                                <?php if (!empty($opsi_ajar)): ?>
                                    <?php foreach ($opsi_ajar as $opt):
                                        $selected = ((int)$opt['mapel_id'] === $filter_mapel_id && $opt['kelas'] === $filter_kelas) ? 'selected' : '';
                                    ?>
                                        <option value="<?= (int)$opt['mapel_id'] . '|' . htmlspecialchars($opt['kelas']); ?>" <?= $selected; ?>>
                                            <?= htmlspecialchars($opt['nama_mapel']); ?> — Kelas <?= htmlspecialchars($opt['kelas']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">Belum ada jadwal / mapel mengajar</option>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="mapel_id" id="mapel_id_input" value="<?= (int)$filter_mapel_id; ?>">
                            <input type="hidden" name="kelas" id="kelas_input" value="<?= htmlspecialchars($filter_kelas); ?>">
                        </div>

                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" class="form-control">
                                <option value="1" <?= ($filter_semester === '1') ? 'selected' : ''; ?>>Semester 1 (Ganjil)</option>
                                <option value="2" <?= ($filter_semester === '2') ? 'selected' : ''; ?>>Semester 2 (Genap)</option>
                            </select>
                        </div>

                        <div>
                            <button type="submit" class="btn-dark-pill" style="border-radius: 10px; padding: 10px 18px;">
                                <i class="fa-solid fa-filter"></i> Tampilkan
                            </button>
                        </div>
                    </form>
                </div>

                <!-- CARD 2: DAFTAR TEMPAT PENGUMPULAN TUGAS -->
                <div class="card-box">
                    <div class="card-head">
                        <div>
                            <h3>Tempat Pengumpulan Tugas (<?= count($list_tugas); ?> Slot)</h3>
                            <p>Pilih salah satu wadah tugas di bawah untuk memeriksa hasil kiriman siswa &amp; memberikan nilai.</p>
                        </div>
                        <button type="button" class="btn-light-pill" onclick="openModal('modalBuatTugas')">
                            <i class="fa-solid fa-folder-plus"></i> Tambah Wadah Tugas
                        </button>
                    </div>

                    <?php if (!empty($list_tugas)): ?>
                        <div class="tugas-grid">
                            <?php foreach ($list_tugas as $tg):
                                $is_sel   = ((int)$tg['id'] === $selected_tugas);
                                $jml_k    = (int) $tg['jml_kumpul'];
                                $jml_n    = (int) $tg['jml_dinilai'];
                                $pct_k    = $total_siswa_kelas > 0 ? min(100, round(($jml_k / $total_siswa_kelas) * 100)) : 0;
                                $dl_text  = !empty($tg['deadline']) ? date('d M Y, H:i', strtotime($tg['deadline'])) : 'Tanpa batas waktu';
                            ?>
                                <div class="tugas-slot <?= $is_sel ? 'is-selected' : ''; ?>">
                                    <div>
                                        <div class="tugas-badges">
                                            <span class="t-badge kat-<?= htmlspecialchars($tg['kategori']); ?>">
                                                <?= ucfirst(htmlspecialchars($tg['kategori'])); ?>
                                            </span>
                                            <span class="t-badge tipe">
                                                <?= htmlspecialchars($label_tipe[$tg['tipe_pengumpulan']] ?? 'Semua'); ?>
                                            </span>
                                            <span class="t-badge st-<?= htmlspecialchars($tg['status']); ?>">
                                                <?= $tg['status'] === 'buka' ? 'Dibuka' : 'Ditutup'; ?>
                                            </span>
                                        </div>

                                        <h4 class="tugas-title"><?= htmlspecialchars($tg['judul']); ?></h4>
                                        <p class="tugas-desc">
                                            <?= !empty($tg['deskripsi']) ? htmlspecialchars($tg['deskripsi']) : 'Tidak ada instruksi tambahan.'; ?>
                                        </p>
                                    </div>

                                    <div>
                                        <div class="tugas-meta">
                                            <span><i class="fa-regular fa-clock"></i> Deadline: <strong><?= htmlspecialchars($dl_text); ?></strong></span>
                                            <span>
                                                <i class="fa-solid fa-inbox"></i> Terkumpul: <strong><?= $jml_k; ?>/<?= $total_siswa_kelas; ?></strong> siswa
                                                &middot; Dinilai: <strong><?= $jml_n; ?></strong>
                                            </span>
                                            <?php if (!empty($tg['file_lampiran'])): ?>
                                                <span>
                                                    <i class="fa-solid fa-paperclip"></i>
                                                    <a href="uploads/tugas_soal/<?= urlencode($tg['file_lampiran']); ?>" target="_blank" style="color:#161616; font-weight:600;">
                                                        Lihat Lampiran Soal
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                            <div class="tugas-progress-bar">
                                                <div class="tugas-progress-fill" style="width: <?= $pct_k; ?>%;"></div>
                                            </div>
                                        </div>

                                        <div class="tugas-slot-actions" style="margin-top: 10px;">
                                            <a href="daftar_nilai.php?mapel_id=<?= (int)$filter_mapel_id; ?>&kelas=<?= urlencode($filter_kelas); ?>&semester=<?= urlencode($filter_semester); ?>&tugas_id=<?= (int)$tg['id']; ?>#panel-penilaian"
                                               class="btn-slot-open">
                                                <?= $is_sel ? 'Sedang Dibuka' : 'Periksa & Nilai (' . $jml_k . ')'; ?>
                                            </a>

                                            <form method="POST" action="daftar_nilai.php?mapel_id=<?= (int)$filter_mapel_id; ?>&kelas=<?= urlencode($filter_kelas); ?>&semester=<?= urlencode($filter_semester); ?>&tugas_id=<?= (int)$tg['id']; ?>" style="display:inline;">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="tugas_id" value="<?= (int)$tg['id']; ?>">
                                                <button type="submit" name="toggle_status_tugas" class="btn-icon-sm" title="<?= $tg['status'] === 'buka' ? 'Tutup Pengumpulan' : 'Buka Pengumpulan'; ?>">
                                                    <i class="fa-solid <?= $tg['status'] === 'buka' ? 'fa-lock-open' : 'fa-lock'; ?>"></i>
                                                </button>
                                            </form>

                                            <form method="POST" action="daftar_nilai.php?mapel_id=<?= (int)$filter_mapel_id; ?>&kelas=<?= urlencode($filter_kelas); ?>&semester=<?= urlencode($filter_semester); ?>"
                                                  onsubmit="return confirm('Hapus tempat pengumpulan tugas ini beserta seluruh file kiriman siswa?');"
                                                  style="display:inline;">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="tugas_id" value="<?= (int)$tg['id']; ?>">
                                                <button type="submit" name="hapus_tugas" class="btn-icon-sm danger" title="Hapus Tempat Tugas">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 32px 16px; background: #f8fafc; border-radius: 14px; border: 1px dashed #cbd5e1;">
                            <p style="margin: 0 0 12px; color: #64748b; font-size: 13.5px;">
                                Belum ada tempat pengumpulan tugas untuk <strong><?= htmlspecialchars($active_mapel_nama); ?></strong> kelas <strong><?= htmlspecialchars($filter_kelas ?: '-'); ?></strong>.
                            </p>
                            <button type="button" class="btn-dark-pill" onclick="openModal('modalBuatTugas')">
                                <i class="fa-solid fa-plus"></i> Buat Tempat Tugas Pertama
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- CARD 3: DAFTAR PENGUMPULAN & PENILAIAN SISWA UNTUK TUGAS TERPILIH -->
                <?php if ($active_tugas !== null): ?>
                <div class="card-box" id="panel-penilaian">
                    <div class="card-head">
                        <div>
                            <h3>Penilaian Tugas: <?= htmlspecialchars($active_tugas['judul']); ?></h3>
                            <p>
                                Kategori Rapor: <strong><?= ucfirst(htmlspecialchars($active_tugas['kategori'])); ?></strong>
                                &middot; Terkumpul <strong><?= $stat_kumpul; ?>/<?= $total_siswa_kelas; ?></strong>
                                &middot; Perlu Dinilai <strong><?= $stat_belum_nilai; ?></strong>
                                &middot; Sudah Dinilai <strong><?= $stat_dinilai; ?></strong>
                            </p>
                        </div>
                        <button type="submit" form="formBatchNilai" name="simpan_nilai_tugas_massal" class="btn-save-green">
                            <i class="fa-solid fa-check-double"></i> Simpan Semua Nilai
                        </button>
                    </div>

                    <div class="sub-toolbar">
                        <div class="filter-chips" id="subFilterChips">
                            <button type="button" class="chip-btn active" data-filter="all">Semua (<?= count($list_siswa_tugas); ?>)</button>
                            <button type="button" class="chip-btn" data-filter="submitted">Sudah Kumpul (<?= $stat_kumpul; ?>)</button>
                            <button type="button" class="chip-btn" data-filter="ungraded">Perlu Dinilai (<?= $stat_belum_nilai; ?>)</button>
                            <button type="button" class="chip-btn" data-filter="graded">Sudah Dinilai (<?= $stat_dinilai; ?>)</button>
                            <button type="button" class="chip-btn" data-filter="missing">Belum Kumpul (<?= max(0, count($list_siswa_tugas) - $stat_kumpul); ?>)</button>
                        </div>
                        <input type="text" id="searchSiswaTugas" class="form-control" placeholder="Cari nama / NIS siswa..." style="max-width: 240px; padding: 7px 12px;">
                    </div>

                    <form id="formBatchNilai" method="POST" action="daftar_nilai.php?mapel_id=<?= (int)$filter_mapel_id; ?>&kelas=<?= urlencode($filter_kelas); ?>&semester=<?= urlencode($filter_semester); ?>&tugas_id=<?= (int)$active_tugas['id']; ?>#panel-penilaian">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="tugas_id" value="<?= (int)$active_tugas['id']; ?>">

                        <div style="overflow-x: auto;">
                            <table class="data-table" id="tabelSubmisiTugas">
                                <thead>
                                    <tr>
                                        <th style="width: 44px; text-align: center;">No</th>
                                        <th>Siswa</th>
                                        <th>Status</th>
                                        <th>Hasil Tugas Siswa</th>
                                        <th>Waktu Kumpul</th>
                                        <th style="text-align: center;">Nilai (0-100)</th>
                                        <th>Catatan / Feedback Guru</th>
                                        <th style="text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($list_siswa_tugas)): ?>
                                        <?php foreach ($list_siswa_tugas as $idx => $st):
                                            $is_late = false;
                                            if ($st['has_submission'] && !empty($active_tugas['deadline']) && !empty($st['submitted_at'])) {
                                                $is_late = (strtotime($st['submitted_at']) > strtotime($active_tugas['deadline']));
                                            }
                                            $row_state = !$st['has_submission']
                                                ? 'missing'
                                                : ($st['is_graded'] ? 'graded' : 'ungraded');
                                        ?>
                                            <tr class="sub-row"
                                                data-submitted="<?= $st['has_submission'] ? '1' : '0'; ?>"
                                                data-graded="<?= $st['is_graded'] ? '1' : '0'; ?>"
                                                data-search="<?= strtolower(htmlspecialchars($st['nama'] . ' ' . $st['nis'])); ?>">
                                                <td style="text-align: center; font-weight: 600;"><?= $idx + 1; ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($st['nama']); ?></strong><br>
                                                    <small style="color: #64748b;">NIS: <?= htmlspecialchars($st['nis']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($st['is_graded']): ?>
                                                        <span class="st-pill graded"><i class="fa-solid fa-check"></i> Dinilai (<?= (int)$st['nilai']; ?>)</span>
                                                    <?php elseif ($st['has_submission']): ?>
                                                        <?php if ($is_late): ?>
                                                            <span class="st-pill late"><i class="fa-solid fa-clock"></i> Terlambat</span>
                                                        <?php else: ?>
                                                            <span class="st-pill ok"><i class="fa-solid fa-paper-plane"></i> Terkumpul</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="st-pill wait">Belum Kumpul</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($st['has_submission']): ?>
                                                        <div class="sub-artifact-list">
                                                            <?php if (!empty($st['file_tugas'])): ?>
                                                                <a href="uploads/pengumpulan_tugas/<?= urlencode($st['file_tugas']); ?>"
                                                                   target="_blank"
                                                                   class="artifact-btn"
                                                                   title="<?= htmlspecialchars($st['nama_file_asli'] ?? $st['file_tugas']); ?>">
                                                                    <i class="fa-solid fa-file-arrow-down"></i>
                                                                    <?= htmlspecialchars(mb_strimwidth($st['nama_file_asli'] ?? 'File Tugas', 0, 24, '...')); ?>
                                                                </a>
                                                            <?php endif; ?>

                                                            <?php if (!empty($st['link_tugas'])): ?>
                                                                <a href="<?= htmlspecialchars($st['link_tugas']); ?>"
                                                                   target="_blank"
                                                                   rel="noopener noreferrer"
                                                                   class="artifact-btn">
                                                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Link
                                                                </a>
                                                            <?php endif; ?>

                                                            <?php if (trim((string)($st['jawaban_teks'] ?? '')) !== ''): ?>
                                                                <button type="button"
                                                                        class="artifact-btn"
                                                                        data-nama="<?= htmlspecialchars($st['nama']); ?>"
                                                                        data-teks="<?= htmlspecialchars($st['jawaban_teks']); ?>"
                                                                        onclick="previewJawabanTeks(this)">
                                                                    <i class="fa-solid fa-align-left"></i> Baca Jawaban
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($st['catatan_siswa'])): ?>
                                                            <div style="font-size: 11.5px; color: #64748b; margin-top: 5px;">
                                                                Catatan siswa: <em><?= htmlspecialchars($st['catatan_siswa']); ?></em>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color: #94a3b8; font-size: 12px;">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="font-size: 12px; color: #475569; white-space: nowrap;">
                                                    <?= ($st['has_submission'] && !empty($st['submitted_at']))
                                                        ? date('d M Y, H:i', strtotime($st['submitted_at']))
                                                        : '-'; ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input type="number"
                                                           name="nilai_tugas[<?= (int)$st['siswa_id']; ?>]"
                                                           id="nilai_input_<?= (int)$st['siswa_id']; ?>"
                                                           value="<?= $st['nilai'] !== null ? (int)$st['nilai'] : ''; ?>"
                                                           placeholder="0-100"
                                                           min="0"
                                                           max="100">
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           name="catatan_guru[<?= (int)$st['siswa_id']; ?>]"
                                                           id="catatan_input_<?= (int)$st['siswa_id']; ?>"
                                                           class="form-control"
                                                           value="<?= htmlspecialchars($st['catatan_guru'] ?? ''); ?>"
                                                           placeholder="Catatan untuk siswa..."
                                                           style="padding: 6px 10px; font-size: 12px; min-width: 170px;">
                                                </td>
                                                <td style="text-align: center;">
                                                    <button type="button"
                                                            class="btn-save-sm"
                                                            onclick="submitSingleGrade(<?= (int)$active_tugas['id']; ?>, <?= (int)$st['siswa_id']; ?>)">
                                                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 24px; color: #64748b;">
                                                Tidak ada siswa di kelas ini.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- CARD 4: REKAP NILAI RAPOR AKHIR (OTOMATIS TERSINKRONISASI KE LIHAT_NILAI.PHP) -->
                <div class="card-box" id="rekap-rapor">
                    <div class="card-head">
                        <div>
                            <h3>Rekap Nilai Rapor: <?= htmlspecialchars($active_mapel_nama); ?> — Kelas <?= htmlspecialchars($filter_kelas ?: '-'); ?> (Semester <?= htmlspecialchars($filter_semester); ?>)</h3>
                            <p>Nilai Pengetahuan &amp; Keterampilan otomatis dihitung dari rata-rata tugas yang telah dinilai, dan langsung tampil di halaman <strong>Lihat Nilai</strong> siswa.</p>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">No</th>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th style="text-align: center;">KKM</th>
                                    <th style="text-align: center;">Pengetahuan</th>
                                    <th style="text-align: center;">Keterampilan</th>
                                    <th style="text-align: center;">Rata-rata</th>
                                    <th>Keterangan Rapor</th>
                                    <th style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($list_siswa_nilai)): ?>
                                    <?php foreach ($list_siswa_nilai as $idx => $s):
                                        $p    = (int) ($s['nilai_pengetahuan'] ?? 0);
                                        $k    = (int) ($s['nilai_keterampilan'] ?? 0);
                                        $kkm  = (int) ($s['nilai_kkm'] ?? 75);
                                        $rata = round(($p + $k) / 2, 1);
                                    ?>
                                        <tr>
                                            <form method="POST" action="daftar_nilai.php?mapel_id=<?= (int)$filter_mapel_id; ?>&kelas=<?= urlencode($filter_kelas); ?>&semester=<?= urlencode($filter_semester); ?>#rekap-rapor">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="siswa_id" value="<?= (int)$s['siswa_id']; ?>">
                                                <input type="hidden" name="mapel_id" value="<?= (int)$filter_mapel_id; ?>">
                                                <input type="hidden" name="semester" value="<?= htmlspecialchars($filter_semester); ?>">

                                                <td style="text-align: center; font-weight: 600;"><?= $idx + 1; ?></td>
                                                <td><?= htmlspecialchars($s['nis']); ?></td>
                                                <td><strong><?= htmlspecialchars($s['nama']); ?></strong></td>
                                                <td style="text-align: center;">
                                                    <input type="number" name="nilai_kkm" value="<?= $kkm; ?>" required min="0" max="100">
                                                </td>
                                                <td style="text-align: center;">
                                                    <input type="number" name="nilai_pengetahuan" value="<?= $p; ?>" required min="0" max="100">
                                                </td>
                                                <td style="text-align: center;">
                                                    <input type="number" name="nilai_keterampilan" value="<?= $k; ?>" required min="0" max="100">
                                                </td>
                                                <td style="text-align: center; font-weight: 700; color: <?= $rata >= $kkm ? '#15803d' : '#b91c1c'; ?>;">
                                                    <?= $rata; ?>
                                                </td>
                                                <td>
                                                    <input type="text" name="keterangan" class="form-control" value="<?= htmlspecialchars($s['keterangan'] ?? 'Baik'); ?>" style="padding: 6px 10px; font-size: 12px;">
                                                </td>
                                                <td style="text-align: center;">
                                                    <button type="submit" name="simpan_nilai" class="btn-save-sm">
                                                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                                                    </button>
                                                </td>
                                            </form>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 24px; color: #64748b;">
                                            Pilih Mata Pelajaran dan Kelas Ajar untuk menampilkan daftar siswa.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- HIDDEN FORM UNTUK SIMPAN NILAI PER BARIS -->
    <form id="singleGradeForm" method="POST" action="daftar_nilai.php?mapel_id=<?= (int)$filter_mapel_id; ?>&kelas=<?= urlencode($filter_kelas); ?>&semester=<?= urlencode($filter_semester); ?>&tugas_id=<?= (int)$selected_tugas; ?>#panel-penilaian" style="display:none;">
        <?= csrf_field(); ?>
        <input type="hidden" name="simpan_nilai_tugas_single" value="1">
        <input type="hidden" name="tugas_id" id="sg_tugas_id">
        <input type="hidden" name="siswa_id" id="sg_siswa_id">
        <input type="hidden" name="nilai_tugas" id="sg_nilai_tugas">
        <input type="hidden" name="catatan_guru" id="sg_catatan_guru">
    </form>

    <!-- MODAL BUAT TEMPAT PENGUMPULAN TUGAS -->
    <div class="modal-backdrop" id="modalBuatTugas">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-folder-plus"></i> Buat Tempat Pengumpulan Tugas</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalBuatTugas')">&times;</button>
            </div>

            <form method="POST" action="daftar_nilai.php?mapel_id=<?= (int)$filter_mapel_id; ?>&kelas=<?= urlencode($filter_kelas); ?>&semester=<?= urlencode($filter_semester); ?>" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 14px;">
                <?= csrf_field(); ?>

                <div class="form-group">
                    <label>Judul Tugas *</label>
                    <input type="text" name="judul" class="form-control" placeholder="Contoh: Tugas 1 - Membuat CRUD PHP & MySQL" required>
                </div>

                <div class="modal-grid">
                    <div class="form-group">
                        <label>Mata Pelajaran &amp; Kelas *</label>
                        <select class="form-control" onchange="
                            let p = this.value.split('|');
                            document.getElementById('modal_mapel_id').value = p[0];
                            document.getElementById('modal_kelas').value = p[1];
                        ">
                            <?php foreach ($opsi_ajar as $opt):
                                $sel = ((int)$opt['mapel_id'] === $filter_mapel_id && $opt['kelas'] === $filter_kelas) ? 'selected' : '';
                            ?>
                                <option value="<?= (int)$opt['mapel_id'] . '|' . htmlspecialchars($opt['kelas']); ?>" <?= $sel; ?>>
                                    <?= htmlspecialchars($opt['nama_mapel']); ?> (<?= htmlspecialchars($opt['kelas']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="mapel_id" id="modal_mapel_id" value="<?= (int)$filter_mapel_id; ?>">
                        <input type="hidden" name="kelas" id="modal_kelas" value="<?= htmlspecialchars($filter_kelas); ?>">
                    </div>

                    <div class="form-group">
                        <label>Semester *</label>
                        <select name="semester" class="form-control">
                            <option value="1" <?= ($filter_semester === '1') ? 'selected' : ''; ?>>Semester 1 (Ganjil)</option>
                            <option value="2" <?= ($filter_semester === '2') ? 'selected' : ''; ?>>Semester 2 (Genap)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-grid">
                    <div class="form-group">
                        <label>Masuk ke Komponen Nilai Rapor *</label>
                        <select name="kategori" class="form-control">
                            <option value="keterampilan">Nilai Keterampilan (Praktik / Proyek)</option>
                            <option value="pengetahuan">Nilai Pengetahuan (Teori / Latihan)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Format Pengumpulan Siswa *</label>
                        <select name="tipe_pengumpulan" class="form-control">
                            <option value="semua">Semua (File, Link, atau Teks)</option>
                            <option value="file">Upload File (PDF/DOCX/ZIP/Gambar)</option>
                            <option value="link">Tautan / Link (GitHub / Google Drive)</option>
                            <option value="teks">Jawaban Teks Langsung</option>
                        </select>
                    </div>
                </div>

                <div class="modal-grid">
                    <div class="form-group">
                        <label>Batas Waktu / Deadline (Opsional)</label>
                        <input type="datetime-local" name="deadline" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Lampiran Soal / Modul Guru (Opsional)</label>
                        <input type="file" name="file_lampiran" class="form-control" style="padding: 7px;">
                    </div>
                </div>

                <div class="form-group">
                    <label>Instruksi / Deskripsi Tugas</label>
                    <textarea name="deskripsi" rows="3" class="form-control" placeholder="Tuliskan detail pengerjaan tugas untuk siswa..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px;">
                    <button type="button" class="btn-light-pill" onclick="closeModal('modalBuatTugas')">Batal</button>
                    <button type="submit" name="buat_tugas" class="btn-dark-pill">
                        <i class="fa-solid fa-check"></i> Buat Tempat Tugas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL PREVIEW JAWABAN TEKS SISWA -->
    <div class="modal-backdrop" id="modalPreviewTeks">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="previewTeksTitle">Jawaban Siswa</h3>
                <button type="button" class="modal-close" onclick="closeModal('modalPreviewTeks')">&times;</button>
            </div>
            <div id="previewTeksBody" style="white-space: pre-wrap; background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 13.5px; line-height: 1.6; color: #161616; max-height: 60vh; overflow-y: auto;"></div>
            <div style="display: flex; justify-content: flex-end; margin-top: 14px;">
                <button type="button" class="btn-dark-pill" onclick="closeModal('modalPreviewTeks')">Tutup</button>
            </div>
        </div>
    </div>

<script>
function openModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('is-open');
}
function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('is-open');
}

// Tutup modal saat klik area gelap di luar kartu
document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) {
            backdrop.classList.remove('is-open');
        }
    });
});

// Simpan nilai satu siswa
function submitSingleGrade(tugasId, siswaId) {
    var nilaiEl   = document.getElementById('nilai_input_' + siswaId);
    var catatanEl = document.getElementById('catatan_input_' + siswaId);
    document.getElementById('sg_tugas_id').value    = tugasId;
    document.getElementById('sg_siswa_id').value    = siswaId;
    document.getElementById('sg_nilai_tugas').value   = nilaiEl ? nilaiEl.value : '';
    document.getElementById('sg_catatan_guru').value  = catatanEl ? catatanEl.value : '';
    document.getElementById('singleGradeForm').submit();
}

// Preview jawaban teks siswa
function previewJawabanTeks(btn) {
    var nama = btn.getAttribute('data-nama') || 'Siswa';
    var teks = btn.getAttribute('data-teks') || '';
    document.getElementById('previewTeksTitle').textContent = 'Jawaban Teks — ' + nama;
    document.getElementById('previewTeksBody').textContent  = teks;
    openModal('modalPreviewTeks');
}

// Filter status & pencarian siswa pada tabel tugas
(function () {
    var chips  = document.querySelectorAll('#subFilterChips .chip-btn');
    var search = document.getElementById('searchSiswaTugas');
    var rows   = document.querySelectorAll('#tabelSubmisiTugas tbody .sub-row');
    var activeFilter = 'all';

    function applyFilter() {
        var q = search ? search.value.trim().toLowerCase() : '';
        rows.forEach(function (row) {
            var submitted = row.getAttribute('data-submitted') === '1';
            var graded    = row.getAttribute('data-graded') === '1';
            var hay       = row.getAttribute('data-search') || '';

            var matchStatus = true;
            if (activeFilter === 'submitted') matchStatus = submitted;
            else if (activeFilter === 'ungraded') matchStatus = submitted && !graded;
            else if (activeFilter === 'graded') matchStatus = graded;
            else if (activeFilter === 'missing') matchStatus = !submitted;

            var matchText = (q === '' || hay.indexOf(q) !== -1);
            row.style.display = (matchStatus && matchText) ? '' : 'none';
        });
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('active'); });
            chip.classList.add('active');
            activeFilter = chip.getAttribute('data-filter');
            applyFilter();
        });
    });

    if (search) {
        search.addEventListener('input', applyFilter);
    }
})();
</script>
</body>
</html>