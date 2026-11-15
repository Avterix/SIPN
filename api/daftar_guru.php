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
$q_u = mysqli_query($koneksi, "SELECT foto, github_connected, github_avatar FROM users WHERE id = '$user_id'");
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

// 1. TAMBAH / UPDATE GURU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_guru'])) {
    csrf_require();
    $edit_id  = (int)($_POST['edit_id'] ?? 0);
    $nip      = mysqli_real_escape_string($koneksi, trim($_POST['nip']));
    $nama     = mysqli_real_escape_string($koneksi, trim($_POST['nama']));
    $jk       = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $user_ref = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : "NULL";

    if ($edit_id > 0) {
        $q = "UPDATE guru SET nip='$nip', nama='$nama', jenis_kelamin='$jk', user_id=$user_ref WHERE id='$edit_id'";
    } else {
        $q = "INSERT INTO guru (nip, nama, jenis_kelamin, user_id) VALUES ('$nip', '$nama', '$jk', $user_ref)";
    }

    if (mysqli_query($koneksi, $q)) {
        $msg_type = "success";
        $msg_text = ($edit_id > 0) ? "Data guru berhasil diperbarui!" : "Data guru baru berhasil ditambahkan!";
    } else {
        $msg_type = "error";
        $msg_text = "Gagal menyimpan data guru: " . mysqli_error($koneksi);
    }
}

// 2. HAPUS GURU
if (isset($_GET['hapus']) && csrf_check($_GET['t'] ?? null)) {
    $hapus_id = (int)$_GET['hapus'];
    if (mysqli_query($koneksi, "DELETE FROM guru WHERE id = '$hapus_id'")) {
        $msg_type = "success";
        $msg_text = "Data guru berhasil dihapus!";
    }
}

// 3. FETCH DATA GURU + JUMLAH MAPEL DIAMPU
$list_guru = [];
$q_guru = mysqli_query($koneksi, "
    SELECT g.*, u.username, COALESCE(mc.jml_mapel, 0) AS jml_mapel
    FROM guru g
    LEFT JOIN users u ON g.user_id = u.id
    LEFT JOIN (
        SELECT guru_id, COUNT(*) AS jml_mapel
        FROM mapel
        WHERE guru_id IS NOT NULL
        GROUP BY guru_id
    ) mc ON mc.guru_id = g.id
    ORDER BY g.nama ASC
");
if ($q_guru) {
    while ($r = mysqli_fetch_assoc($q_guru)) {
        $list_guru[] = $r;
    }
}

$users_guru = [];
$q_ug = mysqli_query($koneksi, "SELECT id, username FROM users WHERE role = 'guru' ORDER BY username ASC");
if ($q_ug) {
    while ($r = mysqli_fetch_assoc($q_ug)) {
        $users_guru[] = $r;
    }
}

// METRIK DIREKTORI GURU
$tot_guru        = count($list_guru);
$guru_linked     = 0;
$guru_laki       = 0;
$guru_perempuan  = 0;
$guru_mengajar   = 0;
$used_user_ids   = [];

foreach ($list_guru as $g) {
    if (!empty($g['user_id'])) {
        $guru_linked++;
        $used_user_ids[(int)$g['user_id']] = true;
    }
    if (($g['jenis_kelamin'] ?? 'L') === 'L') {
        $guru_laki++;
    } else {
        $guru_perempuan++;
    }
    if ((int)($g['jml_mapel'] ?? 0) > 0) {
        $guru_mengajar++;
    }
}

$guru_unlinked  = max(0, $tot_guru - $guru_linked);
$linked_pct     = $tot_guru > 0 ? round(($guru_linked / $tot_guru) * 100) : 0;
$laki_pct       = $tot_guru > 0 ? round(($guru_laki / $tot_guru) * 100) : 0;
$perempuan_pct  = $tot_guru > 0 ? (100 - $laki_pct) : 0;
$akun_tersedia  = 0;
foreach ($users_guru as $ug) {
    if (!isset($used_user_ids[(int)$ug['id']])) {
        $akun_tersedia++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Guru - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    <style>
        .dg-shell {
            --dg-band:  #efefe5;
            --dg-ink:   #161616;
            --dg-soft:  #3b3b37;
            --dg-muted: #6f6f68;
            --dg-dim:   #86867e;
            --dg-tile:  #f2f2f4;
            --dg-line:  #e4e4dc;
            --dg-hatch: #b4b4a8;
            --dg-ok:    #25683d;
            --dg-bad:   #a8432d;

            display: flex;
            flex-direction: column;
            gap: 20px;
            container-type: inline-size;
            color: var(--dg-ink);
            animation: fadeInUp 0.5s ease-out forwards;
        }

        .dg-shell :where(a, button, input, select, [tabindex]):focus-visible {
            outline: 2px solid var(--dg-ink);
            outline-offset: 2px;
        }

        /* ---------- Kartu & Pill ---------- */
        .dg-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            padding: 26px 28px;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .dg-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            border-radius: 999px;
            background: #e3e3d6;
            color: var(--dg-ink);
            font-size: 12px;
            font-weight: 500;
            line-height: 1.25;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }

        a.dg-pill:hover,
        button.dg-pill:hover {
            background: #d6d6c7;
        }

        .dg-pill-dark {
            background: var(--dg-ink);
            color: #ffffff;
        }

        a.dg-pill-dark:hover,
        button.dg-pill-dark:hover {
            background: #2f2f2c;
            color: #ffffff;
        }

        .dg-pill-line {
            background: transparent;
            border: 1px solid var(--dg-line);
        }

        a.dg-pill-line:hover,
        button.dg-pill-line:hover {
            background: var(--dg-tile);
        }

        .dg-pill-danger {
            background: #faece8;
            color: var(--dg-bad);
        }

        .dg-pill-danger:hover {
            background: #f4dcd5;
        }

        .dg-dot-live {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2d7a47;
            display: inline-block;
        }

        /* ---------- 1. Pita Header Editorial (Full-Width Cream) ---------- */
        .dg-band {
            background: var(--dg-band);
            border-radius: 18px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            padding: 32px 36px;
            display: grid;
            grid-template-columns: minmax(0, 1.28fr) minmax(0, 1fr);
            gap: 36px;
            align-items: center;
        }

        .dg-eyebrow {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }

        .dg-h1 {
            font-size: clamp(26px, 3cqw, 40px);
            font-weight: 400;
            line-height: 1.12;
            letter-spacing: -0.025em;
            color: var(--dg-ink);
        }

        .dg-dim {
            color: var(--dg-dim);
        }

        .dg-sub {
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.55;
            color: var(--dg-muted);
            max-width: 54ch;
        }

        /* Proportion Bars (Carbon vs Hatched Track) */
        .dg-bars {
            margin-top: 22px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .dg-bar-item {
            background: rgba(255, 255, 255, 0.55);
            border: 1px solid rgba(22, 22, 22, 0.08);
            border-radius: 14px;
            padding: 13px 15px;
        }

        .dg-bar-top {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .dg-bar-top span {
            color: var(--dg-soft);
            font-weight: 600;
        }

        .dg-bar-top strong {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            color: var(--dg-ink);
        }

        .dg-track {
            width: 100%;
            height: 9px;
            border-radius: 999px;
            overflow: hidden;
            background-color: rgba(0, 0, 0, 0.04);
            background-image: repeating-linear-gradient(
                135deg,
                var(--dg-hatch) 0 1px,
                transparent 1px 4px
            );
        }

        .dg-fill {
            height: 100%;
            border-radius: 999px;
            background: var(--dg-ink);
            transition: width 0.4s ease;
        }

        .dg-bar-foot {
            margin-top: 7px;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: var(--dg-muted);
            font-variant-numeric: tabular-nums;
        }

        /* Right Side: 2x2 Hairline Metric Ledger */
        .dg-ledger-wrap {
            display: flex;
            flex-direction: column;
            gap: 16px;
            border-left: 1px solid rgba(22, 22, 22, 0.12);
            padding-left: 32px;
        }

        .dg-ledger {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dg-stat {
            padding: 12px 16px;
        }

        .dg-stat:nth-child(odd) {
            padding-left: 0;
            border-right: 1px solid rgba(22, 22, 22, 0.1);
        }

        .dg-stat:nth-child(even) {
            padding-right: 0;
        }

        .dg-stat:nth-child(1),
        .dg-stat:nth-child(2) {
            border-bottom: 1px solid rgba(22, 22, 22, 0.1);
            padding-top: 2px;
        }

        .dg-stat:nth-child(3),
        .dg-stat:nth-child(4) {
            padding-bottom: 2px;
        }

        .dg-stat-num {
            display: block;
            font-size: clamp(32px, 3.4cqw, 46px);
            font-weight: 300;
            line-height: 1;
            letter-spacing: -0.04em;
            font-variant-numeric: tabular-nums;
            color: var(--dg-ink);
        }

        .dg-stat-lbl {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: var(--dg-muted);
        }

        .dg-band-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        /* ---------- Alert ---------- */
        .dg-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
        }

        .dg-alert.is-success {
            background: #eaf4ed;
            color: var(--dg-ok);
            border: 1px solid #cce3d3;
        }

        .dg-alert.is-error {
            background: #faece8;
            color: var(--dg-bad);
            border: 1px solid #efd2ca;
        }

        /* ---------- 2. Asymmetric Workbench Grid ---------- */
        .dg-workbench {
            display: grid;
            grid-template-columns: minmax(320px, 385px) minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }

        .dg-form-panel {
            position: sticky;
            top: 20px;
        }

        .dg-form-panel.is-editing {
            border: 1.5px solid var(--dg-ink);
            box-shadow: 0 14px 32px rgba(22, 22, 22, 0.08);
        }

        .dg-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .dg-title {
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--dg-ink);
        }

        .dg-muted {
            font-size: 12px;
            color: var(--dg-muted);
            margin-top: 3px;
            line-height: 1.45;
        }

        /* Live Preview Card inside Form */
        .dg-preview {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 14px;
            background: var(--dg-band);
            border: 1px solid rgba(22, 22, 22, 0.08);
            margin-bottom: 18px;
        }

        .dg-preview-ava {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            border-radius: 50%;
            background: var(--dg-ink);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .dg-preview-meta {
            min-width: 0;
            flex: 1;
        }

        .dg-preview-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--dg-ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dg-preview-sub {
            margin-top: 3px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--dg-muted);
            font-variant-numeric: tabular-nums;
        }

        /* Form Controls */
        .dg-form-stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .dg-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dg-field label {
            font-size: 12px;
            font-weight: 600;
            color: var(--dg-soft);
        }

        .dg-input {
            width: 100%;
            padding: 10px 13px;
            border-radius: 10px;
            background: #f8f8f5;
            border: 1px solid var(--dg-line);
            color: var(--dg-ink);
            font-size: 13px;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .dg-input:hover {
            border-color: #cfcfc4;
        }

        .dg-input:focus {
            background: #ffffff;
            border-color: var(--dg-ink);
        }

        /* Segmented Gender Switch */
        .dg-seg {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            padding: 4px;
            border-radius: 11px;
            background: var(--dg-tile);
            border: 1px solid var(--dg-line);
        }

        .dg-seg-btn {
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--dg-muted);
            background: transparent;
            text-align: center;
            transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }

        .dg-seg-btn.is-active {
            background: #ffffff;
            color: var(--dg-ink);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
        }

        .dg-form-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }

        .dg-btn-submit {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: 999px;
            background: var(--dg-ink);
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.15s ease;
        }

        .dg-btn-submit:hover {
            background: #2f2f2c;
            transform: translateY(-1px);
        }

        .dg-btn-cancel {
            display: none;
        }

        /* ---------- Right Panel: Filter Bar + Roster Views ---------- */
        .dg-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-bottom: 16px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--dg-line);
        }

        .dg-filter-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .dg-fpill {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            color: var(--dg-soft);
            background: var(--dg-tile);
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }

        .dg-fpill:hover {
            background: #e6e6e9;
        }

        .dg-fpill.is-active {
            background: var(--dg-ink);
            color: #ffffff;
        }

        .dg-view-toggle {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px;
            border-radius: 999px;
            background: var(--dg-tile);
            border: 1px solid var(--dg-line);
        }

        .dg-vbtn {
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--dg-muted);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .dg-vbtn.is-active {
            background: #ffffff;
            color: var(--dg-ink);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        }

        /* Table View */
        .dg-table-wrap {
            overflow-x: auto;
        }

        .dg-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .dg-table th {
            padding: 10px 12px;
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--dg-muted);
            border-bottom: 1px solid var(--dg-ink);
            white-space: nowrap;
        }

        .dg-table td {
            padding: 13px 12px;
            border-bottom: 1px solid var(--dg-line);
            vertical-align: middle;
        }

        .dg-table tbody tr {
            transition: background 0.15s ease;
        }

        .dg-table tbody tr:hover {
            background: #fafaf6;
        }

        .dg-person {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .dg-ava {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--dg-band);
            color: var(--dg-ink);
            font-size: 12px;
            font-weight: 700;
            border: 1px solid rgba(22, 22, 22, 0.08);
        }

        .dg-ava.is-dark {
            background: var(--dg-ink);
            color: #ffffff;
        }

        .dg-nip-code {
            font-variant-numeric: tabular-nums;
            font-size: 12.5px;
            color: var(--dg-soft);
            background: var(--dg-tile);
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        .dg-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--dg-tile);
            color: var(--dg-muted);
            font-size: 11px;
            font-weight: 600;
        }

        .dg-chip.is-linked {
            background: var(--dg-band);
            color: var(--dg-ink);
        }

        .dg-chip.is-mapel {
            background: #eef3ef;
            color: var(--dg-ok);
        }

        /* Directory Cards View */
        .dg-grid-cards {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 14px;
        }

        .dg-grid-cards.is-visible {
            display: grid;
        }

        .dg-prof-card {
            border: 1px solid var(--dg-line);
            border-radius: 16px;
            padding: 18px;
            background: #fcfcfa;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dg-prof-card:hover {
            border-color: var(--dg-ink);
            background: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.05);
        }

        .dg-prof-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .dg-prof-name {
            margin-top: 10px;
            font-size: 14.5px;
            font-weight: 600;
            color: var(--dg-ink);
            line-height: 1.3;
        }

        .dg-prof-nip {
            margin-top: 3px;
            font-size: 12px;
            color: var(--dg-muted);
            font-variant-numeric: tabular-nums;
        }

        .dg-prof-foot {
            padding-top: 12px;
            border-top: 1px solid var(--dg-line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
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
            background: var(--dg-band);
            transform: scale(1.1);
        }

        .btn-action-img img {
            width: 18px;
            height: 18px;
            object-fit: contain;
        }

        /* Responsive Container Queries */
        @container (max-width: 1060px) {
            .dg-band {
                grid-template-columns: minmax(0, 1fr);
                gap: 24px;
            }
            .dg-ledger-wrap {
                border-left: 0;
                border-top: 1px solid rgba(22, 22, 22, 0.12);
                padding-left: 0;
                padding-top: 22px;
            }
            .dg-workbench {
                grid-template-columns: minmax(0, 1fr);
            }
            .dg-form-panel {
                position: static;
            }
        }

        @container (max-width: 640px) {
            .dg-band,
            .dg-card {
                padding: 22px 18px;
            }
            .dg-bars {
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
                        <li><a href="daftar_siswa.php"><span class="dot siswa"></span> <span class="menu-label">Kelola Siswa</span></a></li>
                        <li><a href="daftar_guru.php" style="font-weight: 700; color: #fff;"><span class="dot guru"></span> <span class="menu-label">Kelola Guru</span></a></li>
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
                    <h1>Kelola Data Guru</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <div class="search-box">
                        <i class="zoom"></i>
                        <input type="text" id="tableSearch" placeholder="Cari NIP, nama guru, akun...">
                    </div>
                    <button class="icon-btn notification-btn"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/bell.webp" alt="Notifikasi"></button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/settings.webp" alt="Settings"></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="dg-shell">
                <!-- BARIS 1: PITA HEADER EDITORIAL (FULL-WIDTH CREAM + LEDGER 2x2) -->
                <section class="dg-band">
                    <div>
                        <div class="dg-eyebrow">
                            <span class="dg-pill"><span class="dg-dot-live"></span> Faculty Directory</span>
                            <span class="dg-pill">SMK Sangkuriang 1 Cimahi</span>
                        </div>
                        <h2 class="dg-h1">Direktori <span class="dg-dim">pendidik</span><br>&amp; tenaga pengajar</h2>
                        <p class="dg-sub">Kelola identitas NIP, distribusi gender pengajar, serta penautan akun login guru untuk akses input nilai rapor.</p>

                        <div class="dg-bars">
                            <div class="dg-bar-item">
                                <div class="dg-bar-top">
                                    <span>Sinkronisasi Akun</span>
                                    <strong><?= $linked_pct; ?>%</strong>
                                </div>
                                <div class="dg-track">
                                    <div class="dg-fill" style="width: <?= $linked_pct; ?>%;"></div>
                                </div>
                                <div class="dg-bar-foot">
                                    <span><?= $guru_linked; ?> tertaut</span>
                                    <span><?= $guru_unlinked; ?> belum</span>
                                </div>
                            </div>

                            <div class="dg-bar-item">
                                <div class="dg-bar-top">
                                    <span>Rasio Gender (L / P)</span>
                                    <strong><?= $laki_pct; ?>% : <?= $perempuan_pct; ?>%</strong>
                                </div>
                                <div class="dg-track">
                                    <div class="dg-fill" style="width: <?= $laki_pct; ?>%;"></div>
                                </div>
                                <div class="dg-bar-foot">
                                    <span><?= $guru_laki; ?> laki-laki</span>
                                    <span><?= $guru_perempuan; ?> perempuan</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dg-ledger-wrap">
                        <div class="dg-ledger">
                            <div class="dg-stat">
                                <span class="dg-stat-num"><?= $tot_guru; ?></span>
                                <span class="dg-stat-lbl">Total tenaga pendidik</span>
                            </div>
                            <div class="dg-stat">
                                <span class="dg-stat-num"><?= $guru_linked; ?></span>
                                <span class="dg-stat-lbl">Akun login tertaut</span>
                            </div>
                            <div class="dg-stat">
                                <span class="dg-stat-num"><?= $guru_mengajar; ?></span>
                                <span class="dg-stat-lbl">Mengampu mapel</span>
                            </div>
                            <div class="dg-stat">
                                <span class="dg-stat-num"><?= $akun_tersedia; ?></span>
                                <span class="dg-stat-lbl">Akun guru siap taut</span>
                            </div>
                        </div>

                        <div class="dg-band-actions">
                            <a href="#formCard" class="dg-pill dg-pill-dark" onclick="document.getElementById('nip').focus();">+ Daftarkan Guru</a>
                            <a href="daftar_mapel.php" class="dg-pill">Mata Pelajaran</a>
                            <a href="daftar_user.php" class="dg-pill dg-pill-line">Akun User</a>
                        </div>
                    </div>
                </section>

                <?php if (!empty($msg_text)): ?>
                    <div class="dg-alert <?= ($msg_type === 'success') ? 'is-success' : 'is-error'; ?>" role="status">
                        <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                        <span><?= htmlspecialchars($msg_text); ?></span>
                    </div>
                <?php endif; ?>

                <!-- BARIS 2: WORKBENCH ASIMETRIS (STICKY FORM KIRI + ROSTER DIREKTORI KANAN) -->
                <section class="dg-workbench">
                    <!-- PANEL KIRI: FORM INPUT / EDIT GURU -->
                    <div class="dg-card dg-form-panel" id="formCard">
                        <div class="dg-head">
                            <div>
                                <h3 class="dg-title" id="formTitle">Registrasi Guru</h3>
                                <p class="dg-muted" id="formSub">Tambahkan pendidik baru atau tautkan ke akun login guru.</p>
                            </div>
                            <button type="button" class="dg-pill dg-pill-danger dg-btn-cancel" id="btnCancelEdit" onclick="resetFormGuru()">
                                <i class="fa-solid fa-xmark"></i> Batal
                            </button>
                        </div>

                        <!-- Live Preview Kartu Identitas -->
                        <div class="dg-preview" aria-live="polite">
                            <div class="dg-preview-ava" id="previewAva">G</div>
                            <div class="dg-preview-meta">
                                <div class="dg-preview-name" id="previewName">Nama Pendidik Baru</div>
                                <div class="dg-preview-sub">
                                    <span id="previewNip">NIP belum diisi</span>
                                    <span>•</span>
                                    <span id="previewGender">Laki-laki</span>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="daftar_guru.php" id="guruForm" class="dg-form-stack">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="edit_id" id="edit_id" value="0">

                            <div class="dg-field">
                                <label for="nip">Nomor Induk Pegawai (NIP)</label>
                                <input type="text" name="nip" id="nip" class="dg-input" required placeholder="Contoh: 198203112009021004" oninput="syncGuruPreview()">
                            </div>

                            <div class="dg-field">
                                <label for="nama">Nama Lengkap &amp; Gelar</label>
                                <input type="text" name="nama" id="nama" class="dg-input" required placeholder="Contoh: Drs. Ahmad Fauzi, M.Pd." oninput="syncGuruPreview()">
                            </div>

                            <div class="dg-field">
                                <label>Jenis Kelamin</label>
                                <input type="hidden" name="jenis_kelamin" id="jenis_kelamin" value="L">
                                <div class="dg-seg" role="group" aria-label="Pilih Jenis Kelamin">
                                    <button type="button" class="dg-seg-btn is-active" data-jk="L" onclick="setGender('L')">Laki-laki</button>
                                    <button type="button" class="dg-seg-btn" data-jk="P" onclick="setGender('P')">Perempuan</button>
                                </div>
                            </div>

                            <div class="dg-field">
                                <label for="user_id">Tautan Akun Login</label>
                                <select name="user_id" id="user_id" class="dg-input">
                                    <option value="">-- Tanpa Akun Login --</option>
                                    <?php foreach ($users_guru as $u): ?>
                                        <option value="<?= (int)$u['id']; ?>">@<?= htmlspecialchars($u['username']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="dg-form-actions">
                                <button type="submit" name="simpan_guru" class="dg-btn-submit">
                                    <i class="fa-solid fa-check"></i> <span id="submitLabel">Simpan Data Guru</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- PANEL KANAN: DIREKTORI & ROSTER PENDIDIK -->
                    <div class="dg-card">
                        <div class="dg-head">
                            <div>
                                <h3 class="dg-title">Roster Tenaga Pendidik</h3>
                                <p class="dg-muted">Filter berdasarkan status tautan akun, gender, atau beralih ke tampilan kartu.</p>
                            </div>
                            <span class="dg-pill" id="rowCountBadge"><?= $tot_guru; ?> pendidik</span>
                        </div>

                        <div class="dg-toolbar">
                            <div class="dg-filter-pills" role="group" aria-label="Filter daftar guru">
                                <button type="button" class="dg-fpill is-active" data-filter="all" onclick="setRosterFilter('all', this)">Semua (<?= $tot_guru; ?>)</button>
                                <button type="button" class="dg-fpill" data-filter="linked" onclick="setRosterFilter('linked', this)">Tertaut (<?= $guru_linked; ?>)</button>
                                <button type="button" class="dg-fpill" data-filter="unlinked" onclick="setRosterFilter('unlinked', this)">Belum Tertaut (<?= $guru_unlinked; ?>)</button>
                                <button type="button" class="dg-fpill" data-filter="L" onclick="setRosterFilter('L', this)">Laki-laki</button>
                                <button type="button" class="dg-fpill" data-filter="P" onclick="setRosterFilter('P', this)">Perempuan</button>
                            </div>

                            <div class="dg-view-toggle" role="group" aria-label="Mode tampilan">
                                <button type="button" class="dg-vbtn is-active" id="btnViewTable" onclick="switchRosterView('table')">
                                    <i class="fa-solid fa-list"></i> Tabel
                                </button>
                                <button type="button" class="dg-vbtn" id="btnViewCards" onclick="switchRosterView('cards')">
                                    <i class="fa-solid fa-grip"></i> Kartu
                                </button>
                            </div>
                        </div>

                        <!-- MODE 1: TABEL EDITORIAL -->
                        <div class="dg-table-wrap" id="tableViewWrap">
                            <table class="dg-table" id="dataTable">
                                <thead>
                                    <tr>
                                        <th style="width: 46px; text-align: center;">No</th>
                                        <th>Pendidik</th>
                                        <th>NIP</th>
                                        <th>Ampuan</th>
                                        <th>Akun Login</th>
                                        <th style="text-align: center; width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($list_guru)): ?>
                                        <?php foreach ($list_guru as $idx => $g):
                                            $is_linked = !empty($g['username']);
                                            $jk_code   = ($g['jenis_kelamin'] === 'P') ? 'P' : 'L';
                                            $jml_m     = (int)($g['jml_mapel'] ?? 0);
                                        ?>
                                            <tr class="dg-item"
                                                data-linked="<?= $is_linked ? 'linked' : 'unlinked'; ?>"
                                                data-jk="<?= $jk_code; ?>">
                                                <td style="text-align: center; font-weight: 600; color: var(--dg-muted);"><?= $idx + 1; ?></td>
                                                <td>
                                                    <div class="dg-person">
                                                        <span class="dg-ava <?= $is_linked ? 'is-dark' : ''; ?>">
                                                            <?= strtoupper(substr($g['nama'], 0, 1)); ?>
                                                        </span>
                                                        <div>
                                                            <strong style="display:block; color: var(--dg-ink);"><?= htmlspecialchars($g['nama']); ?></strong>
                                                            <span style="font-size: 11.5px; color: var(--dg-muted);">
                                                                <?= ($jk_code === 'L') ? 'Laki-laki' : 'Perempuan'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="dg-nip-code"><?= htmlspecialchars($g['nip']); ?></span></td>
                                                <td>
                                                    <?php if ($jml_m > 0): ?>
                                                        <span class="dg-chip is-mapel"><?= $jml_m; ?> mapel</span>
                                                    <?php else: ?>
                                                        <span class="dg-chip">0 mapel</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($is_linked): ?>
                                                        <span class="dg-chip is-linked">@<?= htmlspecialchars($g['username']); ?></span>
                                                    <?php else: ?>
                                                        <span class="dg-chip">Belum tertaut</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align: center; white-space: nowrap;">
                                                    <button type="button"
                                                        class="btn-action-img"
                                                        title="Edit"
                                                        data-id="<?= (int)$g['id']; ?>"
                                                        data-nip="<?= htmlspecialchars($g['nip'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-nama="<?= htmlspecialchars($g['nama'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-jk="<?= htmlspecialchars($jk_code, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-userid="<?= htmlspecialchars((string)($g['user_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        onclick="editGuruFromBtn(this)">
                                                        <img src="<?= $base_url; ?>/icons/admin/admin_manage/edit.webp" alt="Edit">
                                                    </button>
                                                    <a href="daftar_guru.php?hapus=<?= (int)$g['id']; ?>&t=<?= csrf_token(); ?>"
                                                       onclick="return confirm('Hapus data guru ini?');"
                                                       class="btn-action-img"
                                                       title="Hapus">
                                                        <img src="<?= $base_url; ?>/icons/admin/admin_manage/trash-bin.webp" alt="Hapus">
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr class="dg-empty-row">
                                            <td colspan="6" style="text-align: center; padding: 36px; color: var(--dg-muted);">Belum ada data guru terdaftar.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- MODE 2: KARTU DIREKTORI PENDIDIK -->
                        <div class="dg-grid-cards" id="cardViewWrap">
                            <?php if (!empty($list_guru)): ?>
                                <?php foreach ($list_guru as $g):
                                    $is_linked = !empty($g['username']);
                                    $jk_code   = ($g['jenis_kelamin'] === 'P') ? 'P' : 'L';
                                    $jml_m     = (int)($g['jml_mapel'] ?? 0);
                                ?>
                                    <article class="dg-prof-card dg-item"
                                             data-linked="<?= $is_linked ? 'linked' : 'unlinked'; ?>"
                                             data-jk="<?= $jk_code; ?>">
                                        <div>
                                            <div class="dg-prof-top">
                                                <span class="dg-ava <?= $is_linked ? 'is-dark' : ''; ?>">
                                                    <?= strtoupper(substr($g['nama'], 0, 1)); ?>
                                                </span>
                                                <?php if ($is_linked): ?>
                                                    <span class="dg-chip is-linked">@<?= htmlspecialchars($g['username']); ?></span>
                                                <?php else: ?>
                                                    <span class="dg-chip">Belum tertaut</span>
                                                <?php endif; ?>
                                            </div>
                                            <h4 class="dg-prof-name"><?= htmlspecialchars($g['nama']); ?></h4>
                                            <p class="dg-prof-nip">NIP: <?= htmlspecialchars($g['nip']); ?> • <?= ($jk_code === 'L') ? 'Laki-laki' : 'Perempuan'; ?></p>
                                        </div>

                                        <div class="dg-prof-foot">
                                            <span class="dg-chip <?= $jml_m > 0 ? 'is-mapel' : ''; ?>"><?= $jml_m; ?> mapel diampu</span>
                                            <div>
                                                <button type="button"
                                                    class="btn-action-img"
                                                    title="Edit"
                                                    data-id="<?= (int)$g['id']; ?>"
                                                    data-nip="<?= htmlspecialchars($g['nip'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-nama="<?= htmlspecialchars($g['nama'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-jk="<?= htmlspecialchars($jk_code, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-userid="<?= htmlspecialchars((string)($g['user_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    onclick="editGuruFromBtn(this)">
                                                    <img src="<?= $base_url; ?>/icons/admin/admin_manage/edit.webp" alt="Edit">
                                                </button>
                                                <a href="daftar_guru.php?hapus=<?= (int)$g['id']; ?>&t=<?= csrf_token(); ?>"
                                                   onclick="return confirm('Hapus data guru ini?');"
                                                   class="btn-action-img"
                                                   title="Hapus">
                                                    <img src="<?= $base_url; ?>/icons/admin/admin_manage/trash-bin.webp" alt="Hapus">
                                                </a>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="grid-column: 1 / -1; text-align: center; padding: 32px; color: var(--dg-muted);">Belum ada data guru terdaftar.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<script>
    let activeRosterFilter = 'all';

    function setGender(jk) {
        const val = (jk === 'P') ? 'P' : 'L';
        document.getElementById('jenis_kelamin').value = val;
        document.querySelectorAll('.dg-seg-btn').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.jk === val);
        });
        syncGuruPreview();
    }

    function syncGuruPreview() {
        const nama = document.getElementById('nama').value.trim();
        const nip  = document.getElementById('nip').value.trim();
        const jk   = document.getElementById('jenis_kelamin').value;

        document.getElementById('previewAva').textContent    = nama ? nama.charAt(0).toUpperCase() : 'G';
        document.getElementById('previewName').textContent   = nama || 'Nama Pendidik Baru';
        document.getElementById('previewNip').textContent    = nip ? ('NIP ' + nip) : 'NIP belum diisi';
        document.getElementById('previewGender').textContent = (jk === 'P') ? 'Perempuan' : 'Laki-laki';
    }

    function editGuruFromBtn(btn) {
        document.getElementById('edit_id').value = btn.dataset.id;
        document.getElementById('nip').value     = btn.dataset.nip;
        document.getElementById('nama').value    = btn.dataset.nama;
        document.getElementById('user_id').value = btn.dataset.userid;
        setGender(btn.dataset.jk);

        document.getElementById('formTitle').textContent   = 'Edit Pendidik #' + btn.dataset.id;
        document.getElementById('formSub').textContent     = 'Perbarui profil pendidik lalu simpan perubahan.';
        document.getElementById('submitLabel').textContent = 'Perbarui Data';
        document.getElementById('formCard').classList.add('is-editing');
        document.getElementById('btnCancelEdit').style.display = 'inline-flex';
        document.getElementById('formCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function resetFormGuru() {
        document.getElementById('guruForm').reset();
        document.getElementById('edit_id').value = 0;
        setGender('L');
        document.getElementById('formTitle').textContent   = 'Registrasi Guru';
        document.getElementById('formSub').textContent     = 'Tambahkan pendidik baru atau tautkan ke akun login guru.';
        document.getElementById('submitLabel').textContent = 'Simpan Data Guru';
        document.getElementById('formCard').classList.remove('is-editing');
        document.getElementById('btnCancelEdit').style.display = 'none';
    }

    function applyRosterFilters() {
        const q = (document.getElementById('tableSearch')?.value || '').toLowerCase().trim();
        const tableRows = document.querySelectorAll('#dataTable tbody tr.dg-item');
        const cardItems = document.querySelectorAll('#cardViewWrap .dg-item');
        let visibleCount = 0;

        tableRows.forEach(tr => {
            const matchText = tr.textContent.toLowerCase().includes(q);
            const matchCat  = (activeRosterFilter === 'all') ||
                              (tr.dataset.linked === activeRosterFilter) ||
                              (tr.dataset.jk === activeRosterFilter);
            const show = matchText && matchCat;
            tr.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        cardItems.forEach(card => {
            const matchText = card.textContent.toLowerCase().includes(q);
            const matchCat  = (activeRosterFilter === 'all') ||
                              (card.dataset.linked === activeRosterFilter) ||
                              (card.dataset.jk === activeRosterFilter);
            card.style.display = (matchText && matchCat) ? '' : 'none';
        });

        const badge = document.getElementById('rowCountBadge');
        if (badge) badge.textContent = visibleCount + ' pendidik';
    }

    function setRosterFilter(filterKey, btnEl) {
        activeRosterFilter = filterKey;
        document.querySelectorAll('.dg-fpill').forEach(b => b.classList.remove('is-active'));
        if (btnEl) btnEl.classList.add('is-active');
        applyRosterFilters();
    }

    function switchRosterView(mode) {
        const tableWrap = document.getElementById('tableViewWrap');
        const cardWrap  = document.getElementById('cardViewWrap');
        const btnTable  = document.getElementById('btnViewTable');
        const btnCards  = document.getElementById('btnViewCards');

        if (mode === 'cards') {
            tableWrap.style.display = 'none';
            cardWrap.classList.add('is-visible');
            btnTable.classList.remove('is-active');
            btnCards.classList.add('is-active');
        } else {
            tableWrap.style.display = '';
            cardWrap.classList.remove('is-visible');
            btnCards.classList.remove('is-active');
            btnTable.classList.add('is-active');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('tableSearch');
        if (searchInput) {
            searchInput.addEventListener('input', applyRosterFilters);
        }
    });
</script>
</body>
</html>