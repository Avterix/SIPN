<?php
session_start();
// Set timezone sesuai lokasi lu biar harinya akurat
date_default_timezone_set('Asia/Jakarta');
include "koneksi.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');
$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;

// AMBIL FOTO PROFILE DARI DATABASE
$foto_db = '';
$q_user = mysqli_query($koneksi, "SELECT foto FROM users WHERE id = '$user_id'");
if ($q_user && $u_row = mysqli_fetch_assoc($q_user)) {
    $foto_db = $u_row['foto'] ?? '';
}
$avatar_src = (!empty($foto_db) && file_exists('uploads/avatars/' . $foto_db)) ? 'uploads/avatars/' . $foto_db : '';

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

// 2. LOGIKA KHUSUS SISWA: Ambil data nilai, jadwal real-time & presensi
$grades_data = [];
$jadwal_hari_ini = []; // Diubah namanya biar logis
$kehadiran = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0, 'persentase' => 100];
$hari_ini = ''; 

if ($role === 'siswa') {
    $siswa_id = 0;
    $siswa_kelas = '';
    
    // Cari id siswa dan kelas berdasarkan user_id
    $q_siswa_info = mysqli_query($koneksi, "SELECT id, kelas FROM siswa WHERE user_id = '$user_id'");
    if ($q_siswa_info && mysqli_num_rows($q_siswa_info) > 0) {
        $d_siswa = mysqli_fetch_assoc($q_siswa_info);
        $siswa_id = $d_siswa['id'] ?? 0;
        $siswa_kelas = $d_siswa['kelas'] ?? ''; // Tarik data kelas (Misal: "X RPL")
    }

    // Query dari tabel nilai_rapor
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

    // MENDAPATKAN HARI INI DALAM BAHASA INDONESIA
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

    // AMBIL DATA JADWAL HARI INI & KHUSUS KELAS SISWA TERSEBUT (DIUBAH PAKAI LEFT JOIN)
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

    // DATA DUMMY KEHADIRAN / KEAKTIFAN SISWA
    $kehadiran = [
        'hadir' => 24,
        'sakit' => 1,
        'izin'  => 1,
        'alpha' => 0,
        'persentase' => 92
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SIPN</title>
    <!-- CSS Utama -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.1">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.vercel-insights.com/v1/script.js"></script>
</head>
<body>
<?php include "loader.php"; ?>
		<div class="video">
  		<video autoplay muted playsinline id="bg-video">
   		 <source src="background/main_bg.mp4" type="video/mp4">
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
            <img src="<?= htmlspecialchars($avatar_src); ?>" class="user-avatar-initial" alt="Avatar">
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
            <a href="#"><i class="fa-solid fa-folder-tree" style="width:20px; text-align:center;"></i> <span class="menu-label">Master Data <i class="fa-solid fa-chevron-down arrow"></i></span></a>
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
            <div class="icon-plus" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
            <div class="add-text">
                <strong>System Session</strong>
                <span style="color: #ef4444;">Logout Account</span>
            </div>
        </a>
    </div>
</aside>

        <!-- MAIN CONTENT AREA -->
        <main class="main-content">
            <!-- TOP BAR -->
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
                    <button class="icon-btn notification-btn"><img class="icons" src="icons/dashboard/bell.png"></img></button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="icons/dashboard/settings.png"></img></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <!-- HERO BANNER SECTION -->
            <section class="hero-section">
                <div class="folders-slider">
                <div class="hero-text">
                    <h2>Manage your academic <br> system</h2>
                    <p>Access database modules quickly to sort student scores <br> and school properties</p>
                </div>
                    <div class="folder-card2 create-new-card" onclick="location.href='profile.php'">
                        <div class="dashed-circle"><i class="fa-solid fa-plus"></i></div>
                    </div>
                    <div class="folder-card bg-green">
                        <div class="card-top">
                            <span class="num">01</span>
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </div>
                        <div class="card-body">
                            <div class="folder-icon"><i class="students"></i></div>
                            <h3>Students</h3>
                            <span><?= $tot_siswa; ?> Registered</span>
                        </div>
                    </div>
                    <div class="folder-card bg-purple">
                        <div class="card-top">
                            <span class="num">02</span>
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </div>
                        <div class="card-body">
                            <div class="folder-icon"><i class="teacher"></i></div>
                            <h3>Teachers</h3>
                            <span><?= $tot_guru; ?> Instructors</span>
                        </div>
                    </div>
                    <div class="folder-card bg-dark">
                        <div class="card-body-gallery">
                            <h3>Subjects</h3>
                            <span><?= $tot_mapel; ?> Active Units</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- BOTTOM GRID SECTION -->
            <section class="bottom-grid">
                
                <!-- CARD DIAGRAM / GRADE CHART -->
		<img class="elements" src="elements/element1.png"></img>
                <div class="card storage-card">
                    <?php if ($role === 'siswa'): ?>
                        <!-- Tampilan Bar Chart Khusus Siswa -->
                        <div class="card-header">
                            <h3>Academic Grade Chart</h3>
                            <i class="chart"></i>
                        </div>
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
                                    <span class="grade-bar-label" title="<?= htmlspecialchars($g['nama']); ?>">
                                        <?= htmlspecialchars($g['nama']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted); text-align: center; margin-top: 4px;">
                            <span style="display:inline-block; width:8px; height:8px; background:#78bc9b; border-radius:50%; margin-right:4px;"></span> Ada Nilai
                            <span style="display:inline-block; width:8px; height:8px; background:#cbd5e1; border-radius:50%; margin-left:12px; margin-right:4px;"></span> Belum Ada Nilai
                        </div>

                    <?php else: ?>
                        <!-- Donut Chart untuk Admin & Guru -->
                        <div class="card-header">
                            <h3>Database Ratio</h3>
                            <i class="chart"></i>
                        </div>
                        <div class="storage-content">
                            <div class="chart-container">
                                <div class="donut-hole">
                                    <strong>100%</strong>
                                </div>
                            </div>
                            <div class="storage-legend">
                                <div class="legend-item"><span class="bullet doc"></span> <div><strong>Students</strong><small><?= $tot_siswa; ?> Data</small></div></div>
                                <div class="legend-item"><span class="bullet img"></span> <div><strong>Teachers</strong><small><?= $tot_guru; ?> Data</small></div></div>
                                <div class="legend-item"><span class="bullet vid"></span> <div><strong>Subjects</strong><small><?= $tot_mapel; ?> Data</small></div></div>
                                <div class="legend-item"><span class="bullet free"></span> <div><strong>Status</strong><small>Online</small></div></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SYSTEM INFORMATION CARD -->
                <div class="card files-card">
                    <div class="card-header">
                        <h3>System Information</h3>
                        <i class="system"></i>
                    </div>
                    <div class="file-list">
                        <div class="file-item">
                            <div class="file-info">
                                <div class="file-ico psd"><i class="school"></i></div>
                                <span>SMK Sangkuriang 1 Cimahi</span>
                            </div>
                            <div class="file-users">
                            </div>
                            <span class="file-date">2026/2027</span>
                        </div>
                        <div class="file-item">
                            <div class="file-info">
                                <div class="file-ico jpg"><i class="curriculum"></i></div>
                                <span>Rekayasa Perangkat Lunak</span>
                            </div>
                            <div class="file-users">
                            </div>
                            <span class="file-date">Curriculum</span>
                        </div>
                        <div class="file-item">
                            <div class="file-info">
                                <div class="file-ico pdf"><i class="status"></i></div>
                                <span>Logged as: <?= htmlspecialchars($username); ?></span>
                            </div>
                            <div class="file-users">
                            </div>
                            <span class="file-date">Secure Session</span>
                        </div>
                    </div>
                </div>
            </section>

            <?php if ($role === 'siswa'): ?>
            <!-- SECTION TAMBAHAN KHUSUS SISWA (JADWAL REALTIME & KEAKTIFAN PRESENSI) -->
            <section class="bottom-grid" style="margin-top: 24px;">
                <!-- CARD JADWAL HARI INI & GURU PENGAJAR -->
                <div class="card-badge schedule-card">
                    <div class="card-day">
                        <h3><i class="fa-solid fa-calendar-day" style="color: #6366f1; margin-right: 8px;"></i> Jadwal Pelajaran (<?= $hari_ini; ?>)</h3>
                        <span class="tag-badge-day"><?= $hari_ini; ?></span>
                    </div>
                    <div class="schedule-list">
                        <?php if (count($jadwal_hari_ini) > 0): ?>
                            <?php foreach ($jadwal_hari_ini as $j): ?>
                            <div class="schedule-item">
                                <div class="schedule-time">
                                    <i class="fa-regular fa-clock"></i>
                                    <span><?= $j['jam']; ?></span>
                                </div>
                                <div class="schedule-details">
                                    <h4><?= htmlspecialchars($j['mapel']); ?></h4>
                                    <p><i class="fa-solid fa-chalkboard-user"></i> <?= htmlspecialchars($j['guru']); ?></p>
                                </div>
                                <div class="schedule-meta">
                                    <span class="room-pill"><i class="fa-solid fa-door-open"></i> <?= htmlspecialchars($j['ruang']); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="schedule-item">
                                <p style="text-align:center; width:100%; color: #94a3b8;">Tidak ada jadwal hari <?= $hari_ini; ?> untuk kelas kamu.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- CARD KEAKTIFAN PRESENSI SISWA -->
                <div class="card-att attendance-card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-user-check" style="color: #10b981; margin-right: 8px;"></i> Keaktifan & Presensi</h3>
                        <span class="att-percentage"><?= $kehadiran['persentase']; ?>% High</span>
                    </div>
                    <div class="attendance-body">
                        <div class="att-progress-wrapper">
                            <div class="att-progress-info">
                                <span>Persentase Kehadiran</span>
                                <strong><?= $kehadiran['persentase']; ?>%</strong>
                            </div>
                            <div class="att-progress-bar">
                                <div class="att-progress-fill" style="width: <?= $kehadiran['persentase']; ?>%;"></div>
                            </div>
                        </div>

                        <div class="att-stats-grid">
                            <div class="att-box hadir">
                                <span class="att-num"><?= $kehadiran['hadir']; ?></span>
                                <span class="att-label">Hadir</span>
                            </div>
                            <div class="att-box sakit">
                                <span class="att-num"><?= $kehadiran['sakit']; ?></span>
                                <span class="att-label">Sakit</span>
                            </div>
                            <div class="att-box izin">
                                <span class="att-num"><?= $kehadiran['izin']; ?></span>
                                <span class="att-label">Izin</span>
                            </div>
                            <div class="att-box alpha">
                                <span class="att-num"><?= $kehadiran['alpha']; ?></span>
                                <span class="att-label">Alpha</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

        </main>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const menuLinks = document.querySelectorAll(".sidebar-menu a");
            menuLinks.forEach(link => {
                link.addEventListener("click", function(e) {
                    if(this.parentElement.classList.contains("has-submenu")) {
                        e.preventDefault();
                        const submenu = this.nextElementSibling;
                        submenu.style.display = submenu.style.display === "flex" ? "none" : "flex";
                        return;
                    }
                });
            });
        });
    </script>
</body>
</html>