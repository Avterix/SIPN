<?php
ini_set('session.cookie_path', '/');
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$q_user  = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($q_user);

$username     = $user_data['username'] ?? '';
$role         = strtolower($_SESSION['role'] ?? '');
$foto_db      = $user_data['foto'] ?? '';
$gh_connected = !empty($user_data['github_connected']) && $user_data['github_connected'] == 1;
$gh_avatar    = $user_data['github_avatar'] ?? '';


// Hirarki Foto Profil (Fix Google Avatar & URL CDN)
$avatar_src = '';
if (!empty($foto_db)) {
    if (strpos($foto_db, 'data:image') === 0 || strpos($foto_db, 'http://') === 0 || strpos($foto_db, 'https://') === 0) {
        $avatar_src = $foto_db;
    } elseif (file_exists('uploads/avatars/' . $foto_db)) {
        $avatar_src = 'uploads/avatars/' . $foto_db;
    }
}
if (empty($avatar_src) && $gh_connected && !empty($gh_avatar)) {
    $avatar_src = $gh_avatar;
}

// 1. Ambil statistik database secara aman
$tot_siswa = 0;
$q_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM siswa");
if ($q_siswa && $row = mysqli_fetch_assoc($q_siswa)) {
    $tot_siswa = $row['total'] ?? 0;
}

$tot_guru = 0;
$q_guru = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM guru");
if ($q_guru && $row = mysqli_fetch_assoc($q_guru)) {
    $tot_guru = $row['total'] ?? 0;
}

$tot_mapel = 0;
$q_mapel = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM mapel");
if ($q_mapel && $row = mysqli_fetch_assoc($q_mapel)) {
    $tot_mapel = $row['total'] ?? 0;
}

// 2. LOGIKA KHUSUS SISWA, GURU, & ADMIN
$grades_data     = [];
$jadwal_hari_ini = [];
$kehadiran       = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0, 'persentase' => 100];

$translate_hari = [
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu',
    'Sunday'    => 'Minggu'
];
$hari_ini   = $translate_hari[date('l')];
$hari_besok = $translate_hari[date('l', strtotime('+1 day'))];

$bulan_indo          = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$tanggal_label       = $hari_ini . ', ' . date('d') . ' ' . $bulan_indo[(int)date('n')] . ' ' . date('Y');
$besok_ts            = strtotime('+1 day');
$tanggal_besok_label = $hari_besok . ', ' . date('d', $besok_ts) . ' ' . $bulan_indo[(int)date('n', $besok_ts)];

// Hitung jumlah siswa per kelas
$siswa_per_kelas = [];
$q_spk = mysqli_query($koneksi, "SELECT kelas, COUNT(*) AS jml FROM siswa WHERE kelas IS NOT NULL AND kelas <> '' GROUP BY kelas");
if ($q_spk) {
    while ($r = mysqli_fetch_assoc($q_spk)) {
        $siswa_per_kelas[$r['kelas']] = (int)$r['jml'];
    }
}

// Variabel khusus dashboard guru
$guru_id              = 0;
$guru_nama            = $username;
$guru_nip             = '';
$guru_jadwal_hari_ini = [];
$guru_jadwal_besok    = [];
$guru_kelas_ajar      = [];
$guru_mapel_unik      = [];
$tot_siswa_diampu     = 0;
$tot_sesi_minggu      = 0;
$guru_beban_harian    = ['Senin' => 0, 'Selasa' => 0, 'Rabu' => 0, 'Kamis' => 0, 'Jumat' => 0, 'Sabtu' => 0];

if ($role === 'siswa') {
    $siswa_id    = 0;
    $siswa_kelas = '';

    $q_siswa_info = mysqli_query($koneksi, "SELECT id, kelas FROM siswa WHERE user_id = '$user_id'");
    if ($q_siswa_info && mysqli_num_rows($q_siswa_info) > 0) {
        $d_siswa     = mysqli_fetch_assoc($q_siswa_info);
        $siswa_id    = $d_siswa['id'] ?? 0;
        $siswa_kelas = $d_siswa['kelas'] ?? '';
    }

    $q_nilai = mysqli_query($koneksi, "
        SELECT m.nama_mapel, 
               ROUND((COALESCE(n.nilai_pengetahuan, 0) + COALESCE(n.nilai_keterampilan, 0)) / 2, 0) AS nilai
        FROM mapel m 
        LEFT JOIN nilai_rapor n ON m.id = n.mapel_id AND n.siswa_id = '$siswa_id' 
        LIMIT 5
    ");

    if ($q_nilai && mysqli_num_rows($q_nilai) > 0) {
        while ($row = mysqli_fetch_assoc($q_nilai)) {
            $val = (float)$row['nilai'];
            $grades_data[] = [
                'nama'  => $row['nama_mapel'],
                'nilai' => ($val > 0) ? $val : null
            ];
        }
    }

    while (count($grades_data) < 5) {
        $idx = count($grades_data) + 1;
        $grades_data[] = [
            'nama'  => "Mapel $idx",
            'nilai' => null
        ];
    }

    $q_jadwal = mysqli_query($koneksi, "
        SELECT j.jam_mulai, j.jam_selesai, j.kelas, 
               COALESCE(m.nama_mapel, 'Mata Pelajaran') AS nama_mapel, 
               COALESCE(g.nama, 'Belum Ditentukan') AS nama_guru 
        FROM jadwal j 
        LEFT JOIN mapel m ON j.mapel_id = m.id 
        LEFT JOIN guru g ON m.guru_id = g.id 
        WHERE j.hari = '$hari_ini' AND j.kelas = '$siswa_kelas'
        ORDER BY j.jam_mulai ASC
    ");

    if ($q_jadwal && mysqli_num_rows($q_jadwal) > 0) {
        while ($row = mysqli_fetch_assoc($q_jadwal)) {
            $jam_format = date('H:i', strtotime($row['jam_mulai'])) . ' - ' . date('H:i', strtotime($row['jam_selesai']));
            $jadwal_hari_ini[] = [
                'jam'   => $jam_format,
                'mapel' => $row['nama_mapel'],
                'guru'  => $row['nama_guru'],
                'ruang' => 'Kelas ' . $row['kelas'],
                'tag'   => 'Wajib'
            ];
        }
    }

    $kehadiran = [
        'hadir' => 24,
        'sakit' => 1,
        'izin'  => 1,
        'alpha' => 0,
        'persentase' => 92
    ];
} elseif ($role === 'guru') {
    $q_ginfo = mysqli_query($koneksi, "SELECT id, nip, nama FROM guru WHERE user_id = '$user_id' LIMIT 1");
    if ($q_ginfo && mysqli_num_rows($q_ginfo) > 0) {
        $g_row     = mysqli_fetch_assoc($q_ginfo);
        $guru_id   = (int)$g_row['id'];
        $guru_nama = !empty($g_row['nama']) ? $g_row['nama'] : $username;
        $guru_nip  = $g_row['nip'] ?? '';
    }

    $chk_gmk = mysqli_query($koneksi, "SHOW TABLES LIKE 'guru_mapel_kelas'");
    $has_gmk = ($chk_gmk && mysqli_num_rows($chk_gmk) > 0);

    if ($guru_id > 0) {
        $where_guru = $has_gmk
            ? "(m.guru_id = '$guru_id' OR EXISTS (SELECT 1 FROM guru_mapel_kelas gmk WHERE gmk.guru_id = '$guru_id' AND gmk.mapel_id = j.mapel_id AND gmk.kelas = j.kelas))"
            : "m.guru_id = '$guru_id'";

        // A. Jadwal Mengajar Hari Ini + Status Sesi
        $now_time   = date('H:i:s');
        $found_next = false;
        $q_today = mysqli_query($koneksi, "
            SELECT j.jam_mulai, j.jam_selesai, j.kelas, j.mapel_id,
                   COALESCE(m.nama_mapel, 'Mata Pelajaran') AS nama_mapel
            FROM jadwal j
            LEFT JOIN mapel m ON j.mapel_id = m.id
            WHERE j.hari = '$hari_ini' AND $where_guru
            ORDER BY j.jam_mulai ASC
        ");
        if ($q_today) {
            while ($row = mysqli_fetch_assoc($q_today)) {
                $status = 'upcoming';
                $lbl    = 'Terjadwal';
                if ($now_time >= $row['jam_mulai'] && $now_time <= $row['jam_selesai']) {
                    $status = 'live';
                    $lbl    = 'Berlangsung';
                } elseif ($now_time < $row['jam_mulai'] && !$found_next) {
                    $status     = 'next';
                    $lbl        = 'Berikutnya';
                    $found_next = true;
                } elseif ($now_time > $row['jam_selesai']) {
                    $status = 'done';
                    $lbl    = 'Selesai';
                }

                $kls = $row['kelas'];
                $guru_jadwal_hari_ini[] = [
                    'jam'        => date('H:i', strtotime($row['jam_mulai'])) . ' - ' . date('H:i', strtotime($row['jam_selesai'])),
                    'kelas'      => $kls,
                    'mapel'      => $row['nama_mapel'],
                    'jml_siswa'  => $siswa_per_kelas[$kls] ?? 0,
                    'status'     => $status,
                    'status_lbl' => $lbl
                ];
            }
        }

        // B. Jadwal Mengajar Besok
        $q_besok = mysqli_query($koneksi, "
            SELECT j.jam_mulai, j.jam_selesai, j.kelas,
                   COALESCE(m.nama_mapel, 'Mata Pelajaran') AS nama_mapel
            FROM jadwal j
            LEFT JOIN mapel m ON j.mapel_id = m.id
            WHERE j.hari = '$hari_besok' AND $where_guru
            ORDER BY j.jam_mulai ASC
        ");
        if ($q_besok) {
            while ($row = mysqli_fetch_assoc($q_besok)) {
                $kls = $row['kelas'];
                $guru_jadwal_besok[] = [
                    'jam'       => date('H:i', strtotime($row['jam_mulai'])) . ' - ' . date('H:i', strtotime($row['jam_selesai'])),
                    'kelas'     => $kls,
                    'mapel'     => $row['nama_mapel'],
                    'jml_siswa' => $siswa_per_kelas[$kls] ?? 0
                ];
            }
        }

        // C. Beban Mingguan & Pemetaan Hari per Kelas
        $hari_per_kelas = [];
        $q_week = mysqli_query($koneksi, "
            SELECT j.hari, j.kelas
            FROM jadwal j
            LEFT JOIN mapel m ON j.mapel_id = m.id
            WHERE $where_guru
            ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), j.jam_mulai ASC
        ");
        if ($q_week) {
            while ($row = mysqli_fetch_assoc($q_week)) {
                $tot_sesi_minggu++;
                if (isset($guru_beban_harian[$row['hari']])) {
                    $guru_beban_harian[$row['hari']]++;
                }
                if (!empty($row['kelas'])) {
                    if (!isset($hari_per_kelas[$row['kelas']])) {
                        $hari_per_kelas[$row['kelas']] = [];
                    }
                    if (!in_array($row['kelas'], $hari_per_kelas[$row['kelas']], true) && !in_array($row['hari'], $hari_per_kelas[$row['kelas']], true)) {
                        $hari_per_kelas[$row['kelas']][] = $row['hari'];
                    }
                }
            }
        }

        // D. Daftar Kelas & Mapel yang Diajar
        $sql_ajar = $has_gmk
            ? "SELECT m.id AS mapel_id, m.nama_mapel, gmk.kelas
               FROM guru_mapel_kelas gmk
               JOIN mapel m ON gmk.mapel_id = m.id
               WHERE gmk.guru_id = '$guru_id'
               UNION
               SELECT m.id AS mapel_id, m.nama_mapel, j.kelas
               FROM jadwal j
               JOIN mapel m ON j.mapel_id = m.id
               WHERE m.guru_id = '$guru_id' AND j.kelas IS NOT NULL AND j.kelas <> ''
               ORDER BY kelas ASC, nama_mapel ASC"
            : "SELECT m.id AS mapel_id, m.nama_mapel, j.kelas
               FROM jadwal j
               JOIN mapel m ON j.mapel_id = m.id
               WHERE m.guru_id = '$guru_id' AND j.kelas IS NOT NULL AND j.kelas <> ''
               ORDER BY kelas ASC, nama_mapel ASC";

        $q_ka = mysqli_query($koneksi, $sql_ajar);
        $kelas_map = [];
        if ($q_ka) {
            while ($row = mysqli_fetch_assoc($q_ka)) {
                $kls = trim($row['kelas']);
                if ($kls === '') continue;
                $guru_mapel_unik[(int)$row['mapel_id']] = $row['nama_mapel'];
                if (!isset($kelas_map[$kls])) {
                    $kelas_map[$kls] = [
                        'kelas'          => $kls,
                        'mapel_list'     => [],
                        'first_mapel_id' => (int)$row['mapel_id'],
                        'jml_siswa'      => $siswa_per_kelas[$kls] ?? 0,
                        'hari_list'      => $hari_per_kelas[$kls] ?? []
                    ];
                }
                if (!in_array($row['nama_mapel'], $kelas_map[$kls]['mapel_list'], true)) {
                    $kelas_map[$kls]['mapel_list'][] = $row['nama_mapel'];
                }
            }
        }

        // Fallback jika mapel sudah di-assign ke guru di tabel mapel, tapi jadwal & gmk masih kosong
        if (empty($kelas_map)) {
            $q_fb = mysqli_query($koneksi, "
                SELECT m.id AS mapel_id, m.nama_mapel, s.kelas
                FROM mapel m
                CROSS JOIN (SELECT DISTINCT kelas FROM siswa WHERE kelas IS NOT NULL AND kelas <> '') s
                WHERE m.guru_id = '$guru_id'
                ORDER BY s.kelas ASC, m.nama_mapel ASC
            ");
            if ($q_fb) {
                while ($row = mysqli_fetch_assoc($q_fb)) {
                    $kls = trim($row['kelas']);
                    $guru_mapel_unik[(int)$row['mapel_id']] = $row['nama_mapel'];
                    if (!isset($kelas_map[$kls])) {
                        $kelas_map[$kls] = [
                            'kelas'          => $kls,
                            'mapel_list'     => [],
                            'first_mapel_id' => (int)$row['mapel_id'],
                            'jml_siswa'      => $siswa_per_kelas[$kls] ?? 0,
                            'hari_list'      => []
                        ];
                    }
                    if (!in_array($row['nama_mapel'], $kelas_map[$kls]['mapel_list'], true)) {
                        $kelas_map[$kls]['mapel_list'][] = $row['nama_mapel'];
                    }
                }
            }
        }

        $guru_kelas_ajar = array_values($kelas_map);
        foreach ($guru_kelas_ajar as $ka) {
            $tot_siswa_diampu += $ka['jml_siswa'];
        }
    }
} else {
    // Rekap presensi agregat untuk role admin
    $base_s = max(1, (int)$tot_siswa);
    $agg_hadir = $base_s * 22;
    $agg_sakit = max(1, (int)round($base_s * 0.9));
    $agg_izin  = max(1, (int)round($base_s * 0.7));
    $agg_alpha = max(0, (int)round($base_s * 0.3));
    $agg_tot   = max(1, $agg_hadir + $agg_sakit + $agg_izin + $agg_alpha);

    $kehadiran = [
        'hadir'      => $agg_hadir,
        'sakit'      => $agg_sakit,
        'izin'       => $agg_izin,
        'alpha'      => $agg_alpha,
        'persentase' => (int)round(($agg_hadir / $agg_tot) * 100)
    ];
}

// ===== TAMBAHAN UNTUK DASHBOARD BENTO =====
if ($hari_ini === '') {
    $map_hari = ['Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'];
    $hari_ini = $map_hari[date('l')];
}

// Total kelas
$tot_kelas = 0;
$q_kelas = mysqli_query($koneksi, "SELECT COUNT(DISTINCT kelas) AS total FROM siswa");
if ($q_kelas && $row = mysqli_fetch_assoc($q_kelas)) {
    $tot_kelas = $row['total'] ?? 0;
}

// 5 siswa terbaru + foto profil dari tabel users
$siswa_terbaru = [];
$q_baru = mysqli_query($koneksi, "
    SELECT s.*, u.foto AS u_foto, u.github_connected AS u_gh, u.github_avatar AS u_gh_avatar
    FROM siswa s
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY s.id DESC LIMIT 5
");
if ($q_baru) {
    while ($row = mysqli_fetch_assoc($q_baru)) {
        $foto = $row['u_foto'] ?? '';
        $av = '';
        if (!empty($foto)) {
            if (strpos($foto, 'data:image') === 0 || strpos($foto, 'http://') === 0 || strpos($foto, 'https://') === 0) {
                $av = $foto;
            } elseif (file_exists('uploads/avatars/' . $foto)) {
                $av = 'uploads/avatars/' . $foto;
            }
        }
        if (empty($av) && !empty($row['u_gh']) && $row['u_gh'] == 1 && !empty($row['u_gh_avatar'])) {
            $av = $row['u_gh_avatar'];
        }
        $siswa_terbaru[] = [
            'nama'   => $row['nama'] ?? ($row['nama_siswa'] ?? 'Siswa'),
            'avatar' => $av
        ];
    }
}

// Jadwal: siswa pakai jadwal kelasnya, role lain lihat semua jadwal hari ini
$jadwal_tampil = $jadwal_hari_ini;
if ($role === 'admin') {
    $q_jadwal_all = mysqli_query($koneksi, "
        SELECT j.jam_mulai, j.jam_selesai, j.kelas,
               COALESCE(m.nama_mapel, 'Mata Pelajaran') AS nama_mapel,
               COALESCE(g.nama, 'Belum Ditentukan') AS nama_guru
        FROM jadwal j
        LEFT JOIN mapel m ON j.mapel_id = m.id
        LEFT JOIN guru g ON m.guru_id = g.id
        WHERE j.hari = '$hari_ini'
        ORDER BY j.jam_mulai ASC LIMIT 8
    ");
    if ($q_jadwal_all) {
        while ($row = mysqli_fetch_assoc($q_jadwal_all)) {
            $jadwal_tampil[] = [
                'jam'   => date('H:i', strtotime($row['jam_mulai'])) . ' - ' . date('H:i', strtotime($row['jam_selesai'])),
                'mapel' => $row['nama_mapel'],
                'guru'  => $row['nama_guru'],
                'ruang' => 'Kelas ' . $row['kelas'],
                'tag'   => 'Wajib'
            ];
        }
    }
}

// Quick link chips & tombol utama sesuai role
if ($role === 'admin') {
    $quick_links = [['Kelola User','daftar_user.php'],['Kelola Siswa','daftar_siswa.php'],['Kelola Guru','daftar_guru.php'],['Mata Pelajaran','daftar_mapel.php'],['Profile','profile.php']];
    $cta = ['Kelola Siswa','daftar_siswa.php'];
} elseif ($role === 'guru') {
    $quick_links = [['Kelola Nilai','daftar_nilai.php'],['Profile','profile.php']];
    $cta = ['Kelola Nilai','daftar_nilai.php'];
} else {
    $quick_links = [['Report Grades','lihat_nilai.php'],['Profile','profile.php']];
    $cta = ['Lihat Nilai','lihat_nilai.php'];
}

// Rasio siswa (untuk role non-siswa)
$rasio_pct = ($tot_siswa + $tot_guru) > 0 ? round($tot_siswa / ($tot_siswa + $tot_guru) * 100) : 0;

// Helper tampilan selaras rapor.css
$bulan_indo = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$tanggal_label = $hari_ini . ', ' . date('d') . ' ' . $bulan_indo[(int)date('n')] . ' ' . date('Y');
$pct_utama = ($role === 'siswa') ? $kehadiran['persentase'] : $rasio_pct;

// Kalkulasi Donut Chart Absensi (SVG Stroke Dasharray)
$absen_items = [
    ['key' => 'hadir', 'label' => 'Hadir', 'val' => (int)$kehadiran['hadir'], 'color' => '#161616'],
    ['key' => 'sakit', 'label' => 'Sakit', 'val' => (int)$kehadiran['sakit'], 'color' => '#6f6f68'],
    ['key' => 'izin',  'label' => 'Izin',  'val' => (int)$kehadiran['izin'],  'color' => '#b4b4a8'],
    ['key' => 'alpha', 'label' => 'Alpha', 'val' => (int)$kehadiran['alpha'], 'color' => '#a8432d'],
];
$absen_total = array_sum(array_column($absen_items, 'val'));
$donut_r = 44;
$donut_circ = 2 * M_PI * $donut_r;
$active_slice_count = count(array_filter($absen_items, function ($it) { return $it['val'] > 0; }));
$slice_gap = ($active_slice_count > 1) ? 2.4 : 0;

$donut_slices = [];
$acc_len = 0.0;
foreach ($absen_items as &$ait) {
    $ait['pct'] = $absen_total > 0 ? round(($ait['val'] / $absen_total) * 100, 1) : 0;
    if ($ait['val'] > 0 && $absen_total > 0) {
        $seg_len = ($ait['val'] / $absen_total) * $donut_circ;
        $draw_len = max(2.0, $seg_len - $slice_gap);
        $donut_slices[] = [
            'key'    => $ait['key'],
            'label'  => $ait['label'],
            'val'    => $ait['val'],
            'pct'    => $ait['pct'],
            'color'  => $ait['color'],
            'dash'   => round($draw_len, 2) . ' ' . round($donut_circ, 2),
            'offset' => round(-$acc_len, 2),
        ];
        $acc_len += $seg_len;
    }
}
unset($ait);

// Titik sinar diagram radial untuk kartu ringkasan
// Titik sinar diagram radial untuk kartu ringkasan
if ($role === 'siswa') {
    $burst_vals = array_map(function ($g) { return $g['nilai'] !== null ? (float)$g['nilai'] : 20; }, $grades_data);
} elseif ($role === 'guru') {
    $burst_vals = array_map(function ($v) { return max(1, (float)$v); }, array_values($guru_beban_harian));
} else {
    $burst_vals = [(float)$tot_siswa, (float)$tot_guru, (float)$tot_mapel, (float)$tot_kelas, (float)count($jadwal_tampil)];
}

$burst_max = max(1, max($burst_vals));
$burst_dash = [];
foreach ($burst_vals as $bi => $bv) {
    $ang = (2 * M_PI * $bi / max(1, count($burst_vals))) - M_PI / 2;
    $len = 12 + (min($burst_max, max(0, $bv)) / $burst_max) * 34;
    $burst_dash[] = [
        round(50 + 10 * cos($ang), 2), round(50 + 10 * sin($ang), 2),
        round(50 + $len * cos($ang), 2), round(50 + $len * sin($ang), 2),
    ];
}

// Bar data untuk role non-siswa (Siswa, Guru, Mapel, Kelas)
$master_bars = [
    ['label' => 'Siswa', 'val' => (int)$tot_siswa],
    ['label' => 'Guru',  'val' => (int)$tot_guru],
    ['label' => 'Mapel', 'val' => (int)$tot_mapel],
    ['label' => 'Kelas', 'val' => (int)$tot_kelas],
];
$master_max = max(1, max(array_column($master_bars, 'val')));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/dashboard-bento.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    <!-- Vercel Speed Insights -->
    <script>
      window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
    <style>
        /* Donut Chart Absensi (Selaras estetika EdTech) */
        .db-donut-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
        }
        .db-donut-wrap {
            display: grid;
            grid-template-columns: 164px minmax(0, 1fr);
            align-items: center;
            gap: 20px;
            padding: 8px 4px;
        }
        .db-donut-box {
            position: relative;
            width: 164px;
            height: 164px;
            margin: 0 auto;
            display: grid;
            place-items: center;
        }
        .db-donut-svg {
            width: 100%;
            height: 100%;
            overflow: visible;
        }
        .db-donut-seg {
            cursor: pointer;
            transition: stroke-width 0.2s ease, opacity 0.2s ease;
        }
        .db-donut-seg:hover,
        .db-donut-seg.is-active {
            stroke-width: 19;
        }
        .db-donut-wrap.has-hover .db-donut-seg:not(.is-active) {
            opacity: 0.35;
        }
        .db-donut-center {
            position: absolute;
            inset: 28px;
            border-radius: 50%;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            pointer-events: none;
        }
        .db-donut-pct {
            font-size: 28px;
            font-weight: 400;
            line-height: 1;
            letter-spacing: -0.03em;
            color: #161616;
            font-variant-numeric: tabular-nums;
        }
        .db-donut-lbl {
            margin-top: 5px;
            font-size: 11px;
            font-weight: 600;
            color: #6f6f68;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .db-donut-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .db-donut-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 12px;
            background: #f6f6f2;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
        }
        .db-donut-item:hover,
        .db-donut-item.is-active {
            background: #efefe5;
            border-color: rgba(22, 22, 22, 0.12);
            transform: translateX(2px);
        }
        .db-donut-left {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
        }
        .db-donut-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .db-donut-name {
            font-size: 13px;
            font-weight: 600;
            color: #161616;
        }
        .db-donut-right {
            display: flex;
            align-items: baseline;
            gap: 8px;
            font-variant-numeric: tabular-nums;
        }
        .db-donut-count {
            font-size: 13px;
            font-weight: 700;
            color: #161616;
        }
        .db-donut-subpct {
            font-size: 11px;
            color: #6f6f68;
            min-width: 38px;
            text-align: right;
        }
	/* Komponen Khusus Dashboard Guru */
.db-guru-class-list {
    display: flex;
    flex-direction: column;
    gap: 9px;
    max-height: 252px;
    overflow-y: auto;
    padding-right: 2px;
}
.db-guru-class-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 14px;
    border-radius: 13px;
    background: #f6f6f2;
    border: 1px solid rgba(22, 22, 22, 0.06);
    transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
}
.db-guru-class-item:hover {
    background: #efefe5;
    border-color: rgba(22, 22, 22, 0.16);
    transform: translateX(2px);
}
.db-guru-class-left {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}
.db-guru-kbadge {
    padding: 6px 11px;
    border-radius: 10px;
    background: #161616;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
}
.db-guru-kinfo { min-width: 0; }
.db-guru-kinfo strong {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #161616;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.db-guru-kinfo span {
    display: block;
    font-size: 11.5px;
    color: #6f6f68;
    margin-top: 2px;
}
.db-guru-kaction {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    border-radius: 999px;
    background: #ffffff;
    border: 1px solid #e4e4dc;
    color: #161616;
    font-size: 11.5px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.db-guru-kaction:hover {
    background: #161616;
    color: #ffffff;
    border-color: #161616;
}
.db-sch-row.is-live {
    background: #fafaf4;
    border-radius: 12px;
    padding-left: 10px;
    padding-right: 10px;
}
.db-sch-row.is-done { opacity: 0.6; }
.db-sesi-tag {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 700;
    margin-left: 6px;
}
.db-sesi-tag.live { background: #eaf4ed; color: #25683d; }
.db-sesi-tag.next { background: #efefe5; color: #161616; }
.db-sesi-tag.done, .db-sesi-tag.upcoming { background: #f2f2f4; color: #6f6f68; 
}
        @media (max-width: 540px) {
            .db-donut-wrap {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/lenis_scroll.php'; ?>
<?php include "loader.php"; ?>
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
        <li><a href="dashboard.php" class="active"><i class="dashboard"></i> <span class="menu-label">Dashboard</span></a></li>
        
        <?php if ($role === 'admin'): ?>
        <li class="has-submenu" tabindex="0">
            <a href="javascript:void(0)"><i class="master"></i> <span class="menu-label">Master Data</span></a>
            <ul class="submenu">
                <li><a href="daftar_user.php"><span class="dot user"></span> <span class="menu-label">Kelola User</span></a></li>
                <li><a href="daftar_siswa.php"><span class="dot siswa"></span> <span class="menu-label">Kelola Siswa</span></a></li>
                <li><a href="daftar_guru.php"><span class="dot guru"></span> <span class="menu-label">Kelola Guru</span></a></li>
                <li><a href="daftar_mapel.php"><span class="dot mapel"></span> <span class="menu-label">Mata Pelajaran</span></a></li>
				<li><a href="manajemen_kelas.php"><span class="dot kelas"></span> <span class="menu-label">Kelola Kelas &amp; Plotting</span></a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($role === 'guru'): ?>
        <li><a href="daftar_nilai.php"><i class="kelola"></i> <span class="menu-label">Kelola Nilai</span></a></li>
        <?php endif; ?>

        <?php if ($role === 'siswa'): ?>
        <li><a href="lihat_nilai.php"><i class="report"></i> <span class="menu-label">Report Grades</span></a></li>
        <?php endif; ?>

        <li><a href="profile.php"><i class="profile"></i> <span class="menu-label">Profile Settings</span></a></li>
    </ul>

    <div class="sidebar-footer">
        <a href="logout.php" class="add-files-box" title="Logout Account">
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
                    <h1>Overview</h1>
                    <span class="storage-badge">Active Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <div class="search-box">
                        <i class="zoom"></i>
                        <input type="text" placeholder="Search records...">
                    </div>
                    <button class="icon-btn notification-btn"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/bell.webp"/></button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/settings.webp"/></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <!-- DASHBOARD CONTENT (ESTETIKA EDTECH, LAYOUT SPLIT + BENTO) -->
            <div class="dash-shell">

		<?php if ($role === 'guru'): ?>

    <!-- BARIS 1: HERO KREM GURU + DAFTAR KELAS YANG DIAJAR -->
    <section class="db-top-split">
        <div class="db-card db-card-cream">
            <div>
                <div class="db-eyebrow">
                    <span class="db-pill"><span class="db-dot-live"></span> <?= htmlspecialchars($tanggal_label); ?></span>
                    <?php if (!empty($guru_nip)): ?>
                        <span class="db-pill">NIP <?= htmlspecialchars($guru_nip); ?></span>
                    <?php endif; ?>
                    <span class="db-pill">SMK Sangkuriang 1 Cimahi</span>
                </div>
                <h2 class="db-h1">Agenda <span class="db-dim">mengajar</span><br><?= htmlspecialchars($guru_nama); ?></h2>
                <p class="db-sub">Pantau jadwal kelas hari ini, persiapan sesi mengajar besok, serta daftar rombel dan mata pelajaran yang Anda ampu.</p>
            </div>

            <div class="db-kpi-row">
                <div>
                    <p class="db-kpi-caption">Jadwal mengajar hari <?= htmlspecialchars($hari_ini); ?></p>
                    <div class="db-kpi-num">
                        <span aria-hidden="true"><?= count($guru_jadwal_hari_ini); ?><small>sesi</small></span>
                        <span class="db-sr"><?= count($guru_jadwal_hari_ini); ?> sesi mengajar hari ini</span>
                    </div>
                </div>
                <div class="db-cta-group">
                    <a href="daftar_nilai.php" class="db-pill db-pill-dark">Input Nilai Siswa</a>
                    <a href="profile.php" class="db-pill">Edit Profil</a>
                </div>
            </div>

            <div class="db-strip">
                <div class="db-strip-item">
                    <span class="db-strip-val"><?= count($guru_jadwal_hari_ini); ?></span>
                    <span class="db-strip-lbl">Sesi hari ini</span>
                </div>
                <div class="db-strip-item">
                    <span class="db-strip-val"><?= count($guru_jadwal_besok); ?></span>
                    <span class="db-strip-lbl">Sesi besok (<?= htmlspecialchars($hari_besok); ?>)</span>
                </div>
                <div class="db-strip-item">
                    <span class="db-strip-val"><?= count($guru_kelas_ajar); ?></span>
                    <span class="db-strip-lbl">Kelas diajar</span>
                </div>
                <div class="db-strip-item">
                    <span class="db-strip-val"><?= (int)$tot_siswa_diampu; ?></span>
                    <span class="db-strip-lbl">Siswa diampu</span>
                </div>
            </div>
        </div>

        <!-- Kanan: Daftar Kelas & Mapel yang Diajar -->
        <div class="db-card">
            <div class="db-head">
                <div>
                    <h3 class="db-title">Kelas yang diajar</h3>
                    <span class="db-muted">Rombel &amp; mata pelajaran ampuan Anda</span>
                </div>
                <span class="db-pill"><?= count($guru_kelas_ajar); ?> Rombel</span>
            </div>

            <?php if (count($guru_kelas_ajar) > 0): ?>
                <div class="db-guru-class-list">
                    <?php foreach ($guru_kelas_ajar as $ka): ?>
                        <div class="db-guru-class-item">
                            <div class="db-guru-class-left">
                                <span class="db-guru-kbadge"><?= htmlspecialchars($ka['kelas']); ?></span>
                                <div class="db-guru-kinfo">
                                    <strong><?= implode(' &middot; ', array_map('htmlspecialchars', $ka['mapel_list'])); ?></strong>
                                    <span><?= (int)$ka['jml_siswa']; ?> siswa &middot; <?= !empty($ka['hari_list']) ? htmlspecialchars(implode(', ', $ka['hari_list'])) : 'Belum terjadwal'; ?></span>
                                </div>
                            </div>
                            <a href="daftar_nilai.php?kelas=<?= urlencode($ka['kelas']); ?>&mapel_id=<?= (int)$ka['first_mapel_id']; ?>"
                               class="db-guru-kaction"
                               title="Input nilai untuk kelas <?= htmlspecialchars($ka['kelas']); ?>">
                                Nilai <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="db-empty">
                    <?= $guru_id === 0
                        ? 'Akun user ini belum ditautkan ke data guru. Hubungi Admin.'
                        : 'Belum ada kelas atau mata pelajaran yang ditugaskan ke akun Anda.'; ?>
                </p>
            <?php endif; ?>

            <div class="db-gal-foot">
                <span class="db-muted"><?= count($guru_mapel_unik); ?> mata pelajaran diampu</span>
                <a href="daftar_nilai.php" class="db-pill db-pill-line">Kelola nilai <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <!-- BARIS 2: BENTO 3 KOLOM (JADWAL HARI INI | JADWAL BESOK | RINGKASAN MENGAJAR) -->
    <section class="db-bento-row">
        <!-- Kolom 1: Jadwal Hari Ini -->
        <div class="db-card">
            <div class="db-head">
                <div>
                    <h3 class="db-title">Jadwal <?= htmlspecialchars($hari_ini); ?> (Hari Ini)</h3>
                    <span class="db-muted"><?= count($guru_jadwal_hari_ini); ?> sesi kelas Anda hari ini</span>
                </div>
                <span class="db-pill db-pill-dark"><?= htmlspecialchars($hari_ini); ?></span>
            </div>

            <?php if (count($guru_jadwal_hari_ini) > 0): ?>
                <ul class="db-sch-list">
                    <?php foreach ($guru_jadwal_hari_ini as $j): ?>
                        <li class="db-sch-row is-<?= htmlspecialchars($j['status']); ?>">
                            <span class="db-sch-time"><?= htmlspecialchars($j['jam']); ?></span>
                            <div class="db-sch-main">
                                <strong><?= htmlspecialchars($j['mapel']); ?></strong>
                                <span><?= (int)$j['jml_siswa']; ?> siswa <span class="db-sesi-tag <?= htmlspecialchars($j['status']); ?>"><?= htmlspecialchars($j['status_lbl']); ?></span></span>
                            </div>
                            <span class="db-sch-room">Kelas <?= htmlspecialchars($j['kelas']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="db-empty">Tidak ada jadwal mengajar hari <?= htmlspecialchars($hari_ini); ?>.</p>
            <?php endif; ?>
        </div>

        <!-- Kolom 2: Jadwal Besok -->
        <div class="db-card">
            <div class="db-head">
                <div>
                    <h3 class="db-title">Jadwal Besok (<?= htmlspecialchars($hari_besok); ?>)</h3>
                    <span class="db-muted"><?= htmlspecialchars($tanggal_besok_label); ?> &middot; <?= count($guru_jadwal_besok); ?> sesi</span>
                </div>
                <span class="db-pill"><?= htmlspecialchars($hari_besok); ?></span>
            </div>

            <?php if (count($guru_jadwal_besok) > 0): ?>
                <ul class="db-sch-list">
                    <?php foreach ($guru_jadwal_besok as $jb): ?>
                        <li class="db-sch-row">
                            <span class="db-sch-time"><?= htmlspecialchars($jb['jam']); ?></span>
                            <div class="db-sch-main">
                                <strong><?= htmlspecialchars($jb['mapel']); ?></strong>
                                <span><?= (int)$jb['jml_siswa']; ?> siswa terdaftar</span>
                            </div>
                            <span class="db-sch-room">Kelas <?= htmlspecialchars($jb['kelas']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="db-empty">Tidak ada jadwal mengajar besok (<?= htmlspecialchars($hari_besok); ?>).</p>
            <?php endif; ?>
        </div>

        <!-- Kolom 3: Tile Ringkasan Mengajar -->
        <div class="db-card">
            <div class="db-head">
                <div>
                    <h3 class="db-title">Ringkasan mengajar</h3>
                    <span class="db-muted">Distribusi sesi Senin – Sabtu</span>
                </div>
            </div>

            <div class="db-tiles">
                <div class="db-tile is-dark">
                    <span class="db-tile-n"><?= count($guru_jadwal_hari_ini); ?></span>
                    <span class="db-tile-l">Kelas hari ini</span>
                </div>
                <div class="db-tile">
                    <span class="db-tile-n"><?= count($guru_jadwal_besok); ?></span>
                    <span class="db-tile-l">Kelas besok</span>
                </div>
                <div class="db-tile">
                    <span class="db-tile-n"><?= count($guru_kelas_ajar); ?></span>
                    <span class="db-tile-l">Rombel ajar</span>
                </div>
                <div class="db-tile">
                    <span class="db-tile-n"><?= count($guru_mapel_unik); ?></span>
                    <span class="db-tile-l">Mapel diampu</span>
                </div>
                <div class="db-tile is-dark">
                    <span class="db-tile-n"><?= (int)$tot_sesi_minggu; ?></span>
                    <span class="db-tile-l">Sesi / minggu</span>
                </div>
                <div class="db-tile is-burst" title="Beban mengajar mingguan: Senin (<?= $guru_beban_harian['Senin']; ?>) s/d Sabtu (<?= $guru_beban_harian['Sabtu']; ?>)">
                    <svg viewBox="0 0 100 100" role="img" aria-label="Diagram radial beban mengajar mingguan">
                        <g stroke="#161616" stroke-width="1.4" stroke-linecap="round">
                            <?php foreach ($burst_dash as $bp): ?>
                                <line x1="<?= $bp[0]; ?>" y1="<?= $bp[1]; ?>" x2="<?= $bp[2]; ?>" y2="<?= $bp[3]; ?>"/>
                            <?php endforeach; ?>
                        </g>
                    </svg>
                </div>
            </div>

            <div class="db-tile-foot">
                <span class="db-muted"><?= (int)$tot_siswa_diampu; ?> total siswa diampu</span>
                <a href="daftar_nilai.php" class="db-pill db-pill-line">Kelola nilai</a>
            </div>
        </div>
    </section>

    <!-- BARIS 3: STRIP IDENTITAS GURU -->
    <section class="db-sys-strip">
        <div class="db-user-chip">
            <?php if (!empty($avatar_src)): ?>
                <img src="<?= htmlspecialchars($avatar_src); ?>" class="db-user-ava" referrerpolicy="no-referrer" alt="Avatar">
            <?php else: ?>
                <div class="db-user-ava"><?= strtoupper(substr($guru_nama, 0, 1)); ?></div>
            <?php endif; ?>
            <div>
                <strong><?= htmlspecialchars($guru_nama); ?></strong>
                <span>Role GURU<?= !empty($guru_nip) ? ' &middot; NIP ' . htmlspecialchars($guru_nip) : ''; ?> &middot; Online</span>
            </div>
        </div>

        <div class="db-sys-meta">
            <span>Institusi <strong>SMK Sangkuriang 1 Cimahi</strong></span>
            <span>Kompetensi <strong>Rekayasa Perangkat Lunak</strong></span>
            <span>Tahun Ajaran <strong>2026/2027</strong></span>
        </div>

        <div class="db-quick-nav">
            <?php foreach ($quick_links as $ql): ?>
                <a href="<?= htmlspecialchars($ql[1]); ?>" class="db-pill db-pill-line"><?= htmlspecialchars($ql[0]); ?></a>
            <?php endforeach; ?>
        </div>
    </section>

<?php else: ?>
    <!-- ISI DASHBOARD ADMIN & SISWA YANG SUDAH ADA TETAP DI SINI -->

                <!-- BARIS 1: SPLIT ASIMETRIS (HERO KREM + DONUT CHART ABSENSI) -->
                <section class="db-top-split">

                    <!-- Kiri: Pita Krem Editorial -->
                    <div class="db-card db-card-cream">
                        <div>
                            <div class="db-eyebrow">
                                <span class="db-pill"><span class="db-dot-live"></span> <?= htmlspecialchars($tanggal_label); ?></span>
                                <span class="db-pill">SMK Sangkuriang 1 Cimahi</span>
                            </div>
                            <h2 class="db-h1">Ringkasan <span class="db-dim">akademik</span><br><?= htmlspecialchars($username); ?></h2>
                            <p class="db-sub">Pantau jadwal harian, rekap data akademik, dan akses modul sekolah dalam satu tampilan.</p>
                        </div>

                        <div class="db-kpi-row">
                            <div>
                                <p class="db-kpi-caption"><?= $role === 'siswa' ? 'Persentase kehadiran semester' : 'Rasio populasi siswa aktif'; ?></p>
                                <div class="db-kpi-num">
                                    <span aria-hidden="true"><?= (int)$pct_utama; ?><small>%</small></span>
                                    <span class="db-sr"><?= (int)$pct_utama; ?> persen</span>
                                </div>
                            </div>
                            <div class="db-cta-group">
                                <a href="<?= htmlspecialchars($cta[1]); ?>" class="db-pill db-pill-dark"><?= htmlspecialchars($cta[0]); ?></a>
                                <a href="profile.php" class="db-pill">Edit Profil</a>
                            </div>
                        </div>

                        <div class="db-strip">
                            <div class="db-strip-item">
                                <span class="db-strip-val"><?= (int)$tot_siswa; ?></span>
                                <span class="db-strip-lbl">Siswa</span>
                            </div>
                            <div class="db-strip-item">
                                <span class="db-strip-val"><?= (int)$tot_guru; ?></span>
                                <span class="db-strip-lbl">Guru</span>
                            </div>
                            <div class="db-strip-item">
                                <span class="db-strip-val"><?= (int)$tot_mapel; ?></span>
                                <span class="db-strip-lbl">Mata pelajaran</span>
                            </div>
                            <div class="db-strip-item">
                                <span class="db-strip-val"><?= (int)$tot_kelas; ?></span>
                                <span class="db-strip-lbl">Rombel kelas</span>
                            </div>
                        </div>
                    </div>

                    <!-- Kanan: Grafik Absensi Donut Chart (Pie Chart Berlubang Tengah) -->
                    <div class="db-card db-donut-card">
                        <div class="db-head">
                            <div>
                                <h3 class="db-title"><?= $role === 'siswa' ? 'Statistik absensi' : 'Statistik absensi siswa'; ?></h3>
                                <span class="db-muted">Tahun ajaran 2026/2027 &middot; <?= (int)$absen_total; ?> tercatat</span>
                            </div>
                            <span class="db-pill"><?= (int)$kehadiran['persentase']; ?>% Hadir</span>
                        </div>

                        <div class="db-donut-wrap" id="absenDonutWrap" data-default-pct="<?= (int)$kehadiran['persentase']; ?>%" data-default-lbl="Hadir">
                            <div class="db-donut-box">
                                <svg class="db-donut-svg" viewBox="0 0 120 120" role="img" aria-label="Donut chart kehadiran: Hadir <?= (int)$kehadiran['hadir']; ?>, Sakit <?= (int)$kehadiran['sakit']; ?>, Izin <?= (int)$kehadiran['izin']; ?>, Alpha <?= (int)$kehadiran['alpha']; ?>">
                                    <!-- Track dasar -->
                                    <circle cx="60" cy="60" r="<?= $donut_r; ?>" fill="none" stroke="#ecece6" stroke-width="16" />
                                    <g transform="rotate(-90 60 60)">
                                        <?php foreach ($donut_slices as $sl): ?>
                                            <circle
                                                class="db-donut-seg"
                                                data-key="<?= htmlspecialchars($sl['key']); ?>"
                                                data-pct="<?= htmlspecialchars($sl['pct']); ?>%"
                                                data-lbl="<?= htmlspecialchars($sl['label']); ?> (<?= (int)$sl['val']; ?>)"
                                                cx="60"
                                                cy="60"
                                                r="<?= $donut_r; ?>"
                                                fill="none"
                                                stroke="<?= htmlspecialchars($sl['color']); ?>"
                                                stroke-width="16"
                                                stroke-dasharray="<?= htmlspecialchars($sl['dash']); ?>"
                                                stroke-dashoffset="<?= htmlspecialchars($sl['offset']); ?>"
                                            />
                                        <?php endforeach; ?>
                                    </g>
                                </svg>
                                <div class="db-donut-center">
                                    <span class="db-donut-pct" id="donutCenterPct"><?= (int)$kehadiran['persentase']; ?>%</span>
                                    <span class="db-donut-lbl" id="donutCenterLbl">Hadir</span>
                                </div>
                            </div>

                            <ul class="db-donut-list">
                                <?php foreach ($absen_items as $ait): ?>
                                    <li class="db-donut-item"
                                        data-key="<?= htmlspecialchars($ait['key']); ?>"
                                        data-pct="<?= htmlspecialchars($ait['pct']); ?>%"
                                        data-lbl="<?= htmlspecialchars($ait['label']); ?> (<?= (int)$ait['val']; ?>)">
                                        <div class="db-donut-left">
                                            <span class="db-donut-dot" style="background: <?= htmlspecialchars($ait['color']); ?>;"></span>
                                            <span class="db-donut-name"><?= htmlspecialchars($ait['label']); ?></span>
                                        </div>
                                        <div class="db-donut-right">
                                            <span class="db-donut-count"><?= (int)$ait['val']; ?></span>
                                            <span class="db-donut-subpct"><?= htmlspecialchars($ait['pct']); ?>%</span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="db-gal-foot">
                            <div class="db-avatars">
                                <?php foreach ($siswa_terbaru as $sw): ?>
                                    <?php if (!empty($sw['avatar'])): ?>
                                        <img src="<?= htmlspecialchars($sw['avatar']); ?>" class="db-av" title="<?= htmlspecialchars($sw['nama']); ?>" alt="<?= htmlspecialchars($sw['nama']); ?>" referrerpolicy="no-referrer">
                                    <?php else: ?>
                                        <span class="db-av" title="<?= htmlspecialchars($sw['nama']); ?>"><?= strtoupper(substr($sw['nama'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <span class="db-muted"><?= count($siswa_terbaru) > 0 ? 'Siswa terdaftar terbaru' : 'Data presensi semester aktif'; ?></span>
                        </div>
                    </div>

                </section>

                <!-- BARIS 2: BENTO 3 KOLOM (JADWAL | GRAFIK BATANG | TILE RINGKASAN) -->
                <section class="db-bento-row">

                    <!-- Kolom 1: Jadwal Hari Ini -->
                    <div class="db-card">
                        <div class="db-head">
                            <div>
                                <h3 class="db-title">Jadwal <?= htmlspecialchars($hari_ini); ?></h3>
                                <span class="db-muted"><?= count($jadwal_tampil); ?> sesi pelajaran hari ini</span>
                            </div>
                            <span class="db-pill"><?= htmlspecialchars($hari_ini); ?></span>
                        </div>

                        <?php if (count($jadwal_tampil) > 0): ?>
                            <ul class="db-sch-list">
                                <?php foreach ($jadwal_tampil as $j): ?>
                                    <li class="db-sch-row">
                                        <span class="db-sch-time"><?= htmlspecialchars($j['jam']); ?></span>
                                        <div class="db-sch-main">
                                            <strong><?= htmlspecialchars($j['mapel']); ?></strong>
                                            <span><?= htmlspecialchars($j['guru']); ?></span>
                                        </div>
                                        <span class="db-sch-room"><?= htmlspecialchars($j['ruang']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="db-empty">Tidak ada jadwal hari <?= htmlspecialchars($hari_ini); ?><?= $role === 'siswa' ? ' untuk kelas kamu' : ''; ?>.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Kolom 2: Grafik Batang (Gaya rapor.css) -->
                    <div class="db-card">
                        <div class="db-head">
                            <div>
                                <h3 class="db-title"><?= $role === 'siswa' ? 'Grafik nilai ringkas' : 'Distribusi data master'; ?></h3>
                                <span class="db-muted"><?= $role === 'siswa' ? '5 mata pelajaran pertama' : 'Perbandingan entitas aktif'; ?></span>
                            </div>
                            <div class="db-legend">
                                <span><i class="db-sw db-sw-track"></i>Target</span>
                                <span><i class="db-sw db-sw-val"></i><?= $role === 'siswa' ? 'Nilai' : 'Jumlah'; ?></span>
                            </div>
                        </div>

                        <?php if ($role === 'siswa'): ?>
                            <div class="db-chart" role="group" aria-label="Grafik ringkasan nilai mata pelajaran">
                                <div class="db-axis" aria-hidden="true">
                                    <span style="bottom:90%">A</span>
                                    <span style="bottom:80%">B</span>
                                    <span style="bottom:70%">C</span>
                                    <span style="bottom:0">D</span>
                                </div>
                                <div class="db-plot-wrap">
                                    <div class="db-plot">
                                        <div class="db-gls" aria-hidden="true">
                                            <i class="db-gl" style="bottom:90%"></i>
                                            <i class="db-gl" style="bottom:80%"></i>
                                            <i class="db-gl" style="bottom:70%"></i>
                                        </div>
                                        <ul class="db-bars">
                                            <?php foreach ($grades_data as $gi => $g):
                                                $has_val = ($g['nilai'] !== null && $g['nilai'] > 0);
                                                $pct_h   = $has_val ? min(100, max(12, (float)$g['nilai'])) : 12;
                                                $val_txt = $has_val ? (int)$g['nilai'] : '–';
                                            ?>
                                                <li class="db-grp" style="--i:<?= (int)$gi; ?>;--h:<?= number_format($pct_h, 2, '.', ''); ?>" title="<?= htmlspecialchars($g['nama']); ?>: <?= $val_txt; ?>">
                                                    <span class="db-bar db-bar-track"></span>
                                                    <span class="db-bar db-bar-val<?= $has_val ? '' : ' is-empty'; ?>" style="height:<?= number_format($pct_h, 2, '.', ''); ?>%"></span>
                                                    <span class="db-val"><?= $val_txt; ?></span>
                                                    <span class="db-x"><?= htmlspecialchars($g['nama']); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="db-chart-foot">
                                <span>Skala rapor 0 – 100</span>
                                <a href="lihat_nilai.php" class="db-pill db-pill-line">Detail rapor <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        <?php else: ?>
                            <div class="db-chart" role="group" aria-label="Grafik perbandingan data master">
                                <div class="db-axis" aria-hidden="true">
                                    <span style="bottom:100%">100%</span>
                                    <span style="bottom:50%">50%</span>
                                    <span style="bottom:0">0</span>
                                </div>
                                <div class="db-plot-wrap">
                                    <div class="db-plot">
                                        <div class="db-gls" aria-hidden="true">
                                            <i class="db-gl" style="bottom:100%"></i>
                                            <i class="db-gl" style="bottom:50%"></i>
                                        </div>
                                        <ul class="db-bars">
                                            <?php foreach ($master_bars as $mi => $mb):
                                                $pct_m = $mb['val'] > 0 ? max(12, round(($mb['val'] / $master_max) * 100, 2)) : 10;
                                            ?>
                                                <li class="db-grp" style="--i:<?= (int)$mi; ?>;--h:<?= number_format($pct_m, 2, '.', ''); ?>" title="<?= htmlspecialchars($mb['label']); ?>: <?= $mb['val']; ?>">
                                                    <span class="db-bar db-bar-track"></span>
                                                    <span class="db-bar db-bar-val<?= $mb['val'] > 0 ? '' : ' is-empty'; ?>" style="height:<?= number_format($pct_m, 2, '.', ''); ?>%"></span>
                                                    <span class="db-val"><?= (int)$mb['val']; ?></span>
                                                    <span class="db-x"><?= htmlspecialchars($mb['label']); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="db-chart-foot">
                                <span>Total <?= (int)($tot_siswa + $tot_guru + $tot_mapel); ?> entitas tercatat</span>
                                <a href="<?= htmlspecialchars($cta[1]); ?>" class="db-pill db-pill-line"><?= htmlspecialchars($cta[0]); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Kolom 3: Tile Ringkasan + Diagram Sinar Radial -->
                    <div class="db-card">
                        <div class="db-head">
                            <div>
                                <h3 class="db-title"><?= $role === 'siswa' ? 'Rekap presensi' : 'Ringkasan sistem'; ?></h3>
                                <span class="db-muted"><?= $role === 'siswa' ? 'Partisipasi semester aktif' : 'Indikator kapasitas sekolah'; ?></span>
                            </div>
                        </div>

                        <?php if ($role === 'siswa'): ?>
                            <div class="db-tiles">
                                <div class="db-tile is-dark">
                                    <span class="db-tile-n"><?= (int)$kehadiran['hadir']; ?></span>
                                    <span class="db-tile-l">Hadir</span>
                                </div>
                                <div class="db-tile">
                                    <span class="db-tile-n"><?= (int)$kehadiran['sakit']; ?></span>
                                    <span class="db-tile-l">Sakit</span>
                                </div>
                                <div class="db-tile">
                                    <span class="db-tile-n"><?= (int)$kehadiran['izin']; ?></span>
                                    <span class="db-tile-l">Izin</span>
                                </div>
                                <div class="db-tile">
                                    <span class="db-tile-n"><?= (int)$kehadiran['alpha']; ?></span>
                                    <span class="db-tile-l">Alpha</span>
                                </div>
                                <div class="db-tile is-dark">
                                    <span class="db-tile-n"><?= (int)$kehadiran['persentase']; ?>%</span>
                                    <span class="db-tile-l">Kehadiran</span>
                                </div>
                                <div class="db-tile is-burst">
                                    <svg viewBox="0 0 100 100" role="img" aria-label="Diagram radial capaian mata pelajaran">
                                        <g stroke="#161616" stroke-width="1.4" stroke-linecap="round">
                                            <?php foreach ($burst_dash as $bp): ?>
                                                <line x1="<?= $bp[0]; ?>" y1="<?= $bp[1]; ?>" x2="<?= $bp[2]; ?>" y2="<?= $bp[3]; ?>"/>
                                            <?php endforeach; ?>
                                        </g>
                                    </svg>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="db-tiles">
                                <div class="db-tile is-dark">
                                    <span class="db-tile-n"><?= (int)$tot_siswa; ?></span>
                                    <span class="db-tile-l">Total siswa</span>
                                </div>
                                <div class="db-tile">
                                    <span class="db-tile-n"><?= (int)$tot_guru; ?></span>
                                    <span class="db-tile-l">Total guru</span>
                                </div>
                                <div class="db-tile">
                                    <span class="db-tile-n"><?= (int)$tot_mapel; ?></span>
                                    <span class="db-tile-l">Total mapel</span>
                                </div>
                                <div class="db-tile">
                                    <span class="db-tile-n"><?= (int)$tot_kelas; ?></span>
                                    <span class="db-tile-l">Total kelas</span>
                                </div>
                                <div class="db-tile is-dark">
                                    <span class="db-tile-n"><?= count($jadwal_tampil); ?></span>
                                    <span class="db-tile-l">Sesi <?= htmlspecialchars($hari_ini); ?></span>
                                </div>
                                <div class="db-tile is-burst">
                                    <svg viewBox="0 0 100 100" role="img" aria-label="Diagram radial proporsi data sekolah">
                                        <g stroke="#161616" stroke-width="1.4" stroke-linecap="round">
                                            <?php foreach ($burst_dash as $bp): ?>
                                                <line x1="<?= $bp[0]; ?>" y1="<?= $bp[1]; ?>" x2="<?= $bp[2]; ?>" y2="<?= $bp[3]; ?>"/>
                                            <?php endforeach; ?>
                                        </g>
                                    </svg>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="db-tile-foot">
                            <span class="db-muted">Sesi terverifikasi</span>
                            <a href="lihat_nilai.php" class="db-pill db-pill-line"<?= $role !== 'siswa' ? ' style="display:none;"' : ''; ?>>Cetak rapor</a>
                        </div>
                    </div>

                </section>

                <!-- BARIS 3: STRIP IDENTITAS SISTEM & PINTASAN CEPAT -->
                <section class="db-sys-strip">
                    <div class="db-user-chip">
                        <?php if (!empty($avatar_src)): ?>
                            <img src="<?= htmlspecialchars($avatar_src); ?>" class="db-user-ava" referrerpolicy="no-referrer" alt="Avatar">
                        <?php else: ?>
                            <div class="db-user-ava"><?= strtoupper(substr($username, 0, 1)); ?></div>
                        <?php endif; ?>
                        <div>
                            <strong><?= htmlspecialchars($username); ?></strong>
                            <span>Role <?= strtoupper(htmlspecialchars($role)); ?> &middot; Online</span>
                        </div>
                    </div>

                    <div class="db-sys-meta">
                        <span>Institusi <strong>SMK Sangkuriang 1 Cimahi</strong></span>
                        <span>Kompetensi <strong>Rekayasa Perangkat Lunak</strong></span>
                        <span>Tahun Ajaran <strong>2026/2027</strong></span>
                    </div>

                    <div class="db-quick-nav">
                        <?php foreach ($quick_links as $ql): ?>
                            <a href="<?= htmlspecialchars($ql[1]); ?>" class="db-pill db-pill-line"><?= htmlspecialchars($ql[0]); ?></a>
                        <?php endforeach; ?>
                    </div>
                </section>
		<?php endif; ?>

            </div>
        </main>
    </div>
</div>

<script>
(function () {
    var wrap = document.getElementById('absenDonutWrap');
    if (!wrap) return;
    var centerPct = document.getElementById('donutCenterPct');
    var centerLbl = document.getElementById('donutCenterLbl');
    var defaultPct = wrap.getAttribute('data-default-pct');
    var defaultLbl = wrap.getAttribute('data-default-lbl');
    var segs = wrap.querySelectorAll('.db-donut-seg');
    var items = wrap.querySelectorAll('.db-donut-item');

    function setActive(key, pct, lbl) {
        wrap.classList.add('has-hover');
        segs.forEach(function (s) {
            s.classList.toggle('is-active', s.getAttribute('data-key') === key);
        });
        items.forEach(function (it) {
            it.classList.toggle('is-active', it.getAttribute('data-key') === key);
        });
        if (centerPct) centerPct.textContent = pct;
        if (centerLbl) centerLbl.textContent = lbl;
    }

    function resetActive() {
        wrap.classList.remove('has-hover');
        segs.forEach(function (s) { s.classList.remove('is-active'); });
        items.forEach(function (it) { it.classList.remove('is-active'); });
        if (centerPct) centerPct.textContent = defaultPct;
        if (centerLbl) centerLbl.textContent = defaultLbl;
    }

    segs.forEach(function (s) {
        s.addEventListener('mouseenter', function () {
            setActive(s.getAttribute('data-key'), s.getAttribute('data-pct'), s.getAttribute('data-lbl'));
        });
        s.addEventListener('mouseleave', resetActive);
    });

    items.forEach(function (it) {
        it.addEventListener('mouseenter', function () {
            setActive(it.getAttribute('data-key'), it.getAttribute('data-pct'), it.getAttribute('data-lbl'));
        });
        it.addEventListener('mouseleave', resetActive);
    });
})();
</script>
</body>
</html>