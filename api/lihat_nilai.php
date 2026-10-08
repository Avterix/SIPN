<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include "koneksi.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id  = $_SESSION['user_id'];
$role     = strtolower($_SESSION['role'] ?? '');

// 1. AMBIL DATA USER & FOTO
$q_user = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id'");
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
    } elseif (file_exists('uploads/avatars/' . $foto_db)) {
        $avatar_src = 'uploads/avatars/' . $foto_db;
    }
}
if (empty($avatar_src) && $gh_connected && !empty($gh_avatar)) {
    $avatar_src = $gh_avatar;
}

// Ambil Profil Siswa
$q_siswa = mysqli_query($koneksi, "SELECT * FROM siswa WHERE user_id = '$user_id'");
$siswa_data = ($q_siswa && mysqli_num_rows($q_siswa) > 0) ? mysqli_fetch_assoc($q_siswa) : [];

$siswa_id     = $siswa_data['id'] ?? 0;
$nama_lengkap = $siswa_data['nama'] ?? $username;
$nis          = $siswa_data['nis'] ?? '-';
$kelas        = $siswa_data['kelas'] ?? '-';

// 2. QUERY FETCH NILAI RAPOR
$sql_nilai = "SELECT nr.*, m.nama_mapel, m.kode_mapel 
              FROM nilai_rapor nr 
              JOIN mapel m ON nr.mapel_id = m.id 
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

        $row['kkm_val']         = $kkm;
        $row['pengetahuan_val'] = $pengetahuan;
        $row['keterampilan_val'] = $keterampilan;
        $row['computed_akhir']  = $nilai_akhir;
        $row['computed_predikat'] = $predikat;
        $row['is_tuntas']       = $is_tuntas;

        $list_nilai[] = $row;

        $total_nilai += $nilai_akhir;
        $count_mapel++;
        if ($nilai_akhir > $max_nilai) $max_nilai = $nilai_akhir;
        if ($nilai_akhir < $min_nilai) $min_nilai = $nilai_akhir;
    }
}

$rata_rata = $count_mapel > 0 ? round($total_nilai / $count_mapel, 1) : 0;
if ($count_mapel == 0) $min_nilai = 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Report Card Grades - SIPN Core</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .rapor-container { display: flex; flex-direction: column; gap: 20px; animation: fadeInUp 0.5s ease-out forwards; }
        .card-box { background-color: var(--card-bg, #ffffff); border-radius: 16px; padding: 24px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .student-banner { display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, #231c32 0%, #3b2d54 100%); color: #ffffff; border-radius: 16px; padding: 24px 28px; box-shadow: 0 8px 20px rgba(35, 28, 50, 0.15); }
        .student-info-left { display: flex; align-items: center; gap: 20px; }
        .student-avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255, 255, 255, 0.3); background: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 700; color: #231c32; }
        .student-details h2 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .student-details p { font-size: 13px; opacity: 0.85; display: flex; gap: 16px; }
        .student-details p span { display: inline-flex; align-items: center; gap: 6px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .stat-card { background: #ffffff; border-radius: 14px; padding: 18px 20px; border: 1px solid rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between; }
        .stat-meta h4 { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .stat-meta .number { font-size: 24px; font-weight: 700; color: #0f172a; }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .icon-purple { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
        .icon-green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .icon-orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .icon-blue { background: rgba(14, 165, 233, 0.1); color: #0ea5e9; }
        .table-filter-header { display: flex; align-items: center; justify-content: flex-end; margin-bottom: 20px; gap: 12px; }
        .btn-print { background-color: #231c32; color: #ffffff; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: opacity 0.2s; }
        .btn-print:hover { opacity: 0.9; }
        .table-responsive { width: 100%; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        .data-table th { background-color: #f8fafc; color: #475569; font-weight: 700; padding: 12px 16px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
        .data-table tr:hover { background-color: #f8fafc; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; display: inline-block; }
        .badge-success { color: #95a5a6; }
        .badge-danger { color: #f4f5f8; }
        .badge-predikat { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; }
        .pred-A { color: #15803d; }
        .pred-B { color: #0369a1; }
        .pred-C { color: #b45309; }
        .pred-D { color: #b91c1c; }
        @media print {
            body * { visibility: hidden; }
            .rapor-container, .rapor-container * { visibility: visible; }
            .sidebar, .topbar, .btn-print { display: none !important; }
            .rapor-container { position: absolute; left: 0; top: 0; width: 100%; }
            .card-box { border: none; box-shadow: none; }
        }
    </style>
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
                <h2>SIPN Core</h2>
            </div>

            <div class="user-profile-mini">
                <?php if (!empty($avatar_src)): ?>
                    <img src="<?= htmlspecialchars($avatar_src); ?>" class="user-avatar-initial" style="object-fit: cover;">
                <?php else: ?>
                    <div class="user-avatar-initial"><?= strtoupper(substr($username, 0, 1)); ?></div>
                <?php endif; ?>
                <div class="user-meta-mini">
                    <span class="user-name"><?= htmlspecialchars($username); ?></span>
                    <span class="user-role-badge"><?= htmlspecialchars($role); ?></span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="dashboard"></i> Dashboard</a></li>
                <?php if ($role === 'siswa'): ?>
                <li><a href="lihat_nilai.php" class="active"><i class="report"></i> View Report Card Grades</a></li>
                <?php endif; ?>
                <li><a href="profile.php"><i class="profile"></i> Manage Profile</a></li>
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

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title">
                    <h1>Report Card Grades</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search report card...">
                    </div>
                    <button class="icon-btn notification-btn"><i class="fa-regular fa-bell"></i></button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><i class="fa-solid fa-user-gear"></i></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="rapor-container">
                <div class="student-banner">
                    <div class="student-info-left">
                        <?php if (!empty($avatar_src)): ?>
                            <img src="<?= htmlspecialchars($avatar_src); ?>" class="student-avatar" alt="Avatar">
                        <?php else: ?>
                            <div class="student-avatar"><?= strtoupper(substr($nama_lengkap, 0, 1)); ?></div>
                        <?php endif; ?>
                        
                        <div class="student-details">
                            <h2><?= htmlspecialchars($nama_lengkap); ?></h2>
                            <p>
                                <span><i class="fa-solid fa-id-card"></i> NIS: <?= htmlspecialchars($nis); ?></span>
                                <span><i class="fa-solid fa-school"></i> Kelas: <?= htmlspecialchars($kelas); ?></span>
                                <span><i class="fa-solid fa-graduation-cap"></i> Status: Aktif</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-meta">
                            <h4>Rata-Rata Nilai</h4>
                            <div class="number"><?= $rata_rata; ?></div>
                        </div>
                        <div class="stat-icon icon-purple"><i class="fa-solid fa-chart-line"></i></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-meta">
                            <h4>Total Mata Pelajaran</h4>
                            <div class="number"><?= $count_mapel; ?></div>
                        </div>
                        <div class="stat-icon icon-blue"><i class="fa-solid fa-book-bookmark"></i></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-meta">
                            <h4>Nilai Tertinggi</h4>
                            <div class="number"><?= $max_nilai; ?></div>
                        </div>
                        <div class="stat-icon icon-green"><i class="fa-solid fa-trophy"></i></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-meta">
                            <h4>Mata Pelajaran Tuntas</h4>
                            <div class="number"><?= $tuntas_count; ?> / <?= $count_mapel; ?></div>
                        </div>
                        <div class="stat-icon icon-orange"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                </div>

                <div class="card-box">
                    <div class="table-filter-header">
                        <button onclick="window.print()" class="btn-print">
                            <i class="fa-solid fa-print"></i> Cetak Rapor
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px; text-align: center;">No</th>
                                    <th>Mata Pelajaran</th>
                                    <th style="text-align: center;">KKM</th>
                                    <th style="text-align: center;">Nilai Pengetahuan</th>
                                    <th style="text-align: center;">Nilai Keterampilan</th>
                                    <th style="text-align: center;">Nilai Akhir</th>
                                    <th style="text-align: center;">Predikat</th>
                                    <th style="text-align: center;">Keterangan</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($list_nilai)): ?>
                                    <?php foreach ($list_nilai as $index => $row): ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: 600;"><?= $index + 1; ?></td>
                                            <td>
                                                <strong style="color:#0f172a;"><?= htmlspecialchars($row['nama_mapel']); ?></strong>
                                                <br>
                                                <small style="color:#64748b;"><?= htmlspecialchars($row['kode_mapel'] ?? '-'); ?></small>
                                            </td>
                                            <td style="text-align: center; font-weight: 600;"><?= $row['kkm_val']; ?></td>
                                            <td style="text-align: center;"><?= $row['pengetahuan_val']; ?></td>
                                            <td style="text-align: center;"><?= $row['keterampilan_val']; ?></td>
                                            <td style="text-align: center; font-weight: 700; color: #0f172a;"><?= $row['computed_akhir']; ?></td>
                                            <td style="text-align: center;">
                                                <span class="badge-predikat pred-<?= $row['computed_predikat']; ?>"><?= $row['computed_predikat']; ?></span>
                                            </td>
                                            <td style="text-align: center; color: #64748b;"><?= htmlspecialchars($row['keterangan'] ?? '-'); ?></td>
                                            <td style="text-align: center;">
                                                <?php if ($row['is_tuntas']): ?>
                                                    <span class="badge badge-success">✔</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">x</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 32px; color: #64748b;">
                                            <i class="fa-solid fa-folder-open" style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1; display: block;"></i>
                                            Belum ada data nilai rapor untuk siswa ini.
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
</div>
</body>
</html>