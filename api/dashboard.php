<?php
ini_set('session.cookie_path', '/');
session_start();
date_default_timezone_set('Asia/Jakarta');
include "koneksi.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
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

// 2. LOGIKA KHUSUS SISWA
$grades_data = [];
$jadwal_hari_ini = [];
$kehadiran = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0, 'persentase' => 100];
$hari_ini = ''; 

if ($role === 'siswa') {
    $siswa_id = 0;
    $siswa_kelas = '';
    
    $q_siswa_info = mysqli_query($koneksi, "SELECT id, kelas FROM siswa WHERE user_id = '$user_id'");
    if ($q_siswa_info && mysqli_num_rows($q_siswa_info) > 0) {
        $d_siswa = mysqli_fetch_assoc($q_siswa_info);
        $siswa_id = $d_siswa['id'] ?? 0;
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

    $hari_inggris = date('l');
    $translate_hari = [
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
        'Sunday'    => 'Minggu'
    ];
    $hari_ini = $translate_hari[$hari_inggris];

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
}

// ===== TAMBAHAN UNTUK DASHBOARD BENTO (logic lama tidak diubah) =====
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

// 5 siswa terbaru + foto profil dari tabel users (aturan sama kayak avatar sidebar)
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
if ($role !== 'siswa') {
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

// Gambar galeri: taruh file di folder assets/ dengan nama banner1.jpg, banner2.png, dst
$banner_images = [
    $base_url . '/assets/banner/banner1.jpeg', $base_url . '/assets/banner/banner2.jpeg', $base_url . '/assets/banner/banner3.jpeg', $base_url . '/assets/banner/banner4.jpeg', $base_url . '/assets/banner/banner5.jpeg', $base_url . '/assets/banner/banner6.jpeg'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/dashboard-bento.css?v=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
</head>
<body>
<?php include "loader.php"; ?>
<div class="video">
    <video autoplay muted loop playsinline id="bg-video">
        <source src="<?= $base_url; ?>/background/main_bg.mp4" type="video/mp4">
        Browser Anda tidak mendukung tag video.
    </video>
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
        <li class="has-submenu">
            <a href="#"><i class="master" style="width:20px; text-align:center;"></i> <span class="menu-label">Master Data</span></a>
            <ul class="submenu">
                <li><a href="daftar_user.php"><span class="dot user"></span> <span class="menu-label">Kelola User</span></a></li>
                <li><a href="daftar_siswa.php"><span class="dot siswa"></span> <span class="menu-label">Kelola Siswa</span></a></li>
                <li><a href="daftar_guru.php"><span class="dot guru"></span> <span class="menu-label">Kelola Guru</span></a></li>
                <li><a href="daftar_mapel.php"><span class="dot mapel"></span> <span class="menu-label">Mata Pelajaran</span></a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($role === 'guru'): ?>
        <li><a href="daftar_nilai.php"><i class="fa-solid fa-pen-to-square" style="width:20px; text-align:center;"></i> <span class="menu-label">Kelola Nilai</span></a></li>
        <?php endif; ?>

        <?php if ($role === 'siswa'): ?>
        <li><a href="lihat_nilai.php"><i class="report"></i> <span class="menu-label">Report Grades</span></a></li>
        <?php endif; ?>

        <li><a href="profile.php"><i class="profile" style="width:20px; text-align:center;"></i> <span class="menu-label">Profile Settings</span></a></li>
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
			</button>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <!-- BENTO GRID -->
            <section class="bx-grid">

                <!-- GALERI / TEMPAT GAMBAR -->
                <div class="bx-card bx-gallery">
                    <div class="bx-head">
                        <h3>School Gallery</h3>
                        <span class="bx-pill">2026/2027</span>
                    </div>
                    <div class="bx-img-frame" id="bxFrame">
                        <?php if (count($banner_images) > 0): ?>
                            <?php foreach ($banner_images as $i => $img): ?>
                                <img src="<?= htmlspecialchars($img); ?>" class="bx-slide <?= $i === 0 ? 'active' : ''; ?>" alt="Galeri <?= $i + 1; ?>">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="bx-img-empty">
                                <i class="image"></i>
                                <span>Taruh gambar di <b>assets/banner1.jpg</b></span>
                            </div>
                        <?php endif; ?>
                        <span class="bx-img-badge"><i class="image"></i></span>
                    </div>
                    <div class="bx-slider-bar">
                        <button type="button" class="bx-circle-btn" id="bxPrev"><i class="previous"></i></button>
                        <span class="bx-caption" id="bxCaption">SMK Sangkuriang 1 Cimahi</span>
                        <button type="button" class="bx-circle-btn" id="bxNext"><i class="next"></i></button>
                    </div>
                </div>

                <!-- WELCOME + STATISTIK -->
                <div class="bx-card bx-welcome">
                    <div class="bx-hero-text">
                        <h2>Manage your academic <br> system</h2>
                        <p>Access database modules quickly to sort student scores and school properties</p>
                    </div>
                    <div class="bx-stat-row">
                        <div class="bx-stat"><div class="students"></div><strong><?= $tot_siswa; ?></strong><small>Students</small></div>
                        <div class="bx-stat"><div class="teacher"></div><strong><?= $tot_guru; ?></strong><small>Teachers</small></div>
                        <div class="bx-stat"><i class="subject"></i><strong><?= $tot_mapel; ?></strong><small>Subjects</small></div>
                        <div class="bx-stat"><i class="class"></i><strong><?= $tot_kelas; ?></strong><small>Classes</small></div>
                    </div>
                    <a href="profile.php" class="bx-btn-pill"><i class="fa-solid fa-plus"></i> Edit Profile</a>
                </div>

                <!-- STUDENTS -->
                <div class="bx-card bx-students">
                    <div class="bx-head">
                        <span class="bx-pill">Students</span>
                        <span class="bx-dot-btn"><i class="students"></i></span>
                    </div>
                    <h3 class="bx-big"><?= $tot_siswa; ?></h3>
                    <small class="bx-muted">Registered students</small>
                    <div class="bx-avatars">
                        <?php foreach ($siswa_terbaru as $sw): ?>
                            <?php if (!empty($sw['avatar'])): ?>
                                <img src="<?= htmlspecialchars($sw['avatar']); ?>" class="bx-av bx-av-img" title="<?= htmlspecialchars($sw['nama']); ?>" alt="<?= htmlspecialchars($sw['nama']); ?>" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <span class="bx-av" title="<?= htmlspecialchars($sw['nama']); ?>"><?= strtoupper(substr($sw['nama'], 0, 1)); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (count($siswa_terbaru) === 0): ?><small class="bx-muted">Belum ada data siswa</small><?php endif; ?>
                    </div>
                </div>

                <!-- JADWAL + PROFIL -->
                <div class="bx-mid-bottom">
                    <div class="bx-card bx-schedule">
                        <div class="bx-head">
                            <span class="bx-pill"><i class="calendar"></i> Jadwal <?= $hari_ini; ?></span>
                            <span class="bx-muted"><?= count($jadwal_tampil); ?> sesi</span>
                        </div>
                        <div class="bx-sch-list">
                            <?php if (count($jadwal_tampil) > 0): ?>
                                <?php foreach ($jadwal_tampil as $j): ?>
                                <div class="bx-sch-item">
                                    <span class="bx-sch-time"><i class="clock"></i> <?= $j['jam']; ?></span>
                                    <div class="bx-sch-info">
                                        <h4><?= htmlspecialchars($j['mapel']); ?></h4>
                                        <p><i class="fa-solid fa-chalkboard-user"></i> <?= htmlspecialchars($j['guru']); ?> &middot; <?= htmlspecialchars($j['ruang']); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="bx-muted bx-center">Tidak ada jadwal hari <?= $hari_ini; ?><?= $role === 'siswa' ? ' untuk kelas kamu' : ''; ?>.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bx-card bx-profile">
                        <?php if (!empty($avatar_src)): ?>
                            <img src="<?= htmlspecialchars($avatar_src); ?>" class="bx-profile-img" referrerpolicy="no-referrer" alt="avatar">
                        <?php else: ?>
                            <div class="bx-profile-img bx-profile-empty"><?= strtoupper(substr($username, 0, 1)); ?></div>
                        <?php endif; ?>
                        <strong><?= htmlspecialchars($username); ?></strong>
                        <small class="bx-muted"><?= strtoupper($role); ?></small>
                        <span class="bx-toggle"><span class="bx-toggle-dot"></span> Online</span>
                    </div>
                </div>

                <!-- KEHADIRAN / RASIO -->
                <div class="bx-card bx-progress">
                    <div class="bx-head">
                        <span class="bx-pill"><?= $role === 'siswa' ? 'Kehadiran' : 'Rasio Siswa'; ?></span>
                        <span class="bx-dot-btn"><i class="presence"></i></span>
                    </div>
                    <?php $pct = ($role === 'siswa') ? $kehadiran['persentase'] : $rasio_pct; ?>
                    <h3 class="bx-big"><?= $pct; ?>%</h3>
                    <div class="bx-bar"><div class="bx-bar-fill" style="width: <?= $pct; ?>%;"></div></div>
                    <a href="<?= $cta[1]; ?>" class="bx-btn-bar"><?= $cta[0]; ?></a>
                </div>

                <!-- TEACHERS & SUBJECTS -->
                <div class="bx-card bx-teachers">
                    <div class="bx-head">
                        <span class="bx-pill">Teachers</span>
                        <span class="bx-dot-btn"><i class="teacher"></i></span>
                    </div>
                    <h3 class="bx-big"><?= $tot_guru; ?></h3>
                    <small class="bx-muted"><?= $tot_mapel; ?> Active Subjects</small>
                    <div class="bx-stack"><span></span><span></span><span></span></div>
                </div>
            </section>

            <!-- BARIS BAWAH -->
            <section class="bx-row2">
                <div class="bx-card">
                    <?php if ($role === 'siswa'): ?>
                        <div class="bx-head"><h3>Academic Grade Chart</h3><i class="chart"></i></div>
                        <div class="grade-chart-container">
                            <?php foreach ($grades_data as $g):
                                $has_value = ($g['nilai'] !== null && $g['nilai'] > 0);
                                $bar_height = $has_value ? min(100, max(15, $g['nilai'])) : 15;
                                $bar_class  = $has_value ? 'bar-active' : 'bar-empty';
                                $display_val = $has_value ? $g['nilai'] : '-';
                            ?>
                                <div class="grade-bar-group">
                                    <span class="grade-bar-val"><?= $display_val; ?></span>
                                    <div class="grade-bar-track">
                                        <div class="grade-bar-fill <?= $bar_class; ?>" style="height: <?= $bar_height; ?>%;"></div>
                                    </div>
                                    <span class="grade-bar-label" title="<?= htmlspecialchars($g['nama']); ?>"><?= htmlspecialchars($g['nama']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted); text-align: center; margin-top: 4px;">
                            <span style="display:inline-block; width:8px; height:8px; background:#78bc9b; border-radius:50%; margin-right:4px;"></span> Ada Nilai
                            <span style="display:inline-block; width:8px; height:8px; background:#cbd5e1; border-radius:50%; margin-left:12px; margin-right:4px;"></span> Belum Ada Nilai
                        </div>
                    <?php else: ?>
                        <div class="bx-head"><h3>Database Ratio</h3><i class="chart"></i></div>
                        <div class="storage-content">
                            <div class="chart-container"><div class="donut-hole"><strong>100%</strong></div></div>
                            <div class="storage-legend">
                                <div class="legend-item"><span class="bullet doc"></span> <div><strong>Students</strong><small><?= $tot_siswa; ?> Data</small></div></div>
                                <div class="legend-item"><span class="bullet img"></span> <div><strong>Teachers</strong><small><?= $tot_guru; ?> Data</small></div></div>
                                <div class="legend-item"><span class="bullet vid"></span> <div><strong>Subjects</strong><small><?= $tot_mapel; ?> Data</small></div></div>
                                <div class="legend-item"><span class="bullet free"></span> <div><strong>Status</strong><small>Online</small></div></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bx-card">
                    <div class="bx-head"><h3>System Information</h3><i class="system"></i></div>
                    <div class="file-list">
                        <div class="file-item">
                            <div class="file-info"><div class="file-ico psd"><i class="school"></i></div><span>SMK Sangkuriang 1 Cimahi</span></div>
                            <span class="file-date">2026/2027</span>
                        </div>
                        <div class="file-item">
                            <div class="file-info"><div class="file-ico jpg"><i class="curriculum"></i></div><span>Rekayasa Perangkat Lunak</span></div>
                            <span class="file-date">Curriculum</span>
                        </div>
                        <div class="file-item">
                            <div class="file-info"><div class="file-ico pdf"><i class="status"></i></div><span>Logged as: <?= htmlspecialchars($username); ?></span></div>
                            <span class="file-date">Secure Session</span>
                        </div>
                    </div>
                </div>

                <?php if ($role === 'siswa'): ?>
                <div class="bx-card">
                    <div class="bx-head">
                        <h3><i class="presence" style="color:#10b981; margin-right:8px;"></i> Participation & Attendance</h3>
                        <span class="att-percentage"><?= $kehadiran['persentase']; ?>% High</span>
                    </div>
                    <div class="attendance-body">
                        <div class="att-progress-wrapper">
                            <div class="att-progress-info"><span>attendance percentage</span><strong><?= $kehadiran['persentase']; ?>%</strong></div>
                            <div class="att-progress-bar"><div class="att-progress-fill" style="width: <?= $kehadiran['persentase']; ?>%;"></div></div>
                        </div>
                        <div class="att-stats-grid">
                            <div class="att-box hadir"><span class="att-num"><?= $kehadiran['hadir']; ?></span><span class="att-label">Hadir</span></div>
                            <div class="att-box sakit"><span class="att-num"><?= $kehadiran['sakit']; ?></span><span class="att-label">Sakit</span></div>
                            <div class="att-box izin"><span class="att-num"><?= $kehadiran['izin']; ?></span><span class="att-label">Izin</span></div>
                            <div class="att-box alpha"><span class="att-num"><?= $kehadiran['alpha']; ?></span><span class="att-label">Alpha</span></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<script>
(function () {
    var slides = document.querySelectorAll('#bxFrame .bx-slide');
    if (!slides.length) return;
    var i = 0, cap = document.getElementById('bxCaption');
    function show(n) {
        slides[i].classList.remove('active');
        i = (n + slides.length) % slides.length;
        slides[i].classList.add('active');
        cap.textContent = 'Galeri ' + (i + 1) + ' / ' + slides.length;
    }
    document.getElementById('bxPrev').onclick = function () { show(i - 1); };
    document.getElementById('bxNext').onclick = function () { show(i + 1); };
    setInterval(function () { show(i + 1); }, 6000);
})();
</script>
</body>
</html>