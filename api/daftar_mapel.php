<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include "koneksi.php";

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Admin';
$msg_type = "";
$msg_text = "";

// AMBIL FOTO USER ADMIN
$q_u = mysqli_query($koneksi, "SELECT foto FROM users WHERE id = '$user_id'");
$u_data = ($q_u && mysqli_num_rows($q_u) > 0) ? mysqli_fetch_assoc($q_u) : [];
$avatar_src = (!empty($u_data['foto']) && file_exists('uploads/avatars/' . $u_data['foto'])) ? 'uploads/avatars/' . $u_data['foto'] : '';

// 1. TAMBAH / UPDATE MAPEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_mapel'])) {
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
        $msg_type = "success"; $msg_text = "Mata pelajaran berhasil disimpan!";
    } else {
        $msg_type = "error"; $msg_text = "Gagal menyimpan mapel: " . mysqli_error($koneksi);
    }
}

// 2. HAPUS MAPEL
if (isset($_GET['hapus'])) {
    $hapus_id = (int)$_GET['hapus'];
    if (mysqli_query($koneksi, "DELETE FROM mapel WHERE id = '$hapus_id'")) {
        $msg_type = "success"; $msg_text = "Mata pelajaran berhasil dihapus!";
    }
}

// 3. FETCH MAPEL & DAFTAR GURU
$list_mapel = [];
$q_mapel = mysqli_query($koneksi, "SELECT m.*, g.nama AS nama_guru FROM mapel m LEFT JOIN guru g ON m.guru_id = g.id ORDER BY m.nama_mapel ASC");
while ($r = mysqli_fetch_assoc($q_mapel)) { $list_mapel[] = $r; }

$list_guru_opt = [];
$q_g = mysqli_query($koneksi, "SELECT id, nama FROM guru ORDER BY nama ASC");
while ($r = mysqli_fetch_assoc($q_g)) { $list_guru_opt[] = $r; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mata Pelajaran - SIPN Core</title>
    <link rel="stylesheet" href="style1.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-container { display: flex; flex-direction: column; gap: 20px; animation: fadeInUp 0.4s ease-out forwards; }
        .card-box { background: #fff; border-radius: 16px; padding: 24px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.03); transition: all 0.3s ease; }
        .card-box.form-active { border: 2px solid #6366f1 !important; box-shadow: 0 0 16px rgba(99, 102, 241, 0.2) !important; }
        .form-header-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 2fr 1fr 110px; gap: 12px; align-items: end; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 12px; font-weight: 600; color: #475569; }
        .form-control { padding: 9px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; outline: none; background: #f8fafc; }
        .form-control:focus { border-color: #6366f1; background: #fff; }
        .btn-submit { background: #231c32; color: #fff; padding: 10px 18px; border-radius: 8px; border: none; font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-cancel-edit { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: none; align-items: center; gap: 6px; }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        .data-table th { background: #f8fafc; color: #475569; padding: 12px 14px; border-bottom: 2px solid #e2e8f0; font-weight: 700; }
        .data-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        
        .btn-action-img { background: transparent; border: none; cursor: pointer; padding: 6px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; transition: transform 0.2s ease; text-decoration: none; }
        .btn-action-img:hover { background-color: rgba(99, 102, 241, 0.1); transform: scale(1.15); }
        .btn-action-img img { width: 20px; height: 20px; object-fit: contain; }

        .alert-box { padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        @media (max-width: 900px) { .form-grid-3 { grid-template-columns: 1fr; } }
    </style>
    <script>
        window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
</head>
<body>

    <div class="dashboard-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-brand"><div class="brand-logo"><i class="home"></i></div><h2>SIPN Core</h2></div>
            <div class="user-profile-mini">
                <?php if (!empty($avatar_src)): ?>
                    <img src="<?= htmlspecialchars($avatar_src); ?>" class="user-avatar-initial" style="object-fit: cover;">
                <?php else: ?>
                    <div class="user-avatar-initial"><?= strtoupper(substr($username, 0, 1)); ?></div>
                <?php endif; ?>
                <div class="user-meta-mini"><span class="user-name"><?= htmlspecialchars($username); ?></span><span class="user-role-badge">Admin</span></div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="dashboard"></i> Dashboard</a></li>
                <li class="has-submenu active">
                    <a href="#"><i class="fa-solid fa-folder-tree"></i> Master Data <i class="fa-solid fa-chevron-down arrow"></i></a>
                    <ul class="submenu" style="display: flex;">
                        <li><a href="daftar_user.php"><span class="dot user"></span> Kelola User</a></li>
                        <li><a href="daftar_siswa.php"><span class="dot siswa"></span> Kelola Siswa</a></li>
                        <li><a href="daftar_guru.php"><span class="dot guru"></span> Kelola Guru</a></li>
                        <li><a href="daftar_mapel.php" style="font-weight: 700; color: #fff;"><span class="dot mapel"></span> Mata Pelajaran</a></li>
                    </ul>
                </li>
                <li><a href="profile.php"><i class="fa-solid fa-gear"></i> Manage Profile</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="logout.php" class="add-files-box" style="text-decoration: none; color: inherit;">
                    <div class="icon-plus" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
                    <div class="add-text"><strong>System Session</strong><span style="color: #ef4444;">Logout Account</span></div>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title"><h1>Mata Pelajaran</h1><span class="storage-badge">Role: ADMIN</span></div>
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

                <!-- FORM INPUT / EDIT MAPEL -->
                <div class="card-box" id="formCard">
                    <div class="form-header-row">
                        <h3 style="font-size: 15px;" id="formTitle"><i class="fa-solid fa-book" style="color: #6366f1;"></i> Tambah Mata Pelajaran</h3>
                        <button type="button" class="btn-cancel-edit" id="btnCancelEdit" onclick="resetFormMapel()"><i class="fa-solid fa-xmark"></i> Batal Edit</button>
                    </div>

                    <form method="POST" action="daftar_mapel.php" id="mapelForm">
                        <input type="hidden" name="edit_id" id="edit_id" value="0">
                        <div class="form-grid-3">
                            <div class="form-group">
                                <label>Kode Mapel</label>
                                <input type="text" name="kode_mapel" id="kode_mapel" class="form-control" required placeholder="Ex: MP10">
                            </div>
                            <div class="form-group">
                                <label>Nama Mata Pelajaran</label>
                                <input type="text" name="nama_mapel" id="nama_mapel" class="form-control" required placeholder="Nama Mapel">
                            </div>
                            <div class="form-group">
                                <label>Guru Pengajar</label>
                                <select name="guru_id" id="guru_id" class="form-control">
                                    <option value="">-- Belum Ditentukan --</option>
                                    <?php foreach ($list_guru_opt as $g): ?>
                                        <option value="<?= $g['id']; ?>"><?= htmlspecialchars($g['nama']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="simpan_mapel" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                        </div>
                    </form>
                </div>

                <!-- TABLE MAPEL -->
                <div class="card-box">
                    <h3 style="font-size: 15px; margin-bottom: 16px;">Daftar Mata Pelajaran</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">No</th>
                                    <th>Kode Mapel</th>
                                    <th>Nama Mata Pelajaran</th>
                                    <th>Guru Pengajar</th>
                                    <th style="text-align: center; width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list_mapel as $idx => $m): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: 600;"><?= $idx + 1; ?></td>
                                        <td><strong><?= htmlspecialchars($m['kode_mapel']); ?></strong></td>
                                        <td><?= htmlspecialchars($m['nama_mapel']); ?></td>
                                        <td><span style="color: #6366f1; font-weight: 600;"><?= htmlspecialchars($m['nama_guru'] ?? 'Belum Ada'); ?></span></td>
                                        <td style="text-align: center;">
                                            <button onclick="editMapel(<?= $m['id']; ?>, '<?= htmlspecialchars($m['kode_mapel']); ?>', '<?= htmlspecialchars($m['nama_mapel']); ?>', '<?= $m['guru_id']; ?>')" class="btn-action-img" title="Edit">
                                                <img src="icons/edit.png" alt="Edit">
                                            </button>
                                            <a href="daftar_mapel.php?hapus=<?= $m['id']; ?>" onclick="return confirm('Hapus mapel ini?');" class="btn-action-img" title="Hapus">
                                                <img src="icons/trash-bin.png" alt="Hapus">
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function editMapel(id, kode, nama, guru_id) {
            document.getElementById('edit_id').value = id;
            document.getElementById('kode_mapel').value = kode;
            document.getElementById('nama_mapel').value = nama;
            document.getElementById('guru_id').value = guru_id;
            
            document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-pen-to-square" style="color: #6366f1;"></i> Edit Mata Pelajaran #' + id;
            document.getElementById('formCard').classList.add('form-active');
            document.getElementById('btnCancelEdit').style.display = 'inline-flex';

            document.getElementById('formCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function resetFormMapel() {
            document.getElementById('mapelForm').reset();
            document.getElementById('edit_id').value = 0;
            document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-book" style="color: #6366f1;"></i> Tambah Mata Pelajaran';
            document.getElementById('formCard').classList.remove('form-active');
            document.getElementById('btnCancelEdit').style.display = 'none';
        }

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