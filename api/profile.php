<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
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
    csrf_require();
    $new_username = trim($_POST['username'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $foto_ditolak = false;

    // Foto baru: hanya data URL gambar (png/jpeg/webp), maksimal sekitar 1 MB
    $av = $_POST['avatar_base64'] ?? '';
    if (strpos($av, 'data:') === 0 && $av !== $foto_db) {
        if (preg_match('#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#', $av) && strlen($av) <= 1500000) {
            $foto_db = $av;
        } else {
            $foto_ditolak = true;
        }
    }

    // Username tidak boleh dipakai user lain
    $st_dup = $koneksi->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id <> ? LIMIT 1');
    $st_dup->bind_param('si', $new_username, $user_id);
    $st_dup->execute();
    $st_dup->store_result();
    $username_dipakai = $st_dup->num_rows > 0;
    $st_dup->close();

    if (strlen($new_username) < 3 || strlen($new_username) > 50) {
        $msg_type = "error";
        $msg_text = "Username harus 3 sampai 50 karakter.";
    } elseif ($username_dipakai) {
        $msg_type = "error";
        $msg_text = "Username sudah dipakai user lain.";
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $msg_type = "error";
        $msg_text = "Password baru minimal 6 karakter.";
    } else {
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
            $_SESSION['foto']     = (strpos($foto_db, 'data:') === 0) ? '' : $foto_db;
            $username  = $new_username;
            $msg_type = "success";
            $msg_text = "Profil berhasil diperbarui!";
            if ($foto_ditolak) {
                $msg_text .= " (Foto tidak diubah: gunakan PNG/JPG/WebP maksimal sekitar 1 MB.)";
            }
        } else {
            error_log('SIPN profile update gagal: ' . $stmt_user->error);
            $msg_type = "error";
            $msg_text = "Gagal menyimpan profil. Coba lagi.";
        }
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

// Data visual untuk tile kelengkapan & radial burst SVG
$pf_checks = [
    ['label' => 'Sesi dasar', 'pts' => 30, 'ok' => true],
    ['label' => 'Foto profil', 'pts' => 20, 'ok' => !empty($avatar_src)],
    ['label' => ($role === 'guru' ? 'Data NIP' : 'Data NIS'), 'pts' => 20, 'ok' => (!empty($nis) || !empty($nip))],
    ['label' => 'Nama lengkap', 'pts' => 15, 'ok' => !empty($nama_lengkap)],
    ['label' => 'GitHub OAuth', 'pts' => 15, 'ok' => $gh_connected],
];
$pf_done_count = 0;
$pf_burst = [];
foreach ($pf_checks as $ci => $ck) {
    if ($ck['ok']) $pf_done_count++;
    $ang = (2 * M_PI * $ci / count($pf_checks)) - M_PI / 2;
    $len = $ck['ok'] ? 44 : 18;
    $pf_burst[] = [
        round(50 + 10 * cos($ang), 2), round(50 + 10 * sin($ang), 2),
        round(50 + $len * cos($ang), 2), round(50 + $len * sin($ang), 2),
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile - SIPN Core</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/profile.css?v=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
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

        <?php if ($role === 'admin'): ?>
        <li class="has-submenu" tabindex="0">
            <a href="javascript:void(0)"><i class="master"></i> <span class="menu-label">Master Data</span></a>
            <ul class="submenu">
                <li><a href="daftar_user.php"><span class="dot user"></span> <span class="menu-label">Kelola User</span></a></li>
                <li><a href="daftar_siswa.php"><span class="dot siswa"></span> <span class="menu-label">Kelola Siswa</span></a></li>
                <li><a href="daftar_guru.php"><span class="dot guru"></span> <span class="menu-label">Kelola Guru</span></a></li>
                <li><a href="daftar_mapel.php"><span class="dot mapel"></span> <span class="menu-label">Mata Pelajaran</span></a></li>
				<li><a href="manajemen_kelas.php"><span class="dot kelas"></span> <span class="menu-label">Kelola Kelas &amp; Plotting</span></a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($role === 'guru'): ?>
        <li><a href="daftar_nilai.php"><i class="fa-solid fa-pen-to-square"></i> <span class="menu-label">Kelola Nilai</span></a></li>
        <?php endif; ?>

        <?php if ($role === 'siswa'): ?>
        <li><a href="lihat_nilai.php"><i class="report"></i> <span class="menu-label">Report Grades</span></a></li>
        <?php endif; ?>

        <li><a href="profile.php" class="active"><i class="profile"></i> <span class="menu-label">Profile Settings</span></a></li>
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
                    <h1>User Profile</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search settings...">
                    </div>
                    <button class="icon-btn notification-btn"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/bell.webp"/></button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/settings.webp"/></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <!-- PROFILE CONTENT (ESTETIKA EDTECH, HEADER HORIZONTAL + WORKSPACE ASIMETRIS) -->
            <div class="pf-shell">

                <!-- 1. HEADER IDENTITAS HORIZONTAL (PITA KREM) -->
                <section class="pf-band">
                    <!-- Kiri: Avatar & Identitas Utama -->
                    <div class="pf-identity">
                        <div class="pf-ava-wrap">
                            <?php if (!empty($avatar_src)): ?>
                                <img src="<?= htmlspecialchars($avatar_src); ?>" id="avatarPreview" class="pf-ava-img" alt="Profile Picture" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <div id="avatarPlaceholder" class="pf-ava-fallback"><?= strtoupper(substr($username, 0, 1)); ?></div>
                                <img src="" id="avatarPreview" class="pf-ava-img" style="display:none;" alt="Profile Picture">
                            <?php endif; ?>

                            <label for="avatarInput" class="pf-ava-btn" title="Ubah Foto Profil">
                                <i class="fa-solid fa-camera" aria-hidden="true"></i>
                                <span class="pf-sr">Ubah foto profil</span>
                            </label>
                        </div>

                        <div>
                            <div class="pf-badges">
                                <span class="pf-pill"><span class="pf-dot-ok"></span> Terverifikasi</span>
                                <span class="pf-pill"><?= ucfirst(htmlspecialchars($role)); ?></span>
                            </div>
                            <h2 class="pf-h1">Profil <span class="pf-dim">pengguna</span><br><?= htmlspecialchars($nama_lengkap); ?></h2>
                            <p class="pf-handle">@<?= htmlspecialchars($username); ?> &middot; SMK Sangkuriang 1 Cimahi</p>
                        </div>
                    </div>

                    <!-- Tengah: Kelengkapan Profil -->
                    <div class="pf-progress-col">
                        <p class="pf-caption">Kelengkapan data profil</p>
                        <div class="pf-big-num">
                            <span aria-hidden="true"><?= (int)$progress; ?><small>%</small></span>
                            <span class="pf-sr"><?= (int)$progress; ?> persen lengkap</span>
                        </div>
                        <div class="pf-track" role="progressbar" aria-valuenow="<?= (int)$progress; ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="pf-track-fill" style="width: <?= (int)$progress; ?>%;"></div>
                        </div>
                    </div>

                    <!-- Kanan: Ringkasan Metadata -->
                    <div class="pf-meta-col">
                        <?php if ($role === 'siswa'): ?>
                            <div class="pf-meta-row">
                                <span>NIS</span>
                                <strong><?= htmlspecialchars($nis ?: '–'); ?></strong>
                            </div>
                            <div class="pf-meta-row">
                                <span>Kelas</span>
                                <strong><?= htmlspecialchars($kelas ?: '–'); ?></strong>
                            </div>
                        <?php elseif ($role === 'guru'): ?>
                            <div class="pf-meta-row">
                                <span>NIP</span>
                                <strong><?= htmlspecialchars($nip ?: '–'); ?></strong>
                            </div>
                        <?php endif; ?>

                        <div class="pf-meta-row">
                            <span>Jenis kelamin</span>
                            <strong><?= ($jenis_kelamin === 'L') ? 'Laki-laki' : 'Perempuan'; ?></strong>
                        </div>
                        <div class="pf-meta-row">
                            <span>GitHub</span>
                            <strong><?= $gh_connected ? '@' . htmlspecialchars($gh_username) : 'Belum tertaut'; ?></strong>
                        </div>
                    </div>
                </section>

                <!-- ALERT NOTIFIKASI -->
                <?php if (!empty($msg_text)): ?>
                    <div class="pf-alert <?= ($msg_type === 'success') ? 'is-success' : 'is-error'; ?>" role="status">
                        <div class="pf-alert-inner">
                            <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>" aria-hidden="true"></i>
                            <span><?= htmlspecialchars($msg_text); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 2. WORKSPACE BAWAH ASIMETRIS (FORM KIRI + MODUL KANAN) -->
                <div class="pf-workspace">

                    <!-- KOLOM KIRI: FORMULIR INFORMASI & KEAMANAN -->
                    <section class="pf-card">
                        <div class="pf-head">
                            <div>
                                <h3 class="pf-title">Pengaturan akun & identitas</h3>
                                <p class="pf-sub">Klik ikon kamera di foto profil untuk mengganti avatar (PNG/JPG/WebP, maks 1 MB).</p>
                            </div>
                            <span class="pf-pill">ID #<?= (int)$user_id; ?></span>
                        </div>

                        <form action="profile.php" method="POST">
                            <?= csrf_field(); ?>
                            <input type="file" id="avatarInput" accept="image/*" style="display:none;" onchange="convertBase64(event)">
                            <input type="hidden" name="avatar_base64" id="avatarBase64Input" value="<?= htmlspecialchars($foto_db); ?>">

                            <div class="pf-step-label is-first">
                                <span class="pf-step-num">01</span>
                                <span>Informasi personal</span>
                            </div>

                            <div class="pf-grid-2">
                                <div class="pf-field">
                                    <label for="pfUsername">Username (Login)</label>
                                    <input type="text" id="pfUsername" name="username" class="pf-input" value="<?= htmlspecialchars($username); ?>" required>
                                </div>

                                <?php if ($role === 'siswa' || $role === 'guru'): ?>
                                <div class="pf-field">
                                    <label for="pfNama">Nama Lengkap</label>
                                    <input type="text" id="pfNama" name="nama" class="pf-input" value="<?= htmlspecialchars($nama_lengkap); ?>" required>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($role === 'siswa'): ?>
                            <div class="pf-grid-2">
                                <div class="pf-field">
                                    <label for="pfNis">NIS (Nomor Induk Siswa)</label>
                                    <input type="text" id="pfNis" name="nis" class="pf-input" value="<?= htmlspecialchars($nis); ?>" required>
                                </div>
                                <div class="pf-field">
                                    <label for="pfKelas">Kelas</label>
                                    <input type="text" id="pfKelas" name="kelas" class="pf-input" value="<?= htmlspecialchars($kelas); ?>" placeholder="Contoh: XI RPL 1" required>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($role === 'guru'): ?>
                            <div class="pf-field">
                                <label for="pfNip">NIP (Nomor Induk Pegawai)</label>
                                <input type="text" id="pfNip" name="nip" class="pf-input" value="<?= htmlspecialchars($nip); ?>" required>
                            </div>
                            <?php endif; ?>

                            <?php if ($role === 'siswa' || $role === 'guru'): ?>
                            <div class="pf-field">
                                <label for="pfGender">Jenis Kelamin</label>
                                <select id="pfGender" name="jenis_kelamin" class="pf-input">
                                    <option value="L" <?= ($jenis_kelamin === 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                    <option value="P" <?= ($jenis_kelamin === 'P') ? 'selected' : ''; ?>>Perempuan</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="pf-step-label">
                                <span class="pf-step-num">02</span>
                                <span>Keamanan kata sandi</span>
                            </div>

                            <div class="pf-field">
                                <label for="pfPass">Password Baru <small>(Kosongkan jika tidak ingin diubah, minimal 6 karakter)</small></label>
                                <div class="pf-pass-wrap">
                                    <input type="password" id="pfPass" name="new_password" class="pf-input" placeholder="••••••••">
                                    <button type="button" class="pf-pass-toggle" id="pfPassToggle" aria-label="Tampilkan password">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="pf-form-foot">
                                <span class="pf-sub">Perubahan langsung diterapkan pada sesi aktif.</span>
                                <button type="submit" name="update_profile" class="pf-save-btn">
                                    <i class="fa-solid fa-check" aria-hidden="true"></i> Simpan perubahan
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- KOLOM KANAN: INTEGRASI GITHUB + TILE RINGKASAN -->
                    <aside class="pf-side-stack">

                        <!-- Modul 1: Integrasi GitHub -->
                        <div class="pf-card">
                            <div class="pf-head">
                                <div>
                                    <h3 class="pf-title">Integrasi layanan</h3>
                                    <p class="pf-sub">Autentikasi & sinkronisasi avatar OAuth 2.0.</p>
                                </div>
                                <span class="pf-pill"><?= $gh_connected ? 'Aktif' : 'Nonaktif'; ?></span>
                            </div>

                            <div class="pf-gh-box">
                                <div class="pf-gh-left">
                                    <div class="pf-gh-ico"><img src="<?= $base_url; ?>/icons/profile/github.png" aria-hidden="true"></img></div>
                                    <div class="pf-gh-text">
                                        <strong>GitHub Account</strong>
                                        <?php if ($gh_connected): ?>
                                            <span>Terhubung sebagai <b>@<?= htmlspecialchars($gh_username); ?></b></span>
                                        <?php else: ?>
                                            <span>Belum terhubung ke akun GitHub</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div>
                                    <?php if ($gh_connected): ?>
                                        <a href="github-disconnect.php?t=<?= csrf_token(); ?>" class="pf-pill pf-pill-danger" onclick="return confirm('Apakah Anda yakin ingin melepas koneksi GitHub?');">
                                            Disconnect
                                        </a>
                                    <?php else: ?>
                                        <a href="github-login.php" class="pf-pill pf-pill-dark">
                                            Connect
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="pf-gh-foot">
                                <span class="pf-sub">OAuth 2.0 resmi GitHub</span>
                                <a href="dashboard.php" class="pf-pill pf-pill-line">Kembali ke dashboard</a>
                            </div>
                        </div>

                        <!-- Modul 2: Grid Tile Kelengkapan + Diagram Radial -->
                        <div class="pf-card">
                            <div class="pf-head">
                                <div>
                                    <h3 class="pf-title">Rincian kelengkapan</h3>
                                    <p class="pf-sub"><?= $pf_done_count; ?> dari <?= count($pf_checks); ?> indikator terpenuhi.</p>
                                </div>
                            </div>

                            <div class="pf-tiles">
                                <?php foreach ($pf_checks as $ck): ?>
                                    <div class="pf-tile<?= $ck['ok'] ? ' is-dark' : ''; ?>">
                                        <span class="pf-tile-n"><?= $ck['ok'] ? '+' . $ck['pts'] . '%' : '0%'; ?></span>
                                        <span class="pf-tile-l"><?= htmlspecialchars($ck['label']); ?></span>
                                    </div>
                                <?php endforeach; ?>

                                <div class="pf-tile is-burst">
                                    <svg viewBox="0 0 100 100" role="img" aria-label="Diagram radial kelengkapan profil">
                                        <g stroke="#161616" stroke-width="1.4" stroke-linecap="round">
                                            <?php foreach ($pf_burst as $bp): ?>
                                                <line x1="<?= $bp[0]; ?>" y1="<?= $bp[1]; ?>" x2="<?= $bp[2]; ?>" y2="<?= $bp[3]; ?>"/>
                                            <?php endforeach; ?>
                                        </g>
                                    </svg>
                                </div>
                            </div>
                        </div>

                    </aside>

                </div>

            </div>
        </main>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const menuLinks = document.querySelectorAll(".sidebar-menu a");
            menuLinks.forEach(link => {
                link.addEventListener("click", function(e) {
                    if (this.parentElement.classList.contains("has-submenu")) {
                        e.preventDefault();
                        const submenu = this.nextElementSibling;
                        submenu.style.display = submenu.style.display === "flex" ? "none" : "flex";
                        return;
                    }
                });
            });

            const passInput = document.getElementById("pfPass");
            const passToggle = document.getElementById("pfPassToggle");
            if (passInput && passToggle) {
                passToggle.addEventListener("click", () => {
                    const isHidden = passInput.type === "password";
                    passInput.type = isHidden ? "text" : "password";
                    passToggle.innerHTML = isHidden
                        ? '<i class="fa-regular fa-eye-slash"></i>'
                        : '<i class="fa-regular fa-eye"></i>';
                });
            }
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