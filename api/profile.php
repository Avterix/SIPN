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

$msg_type = $_SESSION['msg_type'] ?? '';
$msg_text = $_SESSION['msg_text'] ?? '';
unset($_SESSION['msg_type'], $_SESSION['msg_text']);

// 1. AMBIL DATA USER
$q_user = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id'");
$user_data = ($q_user && mysqli_num_rows($q_user) > 0) ? mysqli_fetch_assoc($q_user) : [];

$username     = $user_data['username'] ?? $_SESSION['username'] ?? 'User';
$foto_db      = $user_data['foto'] ?? $_SESSION['foto'] ?? '';
$gh_connected = !empty($user_data['github_connected']) && $user_data['github_connected'] == 1;
$gh_username  = $user_data['github_username'] ?? '';
$gh_avatar    = $user_data['github_avatar'] ?? ''; // Ditambahkan agar tidak undefined

// 2. AMBIL DATA DETAIL
$detail_data = [];
if ($role === 'siswa') {
    $q_detail = mysqli_query($koneksi, "SELECT * FROM siswa WHERE user_id = '$user_id'");
    if ($q_detail && mysqli_num_rows($q_detail) > 0) $detail_data = mysqli_fetch_assoc($q_detail);
} elseif ($role === 'guru') {
    $q_detail = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id = '$user_id'");
    if ($q_detail && mysqli_num_rows($q_detail) > 0) $detail_data = mysqli_fetch_assoc($q_detail);
}

$nama_lengkap  = $detail_data['nama'] ?? $username;
$nis            = $detail_data['nis'] ?? '';
$nip            = $detail_data['nip'] ?? '';
$kelas          = $detail_data['kelas'] ?? '';
$jenis_kelamin = $detail_data['jenis_kelamin'] ?? 'L';

// 3. PROSES UPDATE PROFILE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $new_password = $_POST['new_password'] ?? '';
    
    if (!empty($_POST['avatar_base64'])) {
        $foto_db = $_POST['avatar_base64'];
    }

    if (!empty($new_password)) {
        $hashed_pass = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt_user   = $koneksi->prepare("UPDATE users SET username = ?, foto = ?, password = ? WHERE id = ?");
        $stmt_user->bind_param("sssi", $new_username, $foto_db, $hashed_pass, $user_id);
    } else {
        $stmt_user   = $koneksi->prepare("UPDATE users SET username = ?, foto = ? WHERE id = ?");
        $stmt_user->bind_param("ssi", $new_username, $foto_db, $user_id);
    }

    if ($stmt_user->execute()) {
        $_SESSION['username'] = $new_username;
        $_SESSION['foto']     = $foto_db;
        $msg_type = "success";
        $msg_text = "Profil berhasil diperbarui!";
    } else {
        $msg_type = "error";
        $msg_text = "Gagal simpan ke DB: " . $stmt_user->error;
    }
}

// 4. PENENTUAN SOURCE AVATAR (LOGIKA DIPISAH TOTAL)
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

$progress = 30;
if (!empty($avatar_src)) $progress += 20;
if (!empty($nis) || !empty($nip)) $progress += 20;
if (!empty($nama_lengkap)) $progress += 15;
if ($gh_connected) $progress += 15;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile - SIPN Core</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    <style>
        .profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; animation: fadeInUp 0.5s ease-out forwards; }
        .profile-card-left { background-color: var(--card-bg, #ffffff); border-radius: 16px; padding: 24px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.03); display: flex; flex-direction: column; align-items: center; text-align: center; }
        .avatar-wrapper { position: relative; width: 100px; height: 100px; margin-bottom: 14px; }
        .avatar-img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid #ffffff; box-shadow: 0 4px 14px rgba(0,0,0,0.1); }
        .avatar-placeholder { width: 100%; height: 100%; border-radius: 50%; background: #231c32; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; }
        .avatar-upload-btn { position: absolute; bottom: 2px; right: 2px; width: 32px; height: 32px; background: #231c32; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.2); transition: transform 0.2s; }
        .avatar-upload-btn:hover { transform: scale(1.1); }
        .profile-name { font-size: 17px; font-weight: 700; color: var(--text-main, #1e293b); margin-bottom: 2px; }
        .profile-title { font-size: 12px; color: var(--text-muted, #64748b); margin-bottom: 12px; }
        .profile-verified-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 4px 12px; border-radius: 20px; margin-bottom: 16px; }
        .progress-box { width: 100%; margin-bottom: 20px; text-align: left; }
        .progress-header { display: flex; justify-content: space-between; font-size: 10px; font-weight: 700; color: #8c94a6; margin-bottom: 6px; letter-spacing: 0.5px; }
        .progress-bar-bg { width: 100%; height: 6px; background-color: #f1f5f9; border-radius: 10px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background-color: var(--accent-purple, #4f46e5); border-radius: 10px; }
        .profile-meta-list { width: 100%; display: flex; flex-direction: column; gap: 12px; text-align: left; border-top: 1px solid #f1f5f9; padding-top: 16px; }
        .meta-item { display: flex; justify-content: space-between; font-size: 12px; }
        .meta-label { color: var(--text-muted, #64748b); display: flex; align-items: center; gap: 8px; }
        .meta-val { font-weight: 600; color: var(--text-main, #4d4e8d); }
        .card-box { background-color: var(--card-bg, #ffffff); border-radius: 16px; padding: 24px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 20px; }
        .form-section-title { font-size: 15px; font-weight: 700; color: var(--text-main, #0f172a); margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
        .github-integration-card { display: flex; align-items: center; justify-content: space-between; padding: 16px; background: #fafafa; border: 1px solid #e2e8f0; border-radius: 12px; }
        .gh-info-left { display: flex; align-items: center; gap: 14px; }
        .gh-info-left i { font-size: 32px; color: #24292e; }
        .gh-text h4 { font-size: 14px; font-weight: 700; color: #0f172a; }
        .gh-text p { font-size: 12px; color: #64748b; }
        .btn-gh-connect { background-color: #24292e; color: #ffffff; padding: 9px 16px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-gh-disconnect { background-color: #fee2e2; color: #dc2626; padding: 9px 16px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .form-group label { font-size: 12px; font-weight: 600; color: var(--text-main, #334155); }
        .form-control { background-color: #f8fafc; border: 1px solid #cbd5e1; padding: 10px 14px; border-radius: 8px; font-size: 13px; outline: none; }
        .alert-box { padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .save-btn { background-color: #231c32; color: #ffffff; border: none; padding: 11px 22px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        @media (max-width: 900px) { .profile-grid { grid-template-columns: 1fr; } .form-grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php include "loader.php"; ?>
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
                <?php if ($role === 'guru'): ?>
                <li><a href="daftar_nilai.php"><i class="fa-solid fa-pen-to-square"></i> Kelola Nilai</a></li>
                <?php endif; ?>
                <?php if ($role === 'siswa'): ?>
                <li><a href="lihat_nilai.php"><i class="report"></i> View Report Card Grades</a></li>
                <?php endif; ?>
                <li><a href="profile.php" class="active"><i class="profile"></i> Manage Profile</a></li>
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
                    <h1>User Profile</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search settings...">
                    </div>
                    <button class="icon-btn notification-btn"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/bell.png"/></button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/settings.png"/"></i></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="profile-grid">
                
                <!-- LEFT CARD: PROFILE SUMMARY -->
                <div class="profile-card-left">
                    <div class="avatar-wrapper">
                        <?php if (!empty($avatar_src)): ?>
                            <img src="<?= htmlspecialchars($avatar_src); ?>" id="avatarPreview" class="avatar-img" alt="Profile Picture">
                        <?php else: ?>
                            <div id="avatarPlaceholder" class="avatar-placeholder"><?= strtoupper(substr($username, 0, 1)); ?></div>
                            <img src="" id="avatarPreview" class="avatar-img" style="display:none;" alt="Profile Picture">
                        <?php endif; ?>
                        
                        <label for="avatarInput" class="avatar-upload-btn" title="Ubah Foto">
                            <i class="fa-solid fa-camera"></i>
                        </label>
                    </div>

                    <h2 class="profile-name"><?= htmlspecialchars($nama_lengkap); ?></h2>
                    <span class="profile-title">@<?= htmlspecialchars($username); ?> &middot; <?= ucfirst($role); ?></span>

                    <div class="profile-verified-badge">
                        <i class="fa-solid fa-circle-check"></i> Account Verified
                    </div>

                    <div class="progress-box">
                        <div class="progress-header">
                            <span>PROFILE PROGRESS</span>
                            <span><?= $progress; ?>%</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: <?= $progress; ?>%;"></div>
                        </div>
                    </div>

                    <div class="profile-meta-list">
                        <?php if ($role === 'siswa'): ?>
                            <div class="meta-item">
                                <span class="meta-label"><i class="fa-solid fa-id-card"></i> NIS</span>
                                <span class="meta-val"><?= htmlspecialchars($nis ?: '-'); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label"><i class="fa-solid fa-school"></i> Kelas</span>
                                <span class="meta-val"><?= htmlspecialchars($kelas ?: '-'); ?></span>
                            </div>
                        <?php elseif ($role === 'guru'): ?>
                            <div class="meta-item">
                                <span class="meta-label"><i class="fa-solid fa-id-card"></i> NIP</span>
                                <span class="meta-val"><?= htmlspecialchars($nip ?: '-'); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="meta-item">
                            <span class="meta-label"><i class="fa-solid fa-venus-mars"></i> Gender</span>
                            <span class="meta-val"><?= ($jenis_kelamin === 'L') ? 'Laki-laki' : 'Perempuan'; ?></span>
                        </div>

                        <div class="meta-item">
                            <span class="meta-label"><i class="fa-brands fa-github"></i> GitHub</span>
                            <span class="meta-val" style="color: #4d4e8d;">
                                <?= $gh_connected ? '@'.htmlspecialchars($gh_username) : 'Unlinked'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT CONTENT COLUMN -->
                <div class="right-content-wrapper">

                    <?php if (!empty($msg_text)): ?>
                        <div class="alert-box <?= ($msg_type === 'success') ? 'alert-success' : 'alert-error'; ?>">
                            <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                            <?= htmlspecialchars($msg_text); ?>
                        </div>
                    <?php endif; ?>

                    <div class="card-box">
                        <div class="form-section-title">
                            <i class="fa-solid fa-plug" style="color: #4f46e5;"></i> Connected Services & Integrations
                        </div>

                        <div class="github-integration-card">
                            <div class="gh-info-left">
                                <i class="github"></i>
                                <div class="gh-text">
                                    <h4>GitHub Account</h4>
                                    <?php if ($gh_connected): ?>
                                        <p>Terhubung sebagai <strong>@<?= htmlspecialchars($gh_username); ?></strong></p>
                                    <?php else: ?>
                                        <p>Hubungkan akun GitHub milikmu menggunakan OAuth 2.0 API resmi.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <?php if ($gh_connected): ?>
                                    <a href="github-disconnect.php" class="btn-gh-disconnect" onclick="return confirm('Apakah Anda yakin ingin melepas koneksi GitHub?');">
                                        Disconnect
                                    </a>
                                <?php else: ?>
                                    <a href="github-login.php" class="btn-gh-connect">
                                        <i class="connect"></i> Connect GitHub
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- FORM EDIT PROFILE -->
                    <div class="card-box">
                        <div class="form-section-title">
                            <i class="fa-solid fa-user-pen" style="color: #4f46e5;"></i> Personal Information
                        </div>

                        <form action="profile.php" method="POST">
                            <input type="file" id="avatarInput" accept="image/*" style="display:none;" onchange="convertBase64(event)">
                            <input type="hidden" name="avatar_base64" id="avatarBase64Input" value="<?= htmlspecialchars($foto_db); ?>">

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Username (Login)</label>
                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($username); ?>" required>
                                </div>

                                <?php if ($role === 'siswa' || $role === 'guru'): ?>
                                <div class="form-group">
                                    <label>Nama Lengkap</label>
                                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($nama_lengkap); ?>" required>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($role === 'siswa'): ?>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>NIS (Nomor Induk Siswa)</label>
                                    <input type="text" name="nis" class="form-control" value="<?= htmlspecialchars($nis); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Kelas</label>
                                    <input type="text" name="kelas" class="form-control" value="<?= htmlspecialchars($kelas); ?>" placeholder="Contoh: XI RPL 1" required>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($role === 'guru'): ?>
                            <div class="form-group">
                                <label>NIP (Nomor Induk Pegawai)</label>
                                <input type="text" name="nip" class="form-control" value="<?= htmlspecialchars($nip); ?>" required>
                            </div>
                            <?php endif; ?>

                            <?php if ($role === 'siswa' || $role === 'guru'): ?>
                            <div class="form-group">
                                <label>Jenis Kelamin</label>
                                <select name="jenis_kelamin" class="form-control">
                                    <option value="L" <?= ($jenis_kelamin === 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                    <option value="P" <?= ($jenis_kelamin === 'P') ? 'selected' : ''; ?>>Perempuan</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="form-section-title" style="margin-top: 24px;">
                                <i class="fa-solid fa-lock" style="color: #4f46e5;"></i> Security Settings
                            </div>

                            <div class="form-group">
                                <label>New Password <small style="color: #64748b; font-weight: 400;">(Kosongkan jika tidak ingin diubah)</small></label>
                                <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                            </div>

                            <div style="margin-top: 20px; text-align: right;">
                                <button type="submit" name="update_profile" class="save-btn">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Profile Changes
                                </button>
                            </div>
                        </form>
                    </div>

                </div>

            </div>
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

        function convertBase64(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const base64String = e.target.result;
                const imgPreview = document.getElementById('avatarPreview');
                const placeholder = document.getElementById('avatarPlaceholder');
                imgPreview.src = base64String;
                imgPreview.style.display = 'block';
                if (placeholder) placeholder.style.display = 'none';

                document.getElementById('avatarBase64Input').value = base64String;
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>