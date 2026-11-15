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

// 1. TAMBAH / UPDATE SISWA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_siswa'])) {
    csrf_require();
    $edit_id  = (int)($_POST['edit_id'] ?? 0);
    $nis      = mysqli_real_escape_string($koneksi, trim($_POST['nis']));
    $nama     = mysqli_real_escape_string($koneksi, trim($_POST['nama']));
    $kelas    = mysqli_real_escape_string($koneksi, trim($_POST['kelas']));
    $jk       = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $user_ref = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : "NULL";

    if ($edit_id > 0) {
        $q = "UPDATE siswa SET nis='$nis', nama='$nama', kelas='$kelas', jenis_kelamin='$jk', user_id=$user_ref WHERE id='$edit_id'";
    } else {
        $q = "INSERT INTO siswa (nis, nama, kelas, jenis_kelamin, user_id) VALUES ('$nis', '$nama', '$kelas', '$jk', $user_ref)";
    }

    if (mysqli_query($koneksi, $q)) {
        $msg_type = "success";
        $msg_text = ($edit_id > 0) ? "Data siswa berhasil diperbarui!" : "Data siswa baru berhasil ditambahkan!";
    } else {
        $msg_type = "error";
        $msg_text = "Gagal menyimpan data siswa: " . mysqli_error($koneksi);
    }
}

// 2. HAPUS SISWA
if (isset($_GET['hapus']) && csrf_check($_GET['t'] ?? null)) {
    $hapus_id = (int)$_GET['hapus'];
    if (mysqli_query($koneksi, "DELETE FROM siswa WHERE id = '$hapus_id'")) {
        $msg_type = "success";
        $msg_text = "Data siswa berhasil dihapus!";
    }
}

// 3. FETCH DATA SISWA & USERS SISWA UNTUK DROPDOWN
$list_siswa = [];
$q_siswa = mysqli_query($koneksi, "SELECT s.*, u.username FROM siswa s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.nama ASC");
if ($q_siswa) {
    while ($r = mysqli_fetch_assoc($q_siswa)) {
        $list_siswa[] = $r;
    }
}

$users_siswa = [];
$q_us = mysqli_query($koneksi, "SELECT id, username FROM users WHERE role = 'siswa' ORDER BY username ASC");
if ($q_us) {
    while ($r = mysqli_fetch_assoc($q_us)) {
        $users_siswa[] = $r;
    }
}

// METRIK KOHORT & ROMBONGAN BELAJAR
$tot_siswa       = count($list_siswa);
$siswa_linked    = 0;
$siswa_laki      = 0;
$siswa_perempuan = 0;
$kelas_counts    = [];
$used_user_ids   = [];

foreach ($list_siswa as $s) {
    if (!empty($s['user_id'])) {
        $siswa_linked++;
        $used_user_ids[(int)$s['user_id']] = true;
    }
    if (($s['jenis_kelamin'] ?? 'L') === 'P') {
        $siswa_perempuan++;
    } else {
        $siswa_laki++;
    }
    $k_label = trim((string)($s['kelas'] ?? ''));
    if ($k_label !== '') {
        if (!isset($kelas_counts[$k_label])) {
            $kelas_counts[$k_label] = 0;
        }
        $kelas_counts[$k_label]++;
    }
}

ksort($kelas_counts, SORT_NATURAL | SORT_FLAG_CASE);
$tot_kelas      = count($kelas_counts);
$siswa_unlinked = max(0, $tot_siswa - $siswa_linked);
$linked_pct     = $tot_siswa > 0 ? round(($siswa_linked / $tot_siswa) * 100) : 0;

$akun_tersedia = 0;
foreach ($users_siswa as $us) {
    if (!isset($used_user_ids[(int)$us['id']])) {
        $akun_tersedia++;
    }
}

// Top 5 Rombel untuk Grafik Kolom Vertikal
$top_kelas = $kelas_counts;
arsort($top_kelas);
$top_kelas = array_slice($top_kelas, 0, 5, true);
$max_kelas_pop = !empty($top_kelas) ? max(1, max($top_kelas)) : 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Siswa - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    <style>
        .ds-shell {
            --ds-band:  #efefe5;
            --ds-ink:   #161616;
            --ds-soft:  #3b3b37;
            --ds-muted: #6f6f68;
            --ds-dim:   #86867e;
            --ds-tile:  #f2f2f4;
            --ds-line:  #e4e4dc;
            --ds-hatch: #b4b4a8;
            --ds-ok:    #25683d;
            --ds-bad:   #a8432d;

            display: flex;
            flex-direction: column;
            gap: 20px;
            container-type: inline-size;
            color: var(--ds-ink);
            animation: fadeInUp 0.5s ease-out forwards;
        }

        .ds-shell :where(a, button, input, select, [tabindex]):focus-visible {
            outline: 2px solid var(--ds-ink);
            outline-offset: 2px;
        }

        /* ---------- Kartu & Pill ---------- */
        .ds-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            padding: 26px 30px;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .ds-card-cream {
            background: var(--ds-band);
            padding: 32px 34px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 22px;
        }

        .ds-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 999px;
            background: #e3e3d6;
            color: var(--ds-ink);
            font-size: 12px;
            font-weight: 500;
            line-height: 1.25;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }

        a.ds-pill:hover,
        button.ds-pill:hover {
            background: #d6d6c7;
        }

        .ds-pill-dark {
            background: var(--ds-ink);
            color: #ffffff;
        }

        a.ds-pill-dark:hover,
        button.ds-pill-dark:hover {
            background: #2f2f2c;
            color: #ffffff;
        }

        .ds-pill-line {
            background: transparent;
            border: 1px solid var(--ds-line);
        }

        a.ds-pill-line:hover,
        button.ds-pill-line:hover {
            background: var(--ds-tile);
        }

        .ds-pill-danger {
            background: #faece8;
            color: var(--ds-bad);
        }

        .ds-pill-danger:hover {
            background: #f4dcd5;
        }

        .ds-dot-live {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2d7a47;
            display: inline-block;
        }

        /* ---------- 1. Cohort Hero + Vertical Class Columns ---------- */
        .ds-top-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.38fr) minmax(0, 1fr);
            gap: 20px;
            align-items: stretch;
        }

        .ds-eyebrow {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }

        .ds-h1 {
            font-size: clamp(26px, 3cqw, 39px);
            font-weight: 400;
            line-height: 1.12;
            letter-spacing: -0.025em;
            color: var(--ds-ink);
        }

        .ds-dim {
            color: var(--ds-dim);
        }

        .ds-sub {
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.5;
            color: var(--ds-muted);
            max-width: 54ch;
        }

        .ds-hero-bottom {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            padding-top: 18px;
            border-top: 1px solid rgba(22, 22, 22, 0.12);
        }

        .ds-kpi-num {
            font-size: clamp(46px, 5.4cqw, 74px);
            font-weight: 300;
            line-height: 0.96;
            letter-spacing: -0.045em;
            font-variant-numeric: tabular-nums;
        }

        .ds-kpi-num small {
            margin-left: 0.12em;
            font-size: 0.28em;
            font-weight: 400;
            letter-spacing: 0;
            color: var(--ds-dim);
        }

        .ds-micro-ledger {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(22, 22, 22, 0.08);
            border-radius: 14px;
            padding: 12px 16px;
        }

        .ds-micro-item + .ds-micro-item {
            padding-left: 14px;
            border-left: 1px solid rgba(22, 22, 22, 0.1);
        }

        .ds-micro-val {
            display: block;
            font-size: 20px;
            font-weight: 400;
            line-height: 1.1;
            letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
        }

        .ds-micro-lbl {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: var(--ds-muted);
            white-space: nowrap;
        }

        /* Right Card: Vertical Class Column Visualizer */
        .ds-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .ds-title {
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--ds-ink);
        }

        .ds-muted {
            font-size: 12px;
            color: var(--ds-muted);
            margin-top: 3px;
            line-height: 1.45;
        }

        .ds-cols {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(52px, 1fr));
            gap: 12px;
            align-items: end;
            padding-top: 6px;
        }

        .ds-col-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 7px;
            background: transparent;
            cursor: pointer;
            text-align: center;
        }

        .ds-col-count {
            font-size: 12px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            color: var(--ds-ink);
        }

        .ds-col-track {
            width: 100%;
            max-width: 44px;
            height: 116px;
            border-radius: 10px;
            padding: 3px;
            display: flex;
            align-items: flex-end;
            border: 1px solid var(--ds-line);
            background-color: rgba(0, 0, 0, 0.03);
            background-image: repeating-linear-gradient(
                135deg,
                var(--ds-hatch) 0 1px,
                transparent 1px 4px
            );
            transition: border-color 0.15s ease, transform 0.15s ease;
        }

        .ds-col-btn:hover .ds-col-track {
            border-color: var(--ds-ink);
            transform: translateY(-2px);
        }

        .ds-col-fill {
            width: 100%;
            border-radius: 7px;
            background: var(--ds-ink);
            min-height: 10px;
            transition: height 0.35s ease;
        }

        .ds-col-lbl {
            font-size: 11px;
            font-weight: 600;
            color: var(--ds-soft);
            max-width: 72px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ds-card-foot {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid var(--ds-line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        /* ---------- Alert ---------- */
        .ds-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
        }

        .ds-alert.is-success {
            background: #eaf4ed;
            color: var(--ds-ok);
            border: 1px solid #cce3d3;
        }

        .ds-alert.is-error {
            background: #faece8;
            color: var(--ds-bad);
            border: 1px solid #efd2ca;
        }

        /* ---------- 2. Collapsible Registration Drawer ---------- */
        .ds-drawer.is-editing {
            border: 1.5px solid var(--ds-ink);
            box-shadow: 0 12px 30px rgba(22, 22, 22, 0.08);
        }

        .ds-drawer-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .ds-drawer-body {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--ds-line);
        }

        .ds-drawer.is-collapsed .ds-drawer-body {
            display: none;
        }

        .ds-form-grid-5 {
            display: grid;
            grid-template-columns: 1fr 1.8fr 1.1fr 0.95fr 1.15fr auto;
            gap: 12px;
            align-items: end;
        }

        .ds-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ds-field label {
            font-size: 12px;
            font-weight: 600;
            color: var(--ds-soft);
        }

        .ds-input {
            width: 100%;
            padding: 10px 13px;
            border-radius: 10px;
            background: #f8f8f5;
            border: 1px solid var(--ds-line);
            color: var(--ds-ink);
            font-size: 13px;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .ds-input:hover {
            border-color: #cfcfc4;
        }

        .ds-input:focus {
            background: #ffffff;
            border-color: var(--ds-ink);
        }

        .ds-btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 41px;
            padding: 0 20px;
            border-radius: 999px;
            background: var(--ds-ink);
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.15s ease;
            white-space: nowrap;
        }

        .ds-btn-submit:hover {
            background: #2f2f2c;
            transform: translateY(-1px);
        }

        .ds-btn-cancel {
            display: none;
        }

        /* ---------- 3. Dynamic Rombel Tabs & Roster Table ---------- */
        .ds-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 16px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--ds-line);
        }

        .ds-class-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .ds-ctab {
            padding: 6px 13px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            color: var(--ds-soft);
            background: var(--ds-tile);
            transition: all 0.15s ease;
        }

        .ds-ctab:hover {
            background: #e6e6e9;
        }

        .ds-ctab.is-active {
            background: var(--ds-ink);
            color: #ffffff;
        }

        .ds-status-seg {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px;
            border-radius: 999px;
            background: var(--ds-tile);
            border: 1px solid var(--ds-line);
        }

        .ds-sbtn {
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--ds-muted);
        }

        .ds-sbtn.is-active {
            background: #ffffff;
            color: var(--ds-ink);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        }

        .ds-table-wrap {
            overflow-x: auto;
        }

        .ds-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .ds-table th {
            padding: 11px 14px;
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--ds-muted);
            border-bottom: 1px solid var(--ds-ink);
            white-space: nowrap;
        }

        .ds-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--ds-line);
            vertical-align: middle;
        }

        .ds-table tbody tr {
            transition: background 0.15s ease;
        }

        .ds-table tbody tr:hover {
            background: #fafaf6;
        }

        .ds-person {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .ds-ava {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--ds-band);
            color: var(--ds-ink);
            font-size: 12px;
            font-weight: 700;
            border: 1px solid rgba(22, 22, 22, 0.08);
        }

        .ds-ava.is-dark {
            background: var(--ds-ink);
            color: #ffffff;
        }

        .ds-nis-badge {
            font-variant-numeric: tabular-nums;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ds-soft);
            background: var(--ds-tile);
            padding: 4px 9px;
            border-radius: 7px;
            display: inline-block;
        }

        .ds-class-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 11px;
            border-radius: 999px;
            background: var(--ds-band);
            color: var(--ds-ink);
            font-size: 11.5px;
            font-weight: 600;
            border: 1px solid rgba(22, 22, 22, 0.08);
        }

        .ds-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--ds-tile);
            color: var(--ds-muted);
            font-size: 11px;
            font-weight: 600;
        }

        .ds-chip.is-linked {
            background: #eef3ef;
            color: var(--ds-ok);
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
            background: var(--ds-band);
            transform: scale(1.1);
        }

        .btn-action-img img {
            width: 18px;
            height: 18px;
            object-fit: contain;
        }

        /* Responsive Container Queries */
        @container (max-width: 1060px) {
            .ds-top-grid {
                grid-template-columns: minmax(0, 1fr);
            }
            .ds-form-grid-5 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @container (max-width: 640px) {
            .ds-card,
            .ds-card-cream {
                padding: 22px 18px;
            }
            .ds-micro-ledger {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                width: 100%;
            }
            .ds-form-grid-5 {
                grid-template-columns: minmax(0, 1fr);
            }
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
                        <li><a href="daftar_siswa.php" style="font-weight: 700; color: #fff;"><span class="dot siswa"></span> <span class="menu-label">Kelola Siswa</span></a></li>
                        <li><a href="daftar_guru.php"><span class="dot guru"></span> <span class="menu-label">Kelola Guru</span></a></li>
                        <li><a href="daftar_mapel.php"><span class="dot mapel"></span> <span class="menu-label">Mata Pelajaran</span></a></li>
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
                    <h1>Kelola Data Siswa</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <div class="search-box">
                        <i class="zoom"></i>
                        <input type="text" id="tableSearch" placeholder="Cari NIS, nama siswa, kelas...">
                    </div>
                    <button class="icon-btn notification-btn"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/bell.webp" alt="Notifikasi"></button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/settings.webp" alt="Settings"></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="ds-shell">
                <!-- BARIS 1: COHORT HERO + VERTICAL CLASS COLUMNS -->
                <section class="ds-top-grid">
                    <div class="ds-card ds-card-cream">
                        <div>
                            <div class="ds-eyebrow">
                                <span class="ds-pill"><span class="ds-dot-live"></span> Student Cohort</span>
                                <span class="ds-pill">L <?= $siswa_laki; ?> · P <?= $siswa_perempuan; ?></span>
                                <span class="ds-pill"><?= $linked_pct; ?>% Akun Tertaut</span>
                            </div>
                            <h2 class="ds-h1">Registrasi <span class="ds-dim">peserta didik</span><br>&amp; rombongan belajar</h2>
                            <p class="ds-sub">Kelola nomor induk siswa (NIS), penempatan rombongan belajar, dan sinkronisasi akun portal rapor siswa.</p>
                        </div>

                        <div class="ds-hero-bottom">
                            <div>
                                <div class="ds-kpi-num"><?= $tot_siswa; ?><small>siswa</small></div>
                            </div>

                            <div class="ds-micro-ledger">
                                <div class="ds-micro-item">
                                    <span class="ds-micro-val"><?= $tot_kelas; ?></span>
                                    <span class="ds-micro-lbl">Rombel aktif</span>
                                </div>
                                <div class="ds-micro-item">
                                    <span class="ds-micro-val"><?= $siswa_linked; ?></span>
                                    <span class="ds-micro-lbl">Tertaut akun</span>
                                </div>
                                <div class="ds-micro-item">
                                    <span class="ds-micro-val"><?= $siswa_unlinked; ?></span>
                                    <span class="ds-micro-lbl">Belum tertaut</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ds-card" style="display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div class="ds-head">
                                <div>
                                    <h3 class="ds-title">Populasi Rombel</h3>
                                    <p class="ds-muted">Klik kolom kelas untuk memfilter daftar siswa</p>
                                </div>
                                <button type="button" class="ds-pill ds-pill-line" onclick="selectClassTab('all')">Reset Kelas</button>
                            </div>

                            <?php if (!empty($top_kelas)): ?>
                                <div class="ds-cols">
                                    <?php foreach ($top_kelas as $k_nama => $k_jml):
                                        $bar_h = max(12, round(($k_jml / $max_kelas_pop) * 100));
                                    ?>
                                        <button type="button" class="ds-col-btn"
                                                data-kelas="<?= htmlspecialchars($k_nama, ENT_QUOTES, 'UTF-8'); ?>"
                                                onclick="selectClassTab(this.dataset.kelas)"
                                                title="Filter kelas <?= htmlspecialchars($k_nama); ?>">
                                            <span class="ds-col-count"><?= $k_jml; ?></span>
                                            <div class="ds-col-track">
                                                <div class="ds-col-fill" style="height: <?= $bar_h; ?>%;"></div>
                                            </div>
                                            <span class="ds-col-lbl"><?= htmlspecialchars($k_nama); ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="ds-muted" style="padding: 36px 0;">Belum ada data rombongan belajar.</p>
                            <?php endif; ?>
                        </div>

                        <div class="ds-card-foot">
                            <span class="ds-muted"><?= $akun_tersedia; ?> akun siswa siap ditautkan</span>
                            <a href="daftar_user.php" class="ds-pill ds-pill-line">Kelola User</a>
                        </div>
                    </div>
                </section>

                <?php if (!empty($msg_text)): ?>
                    <div class="ds-alert <?= ($msg_type === 'success') ? 'is-success' : 'is-error'; ?>" role="status">
                        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                        <span><?= htmlspecialchars($msg_text); ?></span>
                    </div>
                <?php endif; ?>

                <!-- BARIS 2: COLLAPSIBLE STUDENT REGISTRATION DRAWER -->
                <section class="ds-card ds-drawer" id="formCard">
                    <div class="ds-drawer-head">
                        <div>
                            <h3 class="ds-title" id="formTitle">Registrasi Siswa Baru</h3>
                            <p class="ds-muted" id="formSub">Tambahkan peserta didik baru ke rombongan belajar dan tautkan akun login siswa.</p>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" class="ds-pill ds-pill-danger ds-btn-cancel" id="btnCancelEdit" onclick="resetFormSiswa()">
                                <i class="fa-solid fa-xmark"></i> Batal Edit
                            </button>
                            <button type="button" class="ds-pill ds-pill-dark" id="btnToggleDrawer" onclick="toggleDrawer()">
                                <i class="fa-solid fa-chevron-up" id="drawerIcon"></i> <span id="drawerToggleLbl">Sembunyikan Form</span>
                            </button>
                        </div>
                    </div>

                    <div class="ds-drawer-body" id="drawerBody">
                        <form method="POST" action="daftar_siswa.php" id="siswaForm">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="edit_id" id="edit_id" value="0">

                            <div class="ds-form-grid-5">
                                <div class="ds-field">
                                    <label for="nis">NIS</label>
                                    <input type="text" name="nis" id="nis" class="ds-input" required placeholder="Contoh: 232410089">
                                </div>

                                <div class="ds-field">
                                    <label for="nama">Nama Lengkap Siswa</label>
                                    <input type="text" name="nama" id="nama" class="ds-input" required placeholder="Nama lengkap sesuai rapor">
                                </div>

                                <div class="ds-field">
                                    <label for="kelas">Rombongan Belajar</label>
                                    <input type="text" name="kelas" id="kelas" list="kelasOptions" class="ds-input" required placeholder="Contoh: XI RPL 1">
                                    <datalist id="kelasOptions">
                                        <?php foreach (array_keys($kelas_counts) as $k_opt): ?>
                                            <option value="<?= htmlspecialchars($k_opt); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>

                                <div class="ds-field">
                                    <label for="jenis_kelamin">Jenis Kelamin</label>
                                    <select name="jenis_kelamin" id="jenis_kelamin" class="ds-input">
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>

                                <div class="ds-field">
                                    <label for="user_id">Akun Login Siswa</label>
                                    <select name="user_id" id="user_id" class="ds-input">
                                        <option value="">-- Tanpa Akun --</option>
                                        <?php foreach ($users_siswa as $u): ?>
                                            <option value="<?= (int)$u['id']; ?>">@<?= htmlspecialchars($u['username']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" name="simpan_siswa" class="ds-btn-submit">
                                    <i class="fa-solid fa-check"></i> <span id="submitLabel">Simpan</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <!-- BARIS 3: DYNAMIC ROMBEL TABS + STUDENT ROSTER TABLE -->
                <section class="ds-card">
                    <div class="ds-head">
                        <div>
                            <h3 class="ds-title">Daftar Peserta Didik</h3>
                            <p class="ds-muted">Pilih tab rombongan belajar atau filter status tautan akun login.</p>
                        </div>
                        <span class="ds-pill" id="rowCountBadge"><?= $tot_siswa; ?> siswa</span>
                    </div>

                    <div class="ds-toolbar">
                        <div class="ds-class-tabs" id="classTabsBar" role="group" aria-label="Filter rombongan belajar">
                            <button type="button" class="ds-ctab is-active" data-kelas="all" onclick="selectClassTab('all')">
                                Semua Kelas (<?= $tot_siswa; ?>)
                            </button>
                            <?php foreach ($kelas_counts as $k_name => $k_cnt): ?>
                                <button type="button" class="ds-ctab"
                                        data-kelas="<?= htmlspecialchars($k_name, ENT_QUOTES, 'UTF-8'); ?>"
                                        onclick="selectClassTab(this.dataset.kelas)">
                                    <?= htmlspecialchars($k_name); ?> (<?= $k_cnt; ?>)
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="ds-status-seg" role="group" aria-label="Filter status akun">
                            <button type="button" class="ds-sbtn is-active" data-status="all" onclick="setAccountStatus('all', this)">Semua</button>
                            <button type="button" class="ds-sbtn" data-status="linked" onclick="setAccountStatus('linked', this)">Tertaut</button>
                            <button type="button" class="ds-sbtn" data-status="unlinked" onclick="setAccountStatus('unlinked', this)">Belum Tertaut</button>
                        </div>
                    </div>

                    <div class="ds-table-wrap">
                        <table class="ds-table" id="dataTable">
                            <thead>
                                <tr>
                                    <th style="width: 48px; text-align: center;">No</th>
                                    <th style="width: 130px;">NIS</th>
                                    <th>Peserta Didik</th>
                                    <th>Rombel</th>
                                    <th>Akun Portal</th>
                                    <th style="text-align: center; width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($list_siswa)): ?>
                                    <?php foreach ($list_siswa as $idx => $s):
                                        $is_linked = !empty($s['username']);
                                        $jk_code   = ($s['jenis_kelamin'] === 'P') ? 'P' : 'L';
                                    ?>
                                        <tr class="ds-item"
                                            data-kelas="<?= htmlspecialchars(trim((string)$s['kelas']), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?= $is_linked ? 'linked' : 'unlinked'; ?>">
                                            <td style="text-align: center; font-weight: 600; color: var(--ds-muted);"><?= $idx + 1; ?></td>
                                            <td><span class="ds-nis-badge"><?= htmlspecialchars($s['nis']); ?></span></td>
                                            <td>
                                                <div class="ds-person">
                                                    <span class="ds-ava <?= $is_linked ? 'is-dark' : ''; ?>">
                                                        <?= strtoupper(substr($s['nama'], 0, 1)); ?>
                                                    </span>
                                                    <div>
                                                        <strong style="display:block; color: var(--ds-ink);"><?= htmlspecialchars($s['nama']); ?></strong>
                                                        <span style="font-size: 11.5px; color: var(--ds-muted);">
                                                            <?= ($jk_code === 'L') ? 'Laki-laki' : 'Perempuan'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="ds-class-pill"><?= htmlspecialchars($s['kelas']); ?></span></td>
                                            <td>
                                                <?php if ($is_linked): ?>
                                                    <span class="ds-chip is-linked">@<?= htmlspecialchars($s['username']); ?></span>
                                                <?php else: ?>
                                                    <span class="ds-chip">Belum tertaut</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center; white-space: nowrap;">
                                                <button type="button"
                                                    class="btn-action-img"
                                                    title="Edit"
                                                    data-id="<?= (int)$s['id']; ?>"
                                                    data-nis="<?= htmlspecialchars($s['nis'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-nama="<?= htmlspecialchars($s['nama'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-kelas="<?= htmlspecialchars($s['kelas'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-jk="<?= htmlspecialchars($jk_code, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-userid="<?= htmlspecialchars((string)($s['user_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    onclick="editSiswaFromBtn(this)">
                                                    <img src="<?= $base_url; ?>/icons/admin/admin_manage/edit.webp" alt="Edit">
                                                </button>
                                                <a href="daftar_siswa.php?hapus=<?= (int)$s['id']; ?>&t=<?= csrf_token(); ?>"
                                                   onclick="return confirm('Hapus data siswa ini?');"
                                                   class="btn-action-img"
                                                   title="Hapus">
                                                    <img src="<?= $base_url; ?>/icons/admin/admin_manage/trash-bin.webp" alt="Hapus">
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 36px; color: var(--ds-muted);">Belum ada data siswa terdaftar.</td>
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
    let activeClassFilter  = 'all';
    let activeStatusFilter = 'all';

    function setDrawerOpen(open) {
        const card = document.getElementById('formCard');
        const icon = document.getElementById('drawerIcon');
        const lbl  = document.getElementById('drawerToggleLbl');
        if (open) {
            card.classList.remove('is-collapsed');
            icon.className  = 'fa-solid fa-chevron-up';
            lbl.textContent = 'Sembunyikan Form';
        } else {
            card.classList.add('is-collapsed');
            icon.className  = 'fa-solid fa-plus';
            lbl.textContent = 'Tambah Siswa';
        }
    }

    function toggleDrawer() {
        const isCollapsed = document.getElementById('formCard').classList.contains('is-collapsed');
        setDrawerOpen(isCollapsed);
    }

    function editSiswaFromBtn(btn) {
        setDrawerOpen(true);
        document.getElementById('edit_id').value       = btn.dataset.id;
        document.getElementById('nis').value           = btn.dataset.nis;
        document.getElementById('nama').value          = btn.dataset.nama;
        document.getElementById('kelas').value         = btn.dataset.kelas;
        document.getElementById('jenis_kelamin').value = btn.dataset.jk;
        document.getElementById('user_id').value       = btn.dataset.userid;

        document.getElementById('formTitle').textContent   = 'Edit Data Siswa #' + btn.dataset.id;
        document.getElementById('formSub').textContent     = 'Perbarui biodata, rombongan belajar, atau tautan akun siswa.';
        document.getElementById('submitLabel').textContent = 'Perbarui';
        document.getElementById('formCard').classList.add('is-editing');
        document.getElementById('btnCancelEdit').style.display = 'inline-flex';
        document.getElementById('formCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function resetFormSiswa() {
        document.getElementById('siswaForm').reset();
        document.getElementById('edit_id').value = 0;

        document.getElementById('formTitle').textContent   = 'Registrasi Siswa Baru';
        document.getElementById('formSub').textContent     = 'Tambahkan peserta didik baru ke rombongan belajar dan tautkan akun login siswa.';
        document.getElementById('submitLabel').textContent = 'Simpan';
        document.getElementById('formCard').classList.remove('is-editing');
        document.getElementById('btnCancelEdit').style.display = 'none';
    }

    function applySiswaFilters() {
        const q = (document.getElementById('tableSearch')?.value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#dataTable tbody tr.ds-item');
        let visibleCount = 0;

        rows.forEach(tr => {
            const matchText   = tr.textContent.toLowerCase().includes(q);
            const matchClass  = (activeClassFilter === 'all') || (tr.dataset.kelas === activeClassFilter);
            const matchStatus = (activeStatusFilter === 'all') || (tr.dataset.status === activeStatusFilter);
            const show = matchText && matchClass && matchStatus;
            tr.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        const badge = document.getElementById('rowCountBadge');
        if (badge) badge.textContent = visibleCount + ' siswa';
    }

    function selectClassTab(kelasKey) {
        activeClassFilter = kelasKey;
        document.querySelectorAll('#classTabsBar .ds-ctab').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.kelas === kelasKey);
        });
        applySiswaFilters();
    }

    function setAccountStatus(statusKey, btnEl) {
        activeStatusFilter = statusKey;
        document.querySelectorAll('.ds-sbtn').forEach(b => b.classList.remove('is-active'));
        if (btnEl) btnEl.classList.add('is-active');
        applySiswaFilters();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('tableSearch');
        if (searchInput) {
            searchInput.addEventListener('input', applySiswaFilters);
        }
    });
</script>
</body>
</html>