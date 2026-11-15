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

// 1. TAMBAH / UPDATE USER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    if (isset($_POST['simpan_user'])) {
        $edit_id   = (int)($_POST['edit_id'] ?? 0);
        $usr_name  = mysqli_real_escape_string($koneksi, trim($_POST['username']));
        $usr_role  = mysqli_real_escape_string($koneksi, $_POST['role']);
        if (!in_array($usr_role, ['admin', 'guru', 'siswa'], true)) {
            $usr_role = 'siswa';
        }
        $usr_pass  = $_POST['password'] ?? '';

        $dup = mysqli_query($koneksi, "SELECT id FROM users WHERE LOWER(username) = LOWER('$usr_name') AND id <> '$edit_id'");
        $username_dipakai = ($dup && mysqli_num_rows($dup) > 0);

        if ($usr_name === '' || $username_dipakai) {
            $msg_type = "error";
            $msg_text = "Username kosong atau sudah dipakai!";
        } elseif ($edit_id > 0) {
            $sql_pass = !empty($usr_pass) ? ", password = '" . password_hash($usr_pass, PASSWORD_BCRYPT) . "'" : "";
            $q = "UPDATE users SET username = '$usr_name', role = '$usr_role' $sql_pass WHERE id = '$edit_id'";
            if (mysqli_query($koneksi, $q)) {
                $msg_type = "success";
                $msg_text = "Data user berhasil diperbarui!";
            } else {
                $msg_type = "error";
                $msg_text = "Gagal memperbarui user: " . mysqli_error($koneksi);
            }
        } else {
            if (empty($usr_pass)) {
                $msg_type = "error";
                $msg_text = "Password wajib diisi untuk user baru!";
            } else {
                $hashed = password_hash($usr_pass, PASSWORD_BCRYPT);
                $q = "INSERT INTO users (username, password, role) VALUES ('$usr_name', '$hashed', '$usr_role')";
                if (mysqli_query($koneksi, $q)) {
                    $msg_type = "success";
                    $msg_text = "User baru berhasil ditambahkan!";
                } else {
                    $msg_type = "error";
                    $msg_text = "Gagal menambah user: " . mysqli_error($koneksi);
                }
            }
        }
    }
}

// 2. HAPUS USER
if (isset($_GET['hapus']) && csrf_check($_GET['t'] ?? null)) {
    $hapus_id = (int)$_GET['hapus'];
    if ($hapus_id !== $user_id) {
        if (mysqli_query($koneksi, "DELETE FROM users WHERE id = '$hapus_id'")) {
            $msg_type = "success";
            $msg_text = "User berhasil dihapus!";
        }
    } else {
        $msg_type = "error";
        $msg_text = "Tidak dapat menghapus akun Anda sendiri!";
    }
}

// 3. FETCH USERS BESERTA STATUS PENAUTAN PROFIL (GURU / SISWA)
$list_users = [];
$q_all = mysqli_query($koneksi, "
    SELECT u.*, g.nama AS nama_guru, s.nama AS nama_siswa, s.kelas AS kelas_siswa
    FROM users u
    LEFT JOIN guru g ON g.user_id = u.id
    LEFT JOIN siswa s ON s.user_id = u.id
    ORDER BY u.id DESC
");
if ($q_all) {
    while ($row = mysqli_fetch_assoc($q_all)) {
        $list_users[] = $row;
    }
}

// METRIK KREDENSIAL & ROLE ACCESS
$tot_users   = count($list_users);
$cnt_admin   = 0;
$cnt_guru    = 0;
$cnt_siswa   = 0;
$cnt_bound   = 0;

foreach ($list_users as $u) {
    $r_low = strtolower($u['role'] ?? 'siswa');
    if ($r_low === 'admin') {
        $cnt_admin++;
        $cnt_bound++;
    } elseif ($r_low === 'guru') {
        $cnt_guru++;
        if (!empty($u['nama_guru'])) $cnt_bound++;
    } else {
        $cnt_siswa++;
        if (!empty($u['nama_siswa'])) $cnt_bound++;
    }
}

$pct_admin = $tot_users > 0 ? round(($cnt_admin / $tot_users) * 100, 1) : 0;
$pct_guru  = $tot_users > 0 ? round(($cnt_guru / $tot_users) * 100, 1) : 0;
$pct_siswa = $tot_users > 0 ? max(0, round(100 - $pct_admin - $pct_guru, 1)) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    <style>
        .du-shell {
            --du-band:  #efefe5;
            --du-ink:   #161616;
            --du-soft:  #3b3b37;
            --du-muted: #6f6f68;
            --du-dim:   #86867e;
            --du-tile:  #f2f2f4;
            --du-line:  #e4e4dc;
            --du-hatch: #b4b4a8;
            --du-ok:    #25683d;
            --du-bad:   #a8432d;

            display: flex;
            flex-direction: column;
            gap: 20px;
            container-type: inline-size;
            color: var(--du-ink);
            animation: fadeInUp 0.5s ease-out forwards;
        }

        .du-shell :where(a, button, input, select, [tabindex]):focus-visible {
            outline: 2px solid var(--du-ink);
            outline-offset: 2px;
        }

        /* ---------- Kartu & Pill ---------- */
        .du-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            padding: 26px 28px;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .du-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 999px;
            background: #e3e3d6;
            color: var(--du-ink);
            font-size: 12px;
            font-weight: 500;
            line-height: 1.25;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }

        a.du-pill:hover,
        button.du-pill:hover {
            background: #d6d6c7;
        }

        .du-pill-dark {
            background: var(--du-ink);
            color: #ffffff;
        }

        a.du-pill-dark:hover,
        button.du-pill-dark:hover {
            background: #2f2f2c;
            color: #ffffff;
        }

        .du-pill-line {
            background: transparent;
            border: 1px solid var(--du-line);
        }

        a.du-pill-line:hover,
        button.du-pill-line:hover {
            background: var(--du-tile);
        }

        .du-pill-danger {
            background: #faece8;
            color: var(--du-bad);
        }

        .du-pill-danger:hover {
            background: #f4dcd5;
        }

        .du-dot-live {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2d7a47;
            display: inline-block;
        }

        /* ---------- 1. Access Control Band (Full-Width Cream) ---------- */
        .du-band {
            background: var(--du-band);
            border-radius: 18px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            padding: 32px 36px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .du-band-top {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
            gap: 32px;
            align-items: end;
        }

        .du-eyebrow {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }

        .du-h1 {
            font-size: clamp(26px, 3cqw, 40px);
            font-weight: 400;
            line-height: 1.12;
            letter-spacing: -0.025em;
            color: var(--du-ink);
        }

        .du-dim {
            color: var(--du-dim);
        }

        .du-sub {
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.5;
            color: var(--du-muted);
            max-width: 56ch;
        }

        .du-role-trio {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .du-role-box {
            background: rgba(255, 255, 255, 0.62);
            border: 1px solid rgba(22, 22, 22, 0.08);
            border-radius: 14px;
            padding: 14px 16px;
        }

        .du-role-box.is-carbon {
            background: var(--du-ink);
            color: #ffffff;
            border-color: var(--du-ink);
        }

        .du-role-box.is-carbon .du-role-lbl {
            color: #bcbcb4;
        }

        .du-role-num {
            display: block;
            font-size: 28px;
            font-weight: 300;
            line-height: 1;
            letter-spacing: -0.04em;
            font-variant-numeric: tabular-nums;
        }

        .du-role-lbl {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: var(--du-muted);
        }

        /* Stacked Multi-Role Distribution Bar */
        .du-stack-wrap {
            padding-top: 18px;
            border-top: 1px solid rgba(22, 22, 22, 0.12);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .du-stack-bar {
            width: 100%;
            height: 12px;
            border-radius: 999px;
            overflow: hidden;
            display: flex;
            background: #e1e1d6;
            border: 1px solid rgba(22, 22, 22, 0.08);
        }

        .du-seg-admin {
            background: var(--du-ink);
            height: 100%;
        }

        .du-seg-guru {
            background-color: #7c7c72;
            height: 100%;
        }

        .du-seg-siswa {
            background-color: rgba(22, 22, 22, 0.05);
            background-image: repeating-linear-gradient(
                135deg,
                var(--du-hatch) 0 1.5px,
                transparent 1.5px 5px
            );
            height: 100%;
        }

        .du-stack-legend {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px 20px;
            font-size: 12px;
            color: var(--du-muted);
        }

        .du-legend-items {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 18px;
        }

        .du-leg {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .du-sw {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            display: inline-block;
        }

        .du-sw.admin { background: var(--du-ink); }
        .du-sw.guru  { background: #7c7c72; }
        .du-sw.siswa {
            border: 1px solid var(--du-hatch);
            background-image: repeating-linear-gradient(135deg, var(--du-hatch) 0 1px, transparent 1px 3px);
        }

        /* ---------- Alert ---------- */
        .du-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
        }

        .du-alert.is-success {
            background: #eaf4ed;
            color: var(--du-ok);
            border: 1px solid #cce3d3;
        }

        .du-alert.is-error {
            background: #faece8;
            color: var(--du-bad);
            border: 1px solid #efd2ca;
        }

        /* ---------- 2. Reverse Workbench (Table Left + Sticky Provisioning Right) ---------- */
        .du-workbench {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, 380px);
            gap: 20px;
            align-items: start;
        }

        .du-form-panel {
            position: sticky;
            top: 20px;
        }

        .du-form-panel.is-editing {
            border: 1.5px solid var(--du-ink);
            box-shadow: 0 14px 32px rgba(22, 22, 22, 0.08);
        }

        .du-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .du-title {
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--du-ink);
        }

        .du-muted {
            font-size: 12px;
            color: var(--du-muted);
            margin-top: 3px;
            line-height: 1.45;
        }

        /* Filter Tabs */
        .du-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-bottom: 16px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--du-line);
        }

        .du-role-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .du-rtab {
            padding: 6px 13px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            color: var(--du-soft);
            background: var(--du-tile);
            transition: all 0.15s ease;
        }

        .du-rtab:hover {
            background: #e6e6e9;
        }

        .du-rtab.is-active {
            background: var(--du-ink);
            color: #ffffff;
        }

        /* Ledger Table */
        .du-table-wrap {
            overflow-x: auto;
        }

        .du-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .du-table th {
            padding: 10px 12px;
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--du-muted);
            border-bottom: 1px solid var(--du-ink);
            white-space: nowrap;
        }

        .du-table td {
            padding: 13px 12px;
            border-bottom: 1px solid var(--du-line);
            vertical-align: middle;
        }

        .du-table tbody tr {
            transition: background 0.15s ease;
        }

        .du-table tbody tr:hover {
            background: #fafaf6;
        }

        .du-user-cell {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .du-ava {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--du-band);
            color: var(--du-ink);
            font-size: 12px;
            font-weight: 700;
            border: 1px solid rgba(22, 22, 22, 0.08);
        }

        .du-ava.is-admin {
            background: var(--du-ink);
            color: #ffffff;
        }

        .du-ava.is-guru {
            background: #55554e;
            color: #ffffff;
        }

        .du-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .du-role-badge.role-admin {
            background: var(--du-ink);
            color: #ffffff;
        }

        .du-role-badge.role-guru {
            background: var(--du-band);
            color: var(--du-ink);
            border: 1px solid rgba(22, 22, 22, 0.12);
        }

        .du-role-badge.role-siswa {
            background: var(--du-tile);
            color: var(--du-soft);
        }

        .du-bind-text {
            font-size: 12px;
            color: var(--du-soft);
        }

        .du-bind-empty {
            font-size: 11.5px;
            color: var(--du-dim);
        }

        .du-self-tag {
            display: inline-block;
            margin-left: 6px;
            padding: 2px 7px;
            border-radius: 999px;
            background: #eaf4ed;
            color: var(--du-ok);
            font-size: 10px;
            font-weight: 700;
            vertical-align: middle;
        }

        /* ---------- Pagination Bar (10 items/page) ---------- */
        .du-pager {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--du-line);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .du-pager-info {
            font-size: 12px;
            color: var(--du-muted);
            font-variant-numeric: tabular-nums;
        }

        .du-pager-info strong {
            color: var(--du-ink);
            font-weight: 600;
        }

        .du-pager-nav {
            display: inline-flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px;
        }

        .du-pbtn {
            min-width: 32px;
            height: 32px;
            padding: 0 10px;
            border-radius: 999px;
            border: 1px solid var(--du-line);
            background: #ffffff;
            color: var(--du-soft);
            font-size: 12px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .du-pbtn:hover:not(:disabled):not(.is-active) {
            background: var(--du-band);
            border-color: #cfcfc4;
            color: var(--du-ink);
        }

        .du-pbtn.is-active {
            background: var(--du-ink);
            border-color: var(--du-ink);
            color: #ffffff;
        }

        .du-pbtn:disabled {
            opacity: 0.38;
            cursor: not-allowed;
        }

        .du-pellipsis {
            padding: 0 4px;
            font-size: 12px;
            color: var(--du-dim);
            user-select: none;
        }

        /* Form Controls on Right */
        .du-form-stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .du-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .du-field label {
            font-size: 12px;
            font-weight: 600;
            color: var(--du-soft);
        }

        .du-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .du-input {
            width: 100%;
            padding: 10px 13px;
            border-radius: 10px;
            background: #f8f8f5;
            border: 1px solid var(--du-line);
            color: var(--du-ink);
            font-size: 13px;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .du-input:hover {
            border-color: #cfcfc4;
        }

        .du-input:focus {
            background: #ffffff;
            border-color: var(--du-ink);
        }

        .du-eye-btn {
            position: absolute;
            right: 10px;
            background: transparent;
            color: var(--du-muted);
            padding: 4px 6px;
            font-size: 13px;
            cursor: pointer;
        }

        .du-eye-btn:hover {
            color: var(--du-ink);
        }

        /* Role Selector Cards */
        .du-role-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .du-rcard {
            padding: 10px 8px;
            border-radius: 11px;
            border: 1px solid var(--du-line);
            background: #f8f8f5;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .du-rcard:hover {
            border-color: #bcbcb0;
        }

        .du-rcard.is-active {
            background: var(--du-ink);
            color: #ffffff;
            border-color: var(--du-ink);
        }

        .du-rcard strong {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
        }

        .du-rcard span {
            display: block;
            margin-top: 2px;
            font-size: 10.5px;
            opacity: 0.75;
        }

        .du-btn-submit {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: 999px;
            background: var(--du-ink);
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 4px;
            transition: background 0.15s ease, transform 0.15s ease;
        }

        .du-btn-submit:hover {
            background: #2f2f2c;
            transform: translateY(-1px);
        }

        .du-btn-cancel {
            display: none;
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
            background: var(--du-band);
            transform: scale(1.1);
        }

        .btn-action-img img {
            width: 18px;
            height: 18px;
            object-fit: contain;
        }

        /* Responsive Container Queries */
        @container (max-width: 1060px) {
            .du-band-top {
                grid-template-columns: minmax(0, 1fr);
            }
            .du-workbench {
                grid-template-columns: minmax(0, 1fr);
            }
            .du-form-panel {
                position: static;
                order: -1;
            }
        }

        @container (max-width: 640px) {
            .du-band,
            .du-card {
                padding: 22px 18px;
            }
            .du-role-trio {
                grid-template-columns: repeat(3, minmax(0, 1fr));
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
			<li><a href="manajemen_kelas.php"><span class="dot kelas"></span> <span class="menu-label">Kelola Kelas &amp; Plotting</span></a></li>
                        <li><a href="daftar_user.php" style="font-weight: 700; color: #fff;"><span class="dot user"></span> <span class="menu-label">Kelola User</span></a></li>
                        <li><a href="daftar_siswa.php"><span class="dot siswa"></span> <span class="menu-label">Kelola Siswa</span></a></li>
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
                    <h1>Kelola User System</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <div class="search-box">
                        <i class="zoom"></i>
                        <input type="text" id="tableSearch" placeholder="Cari username, role, profil...">
                    </div>
                    <button class="icon-btn notification-btn"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/bell.webp" alt="Notifikasi"></button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/settings.webp" alt="Settings"></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="du-shell">
                <!-- BARIS 1: ACCESS CONTROL BAND (FULL-WIDTH CREAM + ROLE BAR) -->
                <section class="du-band">
                    <div class="du-band-top">
                        <div>
                            <div class="du-eyebrow">
                                <span class="du-pill"><span class="du-dot-live"></span> Access Control</span>
                                <span class="du-pill"><?= $tot_users; ?> Total Akun</span>
                                <span class="du-pill"><?= $cnt_bound; ?> Terhubung Profil</span>
                            </div>
                            <h2 class="du-h1">Kredensial <span class="du-dim">&amp; hak akses</span><br>pengguna sistem</h2>
                            <p class="du-sub">Kelola akun login administrator, tenaga pengajar, dan peserta didik beserta pembagian otorisasi sistem akademik.</p>
                        </div>

                        <div class="du-role-trio">
                            <div class="du-role-box is-carbon">
                                <span class="du-role-num"><?= $cnt_admin; ?></span>
                                <span class="du-role-lbl">Admin</span>
                            </div>
                            <div class="du-role-box">
                                <span class="du-role-num"><?= $cnt_guru; ?></span>
                                <span class="du-role-lbl">Guru</span>
                            </div>
                            <div class="du-role-box">
                                <span class="du-role-num"><?= $cnt_siswa; ?></span>
                                <span class="du-role-lbl">Siswa</span>
                            </div>
                        </div>
                    </div>

                    <div class="du-stack-wrap">
                        <div class="du-stack-bar" role="img" aria-label="Proporsi role akun: Admin <?= $pct_admin; ?>%, Guru <?= $pct_guru; ?>%, Siswa <?= $pct_siswa; ?>%">
                            <?php if ($pct_admin > 0): ?>
                                <div class="du-seg-admin" style="width: <?= $pct_admin; ?>%;"></div>
                            <?php endif; ?>
                            <?php if ($pct_guru > 0): ?>
                                <div class="du-seg-guru" style="width: <?= $pct_guru; ?>%;"></div>
                            <?php endif; ?>
                            <?php if ($pct_siswa > 0): ?>
                                <div class="du-seg-siswa" style="width: <?= $pct_siswa; ?>%;"></div>
                            <?php endif; ?>
                        </div>

                        <div class="du-stack-legend">
                            <div class="du-legend-items">
                                <span class="du-leg"><i class="du-sw admin"></i> Admin (<?= $pct_admin; ?>%)</span>
                                <span class="du-leg"><i class="du-sw guru"></i> Guru (<?= $pct_guru; ?>%)</span>
                                <span class="du-leg"><i class="du-sw siswa"></i> Siswa (<?= $pct_siswa; ?>%)</span>
                            </div>
                            <span>Enkripsi BCRYPT aktif</span>
                        </div>
                    </div>
                </section>

                <?php if (!empty($msg_text)): ?>
                    <div class="du-alert <?= ($msg_type === 'success') ? 'is-success' : 'is-error'; ?>" role="status">
                        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                        <span><?= htmlspecialchars($msg_text); ?></span>
                    </div>
                <?php endif; ?>

                <!-- BARIS 2: WORKBENCH TERBALIK (ACCOUNT LEDGER KIRI + PROVISIONING KANAN) -->
                <section class="du-workbench">
                    <!-- PANEL KIRI: LEDGER AKUN -->
                    <div class="du-card" id="ledgerCard">
                        <div class="du-head">
                            <div>
                                <h3 class="du-title">Daftar Kredensial Terdaftar</h3>
                                <p class="du-muted">Seluruh akun pengguna beserta status penautan biodata (10 akun per halaman).</p>
                            </div>
                            <span class="du-pill" id="rowCountBadge"><?= $tot_users; ?> akun</span>
                        </div>

                        <div class="du-toolbar">
                            <div class="du-role-tabs" role="group" aria-label="Filter role pengguna">
                                <button type="button" class="du-rtab is-active" data-role="all" onclick="setRoleFilter('all', this)">Semua (<?= $tot_users; ?>)</button>
                                <button type="button" class="du-rtab" data-role="admin" onclick="setRoleFilter('admin', this)">Admin (<?= $cnt_admin; ?>)</button>
                                <button type="button" class="du-rtab" data-role="guru" onclick="setRoleFilter('guru', this)">Guru (<?= $cnt_guru; ?>)</button>
                                <button type="button" class="du-rtab" data-role="siswa" onclick="setRoleFilter('siswa', this)">Siswa (<?= $cnt_siswa; ?>)</button>
                            </div>
                        </div>

                        <div class="du-table-wrap">
                            <table class="du-table" id="dataTable">
                                <thead>
                                    <tr>
                                        <th style="width: 46px; text-align: center;">No</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Profil Tertaut</th>
                                        <th>Terdaftar</th>
                                        <th style="text-align: center; width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($list_users)): ?>
                                        <?php foreach ($list_users as $idx => $u):
                                            $r_low   = strtolower($u['role']);
                                            $is_self = ((int)$u['id'] === $user_id);
                                        ?>
                                            <tr class="du-item" data-role="<?= htmlspecialchars($r_low, ENT_QUOTES, 'UTF-8'); ?>">
                                                <td class="du-row-no" style="text-align: center; font-weight: 600; color: var(--du-muted); font-variant-numeric: tabular-nums;"><?= $idx + 1; ?></td>
                                                <td>
                                                    <div class="du-user-cell">
                                                        <span class="du-ava is-<?= htmlspecialchars($r_low); ?>">
                                                            <?= strtoupper(substr($u['username'], 0, 1)); ?>
                                                        </span>
                                                        <div>
                                                            <strong style="color: var(--du-ink);">@<?= htmlspecialchars($u['username']); ?></strong>
                                                            <?php if ($is_self): ?>
                                                                <span class="du-self-tag">Anda</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="du-role-badge role-<?= htmlspecialchars($r_low); ?>">
                                                        <?= htmlspecialchars($r_low); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($r_low === 'guru' && !empty($u['nama_guru'])): ?>
                                                        <span class="du-bind-text"><?= htmlspecialchars($u['nama_guru']); ?></span>
                                                    <?php elseif ($r_low === 'siswa' && !empty($u['nama_siswa'])): ?>
                                                        <span class="du-bind-text"><?= htmlspecialchars($u['nama_siswa']); ?> <small style="color:var(--du-muted);">(<?= htmlspecialchars($u['kelas_siswa'] ?? ''); ?>)</small></span>
                                                    <?php elseif ($r_low === 'admin'): ?>
                                                        <span class="du-bind-text">Akses Penuh Sistem</span>
                                                    <?php else: ?>
                                                        <span class="du-bind-empty">Belum ditautkan</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="color: var(--du-muted); font-size: 12px; font-variant-numeric: tabular-nums;">
                                                    <?= !empty($u['created_at']) ? date('d M Y, H:i', strtotime($u['created_at'])) : '-'; ?>
                                                </td>
                                                <td style="text-align: center; white-space: nowrap;">
                                                    <button type="button"
                                                        class="btn-action-img"
                                                        title="Edit"
                                                        data-id="<?= (int)$u['id']; ?>"
                                                        data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-role="<?= htmlspecialchars($r_low, ENT_QUOTES, 'UTF-8'); ?>"
                                                        onclick="editUserFromBtn(this)">
                                                        <img src="<?= $base_url; ?>/icons/admin/admin_manage/edit.webp" alt="Edit">
                                                    </button>

                                                    <?php if (!$is_self): ?>
                                                        <a href="daftar_user.php?hapus=<?= (int)$u['id']; ?>&t=<?= csrf_token(); ?>"
                                                           onclick="return confirm('Hapus user ini?');"
                                                           class="btn-action-img"
                                                           title="Hapus">
                                                            <img src="<?= $base_url; ?>/icons/admin/admin_manage/trash-bin.webp" alt="Hapus">
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr id="emptyFilterRow" style="display: none;">
                                            <td colspan="6" style="text-align: center; padding: 32px; color: var(--du-muted);">Tidak ada akun yang cocok dengan filter pencarian.</td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 36px; color: var(--du-muted);">Belum ada user terdaftar.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- NAVIGASI PAGINATION (1–10, 11–20, DST.) -->
                        <div class="du-pager" id="pagerBar">
                            <div class="du-pager-info" id="pagerInfo">Menampilkan 0 akun</div>
                            <div class="du-pager-nav" id="pagerNav" role="navigation" aria-label="Navigasi halaman daftar user"></div>
                        </div>
                    </div>

                    <!-- PANEL KANAN: STICKY PROVISIONING & EDIT FORM -->
                    <div class="du-card du-form-panel" id="formCard">
                        <div class="du-head">
                            <div>
                                <h3 class="du-title" id="formTitle">Buat Akun Baru</h3>
                                <p class="du-muted" id="formSub">Tambahkan kredensial pengguna dan tentukan level akses.</p>
                            </div>
                            <button type="button" class="du-pill du-pill-danger du-btn-cancel" id="btnCancelEdit" onclick="resetFormUser()">
                                <i class="fa-solid fa-xmark"></i> Batal
                            </button>
                        </div>

                        <form method="POST" action="daftar_user.php" id="userForm" class="du-form-stack">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="edit_id" id="edit_id" value="0">
                            <input type="hidden" name="role" id="role" value="siswa">

                            <div class="du-field">
                                <label for="username">Username</label>
                                <input type="text" name="username" id="username" class="du-input" placeholder="Masukkan username unik" required>
                            </div>

                            <div class="du-field">
                                <label for="password">Password <small id="passHint" style="color: var(--du-muted); font-weight: 400;">(Wajib untuk akun baru)</small></label>
                                <div class="du-input-wrap">
                                    <input type="password" name="password" id="password" class="du-input" placeholder="••••••••" style="padding-right: 38px;">
                                    <button type="button" class="du-eye-btn" onclick="togglePassVisibility()" title="Tampilkan / Sembunyikan Password">
                                        <i class="fa-regular fa-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="du-field">
                                <label>Level Otorisasi (Role)</label>
                                <div class="du-role-cards" role="group" aria-label="Pilih Role Akses">
                                    <button type="button" class="du-rcard is-active" data-role="siswa" onclick="selectRoleCard('siswa')">
                                        <strong>Siswa</strong>
                                        <span>Rapor</span>
                                    </button>
                                    <button type="button" class="du-rcard" data-role="guru" onclick="selectRoleCard('guru')">
                                        <strong>Guru</strong>
                                        <span>Penilaian</span>
                                    </button>
                                    <button type="button" class="du-rcard" data-role="admin" onclick="selectRoleCard('admin')">
                                        <strong>Admin</strong>
                                        <span>Master</span>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" name="simpan_user" class="du-btn-submit">
                                <i class="fa-solid fa-check"></i> <span id="submitLabel">Simpan Akun</span>
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<script>
    const ROWS_PER_PAGE = 10;
    let activeRoleFilter = 'all';
    let currentPage = 1;

    function selectRoleCard(roleVal) {
        const clean = ['admin', 'guru', 'siswa'].includes(roleVal) ? roleVal : 'siswa';
        document.getElementById('role').value = clean;
        document.querySelectorAll('.du-rcard').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.role === clean);
        });
    }

    function togglePassVisibility() {
        const inp  = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'fa-regular fa-eye-slash';
        } else {
            inp.type = 'password';
            icon.className = 'fa-regular fa-eye';
        }
    }

    function editUserFromBtn(btn) {
        document.getElementById('edit_id').value  = btn.dataset.id;
        document.getElementById('username').value = btn.dataset.username;
        document.getElementById('password').value = '';
        selectRoleCard(btn.dataset.role);

        document.getElementById('formTitle').textContent   = 'Edit Akun #' + btn.dataset.id;
        document.getElementById('formSub').textContent     = 'Ubah username, role akses, atau reset kata sandi.';
        document.getElementById('passHint').textContent    = '(Kosongkan jika tidak diubah)';
        document.getElementById('submitLabel').textContent = 'Perbarui Akun';
        document.getElementById('formCard').classList.add('is-editing');
        document.getElementById('btnCancelEdit').style.display = 'inline-flex';
        document.getElementById('formCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function resetFormUser() {
        document.getElementById('userForm').reset();
        document.getElementById('edit_id').value = 0;
        selectRoleCard('siswa');

        document.getElementById('formTitle').textContent   = 'Buat Akun Baru';
        document.getElementById('formSub').textContent     = 'Tambahkan kredensial pengguna dan tentukan level akses.';
        document.getElementById('passHint').textContent    = '(Wajib untuk akun baru)';
        document.getElementById('submitLabel').textContent = 'Simpan Akun';
        document.getElementById('formCard').classList.remove('is-editing');
        document.getElementById('btnCancelEdit').style.display = 'none';
    }

    function goToPage(page, shouldScroll = false) {
        currentPage = page;
        renderUserTable();
        if (shouldScroll) {
            const card = document.getElementById('ledgerCard');
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function renderPagerButtons(totalPages) {
        const nav = document.getElementById('pagerNav');
        if (!nav) return;

        if (totalPages <= 1) {
            nav.innerHTML = '';
            return;
        }

        const pages = new Set([1, totalPages, currentPage - 1, currentPage, currentPage + 1]);
        const sortedPages = [...pages].filter(p => p >= 1 && p <= totalPages).sort((a, b) => a - b);

        let html = `<button type="button" class="du-pbtn" ${currentPage === 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1}, true)" title="Halaman Sebelumnya"><i class="fa-solid fa-chevron-left"></i></button>`;

        let prev = 0;
        sortedPages.forEach(p => {
            if (prev && p - prev > 1) {
                html += `<span class="du-pellipsis">…</span>`;
            }
            const startNum = (p - 1) * ROWS_PER_PAGE + 1;
            const endNum   = p * ROWS_PER_PAGE;
            html += `<button type="button" class="du-pbtn ${p === currentPage ? 'is-active' : ''}" onclick="goToPage(${p}, true)" title="Urutan ${startNum}–${endNum}">${p}</button>`;
            prev = p;
        });

        html += `<button type="button" class="du-pbtn" ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1}, true)" title="Halaman Berikutnya"><i class="fa-solid fa-chevron-right"></i></button>`;
        nav.innerHTML = html;
    }

    function renderUserTable() {
        const q = (document.getElementById('tableSearch')?.value || '').toLowerCase().trim();
        const allRows = Array.from(document.querySelectorAll('#dataTable tbody tr.du-item'));
        const emptyRow = document.getElementById('emptyFilterRow');

        const matchedRows = allRows.filter(tr => {
            const matchText = tr.textContent.toLowerCase().includes(q);
            const matchRole = (activeRoleFilter === 'all') || (tr.dataset.role === activeRoleFilter);
            return matchText && matchRole;
        });

        const totalMatched = matchedRows.length;
        const totalPages   = Math.max(1, Math.ceil(totalMatched / ROWS_PER_PAGE));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = (currentPage - 1) * ROWS_PER_PAGE;
        const endIdx   = startIdx + ROWS_PER_PAGE;

        allRows.forEach(tr => {
            tr.style.display = 'none';
        });

        matchedRows.forEach((tr, i) => {
            if (i >= startIdx && i < endIdx) {
                tr.style.display = '';
                const noCell = tr.querySelector('.du-row-no');
                if (noCell) noCell.textContent = i + 1;
            }
        });

        if (emptyRow) {
            emptyRow.style.display = (allRows.length > 0 && totalMatched === 0) ? '' : 'none';
        }

        const badge = document.getElementById('rowCountBadge');
        if (badge) badge.textContent = totalMatched + ' akun';

        const pagerInfo = document.getElementById('pagerInfo');
        if (pagerInfo) {
            if (totalMatched === 0) {
                pagerInfo.innerHTML = 'Menampilkan <strong>0</strong> akun';
            } else {
                const from = startIdx + 1;
                const to   = Math.min(endIdx, totalMatched);
                pagerInfo.innerHTML = `Menampilkan <strong>${from}–${to}</strong> dari <strong>${totalMatched}</strong> akun`;
            }
        }

        renderPagerButtons(totalPages);
    }

    function setRoleFilter(roleKey, btnEl) {
        activeRoleFilter = roleKey;
        currentPage = 1;
        document.querySelectorAll('.du-rtab').forEach(b => b.classList.remove('is-active'));
        if (btnEl) btnEl.classList.add('is-active');
        renderUserTable();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('tableSearch');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                currentPage = 1;
                renderUserTable();
            });
        }
        renderUserTable();
    });
</script>
</body>
</html>