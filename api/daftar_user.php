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
$foto_db = $u_data['foto'] ?? '';
$avatar_src = (!empty($foto_db) && file_exists('uploads/avatars/' . $foto_db)) ? 'uploads/avatars/' . $foto_db : '';

// 1. TAMBAH / UPDATE USER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simpan_user'])) {
        $edit_id   = (int)($_POST['edit_id'] ?? 0);
        $usr_name  = mysqli_real_escape_string($koneksi, trim($_POST['username']));
        $usr_role  = mysqli_real_escape_string($koneksi, $_POST['role']);
        $usr_pass  = $_POST['password'] ?? '';

        if ($edit_id > 0) {
            $sql_pass = !empty($usr_pass) ? ", password = '" . password_hash($usr_pass, PASSWORD_BCRYPT) . "'" : "";
            $q = "UPDATE users SET username = '$usr_name', role = '$usr_role' $sql_pass WHERE id = '$edit_id'";
            if (mysqli_query($koneksi, $q)) {
                $msg_type = "success"; $msg_text = "Data user berhasil diperbarui!";
            } else {
                $msg_type = "error"; $msg_text = "Gagal memperbarui user: " . mysqli_error($koneksi);
            }
        } else {
            if (empty($usr_pass)) {
                $msg_type = "error"; $msg_text = "Password wajib diisi untuk user baru!";
            } else {
                $hashed = password_hash($usr_pass, PASSWORD_BCRYPT);
                $q = "INSERT INTO users (username, password, role) VALUES ('$usr_name', '$hashed', '$usr_role')";
                if (mysqli_query($koneksi, $q)) {
                    $msg_type = "success"; $msg_text = "User baru berhasil ditambahkan!";
                } else {
                    $msg_type = "error"; $msg_text = "Gagal menambah user: " . mysqli_error($koneksi);
                }
            }
        }
    }
}

// 2. HAPUS USER
if (isset($_GET['hapus'])) {
    $hapus_id = (int)$_GET['hapus'];
    if ($hapus_id !== $user_id) {
        if (mysqli_query($koneksi, "DELETE FROM users WHERE id = '$hapus_id'")) {
            $msg_type = "success"; $msg_text = "User berhasil dihapus!";
        }
    } else {
        $msg_type = "error"; $msg_text = "Tidak dapat menghapus akun Anda sendiri!";
    }
}

// 3. FETCH USERS
$list_users = [];
$q_all = mysqli_query($koneksi, "SELECT * FROM users ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($q_all)) {
    $list_users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - SIPN Core</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png"> 
   <style>
        .page-container { display: flex; flex-direction: column; gap: 20px; animation: fadeInUp 0.4s ease-out forwards; }
        .card-box { background: #fff; border-radius: 16px; padding: 24px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr 120px; gap: 16px; align-items: end; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 12px; font-weight: 600; color: #475569; }
        .form-control { padding: 9px 12px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; outline: none; background: #f8fafc; }
        .btn-submit { background: #231c32; color: #fff; padding: 10px 18px; border-radius: 8px; border: none; font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        .data-table th { background: #f8fafc; color: #475569; padding: 12px 14px; border-bottom: 2px solid #e2e8f0; font-weight: 700; }
        .data-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .badge-role { padding: 4px 0px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .role-admin {color: #64748b; }
        .role-guru {color: #64748b; }
        .role-siswa {color: #64748b; }

        /* ACTION BUTTON IMAGE STYLE */
        .btn-action-img {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
            text-decoration: none;
        }

        .btn-action-img:hover {
            transform: scale(1.2);
        }

        .btn-action-img img {
            width: 20px;
            height: 20px;
            object-fit: contain;
        }

        .alert-box { padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
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
                    <span class="user-role-badge">Admin</span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="dashboard"></i> Dashboard</a></li>
                <li class="has-submenu active">
                    <a href="#"><i class="fa-solid fa-folder-tree"></i> Master Data <i class="fa-solid fa-chevron-down arrow"></i></a>
                    <ul class="submenu" style="display: flex;">
                        <li><a href="daftar_user.php" style="font-weight: 700; color: #fff;"><span class="dot user"></span> Kelola User</a></li>
                        <li><a href="daftar_siswa.php"><span class="dot siswa"></span> Kelola Siswa</a></li>
                        <li><a href="daftar_guru.php"><span class="dot guru"></span> Kelola Guru</a></li>
                        <li><a href="daftar_mapel.php"><span class="dot mapel"></span> Mata Pelajaran</a></li>
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
                <div class="topbar-title"><h1>Kelola User System</h1><span class="storage-badge">Role: ADMIN</span></div>
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

                <!-- FORM INPUT / EDIT USER -->
                <div class="card-box">
                    <h3 style="font-size: 15px; margin-bottom: 16px;" id="formTitle">Tambah User Baru</h3>
                    <form method="POST" action="daftar_user.php">
                        <input type="hidden" name="edit_id" id="edit_id" value="0">
                        <div class="form-grid-3">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username" required>
                            </div>
                            <div class="form-group">
                                <label>Password <small style="color: #64748b;">(Kosongkan jika tidak ubah)</small></label>
                                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label>Role Access</label>
                                <select name="role" id="role" class="form-control" required>
                                    <option value="siswa">Siswa</option>
                                    <option value="guru">Guru</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <button type="submit" name="simpan_user" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                        </div>
                    </form>
                </div>

                <!-- TABLE DAFTAR USER -->
                <div class="card-box">
                    <h3 style="font-size: 15px; margin-bottom: 16px;">Daftar Users Terdaftar</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">No</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Tanggal Dibuat</th>
                                    <th style="text-align: center; width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list_users as $idx => $u): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: 600;"><?= $idx + 1; ?></td>
                                        <td><strong><?= htmlspecialchars($u['username']); ?></strong></td>
                                        <td>
                                            <span class="badge-role role-<?= strtolower($u['role']); ?>">
                                                <?= htmlspecialchars($u['role']); ?>
                                            </span>
                                        </td>
                                        <td style="color: #64748b;"><?= date('d M Y, H:i', strtotime($u['created_at'])); ?></td>
                                        <td style="text-align: center;">
                                            <!-- Edit Image Button -->
                                            <button onclick="editUser(<?= $u['id']; ?>, '<?= htmlspecialchars($u['username']); ?>', '<?= $u['role']; ?>')" class="btn-action-img" title="Edit">
                                                <img src="icons/admin/admin_manage/edit.png" alt="Edit">
                                            </button>
                                            
                                            <!-- Delete Image Button -->
                                            <?php if ($u['id'] !== $user_id): ?>
                                                <a href="daftar_user.php?hapus=<?= $u['id']; ?>" onclick="return confirm('Hapus user ini?');" class="btn-action-img" title="Hapus">
                                                    <img src="icons/admin/admin_manage/trash-bin.png" alt="Hapus">
                                                </a>
                                            <?php endif; ?>
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
        function editUser(id, username, role) {
            document.getElementById('edit_id').value = id;
            document.getElementById('username').value = username;
            document.getElementById('role').value = role;
            document.getElementById('formTitle').innerText = 'Edit User #' + id;
            window.scrollTo({ top: 0, behavior: 'smooth' });
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