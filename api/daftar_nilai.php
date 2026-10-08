<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include "koneksi.php";

// 1. PROTEKSI HAK AKSES GURU & ADMIN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id  = $_SESSION['user_id'];
$role     = strtolower($_SESSION['role'] ?? '');
$username = $_SESSION['username'] ?? 'User';

if ($role !== 'guru' && $role !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$msg_type = "";
$msg_text = "";

// Ambil data User & Guru ID
$q_user = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id'");
$user_data = ($q_user && mysqli_num_rows($q_user) > 0) ? mysqli_fetch_assoc($q_user) : [];
$foto_db = $user_data['foto'] ?? '';

$q_guru = mysqli_query($koneksi, "SELECT id, nama FROM guru WHERE user_id = '$user_id'");
$guru_data = ($q_guru && mysqli_num_rows($q_guru) > 0) ? mysqli_fetch_assoc($q_guru) : [];
$guru_id = $guru_data['id'] ?? 0;

$avatar_src = (!empty($foto_db) && file_exists('uploads/avatars/' . $foto_db)) 
    ? 'uploads/avatars/' . $foto_db 
    : '';

// 2. PROSES INSERT / UPDATE NILAI RAPOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_nilai'])) {
    $siswa_id           = (int)$_POST['siswa_id'];
    $mapel_id           = (int)$_POST['mapel_id'];
    $semester           = mysqli_real_escape_string($koneksi, $_POST['semester']);
    $nilai_kkm          = (int)$_POST['nilai_kkm'];
    $nilai_pengetahuan  = (int)$_POST['nilai_pengetahuan'];
    $nilai_keterampilan = (int)$_POST['nilai_keterampilan'];
    $keterangan         = mysqli_real_escape_string($koneksi, $_POST['keterangan']);

    // Cek apakah nilai siswa pada mapel & semester ini sudah ada
    $q_cek = mysqli_query($koneksi, "SELECT id FROM nilai_rapor WHERE siswa_id = '$siswa_id' AND mapel_id = '$mapel_id' AND semester = '$semester'");
    
    if (mysqli_num_rows($q_cek) > 0) {
        // Update jika sudah ada
        $query_save = "UPDATE nilai_rapor SET 
                       nilai_kkm = '$nilai_kkm', 
                       nilai_pengetahuan = '$nilai_pengetahuan', 
                       nilai_keterampilan = '$nilai_keterampilan', 
                       keterangan = '$keterangan' 
                       WHERE siswa_id = '$siswa_id' AND mapel_id = '$mapel_id' AND semester = '$semester'";
    } else {
        // Insert jika belum ada
        $query_save = "INSERT INTO nilai_rapor (siswa_id, mapel_id, semester, nilai_kkm, nilai_pengetahuan, nilai_keterampilan, keterangan) 
                       VALUES ('$siswa_id', '$mapel_id', '$semester', '$nilai_kkm', '$nilai_pengetahuan', '$nilai_keterampilan', '$keterangan')";
    }

    if (mysqli_query($koneksi, $query_save)) {
        $msg_type = "success";
        $msg_text = "Nilai siswa berhasil disimpan!";
    } else {
        $msg_type = "error";
        $msg_text = "Gagal menyimpan nilai: " . mysqli_error($koneksi);
    }
}

// 3. AMBIL DAFTAR MAPEL & KELAS YANG DIAJAR GURU INI
$filter_kelas    = $_GET['kelas'] ?? '';
$filter_mapel_id = $_GET['mapel_id'] ?? '';
$filter_semester = $_GET['semester'] ?? '1';

// Jika admin, ambil semua kelas & mapel
if ($role === 'admin') {
    $q_mapel_guru = mysqli_query($koneksi, "SELECT m.id AS mapel_id, m.nama_mapel, s.kelas 
                                            FROM mapel m 
                                            CROSS JOIN (SELECT DISTINCT kelas FROM siswa) s");
} else {
    // Jika guru, ambil mapel yang terdaftar di guru_mapel_kelas ATAU yang guru_id-nya di tabel mapel
    $q_mapel_guru = mysqli_query($koneksi, "
        SELECT m.id AS mapel_id, m.nama_mapel, gmk.kelas 
        FROM guru_mapel_kelas gmk
        JOIN mapel m ON gmk.mapel_id = m.id
        WHERE gmk.guru_id = '$guru_id'
        UNION
        SELECT m.id AS mapel_id, m.nama_mapel, s.kelas
        FROM mapel m
        CROSS JOIN (SELECT DISTINCT kelas FROM siswa) s
        WHERE m.guru_id = '$guru_id'
    ");
}

$opsi_ajar = [];
while ($row = mysqli_fetch_assoc($q_mapel_guru)) {
    $opsi_ajar[] = $row;
}

// Auto select filter pertama jika belum dipilih
if (empty($filter_mapel_id) && !empty($opsi_ajar)) {
    $filter_mapel_id = $opsi_ajar[0]['mapel_id'];
    $filter_kelas    = $opsi_ajar[0]['kelas'];
}

// 4. FETCH DAFTAR SISWA BERDASARKAN KELAS & FILTER
$list_siswa_nilai = [];
if (!empty($filter_kelas)) {
    $q_siswa_list = mysqli_query($koneksi, "
        SELECT s.id AS siswa_id, s.nis, s.nama, s.kelas,
               nr.id AS nilai_id, nr.nilai_kkm, nr.nilai_pengetahuan, nr.nilai_keterampilan, nr.keterangan
        FROM siswa s
        LEFT JOIN nilai_rapor nr ON s.id = nr.siswa_id AND nr.mapel_id = '$filter_mapel_id' AND nr.semester = '$filter_semester'
        WHERE s.kelas = '$filter_kelas'
        ORDER BY s.nama ASC
    ");

    while ($r = mysqli_fetch_assoc($q_siswa_list)) {
        $list_siswa_nilai[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Nilai Siswa - SIPN Core</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-container { display: flex; flex-direction: column; gap: 20px; animation: fadeInUp 0.4s ease-out forwards; }
        .card-box { background: #fff; border-radius: 16px; padding: 24px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 12px; font-weight: 600; color: #475569; }
        .form-control { padding: 9px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; outline: none; background: #f8fafc; }
        .form-control:focus { border-color: #231c32; background: #fff; }
        .btn-filter { background: #231c32; color: #fff; padding: 10px 18px; border-radius: 8px; border: none; font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        .data-table th { background: #f8fafc; color: #475569; padding: 12px 14px; border-bottom: 2px solid #e2e8f0; font-weight: 700; }
        .data-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .data-table input[type="number"] { width: 70px; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 6px; text-align: center; }
        .btn-save-sm { background: #10b981; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .alert-box { padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
    <script defer src="https://cdn.vercel-insights.com/v1/script.js"></script>
</head>
<body>

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
                
                <?php if ($role === 'admin'): ?>
                <li class="has-submenu">
                    <a href="#"><i class="fa-solid fa-folder-tree"></i> Master Data <i class="fa-solid fa-chevron-down arrow"></i></a>
                    <ul class="submenu">
                        <li><a href="daftar_user.php"><span class="dot user"></span> Kelola User</a></li>
                        <li><a href="daftar_siswa.php"><span class="dot siswa"></span> Kelola Siswa</a></li>
                        <li><a href="daftar_guru.php"><span class="dot guru"></span> Kelola Guru</a></li>
                        <li><a href="daftar_mapel.php"><span class="dot mapel"></span> Mata Pelajaran</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($role === 'guru' || $role === 'admin'): ?>
                <li><a href="daftar_nilai.php" class="active"><i class="fa-solid fa-pen-to-square"></i> Kelola Nilai</a></li>
                <?php endif; ?>

                <?php if ($role === 'siswa'): ?>
                <li><a href="lihat_nilai.php"><i class="report"></i> View Report Card Grades</a></li>
                <?php endif; ?>

                <li><a href="profile.php"><i class="fa-solid fa-gear"></i> Manage Profile</a></li>
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
            <header class="topbar">
                <div class="topbar-title">
                    <h1>Kelola Nilai Siswa</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><i class="fa-solid fa-user-gear"></i></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="page-container">
                
                <?php if (!empty($msg_text)): ?>
                    <div class="alert-box <?= ($msg_type === 'success') ? 'alert-success' : 'alert-error'; ?>">
                        <?= htmlspecialchars($msg_text); ?>
                    </div>
                <?php endif; ?>

                <!-- CARD FILTER MAPEL & KELAS -->
                <div class="card-box">
                    <form method="GET" action="daftar_nilai.php" class="filter-grid">
                        <div class="form-group">
                            <label>Mata Pelajaran & Kelas Ajar</label>
                            <select name="mapel_kelas" class="form-control" onchange="
                                let val = this.value.split('|');
                                document.getElementById('mapel_id_input').value = val[0];
                                document.getElementById('kelas_input').value = val[1];
                            ">
                                <?php if (!empty($opsi_ajar)): ?>
                                    <?php foreach ($opsi_ajar as $opt): 
                                        $selected = ($opt['mapel_id'] == $filter_mapel_id && $opt['kelas'] == $filter_kelas) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $opt['mapel_id'].'|'.$opt['kelas']; ?>" <?= $selected; ?>>
                                            <?= htmlspecialchars($opt['nama_mapel']); ?> - Kelas <?= htmlspecialchars($opt['kelas']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">Belum ada jadwal mengajar</option>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="mapel_id" id="mapel_id_input" value="<?= $filter_mapel_id; ?>">
                            <input type="hidden" name="kelas" id="kelas_input" value="<?= $filter_kelas; ?>">
                        </div>

                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" class="form-control">
                                <option value="1" <?= ($filter_semester == '1') ? 'selected' : ''; ?>>Semester 1 (Ganjil)</option>
                                <option value="2" <?= ($filter_semester == '2') ? 'selected' : ''; ?>>Semester 2 (Genap)</option>
                            </select>
                        </div>

                        <div>
                            <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Tampilkan Siswa</button>
                        </div>
                    </form>
                </div>

                <!-- TABLE INPUT NILAI SISWA -->
                <div class="card-box">
                    <h3 style="font-size: 15px; margin-bottom: 16px;">
                        Input Nilai: Kelas <?= htmlspecialchars($filter_kelas ?: '-'); ?> (Semester <?= htmlspecialchars($filter_semester); ?>)
                    </h3>

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
                                    <th>Keterangan</th>
                                    <th style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($list_siswa_nilai)): ?>
                                    <?php foreach ($list_siswa_nilai as $idx => $s): 
                                        $p = $s['nilai_pengetahuan'] ?? 0;
                                        $k = $s['nilai_keterampilan'] ?? 0;
                                        $rata = round(($p + $k) / 2, 1);
                                    ?>
                                        <tr>
                                            <form method="POST" action="daftar_nilai.php?mapel_id=<?= $filter_mapel_id; ?>&kelas=<?= urlencode($filter_kelas); ?>&semester=<?= $filter_semester; ?>">
                                                <input type="hidden" name="siswa_id" value="<?= $s['siswa_id']; ?>">
                                                <input type="hidden" name="mapel_id" value="<?= $filter_mapel_id; ?>">
                                                <input type="hidden" name="semester" value="<?= $filter_semester; ?>">

                                                <td style="text-align: center; font-weight: 600;"><?= $idx + 1; ?></td>
                                                <td><?= htmlspecialchars($s['nis']); ?></td>
                                                <td><strong><?= htmlspecialchars($s['nama']); ?></strong></td>
                                                <td style="text-align: center;">
                                                    <input type="number" name="nilai_kkm" value="<?= $s['nilai_kkm'] ?? 75; ?>" required min="0" max="100">
                                                </td>
                                                <td style="text-align: center;">
                                                    <input type="number" name="nilai_pengetahuan" value="<?= $p; ?>" required min="0" max="100">
                                                </td>
                                                <td style="text-align: center;">
                                                    <input type="number" name="nilai_keterampilan" value="<?= $k; ?>" required min="0" max="100">
                                                </td>
                                                <td style="text-align: center; font-weight: 700;">
                                                    <?= $rata; ?>
                                                </td>
                                                <td>
                                                    <input type="text" name="keterangan" class="form-control" value="<?= htmlspecialchars($s['keterangan'] ?? 'Baik'); ?>" style="padding: 4px 8px; font-size: 12px;">
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

</body>
</html>