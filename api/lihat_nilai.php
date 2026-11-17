<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role    = strtolower($_SESSION['role'] ?? '');

// Pastikan tabel tugas & pengumpulan_tugas tersedia
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

$dir_kumpul = __DIR__ . '/uploads/pengumpulan_tugas';
if (!is_dir($dir_kumpul)) {
    @mkdir($dir_kumpul, 0755, true);
}

// 1. AMBIL DATA USER & FOTO
$q_user    = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id'");
$user_data = ($q_user && mysqli_num_rows($q_user) > 0) ? mysqli_fetch_assoc($q_user) : [];

$username     = $user_data['username'] ?? $_SESSION['username'] ?? 'User';
$foto_db      = $user_data['foto'] ?? '';
$gh_connected = !empty($user_data['github_connected']) && $user_data['github_connected'] == 1;
$gh_avatar    = $user_data['github_avatar'] ?? '';

// Hirarki Foto Profil (LOGIKA DIPISAH TOTAL)
$avatar_src = '';
if (!empty($foto_db)) {
    if (strpos($foto_db, 'data:image') === 0 || filter_var($foto_db, FILTER_VALIDATE_URL)) {
        $avatar_src = $foto_db;
    } elseif (file_exists(__DIR__ . '/uploads/avatars/' . $foto_db)) {
        $avatar_src = 'uploads/avatars/' . $foto_db;
    }
}
if (empty($avatar_src) && $gh_connected && !empty($gh_avatar)) {
    $avatar_src = $gh_avatar;
}

// Ambil Profil Siswa
$q_siswa    = mysqli_query($koneksi, "SELECT * FROM siswa WHERE user_id = '$user_id'");
$siswa_data = ($q_siswa && mysqli_num_rows($q_siswa) > 0) ? mysqli_fetch_assoc($q_siswa) : [];

$siswa_id     = (int) ($siswa_data['id'] ?? 0);
$nama_lengkap = $siswa_data['nama'] ?? $username;
$nis          = $siswa_data['nis'] ?? '-';
$kelas        = trim((string) ($siswa_data['kelas'] ?? '-'));

$msg_type = '';
$msg_text = '';

// 2. HANDLE PENGUMPULAN / UPDATE TUGAS OLEH SISWA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_tugas'])) {
    csrf_require();

    $tugas_id      = (int) ($_POST['tugas_id'] ?? 0);
    $link_input    = trim((string) ($_POST['link_tugas'] ?? ''));
    $teks_input    = trim((string) ($_POST['jawaban_teks'] ?? ''));
    $catatan_siswa = trim((string) ($_POST['catatan_siswa'] ?? ''));

    if ($siswa_id <= 0) {
        $msg_type = 'error';
        $msg_text = 'Profil siswa tidak ditemukan. Hubungi admin untuk menautkan akun.';
    } elseif ($tugas_id <= 0) {
        $msg_type = 'error';
        $msg_text = 'Tempat pengumpulan tugas tidak valid.';
    } else {
        $kelas_esc = mysqli_real_escape_string($koneksi, $kelas);
        $q_tcek = mysqli_query($koneksi, "
            SELECT * FROM tugas
            WHERE id = '$tugas_id' AND TRIM(kelas) = '$kelas_esc'
            LIMIT 1
        ");

        if (!$q_tcek || mysqli_num_rows($q_tcek) === 0) {
            $msg_type = 'error';
            $msg_text = 'Tugas tidak ditemukan atau bukan untuk kelas kamu.';
        } else {
            $tugas_row = mysqli_fetch_assoc($q_tcek);

            if ($tugas_row['status'] !== 'buka') {
                $msg_type = 'error';
                $msg_text = 'Pengumpulan untuk tugas ini sudah ditutup oleh guru.';
            } else {
                // Cek apakah sudah pernah mengumpulkan sebelumnya
                $q_prev = mysqli_query($koneksi, "
                    SELECT * FROM pengumpulan_tugas
                    WHERE tugas_id = '$tugas_id' AND siswa_id = '$siswa_id'
                    LIMIT 1
                ");
                $prev_sub = ($q_prev && mysqli_num_rows($q_prev) > 0) ? mysqli_fetch_assoc($q_prev) : null;

                if ($prev_sub && $prev_sub['nilai'] !== null) {
                    $msg_type = 'error';
                    $msg_text = 'Tugas ini sudah dinilai oleh guru sehingga tidak dapat diubah lagi.';
                } else {
                    $tipe = $tugas_row['tipe_pengumpulan'];
                    $valid = true;

                    // Validasi Link jika diisi
                    if ($link_input !== '') {
                        if (!preg_match('#^https?://#i', $link_input)) {
                            $link_input = 'https://' . $link_input;
                        }
                        if (!filter_var($link_input, FILTER_VALIDATE_URL)) {
                            $valid    = false;
                            $msg_type = 'error';
                            $msg_text = 'Format tautan / URL tidak valid. Pastikan diawali http:// atau https://';
                        }
                    }

                    // Proses upload file jika ada file baru
                    $file_db        = $prev_sub['file_tugas'] ?? null;
                    $nama_asli_db   = $prev_sub['nama_file_asli'] ?? null;
                    $uploaded_baru  = false;

                    if ($valid && !empty($_FILES['file_tugas']['name']) && $_FILES['file_tugas']['error'] !== UPLOAD_ERR_NO_FILE) {
                        if ($_FILES['file_tugas']['error'] !== UPLOAD_ERR_OK) {
                            $valid    = false;
                            $msg_type = 'error';
                            $msg_text = 'Terjadi kesalahan saat mengunggah file.';
                        } else {
                            $orig_name = basename($_FILES['file_tugas']['name']);
                            $ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                            $allowed_ext = [
                                'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx',
                                'zip', 'rar', '7z', 'txt', 'csv',
                                'jpg', 'jpeg', 'png', 'webp', 'mp4',
                                'html', 'css', 'js', 'py', 'java', 'cpp', 'sql', 'json'
                            ];

                            if (!in_array($ext, $allowed_ext, true)) {
                                $valid    = false;
                                $msg_type = 'error';
                                $msg_text = 'Format file .' . htmlspecialchars($ext) . ' tidak diizinkan.';
                            } elseif ($_FILES['file_tugas']['size'] > 20 * 1024 * 1024) {
                                $valid    = false;
                                $msg_type = 'error';
                                $msg_text = 'Ukuran file maksimal 20 MB.';
                            } else {
                                $safe_file = 'tugas_' . $tugas_id . '_siswa_' . $siswa_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                                if (move_uploaded_file($_FILES['file_tugas']['tmp_name'], $dir_kumpul . '/' . $safe_file)) {
                                    // Hapus file lama jika mengganti file
                                    if (!empty($file_db) && file_exists($dir_kumpul . '/' . $file_db)) {
                                        @unlink($dir_kumpul . '/' . $file_db);
                                    }
                                    $file_db       = $safe_file;
                                    $nama_asli_db  = $orig_name;
                                    $uploaded_baru = true;
                                } else {
                                    $valid    = false;
                                    $msg_type = 'error';
                                    $msg_text = 'Gagal menyimpan file ke server.';
                                }
                            }
                        }
                    }

                    // Pastikan minimal ada isi sesuai tipe pengumpulan
                    if ($valid) {
                        if ($tipe === 'file' && empty($file_db)) {
                            $valid    = false;
                            $msg_type = 'error';
                            $msg_text = 'Tugas ini mewajibkan upload file.';
                        } elseif ($tipe === 'link' && $link_input === '') {
                            $valid    = false;
                            $msg_type = 'error';
                            $msg_text = 'Tugas ini mewajibkan pengisian tautan / link.';
                        } elseif ($tipe === 'teks' && $teks_input === '') {
                            $valid    = false;
                            $msg_type = 'error';
                            $msg_text = 'Tugas ini mewajibkan pengisian jawaban teks.';
                        } elseif ($tipe === 'semua' && empty($file_db) && $link_input === '' && $teks_input === '') {
                            $valid    = false;
                            $msg_type = 'error';
                            $msg_text = 'Isi minimal salah satu: upload file, link tugas, atau jawaban teks.';
                        }
                    }

                    if ($valid) {
                        $file_sql = !empty($file_db) ? "'" . mysqli_real_escape_string($koneksi, $file_db) . "'" : "NULL";
                        $orig_sql = !empty($nama_asli_db) ? "'" . mysqli_real_escape_string($koneksi, $nama_asli_db) . "'" : "NULL";
                        $link_sql = ($link_input !== '') ? "'" . mysqli_real_escape_string($koneksi, $link_input) . "'" : "NULL";
                        $teks_sql = ($teks_input !== '') ? "'" . mysqli_real_escape_string($koneksi, $teks_input) . "'" : "NULL";
                        $cat_sql  = ($catatan_siswa !== '') ? "'" . mysqli_real_escape_string($koneksi, $catatan_siswa) . "'" : "NULL";

                        $q_save_sub = mysqli_query($koneksi, "
                            INSERT INTO pengumpulan_tugas (tugas_id, siswa_id, file_tugas, nama_file_asli, link_tugas, jawaban_teks, catatan_siswa, submitted_at)
                            VALUES ('$tugas_id', '$siswa_id', $file_sql, $orig_sql, $link_sql, $teks_sql, $cat_sql, NOW())
                            ON DUPLICATE KEY UPDATE
                                file_tugas     = $file_sql,
                                nama_file_asli = $orig_sql,
                                link_tugas     = $link_sql,
                                jawaban_teks   = $teks_sql,
                                catatan_siswa  = $cat_sql,
                                submitted_at   = NOW()
                        ");

                        if ($q_save_sub) {
                            $msg_type = 'success';
                            $msg_text = $prev_sub
                                ? 'Kiriman tugas berhasil diperbarui dan masuk ke daftar penilaian guru!'
                                : 'Tugas berhasil dikumpulkan! Hasil kiriman otomatis masuk ke halaman penilaian guru.';
                        } else {
                            $msg_type = 'error';
                            $msg_text = 'Gagal menyimpan pengumpulan tugas: ' . mysqli_error($koneksi);
                        }
                    }
                }
            }
        }
    }
}

// 3. AMBIL DAFTAR TUGAS UNTUK KELAS SISWA INI
$list_tugas_siswa = [];
$t_total          = 0;
$t_belum          = 0;
$t_terkumpul      = 0;
$t_dinilai        = 0;

if ($siswa_id > 0 && $kelas !== '' && $kelas !== '-') {
    $kelas_esc = mysqli_real_escape_string($koneksi, $kelas);
    $q_ts = mysqli_query($koneksi, "
        SELECT t.*,
               m.nama_mapel, m.kode_mapel,
               g.nama AS nama_guru,
               pt.id AS pengumpulan_id,
               pt.file_tugas, pt.nama_file_asli, pt.link_tugas,
               pt.jawaban_teks, pt.catatan_siswa, pt.submitted_at,
               pt.nilai AS nilai_tugas, pt.catatan_guru, pt.graded_at
        FROM tugas t
        JOIN mapel m ON t.mapel_id = m.id
        LEFT JOIN guru g ON t.guru_id = g.id
        LEFT JOIN pengumpulan_tugas pt ON pt.tugas_id = t.id AND pt.siswa_id = '$siswa_id'
        WHERE TRIM(t.kelas) = '$kelas_esc'
        ORDER BY (pt.id IS NULL) DESC, t.created_at DESC, t.id DESC
    ");

    if ($q_ts) {
        while ($tr = mysqli_fetch_assoc($q_ts)) {
            $has_sub   = !empty($tr['file_tugas']) || !empty($tr['link_tugas']) || trim((string)($tr['jawaban_teks'] ?? '')) !== '';
            $is_graded = ($tr['nilai_tugas'] !== null);

            $t_total++;
            if ($is_graded) {
                $t_dinilai++;
                $t_terkumpul++;
            } elseif ($has_sub) {
                $t_terkumpul++;
            } else {
                $t_belum++;
            }

            $tr['has_sub']   = $has_sub;
            $tr['is_graded'] = $is_graded;
            $list_tugas_siswa[] = $tr;
        }
    }
}

// 4. QUERY FETCH NILAI RAPOR
$sql_nilai = "SELECT nr.*, m.nama_mapel, m.kode_mapel, g.nama AS nama_guru
              FROM nilai_rapor nr
              JOIN mapel m ON nr.mapel_id = m.id
              LEFT JOIN guru g ON m.guru_id = g.id
              WHERE nr.siswa_id = '$siswa_id'
              ORDER BY m.nama_mapel ASC";

$q_nilai = mysqli_query($koneksi, $sql_nilai);

$list_nilai   = [];
$total_nilai  = 0;
$count_mapel  = 0;
$max_nilai    = 0;
$min_nilai    = 100;
$tuntas_count = 0;

if ($q_nilai && mysqli_num_rows($q_nilai) > 0) {
    while ($row = mysqli_fetch_assoc($q_nilai)) {
        $kkm          = $row['nilai_kkm'] ?? 75;
        $pengetahuan  = $row['nilai_pengetahuan'] ?? 0;
        $keterampilan = $row['nilai_keterampilan'] ?? 0;

        $nilai_akhir = round(($pengetahuan + $keterampilan) / 2, 1);

        if ($nilai_akhir >= 90) $predikat = 'A';
        elseif ($nilai_akhir >= 80) $predikat = 'B';
        elseif ($nilai_akhir >= 70) $predikat = 'C';
        else $predikat = 'D';

        $is_tuntas = ($nilai_akhir >= $kkm);
        if ($is_tuntas) $tuntas_count++;

        $row['kkm_val']           = $kkm;
        $row['pengetahuan_val']   = $pengetahuan;
        $row['keterampilan_val']  = $keterampilan;
        $row['computed_akhir']    = $nilai_akhir;
        $row['computed_predikat'] = $predikat;
        $row['is_tuntas']         = $is_tuntas;

        $list_nilai[] = $row;

        $total_nilai += $nilai_akhir;
        $count_mapel++;
        if ($nilai_akhir > $max_nilai) $max_nilai = $nilai_akhir;
        if ($nilai_akhir < $min_nilai) $min_nilai = $nilai_akhir;
    }
}

$rata_rata = $count_mapel > 0 ? round($total_nilai / $count_mapel, 1) : 0;
if ($count_mapel == 0) $min_nilai = 0;

// ---------- Tambahan khusus tampilan ----------
$pred_rata = '–';
if ($count_mapel > 0) {
    if ($rata_rata >= 90) $pred_rata = 'A';
    elseif ($rata_rata >= 80) $pred_rata = 'B';
    elseif ($rata_rata >= 70) $pred_rata = 'C';
    else $pred_rata = 'D';
}
$belum_tuntas = $count_mapel - $tuntas_count;

function rp_initial($s)
{
    $s = trim((string) $s);
    if ($s === '') return '?';
    return function_exists('mb_substr') ? mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($s, 0, 1));
}
function rp_pct($v)
{
    return number_format(max(0, min(100, (float) $v)), 2, '.', '');
}

// Daftar guru pengajar (satu baris per mapel, tanpa duplikat)
$guru_list  = [];
$seen_mapel = [];
foreach ($list_nilai as $row) {
    $g   = trim((string) ($row['nama_guru'] ?? ''));
    $key = $row['mapel_id'] ?? $row['nama_mapel'];
    if ($g === '' || isset($seen_mapel[$key])) continue;
    $seen_mapel[$key] = true;
    $guru_list[] = ['nama' => $g, 'mapel' => $row['nama_mapel'], 'nilai' => $row['computed_akhir']];
}

// Catatan guru (kolom keterangan yang terisi)
$notes = [];
foreach ($list_nilai as $row) {
    $k = trim((string) ($row['keterangan'] ?? ''));
    if ($k === '' || $k === '-') continue;
    $g = trim((string) ($row['nama_guru'] ?? ''));
    $notes[] = [
        'guru'  => $g !== '' ? $g : 'Guru mapel',
        'mapel' => $row['nama_mapel'],
        'teks'  => $k,
        'akhir' => $row['computed_akhir'],
        'pred'  => $row['computed_predikat'],
        'kkm'   => $row['kkm_val'],
    ];
}

// Titik sinar diagram radial (satu sinar per mapel, panjang = nilai akhir)
$burst = [];
foreach ($list_nilai as $i => $row) {
    $ang = (2 * M_PI * $i / max(1, $count_mapel)) - M_PI / 2;
    $len = 12 + (max(0, min(100, (float) $row['computed_akhir'])) / 100) * 34;
    $burst[] = [
        round(50 + 10 * cos($ang), 2), round(50 + 10 * sin($ang), 2),
        round(50 + $len * cos($ang), 2), round(50 + $len * sin($ang), 2),
    ];
}

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
    <title>View Report Card &amp; Tugas - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    <link rel="stylesheet" href="<?= $base_url; ?>/rapor.css?v=1">
    <style>
        /* Alert Notifikasi Pengumpulan */
        .rp-alert {
            padding: 13px 16px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .rp-alert.is-success {
            background: #eaf4ed;
            color: #1e5b34;
            border: 1px solid #b7dfc4;
        }
        .rp-alert.is-error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* Bagian Pengumpulan Tugas Siswa (Selaras rapor.css) */
        .rp-tasks {
            padding: 24px 28px;
            border-bottom: 1px solid rgba(22, 22, 22, 0.08);
            background: #ffffff;
        }
        .rp-task-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 14px 0 18px;
            padding: 10px 14px;
            border-radius: 14px;
            background: #f6f6f2;
        }
        .rp-task-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .rp-tchip {
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid rgba(22, 22, 22, 0.12);
            background: #ffffff;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .rp-tchip.active {
            background: #161616;
            color: #ffffff;
            border-color: #161616;
        }
        .rp-task-search {
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid rgba(22, 22, 22, 0.14);
            background: #ffffff;
            font-size: 12.5px;
            min-width: 210px;
            outline: none;
        }
        .rp-task-search:focus {
            border-color: #161616;
        }

        .rp-task-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 14px;
        }
        .rp-task-card {
            border-radius: 16px;
            padding: 18px;
            background: #f6f6f2;
            border: 1px solid rgba(22, 22, 22, 0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            transition: border-color 0.18s ease, background 0.18s ease;
        }
        .rp-task-card:hover {
            background: #f1f1ea;
            border-color: rgba(22, 22, 22, 0.22);
        }
        .rp-task-card.is-graded {
            background: #ffffff;
            border-color: rgba(22, 22, 22, 0.18);
        }
        .rp-task-badges {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }
        .rp-tbadge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
        }
        .rp-tbadge.mapel { background: #161616; color: #ffffff; }
        .rp-tbadge.kat   { background: #ffffff; color: #161616; border: 1px solid rgba(22, 22, 22, 0.14); }
        .rp-tbadge.st-pending { background: #fef3c7; color: #b45309; }
        .rp-tbadge.st-sent    { background: #dcfce7; color: #15803d; }
        .rp-tbadge.st-graded  { background: #161616; color: #ffffff; }
        .rp-tbadge.st-closed  { background: #fee2e2; color: #b91c1c; }

        .rp-task-title {
            margin: 0 0 6px;
            font-size: 15px;
            font-weight: 700;
            color: #161616;
        }
        .rp-task-desc {
            margin: 0;
            font-size: 12.5px;
            color: #575750;
            line-height: 1.5;
        }
        .rp-task-info {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed rgba(22, 22, 22, 0.12);
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 12px;
            color: #575750;
        }
        .rp-task-subbox {
            margin-top: 8px;
            padding: 10px 12px;
            border-radius: 12px;
            background: #ffffff;
            border: 1px solid rgba(22, 22, 22, 0.08);
            font-size: 12px;
        }
        .rp-task-artifacts {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .rp-art-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 8px;
            background: #f6f6f2;
            border: 1px solid rgba(22, 22, 22, 0.12);
            color: #161616;
            font-size: 11.5px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .rp-art-link:hover {
            background: #efefe5;
        }
        .rp-grade-callout {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 11px;
            background: #efefe5;
            margin-top: 8px;
        }
        .rp-grade-score {
            font-size: 18px;
            font-weight: 700;
            color: #161616;
            font-variant-numeric: tabular-nums;
        }
        .rp-btn-submit-task {
            width: 100%;
            padding: 10px 14px;
            border-radius: 999px;
            border: none;
            background: #161616;
            color: #ffffff;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: opacity 0.15s ease;
        }
        .rp-btn-submit-task:hover { opacity: 0.88; }
        .rp-btn-submit-task.is-edit {
            background: #ffffff;
            color: #161616;
            border: 1px solid rgba(22, 22, 22, 0.2);
        }
        .rp-btn-submit-task.is-edit:hover {
            background: #161616;
            color: #ffffff;
        }

        /* Modal Pengumpulan Tugas */
        .rp-modal-bg {
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
        .rp-modal-bg.is-open { display: flex; }
        .rp-modal-box {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 540px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px 26px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.22);
        }
        .rp-modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 12px;
            margin-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .rp-modal-head h3 { margin: 0; font-size: 16.5px; color: #161616; }
        .rp-modal-head p  { margin: 4px 0 0; font-size: 12px; color: #6f6f68; }
        .rp-modal-x {
            background: #f1f5f9;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            flex-shrink: 0;
        }
        .rp-fgroup {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .rp-fgroup label {
            font-size: 12px;
            font-weight: 700;
            color: #161616;
        }
        .rp-fcontrol {
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            font-family: inherit;
            background: #f8fafc;
            color: #161616;
            outline: none;
        }
        .rp-fcontrol:focus {
            border-color: #161616;
            background: #ffffff;
        }
        .rp-fhint {
            font-size: 11.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/lenis_scroll.php'; ?>
<?php include "loader.php"; ?>
<div class="video">
    <div class="dashboard-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo"><i class="home"></i></div>
                <h2>SIPN Core</h2>
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
                <?php if ($role === 'siswa'): ?>
                <li><a href="lihat_nilai.php" class="active"><i class="report"></i> <span class="menu-label">Report Grades</span></a></li>
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

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title">
                    <h1>Report Card Grades &amp; Tugas</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/settings.webp"/></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="rapor-container">

                <?php if ($msg_text !== ''): ?>
                    <div class="rp-alert <?= $msg_type === 'success' ? 'is-success' : 'is-error'; ?> rp-no-print">
                        <i class="fa-solid <?= $msg_type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                        <span><?= htmlspecialchars($msg_text); ?></span>
                    </div>
                <?php endif; ?>

                <div class="rp-card">

                    <!-- ===== Pita atas (krem) ===== -->
                    <section class="rp-band">
                        <div class="rp-hero">
                            <!-- Ringkasan utama -->
                            <div class="rp-intro">
                                <h2 class="rp-h1">Hasil <span class="rp-dim">belajar</span><br><?= e($nama_lengkap); ?></h2>
                                <p class="rp-caption">Rata-rata nilai akhir</p>
                                <?php if ($count_mapel > 0): ?>
                                    <div class="rp-grade"><span aria-hidden="true"><?= e($pred_rata); ?>, <?= e($rata_rata); ?><small>/100</small></span><span class="rp-sr">Rata-rata <?= e($rata_rata); ?>, predikat <?= e($pred_rata); ?></span></div>
                                <?php else: ?>
                                    <div class="rp-grade"><span aria-hidden="true">–</span><span class="rp-sr">Belum ada nilai</span></div>
                                <?php endif; ?>
                                <p class="rp-meta">
                                    <span>NIS <strong><?= e($nis); ?></strong></span>
                                    <span>Kelas <strong><?= e($kelas); ?></strong></span>
                                    <span>Status <strong>Aktif</strong></span>
                                </p>
                            </div>

                            <!-- Grafik -->
                            <div class="rp-chart-col rp-no-print">
                                <div class="rp-head">
                                    <h3 class="rp-title">Nilai per mata pelajaran</h3>
                                    <div class="rp-legend">
                                        <span><i class="rp-sw rp-sw-kkm"></i>KKM</span>
                                        <span><i class="rp-sw rp-sw-nilai"></i>Nilai akhir</span>
                                    </div>
                                </div>

                                <?php if ($count_mapel > 0): ?>
                                    <div class="rp-chart" role="group" aria-label="Grafik nilai akhir dibanding KKM untuk setiap mata pelajaran">
                                        <div class="rp-axis" aria-hidden="true">
                                            <span style="bottom:90%">A</span>
                                            <span style="bottom:80%">B</span>
                                            <span style="bottom:70%">C</span>
                                            <span style="bottom:0">D</span>
                                        </div>
                                        <div class="rp-scroll">
                                            <div class="rp-plot">
                                                <div class="rp-gls" aria-hidden="true">
                                                    <i class="rp-gl" style="bottom:90%"></i>
                                                    <i class="rp-gl" style="bottom:80%"></i>
                                                    <i class="rp-gl" style="bottom:70%"></i>
                                                </div>
                                                <ul class="rp-bars">
                                                    <?php foreach ($list_nilai as $i => $row): ?>
                                                        <li class="rp-grp" tabindex="0"
                                                            style="--i:<?= (int) $i; ?>;--h:<?= rp_pct($row['computed_akhir']); ?>"
                                                            title="<?= e($row['nama_mapel']); ?>: nilai akhir <?= e($row['computed_akhir']); ?>, KKM <?= e($row['kkm_val']); ?>">
                                                            <span class="rp-bar rp-bar-kkm" style="height:<?= rp_pct($row['kkm_val']); ?>%"></span>
                                                            <span class="rp-bar rp-bar-nilai" style="height:<?= rp_pct($row['computed_akhir']); ?>%"></span>
                                                            <span class="rp-val"><?= e($row['computed_akhir']); ?></span>
                                                            <span class="rp-x"><?= e($row['kode_mapel'] ?? '-'); ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="rp-empty">Belum ada nilai untuk ditampilkan di grafik.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Guru pengajar -->
                            <aside class="rp-teachers rp-no-print">
                                <h3 class="rp-title">Guru pengajar</h3>
                                <p class="rp-sub">Guru pengampu setiap mata pelajaran di rapor ini.</p>
                                <?php if (!empty($guru_list)): ?>
                                    <ul class="rp-people" id="rpPeople">
                                        <?php foreach ($guru_list as $gi => $g): ?>
                                            <li class="rp-person<?= $gi >= 3 ? ' rp-extra' : ''; ?>">
                                                <span class="rp-ava"><?= e(rp_initial($g['nama'])); ?></span>
                                                <div>
                                                    <strong><?= e($g['nama']); ?></strong>
                                                    <span class="rp-person-sub"><?= e($g['mapel']); ?></span>
                                                </div>
                                                <span class="rp-person-val" title="Nilai akhir"><?= e($g['nilai']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (count($guru_list) > 3): ?>
                                        <button type="button" class="rp-pill" id="rpPeopleToggle" aria-expanded="false">Lihat semua</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="rp-empty">Belum ada guru yang ditautkan ke mata pelajaran ini.</p>
                                <?php endif; ?>
                            </aside>
                        </div>
                    </section>

                    <!-- ===== Bagian Tempat Pengumpulan Tugas Siswa (Tidak ikut tercetak saat Print Rapor) ===== -->
                    <section class="rp-tasks rp-no-print" id="pengumpulan-tugas">
                        <div class="rp-head">
                            <div>
                                <h3 class="rp-title">Pengumpulan Tugas Kelas <?= e($kelas); ?></h3>
                                <p class="rp-sub">Kirimkan tugas sesuai wadah yang diberikan guru. Nilai yang diberikan guru otomatis masuk ke rincian rapor di bawah.</p>
                            </div>
                            <span class="rp-pill"><?= $t_terkumpul; ?>/<?= $t_total; ?> Terkumpul</span>
                        </div>

                        <?php if (!empty($list_tugas_siswa)): ?>
                            <div class="rp-task-bar">
                                <div class="rp-task-chips" id="siswaTaskFilter">
                                    <button type="button" class="rp-tchip active" data-filter="all">Semua (<?= $t_total; ?>)</button>
                                    <button type="button" class="rp-tchip" data-filter="pending">Belum Kumpul (<?= $t_belum; ?>)</button>
                                    <button type="button" class="rp-tchip" data-filter="submitted">Sudah Kumpul (<?= $t_terkumpul; ?>)</button>
                                    <button type="button" class="rp-tchip" data-filter="graded">Sudah Dinilai (<?= $t_dinilai; ?>)</button>
                                </div>
                                <input type="text" id="siswaTaskSearch" class="rp-task-search" placeholder="Cari judul tugas / mapel...">
                            </div>

                            <div class="rp-task-grid" id="siswaTaskGrid">
                                <?php foreach ($list_tugas_siswa as $tg):
                                    $is_open   = ($tg['status'] === 'buka');
                                    $has_sub   = $tg['has_sub'];
                                    $is_graded = $tg['is_graded'];
                                    $dl_str    = !empty($tg['deadline']) ? date('d M Y, H:i', strtotime($tg['deadline'])) : 'Tanpa batas waktu';
                                    $is_over   = (!empty($tg['deadline']) && time() > strtotime($tg['deadline']));
                                ?>
                                    <article class="rp-task-card <?= $is_graded ? 'is-graded' : ''; ?>"
                                             data-sub="<?= $has_sub ? '1' : '0'; ?>"
                                             data-graded="<?= $is_graded ? '1' : '0'; ?>"
                                             data-hay="<?= strtolower(htmlspecialchars($tg['judul'] . ' ' . $tg['nama_mapel'] . ' ' . ($tg['nama_guru'] ?? ''))); ?>">
                                        <div>
                                            <div class="rp-task-badges">
                                                <span class="rp-tbadge mapel"><?= e($tg['nama_mapel']); ?></span>
                                                <span class="rp-tbadge kat"><?= ucfirst(e($tg['kategori'])); ?></span>

                                                <?php if ($is_graded): ?>
                                                    <span class="rp-tbadge st-graded">Dinilai&nbsp&nbsp✓</span>
                                                <?php elseif ($has_sub): ?>
                                                    <span class="rp-tbadge st-sent"><i class="fa-solid fa-paper-plane"></i> Terkumpul</span>
                                                <?php elseif (!$is_open): ?>
                                                    <span class="rp-tbadge st-closed"><i class="fa-solid fa-lock"></i> Ditutup</span>
                                                <?php else: ?>
                                                    <span class="rp-tbadge st-pending"><i class="fa-regular fa-clock"></i> Belum Kumpul</span>
                                                <?php endif; ?>
                                            </div>

                                            <h4 class="rp-task-title"><?= e($tg['judul']); ?></h4>
                                            <p class="rp-task-desc">
                                                <?= !empty($tg['deskripsi']) ? nl2br(e($tg['deskripsi'])) : 'Silakan kumpulkan hasil tugas sesuai format yang diminta guru.'; ?>
                                            </p>

                                            <div class="rp-task-info">
                                                <span> 〇&nbsp&nbsp&nbspGuru: <strong><?= e($tg['nama_guru'] ?? 'Guru Mapel'); ?></strong> (Semester <?= e($tg['semester']); ?>)</span>
                                                <span>〇&nbsp&nbsp&nbspFormat: <strong><?= e($label_tipe[$tg['tipe_pengumpulan']] ?? 'Semua'); ?></strong></span>
                                                <span style="<?= ($is_over && !$has_sub) ? 'color:#b91c1c;' : ''; ?>">
                                                     		〇&nbsp&nbsp&nbspDeadline: <strong><?= e($dl_str); ?></strong>
                                                </span>

                                                <?php if (!empty($tg['file_lampiran'])): ?>
                                                    <div style="margin-top: 4px;">
                                                        <a href="uploads/tugas_soal/<?= urlencode($tg['file_lampiran']); ?>" target="_blank" class="rp-art-link">
                                                            <i class="download"></i> Unduh Lampiran Soal
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($has_sub): ?>
                                                <div class="rp-task-subbox">
                                                    <div style="display:flex; justify-content:space-between; align-items:center; gap:6px;">
                                                        <strong style="color:#161616;">Kiriman Kamu</strong>
                                                        <?php if (!empty($tg['submitted_at'])): ?>
                                                            <span style="font-size:11px; color:#6f6f68;"><?= date('d M Y, H:i', strtotime($tg['submitted_at'])); ?></span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="rp-task-artifacts">
                                                        <?php if (!empty($tg['file_tugas'])): ?>
                                                            <a href="uploads/pengumpulan_tugas/<?= urlencode($tg['file_tugas']); ?>" target="_blank" class="rp-art-link">
                                                                 <?= e(mb_strimwidth($tg['nama_file_asli'] ?? 'File Kamu', 0, 26, '...')); ?>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if (!empty($tg['link_tugas'])): ?>
                                                            <a href="<?= e($tg['link_tugas']); ?>" target="_blank" rel="noopener noreferrer" class="rp-art-link">
                                                                 Lihat Link
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if (trim((string)($tg['jawaban_teks'] ?? '')) !== ''): ?>
                                                            <button type="button"
                                                                    class="rp-art-link"
                                                                    data-judul="<?= e($tg['judul']); ?>"
                                                                    data-teks="<?= e($tg['jawaban_teks']); ?>"
                                                                    onclick="bukaPreviewJawaban(this)">
                                                                 Jawaban Teks
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (!empty($tg['catatan_siswa'])): ?>
                                                        <div style="margin-top:6px; color:#6f6f68; font-size:11.5px;">
                                                            Catatan: <em><?= e($tg['catatan_siswa']); ?></em>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($is_graded): ?>
                                                <div class="rp-grade-callout">
                                                    <div>
                                                        <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:#6f6f68;">Nilai Tugas</div>
                                                        <?php if (!empty($tg['catatan_guru'])): ?>
                                                            <div style="font-size:12px; color:#161616; margin-top:2px;">“<?= e($tg['catatan_guru']); ?>”</div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="rp-grade-score"><?= (int) $tg['nilai_tugas']; ?><small style="font-size:12px; color:#6f6f68;">/100</small></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div>
                                            <?php if ($is_graded): ?>
                                                <button type="button" class="rp-btn-submit-task is-edit" disabled style="opacity:0.65; cursor:default;">
                                                    ✦&nbsp&nbsp&nbspSudah Dinilai Guru
                                                </button>
                                            <?php elseif ($is_open): ?>
                                                <button type="button"
                                                        class="rp-btn-submit-task <?= $has_sub ? 'is-edit' : ''; ?>"
                                                        data-id="<?= (int) $tg['id']; ?>"
                                                        data-judul="<?= e($tg['judul']); ?>"
                                                        data-mapel="<?= e($tg['nama_mapel']); ?>"
                                                        data-tipe="<?= e($tg['tipe_pengumpulan']); ?>"
                                                        data-link="<?= e($tg['link_tugas'] ?? ''); ?>"
                                                        data-teks="<?= e($tg['jawaban_teks'] ?? ''); ?>"
                                                        data-catatan="<?= e($tg['catatan_siswa'] ?? ''); ?>"
                                                        data-file="<?= e($tg['nama_file_asli'] ?? ''); ?>"
                                                        onclick="bukaModalKumpul(this)">
                                                    <i class="fa-solid <?= $has_sub ? 'fa-pen-to-square' : 'fa-cloud-arrow-up'; ?>"></i>
                                                    <?= $has_sub ? 'Edit / Kirim Ulang Tugas' : 'Kumpulkan Tugas'; ?>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="rp-btn-submit-task is-edit" disabled style="opacity:0.6; cursor:not-allowed;">
                                                    <i class="fa-solid fa-lock"></i> Pengumpulan Ditutup
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="rp-empty">Belum ada tempat pengumpulan tugas dari guru untuk kelas <?= e($kelas); ?>.</p>
                        <?php endif; ?>
                    </section>

                    <!-- ===== Bagian bawah (putih) ===== -->
                    <section class="rp-lower rp-no-print">
                        <!-- Ringkasan -->
                        <div class="rp-panel">
                            <div class="rp-head">
                                <h3 class="rp-title">Ringkasan nilai</h3>
                                <button type="button" class="rp-pill rp-pill-line" onclick="window.print()">
                                    <i class="fa-solid fa-print" aria-hidden="true"></i> Cetak rapor
                                </button>
                            </div>
                            <div class="rp-tiles">
                                <div class="rp-tile">
                                    <span class="rp-tile-n"><?= e($min_nilai); ?></span>
                                    <span class="rp-tile-l">Terendah</span>
                                </div>
                                <div class="rp-tile is-dark">
                                    <span class="rp-tile-n"><?= e($max_nilai); ?></span>
                                    <span class="rp-tile-l">Tertinggi</span>
                                </div>
                                <div class="rp-tile is-dark">
                                    <span class="rp-tile-n"><?= e($tuntas_count); ?>/<?= e($count_mapel); ?></span>
                                    <span class="rp-tile-l">Tuntas</span>
                                </div>
                                <div class="rp-tile">
                                    <span class="rp-tile-n"><?= e($count_mapel); ?></span>
                                    <span class="rp-tile-l">Total mapel</span>
                                </div>
                                <div class="rp-tile">
                                    <span class="rp-tile-n"><?= e($belum_tuntas); ?></span>
                                    <span class="rp-tile-l">Belum tuntas</span>
                                </div>
                                <div class="rp-tile is-burst">
                                    <svg viewBox="0 0 100 100" role="img" aria-label="Diagram radial: satu garis untuk setiap mata pelajaran, makin panjang makin tinggi nilainya">
                                        <g stroke="#161616" stroke-width="1.4" stroke-linecap="round">
                                            <?php foreach ($burst as $bp): ?>
                                                <line x1="<?= $bp[0]; ?>" y1="<?= $bp[1]; ?>" x2="<?= $bp[2]; ?>" y2="<?= $bp[3]; ?>"/>
                                            <?php endforeach; ?>
                                        </g>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Ketuntasan per mapel -->
                        <div class="rp-panel">
                            <div class="rp-head">
                                <h3 class="rp-title">Ketuntasan per mapel</h3>
                            </div>
                            <?php if (!empty($list_nilai)): ?>
                                <ul class="rp-lines">
                                    <?php foreach ($list_nilai as $row): ?>
                                        <li class="rp-line">
                                            <span class="rp-line-meta">KKM <?= e($row['kkm_val']); ?></span>
                                            <span class="rp-line-name">
                                                <?= e($row['nama_mapel']); ?>
                                                <?php if ($row['is_tuntas']): ?>
                                                    <span class="rp-mark is-ok" aria-hidden="true">✔</span><span class="rp-sr">tuntas</span>
                                                <?php else: ?>
                                                    <span class="rp-mark is-no" aria-hidden="true">✕</span><span class="rp-sr">belum tuntas</span>
                                                <?php endif; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="rp-empty">Belum ada data nilai rapor untuk siswa ini.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Catatan guru -->
                        <div class="rp-panel rp-panel-notes">
                            <div class="rp-head">
                                <h3 class="rp-title">Catatan guru</h3>
                            </div>
                            <?php if (!empty($notes)): ?>
                                <div class="rp-notes">
                                    <?php foreach ($notes as $n): ?>
                                        <article class="rp-note">
                                            <div class="rp-note-head">
                                                <span class="rp-ava rp-ava-sm"><?= e(rp_initial($n['guru'])); ?></span>
                                                <div>
                                                    <strong><?= e($n['guru']); ?></strong>
                                                    <span class="rp-person-sub">Guru <?= e($n['mapel']); ?></span>
                                                </div>
                                            </div>
                                            <p class="rp-note-body" title="<?= e($n['teks']); ?>"><?= e($n['teks']); ?></p>
                                            <div class="rp-note-foot">
                                                <span class="rp-chip is-dark">Nilai akhir <?= e($n['akhir']); ?></span>
                                                <span class="rp-chip">Predikat <?= e($n['pred']); ?></span>
                                                <span class="rp-pill">KKM <?= e($n['kkm']); ?></span>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="rp-empty">Belum ada catatan dari guru.</p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- ===== Rincian nilai ===== -->
                    <section class="rp-detail">
                        <div class="rp-head">
                            <div>
                                <h3 class="rp-title">Rincian nilai</h3>
                                <p class="rp-sub">Nilai akhir adalah rata-rata nilai pengetahuan dan keterampilan.</p>
                            </div>
                        </div>

                        <div class="rp-table-wrap">
                            <table class="rp-table">
                                <thead>
                                    <tr>
                                        <th class="rp-c" style="width:50px">No</th>
                                        <th>Mata Pelajaran</th>
                                        <th class="rp-c">KKM</th>
                                        <th class="rp-c">Nilai Pengetahuan</th>
                                        <th class="rp-c">Nilai Keterampilan</th>
                                        <th class="rp-c">Nilai Akhir</th>
                                        <th class="rp-c">Predikat</th>
                                        <th class="rp-c">Keterangan</th>
                                        <th class="rp-c">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($list_nilai)): ?>
                                        <?php foreach ($list_nilai as $index => $row): ?>
                                            <tr>
                                                <td class="rp-c"><?= $index + 1; ?></td>
                                                <td>
                                                    <span class="rp-strong"><?= e($row['nama_mapel']); ?></span>
                                                    <small>
                                                        <?= e($row['kode_mapel'] ?? '-'); ?>
                                                        <?php if (!empty($row['semester'])): ?>, semester <?= e($row['semester']); ?><?php endif; ?>
                                                    </small>
                                                </td>
                                                <td class="rp-c rp-strong"><?= e($row['kkm_val']); ?></td>
                                                <td class="rp-c"><?= e($row['pengetahuan_val']); ?></td>
                                                <td class="rp-c"><?= e($row['keterampilan_val']); ?></td>
                                                <td class="rp-c rp-strong"><?= e($row['computed_akhir']); ?></td>
                                                <td class="rp-c">
                                                    <span class="rp-badge is-<?= strtolower(e($row['computed_predikat'])); ?>"><?= e($row['computed_predikat']); ?></span>
                                                </td>
                                                <td class="rp-c rp-ket"><?= e(($row['keterangan'] ?? '') !== '' ? $row['keterangan'] : '-'); ?></td>
                                                <td class="rp-c">
                                                    <?php if ($row['is_tuntas']): ?>
                                                        <span class="rp-mark is-ok" title="Tuntas" aria-hidden="true">✔</span><span class="rp-sr">Tuntas</span>
                                                    <?php else: ?>
                                                        <span class="rp-mark is-no" title="Belum tuntas" aria-hidden="true">✕</span><span class="rp-sr">Belum tuntas</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="rp-empty-row">Belum ada data nilai rapor untuk siswa ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                </div>
            </div>
        </main>
    </div>
</div>

<!-- MODAL KUMPULKAN / EDIT TUGAS SISWA -->
<div class="rp-modal-bg rp-no-print" id="modalKumpulTugas">
    <div class="rp-modal-box">
        <div class="rp-modal-head">
            <div>
                <h3 id="mkTitle">Kumpulkan Tugas</h3>
                <p id="mkSubtitle">Mata Pelajaran</p>
            </div>
            <button type="button" class="rp-modal-x" onclick="tutupModal('modalKumpulTugas')">&times;</button>
        </div>

        <form method="POST" action="lihat_nilai.php#pengumpulan-tugas" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:14px;">
            <?= csrf_field(); ?>
            <input type="hidden" name="tugas_id" id="mkTugasId">

            <div class="rp-fgroup" id="wrapInputFile">
                <label>Upload File Tugas <span id="reqFileBadge" style="color:#b91c1c;"></span></label>
                <input type="file" name="file_tugas" id="mkFileInput" class="rp-fcontrol" style="padding:8px;">
                <span class="rp-fhint" id="mkFileHint">Maksimal 20 MB (PDF, DOCX, PPTX, XLSX, ZIP/RAR, Gambar, atau file kode).</span>
            </div>

            <div class="rp-fgroup" id="wrapInputLink">
                <label>Tautan / Link Tugas <span id="reqLinkBadge" style="color:#b91c1c;"></span></label>
                <input type="url" name="link_tugas" id="mkLinkInput" class="rp-fcontrol" placeholder="https://github.com/... atau https://drive.google.com/...">
            </div>

            <div class="rp-fgroup" id="wrapInputTeks">
                <label>Jawaban Teks Langsung <span id="reqTeksBadge" style="color:#b91c1c;"></span></label>
                <textarea name="jawaban_teks" id="mkTeksInput" rows="4" class="rp-fcontrol" placeholder="Tuliskan jawaban tugas kamu di sini..."></textarea>
            </div>

            <div class="rp-fgroup">
                <label>Catatan Tambahan untuk Guru (Opsional)</label>
                <input type="text" name="catatan_siswa" id="mkCatatanInput" class="rp-fcontrol" placeholder="Contoh: Tugas sudah dikerjakan sesuai instruksi">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:4px;">
                <button type="button" class="rp-pill rp-pill-line" onclick="tutupModal('modalKumpulTugas')">Batal</button>
                <button type="submit" name="kirim_tugas" class="rp-btn-submit-task" style="width:auto; padding:10px 20px;">
                    <i class="fa-solid fa-paper-plane"></i> Kirim Tugas
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PREVIEW JAWABAN TEKS -->
<div class="rp-modal-bg rp-no-print" id="modalBacaJawaban">
    <div class="rp-modal-box">
        <div class="rp-modal-head">
            <div>
                <h3 id="mbTitle">Jawaban Teks Kamu</h3>
                <p>Ringkasan teks yang dikirimkan ke guru</p>
            </div>
            <button type="button" class="rp-modal-x" onclick="tutupModal('modalBacaJawaban')">&times;</button>
        </div>
        <div id="mbBody" style="white-space:pre-wrap; background:#f6f6f2; padding:14px 16px; border-radius:12px; font-size:13px; line-height:1.6; color:#161616; max-height:55vh; overflow-y:auto;"></div>
        <div style="display:flex; justify-content:flex-end; margin-top:14px;">
            <button type="button" class="rp-pill rp-pill-line" onclick="tutupModal('modalBacaJawaban')">Tutup</button>
        </div>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('rpPeopleToggle');
    var list = document.getElementById('rpPeople');
    if (btn && list) {
        btn.addEventListener('click', function () {
            var open = list.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.textContent = open ? 'Lihat lebih sedikit' : 'Lihat semua';
        });
    }
})();

function tutupModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('is-open');
}

document.querySelectorAll('.rp-modal-bg').forEach(function (bg) {
    bg.addEventListener('click', function (e) {
        if (e.target === bg) bg.classList.remove('is-open');
    });
});

function bukaModalKumpul(btn) {
    var id      = btn.getAttribute('data-id');
    var judul   = btn.getAttribute('data-judul') || 'Tugas';
    var mapel   = btn.getAttribute('data-mapel') || '';
    var tipe    = btn.getAttribute('data-tipe') || 'semua';
    var link    = btn.getAttribute('data-link') || '';
    var teks    = btn.getAttribute('data-teks') || '';
    var catatan = btn.getAttribute('data-catatan') || '';
    var fileOld = btn.getAttribute('data-file') || '';

    document.getElementById('mkTugasId').value      = id;
    document.getElementById('mkTitle').textContent  = judul;
    document.getElementById('mkSubtitle').textContent = 'Mata Pelajaran: ' + mapel;
    document.getElementById('mkLinkInput').value    = link;
    document.getElementById('mkTeksInput').value    = teks;
    document.getElementById('mkCatatanInput').value = catatan;
    document.getElementById('mkFileInput').value    = '';

    var wrapFile = document.getElementById('wrapInputFile');
    var wrapLink = document.getElementById('wrapInputLink');
    var wrapTeks = document.getElementById('wrapInputTeks');

    wrapFile.style.display = (tipe === 'semua' || tipe === 'file') ? 'flex' : 'none';
    wrapLink.style.display = (tipe === 'semua' || tipe === 'link') ? 'flex' : 'none';
    wrapTeks.style.display = (tipe === 'semua' || tipe === 'teks') ? 'flex' : 'none';

    document.getElementById('reqFileBadge').textContent = (tipe === 'file') ? '*' : '';
    document.getElementById('reqLinkBadge').textContent = (tipe === 'link') ? '*' : '';
    document.getElementById('reqTeksBadge').textContent = (tipe === 'teks') ? '*' : '';

    var hintEl = document.getElementById('mkFileHint');
    if (fileOld) {
        hintEl.innerHTML = 'File terunggah saat ini: <strong>' + fileOld + '</strong>. Biarkan kosong jika tidak ingin mengganti file.';
    } else {
        hintEl.textContent = 'Maksimal 20 MB (PDF, DOCX, PPTX, XLSX, ZIP/RAR, Gambar, atau file kode).';
    }

    document.getElementById('modalKumpulTugas').classList.add('is-open');
}

function bukaPreviewJawaban(btn) {
    document.getElementById('mbTitle').textContent = 'Jawaban Teks — ' + (btn.getAttribute('data-judul') || '');
    document.getElementById('mbBody').textContent  = btn.getAttribute('data-teks') || '';
    document.getElementById('modalBacaJawaban').classList.add('is-open');
}

// Filter & pencarian kartu tugas siswa
(function () {
    var chips  = document.querySelectorAll('#siswaTaskFilter .rp-tchip');
    var search = document.getElementById('siswaTaskSearch');
    var cards  = document.querySelectorAll('#siswaTaskGrid .rp-task-card');
    var active = 'all';

    function filterTasks() {
        var q = search ? search.value.trim().toLowerCase() : '';
        cards.forEach(function (card) {
            var sub    = card.getAttribute('data-sub') === '1';
            var graded = card.getAttribute('data-graded') === '1';
            var hay    = card.getAttribute('data-hay') || '';

            var okStatus = true;
            if (active === 'pending') okStatus = !sub;
            else if (active === 'submitted') okStatus = sub;
            else if (active === 'graded') okStatus = graded;

            var okSearch = (q === '' || hay.indexOf(q) !== -1);
            card.style.display = (okStatus && okSearch) ? '' : 'none';
        });
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('active'); });
            chip.classList.add('active');
            active = chip.getAttribute('data-filter');
            filterTasks();
        });
    });

    if (search) {
        search.addEventListener('input', filterTasks);
    }
})();
</script>
</body>
</html>