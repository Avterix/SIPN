<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
$role     = strtolower($_SESSION['role'] ?? 'admin');
$username = $_SESSION['username'] ?? 'Admin';
$msg_type = "";
$msg_text = "";

// AMBIL FOTO USER ADMIN (HIRARKI LENGKAP SEPERTI DASHBOARD & PROFILE)
$q_u = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id'");
$u_data = ($q_u && mysqli_num_rows($q_u) > 0) ? mysqli_fetch_assoc($q_u) : [];
$foto_db = $u_data['foto'] ?? '';
$avatar_src = '';
if (!empty($foto_db)) {
    if (strpos($foto_db, 'data:image') === 0 || filter_var($foto_db, FILTER_VALIDATE_URL)) {
        $avatar_src = $foto_db;
    } elseif (file_exists('uploads/avatars/' . $foto_db)) {
        $avatar_src = 'uploads/avatars/' . $foto_db;
    }
}
if (empty($avatar_src) && !empty($u_data['github_connected']) && $u_data['github_connected'] == 1 && !empty($u_data['github_avatar'])) {
    $avatar_src = $u_data['github_avatar'];
}

// 1. TAMBAH / UPDATE MAPEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_mapel'])) {
    csrf_require();
    $edit_id    = (int)($_POST['edit_id'] ?? 0);
    $kode_mapel = mysqli_real_escape_string($koneksi, trim($_POST['kode_mapel']));
    $nama_mapel = mysqli_real_escape_string($koneksi, trim($_POST['nama_mapel']));
    $guru_ref   = !empty($_POST['guru_id']) ? (int)$_POST['guru_id'] : "NULL";

    if ($edit_id > 0) {
        $q = "UPDATE mapel SET kode_mapel='$kode_mapel', nama_mapel='$nama_mapel', guru_id=$guru_ref WHERE id='$edit_id'";
    } else {
        $q = "INSERT INTO mapel (kode_mapel, nama_mapel, guru_id) VALUES ('$kode_mapel', '$nama_mapel', $guru_ref)";
    }

    if (mysqli_query($koneksi, $q)) {
        $msg_type = "success";
        $msg_text = ($edit_id > 0) ? "Mata pelajaran berhasil diperbarui!" : "Mata pelajaran baru berhasil disimpan!";
    } else {
        $msg_type = "error";
        $msg_text = "Gagal menyimpan mapel: " . mysqli_error($koneksi);
    }
}

// 2. HAPUS MAPEL
if (isset($_GET['hapus']) && csrf_check($_GET['t'] ?? null)) {
    $hapus_id = (int)$_GET['hapus'];
    if (mysqli_query($koneksi, "DELETE FROM mapel WHERE id = '$hapus_id'")) {
        $msg_type = "success";
        $msg_text = "Mata pelajaran berhasil dihapus!";
    }
}

// 3. FETCH MAPEL & DAFTAR GURU
$list_mapel = [];
$q_mapel = mysqli_query($koneksi, "SELECT m.*, g.nama AS nama_guru FROM mapel m LEFT JOIN guru g ON m.guru_id = g.id ORDER BY m.nama_mapel ASC");
if ($q_mapel) {
    while ($r = mysqli_fetch_assoc($q_mapel)) {
        $list_mapel[] = $r;
    }
}

$list_guru_opt = [];
$q_g = mysqli_query($koneksi, "SELECT id, nama FROM guru ORDER BY nama ASC");
if ($q_g) {
    while ($r = mysqli_fetch_assoc($q_g)) {
        $list_guru_opt[] = $r;
    }
}

// METRIK KURIKULUM & BEBAN PENGAJAR
$tot_mapel        = count($list_mapel);
$mapel_assigned   = 0;
$guru_load_counts = [];

foreach ($list_mapel as $m) {
    if (!empty($m['guru_id']) && !empty($m['nama_guru'])) {
        $mapel_assigned++;
        $g_key = $m['nama_guru'];
        if (!isset($guru_load_counts[$g_key])) {
            $guru_load_counts[$g_key] = 0;
        }
        $guru_load_counts[$g_key]++;
    }
}

arsort($guru_load_counts);
$top_pengampu      = array_slice($guru_load_counts, 0, 4, true);
$max_pengampu_load = !empty($top_pengampu) ? max(1, max($top_pengampu)) : 1;

$mapel_unassigned = max(0, $tot_mapel - $mapel_assigned);
$assigned_pct     = $tot_mapel > 0 ? round(($mapel_assigned / $tot_mapel) * 100) : 0;
$active_teachers  = count($guru_load_counts);

// Geometri SVG Donut Ring
$ring_r    = 36;
$ring_circ = round(2 * M_PI * $ring_r, 2);
$ring_dash = round(($assigned_pct / 100) * $ring_circ, 2);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mata Pelajaran - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    <style>
        .dm-shell {
            --dm-band:  #efefe5;
            --dm-ink:   #161616;
            --dm-soft:  #3b3b37;
            --dm-muted: #6f6f68;
            --dm-dim:   #86867e;
            --dm-tile:  #f2f2f4;
            --dm-line:  #e4e4dc;
            --dm-hatch: #b4b4a8;
            --dm-ok:    #ffffff;
            --dm-bad:   #a8432d;

            display: flex;
            flex-direction: column;
            gap: 20px;
            container-type: inline-size;
            color: var(--dm-ink);
            animation: fadeInUp 0.5s ease-out forwards;
        }

        .dm-shell :where(a, button, input, select, [tabindex]):focus-visible {
            outline: 2px solid var(--dm-ink);
            outline-offset: 2px;
        }

        /* ---------- Kartu & Pill ---------- */
        .dm-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            padding: 26px 30px;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .dm-card-cream {
            background: var(--dm-band);
            padding: 32px 34px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 24px;
        }

        .dm-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 999px;
            background: #e3e3d6;
            color: var(--dm-ink);
            font-size: 12px;
            font-weight: 500;
            line-height: 1.25;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }

        a.dm-pill:hover,
        button.dm-pill:hover {
            background: #d6d6c7;
        }

        .dm-pill-dark {
            background: var(--dm-ink);
            color: #ffffff;
        }

        a.dm-pill-dark:hover,
        button.dm-pill-dark:hover {
            background: #2f2f2c;
            color: #ffffff;
        }

        .dm-pill-line {
            background: transparent;
            border: 1px solid var(--dm-line);
        }

        a.dm-pill-line:hover,
        button.dm-pill-line:hover {
            background: var(--dm-tile);
        }

        .dm-pill-danger {
            background: #faece8;
            color: var(--dm-bad);
        }

        .dm-pill-danger:hover {
            background: #f4dcd5;
        }

        .dm-dot-live {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2d7a47;
            display: inline-block;
        }

        /* ---------- 1. Baris Atas: Syllabus Hero + Workload Card ---------- */
        .dm-top-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
            gap: 20px;
            align-items: stretch;
        }

        .dm-eyebrow {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }

        .dm-h1 {
            font-size: clamp(26px, 3cqw, 39px);
            font-weight: 400;
            line-height: 1.12;
            letter-spacing: -0.025em;
            color: var(--dm-ink);
        }

        .dm-dim {
            color: var(--dm-dim);
        }

        .dm-sub {
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.5;
            color: var(--dm-muted);
            max-width: 52ch;
        }

        /* KPI + SVG Ring Gauge */
        .dm-kpi-cluster {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding-top: 18px;
            border-top: 1px solid rgba(22, 22, 22, 0.12);
        }

        .dm-kpi-num {
            font-size: clamp(46px, 5.4cqw, 74px);
            font-weight: 300;
            line-height: 0.96;
            letter-spacing: -0.045em;
            font-variant-numeric: tabular-nums;
        }

        .dm-kpi-num small {
            margin-left: 0.12em;
            font-size: 0.28em;
            font-weight: 400;
            letter-spacing: 0;
            color: var(--dm-dim);
        }

        .dm-kpi-meta {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px 16px;
            font-size: 12px;
            color: var(--dm-muted);
        }

        .dm-kpi-meta strong {
            color: var(--dm-ink);
            font-weight: 600;
        }

        .dm-gauge-box {
            display: flex;
            align-items: center;
            gap: 14px;
            background: rgba(255, 255, 255, 0.65);
            border: 1px solid rgba(22, 22, 22, 0.08);
            padding: 12px 16px;
            border-radius: 16px;
        }

        .dm-gauge-svg {
            width: 84px;
            height: 84px;
            flex: 0 0 84px;
        }

        .dm-gauge-caption {
            font-size: 12px;
            line-height: 1.4;
            color: var(--dm-soft);
        }

        .dm-gauge-caption strong {
            display: block;
            font-size: 14px;
            color: var(--dm-ink);
        }

        /* Right Card: Distribusi Pengampu */
        .dm-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .dm-title {
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--dm-ink);
        }

        .dm-muted {
            font-size: 12px;
            color: var(--dm-muted);
            margin-top: 3px;
            line-height: 1.45;
        }

        .dm-load-list {
            display: flex;
            flex-direction: column;
            gap: 13px;
        }

        .dm-load-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dm-load-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 12.5px;
        }

        .dm-load-name {
            font-weight: 600;
            color: var(--dm-ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dm-load-val {
            font-variant-numeric: tabular-nums;
            font-size: 12px;
            color: var(--dm-muted);
            white-space: nowrap;
        }

        .dm-track {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background-color: rgba(0, 0, 0, 0.04);
            background-image: repeating-linear-gradient(
                135deg,
                var(--dm-hatch) 0 1px,
                transparent 1px 4px
            );
        }

        .dm-fill {
            height: 100%;
            border-radius: 999px;
            background: var(--dm-ink);
        }

        .dm-card-foot {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid var(--dm-line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        /* ---------- Alert ---------- */
        .dm-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
        }

        .dm-alert.is-success {
            background: #4A5D4E;
            color: var(--dm-ok);
            border: 1px solid #cce3d3;
        }

        .dm-alert.is-error {
            background: #faece8;
            color: var(--dm-bad);
            border: 1px solid #efd2ca;
        }

        /* ---------- 2. Horizontal Syllabus Composer Bar ---------- */
        .dm-composer.is-editing {
            border: 1.5px solid var(--dm-ink);
            box-shadow: 0 12px 30px rgba(22, 22, 22, 0.08);
        }

        .dm-composer-grid {
            display: grid;
            grid-template-columns: auto 150px minmax(200px, 1.5fr) minmax(190px, 1.1fr) auto;
            gap: 14px;
            align-items: end;
        }

        .dm-code-preview {
            height: 41px;
            padding: 0 14px;
            border-radius: 10px;
            background: var(--dm-band);
            border: 1px solid rgba(22, 22, 22, 0.12);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: var(--dm-ink);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .dm-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dm-field label {
            font-size: 12px;
            font-weight: 600;
            color: var(--dm-soft);
        }

        .dm-input {
            width: 100%;
            padding: 10px 13px;
            border-radius: 10px;
            background: #f8f8f5;
            border: 1px solid var(--dm-line);
            color: var(--dm-ink);
            font-size: 13px;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .dm-input:hover {
            border-color: #cfcfc4;
        }

        .dm-input:focus {
            background: #ffffff;
            border-color: var(--dm-ink);
        }

        .dm-btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 41px;
            padding: 0 22px;
            border-radius: 999px;
            background: var(--dm-ink);
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.15s ease;
            white-space: nowrap;
        }

        .dm-btn-submit:hover {
            background: #2f2f2c;
            transform: translateY(-1px);
        }

        .dm-btn-cancel {
            display: none;
        }

        /* ---------- 3. Full-Width Course Matrix & Table ---------- */
        .dm-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-bottom: 16px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--dm-line);
        }

        .dm-filter-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .dm-fpill {
            padding: 6px 13px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            color: var(--dm-soft);
            background: var(--dm-tile);
            transition: all 0.15s ease;
        }

        .dm-fpill:hover {
            background: #e6e6e9;
        }

        .dm-fpill.is-active {
            background: var(--dm-ink);
            color: #ffffff;
        }

        .dm-view-toggle {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px;
            border-radius: 999px;
            background: var(--dm-tile);
            border: 1px solid var(--dm-line);
        }

        .dm-vbtn {
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--dm-muted);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .dm-vbtn.is-active {
            background: #ffffff;
            color: var(--dm-ink);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        }

        /* 3-Column Syllabus Matrix Cards */
        .dm-matrix {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .dm-course-card {
            border: 1px solid var(--dm-line);
            border-radius: 16px;
            padding: 20px;
            background: #fcfcfa;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
            transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dm-course-card:hover {
            border-color: var(--dm-ink);
            background: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.05);
        }

        .dm-course-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .dm-plaque {
            display: inline-flex;
            align-items: center;
            padding: 5px 11px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            font-variant-numeric: tabular-nums;
            background: var(--dm-band);
            color: var(--dm-ink);
            border: 1px solid rgba(22, 22, 22, 0.1);
        }

        .dm-plaque.is-assigned {
            background: var(--dm-ink);
            color: #ffffff;
            border-color: var(--dm-ink);
        }

        .dm-course-title {
            margin-top: 12px;
            font-size: 15.5px;
            font-weight: 600;
            line-height: 1.35;
            color: var(--dm-ink);
        }

        .dm-course-foot {
            padding-top: 12px;
            border-top: 1px solid var(--dm-line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .dm-teacher-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            font-size: 12.5px;
            color: var(--dm-soft);
        }

        .dm-teacher-ava {
            width: 24px;
            height: 24px;
            flex: 0 0 24px;
            border-radius: 50%;
            background: var(--dm-band);
            color: var(--dm-ink);
            font-size: 10.5px;
            font-weight: 700;
            display: grid;
            place-items: center;
        }

        .dm-teacher-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
        }

        .dm-unassigned-tag {
            font-size: 11.5px;
            color: var(--dm-bad);
            background: #faece8;
            padding: 3px 9px;
            border-radius: 999px;
            font-weight: 600;
        }

        /* Compact Table View */
        .dm-table-wrap {
            display: none;
            overflow-x: auto;
        }

        .dm-table-wrap.is-visible {
            display: block;
        }

        .dm-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .dm-table th {
            padding: 11px 14px;
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--dm-muted);
            border-bottom: 1px solid var(--dm-ink);
            white-space: nowrap;
        }

        .dm-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--dm-line);
            vertical-align: middle;
        }

        .dm-table tbody tr:hover {
            background: #fafaf6;
        }

        .btn-action-img {
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            padding: 6px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease, transform 0.15s ease;
            text-decoration: none;
        }

        .btn-action-img:hover {
            background: var(--dm-band);
            transform: scale(1.1);
        }

        .btn-action-img img {
            width: 18px;
            height: 18px;
            object-fit: contain;
        }

        /* Responsive Container Queries */
        @container (max-width: 1040px) {
            .dm-top-grid {
                grid-template-columns: minmax(0, 1fr);
            }
            .dm-composer-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .dm-code-preview {
                display: none;
            }
            .dm-matrix {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @container (max-width: 640px) {
            .dm-card,
            .dm-card-cream {
                padding: 22px 18px;
            }
            .dm-composer-grid,
            .dm-matrix {
                grid-template-columns: minmax(0, 1fr);
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
                <li><a href="dashboard.php"><i class="dashboard"></i> <span class="menu-label">Dashboard</span></a></li>
                <li class="has-submenu active" tabindex="0">
                    <a href="javascript:void(0)" class="active"><i class="master"></i> <span class="menu-label">Master Data</span></a>
                    <ul class="submenu">
                        <li><a href="daftar_user.php"><span class="dot user"></span> <span class="menu-label">Kelola User</span></a></li>
                        <li><a href="daftar_siswa.php"><span class="dot siswa"></span> <span class="menu-label">Kelola Siswa</span></a></li>
                        <li><a href="daftar_guru.php"><span class="dot guru"></span> <span class="menu-label">Kelola Guru</span></a></li>
                        <li><a href="daftar_mapel.php" style="font-weight: 700; color: #fff;"><span class="dot mapel"></span> <span class="menu-label">Mata Pelajaran</span></a></li>
					<li><a href="manajemen_kelas.php"><span class="dot kelas"></span> <span class="menu-label">Kelola Kelas &amp; Plotting</span></a></li>
                    </ul>
                </li>
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
                    <h1>Mata Pelajaran</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <div class="search-box">
                        <i class="zoom"></i>
                        <input type="text" id="tableSearch" placeholder="Cari kode mapel, pelajaran, guru...">
                    </div>
                    <button class="icon-btn notification-btn"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/bell.webp" alt="Notifikasi"></button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/settings.webp" alt="Settings"></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="dm-shell">
                <!-- BARIS 1: SYLLABUS HERO + WORKLOAD RANKING -->
                <section class="dm-top-grid">
                    <div class="dm-card dm-card-cream">
                        <div>
                            <div class="dm-eyebrow">
                                <span class="dm-pill"><span class="dm-dot-live"></span> Curriculum Matrix</span>
                                <span class="dm-pill">SMK Sangkuriang 1 Cimahi</span>
                            </div>
                            <h2 class="dm-h1">Kurikulum <span class="dm-dim">&amp; daftar</span><br>mata pelajaran</h2>
                            <p class="dm-sub">Susun kode silabus akademik dan tetapkan guru pengampu untuk setiap mata pelajaran di sistem penilaian.</p>
                        </div>

                        <div class="dm-kpi-cluster">
                            <div>
                                <div class="dm-kpi-num"><?= $tot_mapel; ?><small>mapel</small></div>
                                <div class="dm-kpi-meta">
                                    <span><strong><?= $mapel_assigned; ?></strong> terampu</span>
                                    <span><strong><?= $mapel_unassigned; ?></strong> belum ditentukan</span>
                                    <span><strong><?= $active_teachers; ?></strong> pengampu aktif</span>
                                </div>
                            </div>

                            <div class="dm-gauge-box">
                                <svg class="dm-gauge-svg" viewBox="0 0 92 92" role="img" aria-label="Cakupan pengampu mata pelajaran <?= $assigned_pct; ?> persen">
                                    <circle cx="46" cy="46" r="<?= $ring_r; ?>" fill="none" stroke="#d9d9ce" stroke-width="8"/>
                                    <circle cx="46" cy="46" r="<?= $ring_r; ?>" fill="none" stroke="#161616" stroke-width="8"
                                            stroke-linecap="round"
                                            stroke-dasharray="<?= $ring_dash; ?> <?= $ring_circ; ?>"
                                            transform="rotate(-90 46 46)"/>
                                    <text x="46" y="50" text-anchor="middle" font-size="16" font-weight="600" fill="#161616"><?= $assigned_pct; ?>%</text>
                                </svg>
                                <div class="dm-gauge-caption">
                                    <strong>Cakupan Pengampu</strong>
                                    <?= $mapel_assigned; ?> dari <?= $tot_mapel; ?> mapel memiliki guru
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dm-card" style="display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div class="dm-head">
                                <div>
                                    <h3 class="dm-title">Distribusi Pengampu</h3>
                                    <p class="dm-muted">Beban jumlah mata pelajaran per guru</p>
                                </div>
                                <a href="daftar_guru.php" class="dm-pill dm-pill-line">Direktori Guru</a>
                            </div>

                            <?php if (!empty($top_pengampu)): ?>
                                <div class="dm-load-list">
                                    <?php foreach ($top_pengampu as $g_nama => $jml):
                                        $w_pct = round(($jml / $max_pengampu_load) * 100);
                                    ?>
                                        <div class="dm-load-row">
                                            <div class="dm-load-top">
                                                <span class="dm-load-name"><?= htmlspecialchars($g_nama); ?></span>
                                                <span class="dm-load-val"><?= $jml; ?> mapel</span>
                                            </div>
                                            <div class="dm-track">
                                                <div class="dm-fill" style="width: <?= $w_pct; ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="dm-muted" style="padding: 24px 0;">Belum ada guru yang ditautkan ke mata pelajaran.</p>
                            <?php endif; ?>
                        </div>

                        <div class="dm-card-foot">
                            <span class="dm-muted"><?= count($list_guru_opt); ?> guru tersedia di sistem</span>
                            <a href="#formCard" class="dm-pill dm-pill-dark" onclick="document.getElementById('kode_mapel').focus();">+ Mapel Baru</a>
                        </div>
                    </div>
                </section>

                <?php if (!empty($msg_text)): ?>
                    <div class="dm-alert <?= ($msg_type === 'success') ? 'is-success' : 'is-error'; ?>" role="status">
                        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                        <span><?= htmlspecialchars($msg_text); ?></span>
                    </div>
                <?php endif; ?>

                <!-- BARIS 2: HORIZONTAL SYLLABUS COMPOSER -->
                <section class="dm-card dm-composer" id="formCard">
                    <div class="dm-head">
                        <div>
                            <h3 class="dm-title" id="formTitle">Tambah Mata Pelajaran</h3>
                            <p class="dm-muted" id="formSub">Masukkan kode unik mata pelajaran, nama silabus, dan guru pengampu.</p>
                        </div>
                        <button type="button" class="dm-pill dm-pill-danger dm-btn-cancel" id="btnCancelEdit" onclick="resetFormMapel()">
                            <i class="fa-solid fa-xmark"></i> Batal Edit
                        </button>
                    </div>

                    <form method="POST" action="daftar_mapel.php" id="mapelForm">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="edit_id" id="edit_id" value="0">

                        <div class="dm-composer-grid">
                            <div class="dm-code-preview" id="codePreviewBadge" aria-hidden="true">MP-00</div>

                            <div class="dm-field">
                                <label for="kode_mapel">Kode Mapel</label>
                                <input type="text" name="kode_mapel" id="kode_mapel" class="dm-input" required placeholder="Ex: MP10" oninput="syncCodePreview()">
                            </div>

                            <div class="dm-field">
                                <label for="nama_mapel">Nama Mata Pelajaran</label>
                                <input type="text" name="nama_mapel" id="nama_mapel" class="dm-input" required placeholder="Contoh: Pemrograman Web & Perangkat Bergerak">
                            </div>

                            <div class="dm-field">
                                <label for="guru_id">Guru Pengampu</label>
                                <select name="guru_id" id="guru_id" class="dm-input">
                                    <option value="">Belum Ditentukan</option>
                                    <?php foreach ($list_guru_opt as $g): ?>
                                        <option value="<?= (int)$g['id']; ?>"><?= htmlspecialchars($g['nama']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" name="simpan_mapel" class="dm-btn-submit">
                                <i class="fa-solid fa-check"></i> <span id="submitLabel">Simpan Mapel</span>
                            </button>
                        </div>
                    </form>
                </section>

                <!-- BARIS 3: FULL-WIDTH COURSE MATRIX & TABLE -->
                <section class="dm-card">
                    <div class="dm-head">
                        <div>
                            <h3 class="dm-title">Katalog Mata Pelajaran</h3>
                            <p class="dm-muted">Daftar seluruh mata pelajaran aktif beserta status penugasan guru.</p>
                        </div>
                        <span class="dm-pill" id="rowCountBadge"><?= $tot_mapel; ?> mapel</span>
                    </div>

                    <div class="dm-toolbar">
                        <div class="dm-filter-pills" role="group" aria-label="Filter mata pelajaran">
                            <button type="button" class="dm-fpill is-active" data-filter="all" onclick="setMapelFilter('all', this)">Semua (<?= $tot_mapel; ?>)</button>
                            <button type="button" class="dm-fpill" data-filter="assigned" onclick="setMapelFilter('assigned', this)">Sudah Diampu (<?= $mapel_assigned; ?>)</button>
                            <button type="button" class="dm-fpill" data-filter="unassigned" onclick="setMapelFilter('unassigned', this)">Belum Diampu (<?= $mapel_unassigned; ?>)</button>
                        </div>

                        <div class="dm-view-toggle" role="group" aria-label="Mode tampilan">
                            <button type="button" class="dm-vbtn is-active" id="btnViewMatrix" onclick="switchMapelView('matrix')">
                                <i class="kartu"></i> Matriks
                            </button>
                            <button type="button" class="dm-vbtn" id="btnViewTable" onclick="switchMapelView('table')">
                                <i class="tabel"></i> Tabel
                            </button>
                        </div>
                    </div>

                    <!-- MODE 1: 3-COLUMN COURSE MATRIX -->
                    <div class="dm-matrix" id="matrixViewWrap">
                        <?php if (!empty($list_mapel)): ?>
                            <?php foreach ($list_mapel as $idx => $m):
                                $has_guru = !empty($m['nama_guru']);
                                $status_key = $has_guru ? 'assigned' : 'unassigned';
                            ?>
                                <article class="dm-course-card dm-item" data-status="<?= $status_key; ?>">
                                    <div>
                                        <div class="dm-course-head">
                                            <span class="dm-plaque <?= $has_guru ? 'is-assigned' : ''; ?>">
                                                <?= htmlspecialchars($m['kode_mapel']); ?>
                                            </span>
                                            <span style="font-size: 11.5px; color: var(--dm-dim); font-variant-numeric: tabular-nums;">
                                                #<?= str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT); ?>
                                            </span>
                                        </div>
                                        <h4 class="dm-course-title"><?= htmlspecialchars($m['nama_mapel']); ?></h4>
                                    </div>

                                    <div class="dm-course-foot">
                                        <?php if ($has_guru): ?>
                                            <div class="dm-teacher-pill" title="<?= htmlspecialchars($m['nama_guru']); ?>">
                                                <span class="dm-teacher-ava"><?= strtoupper(substr($m['nama_guru'], 0, 1)); ?></span>
                                                <span class="dm-teacher-name"><?= htmlspecialchars($m['nama_guru']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="dm-unassigned-tag">Belum ditentukan</span>
                                        <?php endif; ?>

                                        <div style="white-space: nowrap;">
                                            <button type="button"
                                                class="btn-action-img"
                                                title="Edit"
                                                data-id="<?= (int)$m['id']; ?>"
                                                data-kode="<?= htmlspecialchars($m['kode_mapel'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-nama="<?= htmlspecialchars($m['nama_mapel'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-guruid="<?= htmlspecialchars((string)($m['guru_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="editMapelFromBtn(this)">
                                                <img src="<?= $base_url; ?>/icons/admin/admin_manage/edit.webp" alt="Edit">
                                            </button>
                                            <a href="daftar_mapel.php?hapus=<?= (int)$m['id']; ?>&t=<?= csrf_token(); ?>"
                                               onclick="return confirm('Hapus mapel ini?');"
                                               class="btn-action-img"
                                               title="Hapus">
                                                <img src="<?= $base_url; ?>/icons/admin/admin_manage/trash-bin.webp" alt="Hapus">
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="grid-column: 1 / -1; text-align: center; padding: 36px; color: var(--dm-muted);">Belum ada mata pelajaran terdaftar.</p>
                        <?php endif; ?>
                    </div>

                    <!-- MODE 2: COMPACT TABLE VIEW -->
                    <div class="dm-table-wrap" id="tableViewWrap">
                        <table class="dm-table" id="dataTable">
                            <thead>
                                <tr>
                                    <th style="width: 48px; text-align: center;">No</th>
                                    <th style="width: 130px;">Kode Mapel</th>
                                    <th>Nama Mata Pelajaran</th>
                                    <th>Guru Pengampu</th>
                                    <th style="text-align: center; width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($list_mapel)): ?>
                                    <?php foreach ($list_mapel as $idx => $m):
                                        $has_guru = !empty($m['nama_guru']);
                                        $status_key = $has_guru ? 'assigned' : 'unassigned';
                                    ?>
                                        <tr class="dm-item" data-status="<?= $status_key; ?>">
                                            <td style="text-align: center; font-weight: 600; color: var(--dm-muted);"><?= $idx + 1; ?></td>
                                            <td>
                                                <span class="dm-plaque <?= $has_guru ? 'is-assigned' : ''; ?>">
                                                    <?= htmlspecialchars($m['kode_mapel']); ?>
                                                </span>
                                            </td>
                                            <td><strong><?= htmlspecialchars($m['nama_mapel']); ?></strong></td>
                                            <td>
                                                <?php if ($has_guru): ?>
                                                    <div class="dm-teacher-pill">
                                                        <span class="dm-teacher-ava"><?= strtoupper(substr($m['nama_guru'], 0, 1)); ?></span>
                                                        <span><?= htmlspecialchars($m['nama_guru']); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="dm-unassigned-tag">Belum ditentukan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center; white-space: nowrap;">
                                                <button type="button"
                                                    class="btn-action-img"
                                                    title="Edit"
                                                    data-id="<?= (int)$m['id']; ?>"
                                                    data-kode="<?= htmlspecialchars($m['kode_mapel'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-nama="<?= htmlspecialchars($m['nama_mapel'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-guruid="<?= htmlspecialchars((string)($m['guru_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    onclick="editMapelFromBtn(this)">
                                                    <img src="<?= $base_url; ?>/icons/admin/admin_manage/edit.webp" alt="Edit">
                                                </button>
                                                <a href="daftar_mapel.php?hapus=<?= (int)$m['id']; ?>&t=<?= csrf_token(); ?>"
                                                   onclick="return confirm('Hapus mapel ini?');"
                                                   class="btn-action-img"
                                                   title="Hapus">
                                                    <img src="<?= $base_url; ?>/icons/admin/admin_manage/trash-bin.webp" alt="Hapus">
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 36px; color: var(--dm-muted);">Belum ada mata pelajaran terdaftar.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<script>
    let activeMapelFilter = 'all';

    function syncCodePreview() {
        const val = document.getElementById('kode_mapel').value.trim().toUpperCase();
        document.getElementById('codePreviewBadge').textContent = val || 'MP-00';
    }

    function editMapelFromBtn(btn) {
        document.getElementById('edit_id').value    = btn.dataset.id;
        document.getElementById('kode_mapel').value = btn.dataset.kode;
        document.getElementById('nama_mapel').value = btn.dataset.nama;
        document.getElementById('guru_id').value    = btn.dataset.guruid;
        syncCodePreview();

        document.getElementById('formTitle').textContent   = 'Edit Mata Pelajaran #' + btn.dataset.id;
        document.getElementById('formSub').textContent     = 'Perbarui kode silabus, nama pelajaran, atau guru pengampu.';
        document.getElementById('submitLabel').textContent = 'Perbarui Mapel';
        document.getElementById('formCard').classList.add('is-editing');
        document.getElementById('btnCancelEdit').style.display = 'inline-flex';
        document.getElementById('formCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function resetFormMapel() {
        document.getElementById('mapelForm').reset();
        document.getElementById('edit_id').value = 0;
        syncCodePreview();

        document.getElementById('formTitle').textContent   = 'Tambah Mata Pelajaran';
        document.getElementById('formSub').textContent     = 'Masukkan kode unik mata pelajaran, nama silabus, dan guru pengampu.';
        document.getElementById('submitLabel').textContent = 'Simpan Mapel';
        document.getElementById('formCard').classList.remove('is-editing');
        document.getElementById('btnCancelEdit').style.display = 'none';
    }

    function applyMapelFilters() {
        const q = (document.getElementById('tableSearch')?.value || '').toLowerCase().trim();
        const cards = document.querySelectorAll('#matrixViewWrap .dm-item');
        const rows  = document.querySelectorAll('#dataTable tbody tr.dm-item');
        let visibleCount = 0;

        cards.forEach(card => {
            const matchText = card.textContent.toLowerCase().includes(q);
            const matchCat  = (activeMapelFilter === 'all') || (card.dataset.status === activeMapelFilter);
            const show = matchText && matchCat;
            card.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        rows.forEach(tr => {
            const matchText = tr.textContent.toLowerCase().includes(q);
            const matchCat  = (activeMapelFilter === 'all') || (tr.dataset.status === activeMapelFilter);
            tr.style.display = (matchText && matchCat) ? '' : 'none';
        });

        const badge = document.getElementById('rowCountBadge');
        if (badge) badge.textContent = visibleCount + ' mapel';
    }

    function setMapelFilter(filterKey, btnEl) {
        activeMapelFilter = filterKey;
        document.querySelectorAll('.dm-fpill').forEach(b => b.classList.remove('is-active'));
        if (btnEl) btnEl.classList.add('is-active');
        applyMapelFilters();
    }

    function switchMapelView(mode) {
        const matrixWrap = document.getElementById('matrixViewWrap');
        const tableWrap  = document.getElementById('tableViewWrap');
        const btnMatrix  = document.getElementById('btnViewMatrix');
        const btnTable   = document.getElementById('btnViewTable');

        if (mode === 'table') {
            matrixWrap.style.display = 'none';
            tableWrap.classList.add('is-visible');
            btnMatrix.classList.remove('is-active');
            btnTable.classList.add('is-active');
        } else {
            matrixWrap.style.display = '';
            tableWrap.classList.remove('is-visible');
            btnTable.classList.remove('is-active');
            btnMatrix.classList.add('is-active');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('tableSearch');
        if (searchInput) {
            searchInput.addEventListener('input', applyMapelFilters);
        }
    });
</script>
</body>
</html>