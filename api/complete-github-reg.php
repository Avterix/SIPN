<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . "/koneksi.php";

// Validasi apakah user datang dari alur callback GitHub yang sah
if (!isset($_SESSION['temp_github'])) {
    session_write_close();
    header("Location: login.php");
    exit;
}

$temp = $_SESSION['temp_github'];
$msg_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_registration'])) {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = trim($_POST['password']);
    $nis      = mysqli_real_escape_string($koneksi, trim($_POST['nis']));
    $nama     = mysqli_real_escape_string($koneksi, trim($_POST['nama']));
    $kelas    = mysqli_real_escape_string($koneksi, trim($_POST['kelas_gabungan']));
    $jk       = mysqli_real_escape_string($koneksi, trim($_POST['jenis_kelamin']));

    $gh_id       = $temp['github_id'];
    $gh_username = $temp['github_username'];
    $gh_email    = $temp['email'];
    $gh_avatar   = $temp['github_avatar'];

    // 1. Cek ketersediaan username & panjang password
    $q_chk = mysqli_query($koneksi, "SELECT id FROM users WHERE username = '$username'");
    if ($q_chk && mysqli_num_rows($q_chk) > 0) {
        $msg_error = "Username '$username' sudah dipakai, silakan pilih username lain.";
    } elseif (strlen($password) < 6) {
        $msg_error = "Password minimal 6 karakter!";
    } else {
        $hashed_pass = password_hash($password, PASSWORD_BCRYPT);

        // 2. Gunakan Transaction untuk konsistensi data 'users' dan 'siswa'
        mysqli_begin_transaction($koneksi);
        try {
            // Memasukkan data ke tabel users menggunakan kolom bernuansa GitHub
            $sql_ins = "INSERT INTO users (username, password, role, foto, github_id, github_username, github_connected) 
                        VALUES ('$username', '$hashed_pass', 'siswa', '$gh_avatar', '$gh_id', '$gh_username', 1)";
            mysqli_query($koneksi, $sql_ins);
            $new_user_id = mysqli_insert_id($koneksi);

            $sql_siswa = "INSERT INTO siswa (nis, nama, kelas, jenis_kelamin, user_id) 
                         VALUES ('$nis', '$nama', '$kelas', '$jk', '$new_user_id')";
            mysqli_query($koneksi, $sql_siswa);

            mysqli_commit($koneksi);

            // Bersihkan data temporary
            unset($_SESSION['temp_github']);

            // Set Session Login Lengkap
            $_SESSION['user_id']   = $new_user_id;
            $_SESSION['username']  = $username;
            $_SESSION['role']      = 'siswa';
            $_SESSION['foto']      = $gh_avatar;
            $_SESSION['login']     = true;
            $_SESSION['logged_in'] = true;

            $_SESSION['msg_type'] = "success";
            $_SESSION['msg_text'] = "Pendaftaran via GitHub berhasil! Selamat datang.";
            
            session_write_close();
            header("Location: dashboard.php");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $msg_error = "Gagal mendaftar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Setup GitHub - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url ?? ''; ?>/auth-style.css">
    <link href="https://googleapis.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cloudflare.com">
    <link rel="icon" type="image/png" href="<?= $base_url ?? ''; ?>/icons/favicon.png">
    
    <style>
        *, *::before, *::after, body, input, button, select, label, h1, h2, h3, h4, p, span, div {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .github-user-card {
            display: flex;
            align-items: center;
            gap: 14px;
            background-color: var(--card-bg, #14161b);
            border: 1px solid var(--input-border, #2a2e39);
            padding: 12px 16px;
            border-radius: var(--radius-sm, 12px);
            margin-bottom: 18px;
        }

        .github-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--input-focus, #6366f1);
            flex-shrink: 0;
        }

        .github-avatar-fallback {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: var(--input-focus, #6366f1);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .github-user-info h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary, #ffffff);
            margin-bottom: 2px;
        }

        .github-user-info p {
            font-size: 12px;
            color: var(--text-secondary, #9ca3af);
        }

        #jurusan_container {
            display: none;
        }
    </style>
</head>
<body>

    <div class="auth-wrapper">
        <!-- SISI KIRI: FORM REGISTRASI SISWA GITHUB -->
        <div class="auth-form-side">
            <div class="auth-top-logo">
                <i class="fa-solid fa-shapes"></i>
            </div>

            <div class="auth-header-text">
                <span class="auth-pill"><i class="fa-brands fa-github" style="color: #ffffff; margin-right: 4px;"></i> GitHub Student Account</span>
                <h1>Complete Setup</h1>
                <p>Enter your student details to complete registration</p>
            </div>

            <!-- PREVIEW PROFIL GITHUB -->
            <div class="github-user-card">
                <?php if (!empty($temp['github_avatar'])): ?>
                    <img src="<?= htmlspecialchars($temp['github_avatar']); ?>" class="github-avatar" alt="GitHub Avatar" referrerpolicy="no-referrer">
                <?php else: ?>
                    <div class="github-avatar-fallback">
                        <?= strtoupper(substr($temp['github_username'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="github-user-info">
                    <h4><?= htmlspecialchars($temp['nama_lengkap']); ?> (@<?= htmlspecialchars($temp['github_username']); ?>)</h4>
                    <p><?= !empty($temp['email']) ? htmlspecialchars($temp['email']) : 'No public email provided'; ?></p>
                </div>
            </div>

            <?php if (!empty($msg_error)): ?>
                <div class="auth-alert"><?= htmlspecialchars($msg_error); ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form" id="githubRegForm">
                
                <div class="input-group">
                    <input type="text" name="username" class="auth-input" value="<?= htmlspecialchars($temp['github_username']); ?>" placeholder="Username" required autocomplete="off">
                </div>

                <!-- INPUT PASSWORD -->
                <div class="input-group">
                    <input type="password" name="password" class="auth-input" placeholder="Create Password (min. 6 chars)" required>
                </div>

                <div class="input-group">
                    <input type="text" name="nis" class="auth-input" placeholder="Student ID (NIS)" required>
                </div>

                <div class="input-group">
                    <input type="text" name="nama" class="auth-input" value="<?= htmlspecialchars($temp['nama_lengkap']); ?>" placeholder="Full Name" required>
                </div>

                <!-- DROPDOWN TINGKAT KELAS -->
                <div class="input-group">
                    <select id="tingkat_kelas" class="auth-input select-custom" required onchange="tampilkanJurusan()">
                        <option value="" disabled selected>Pilih Tingkat Kelas</option>
                        <option value="X" style="background:#18181b; color:#fff;">Kelas X (Sepuluh)</option>
                        <option value="XI" style="background:#18181b; color:#fff;">Kelas XI (Sebelas)</option>
                        <option value="XII" style="background:#18181b; color:#fff;">Kelas XII (Dua Belas)</option>
                    </select>
                </div>

                <!-- DROPDOWN JURUSAN -->
                <div class="input-group" id="jurusan_container">
                    <select id="jurusan" class="auth-input select-custom" onchange="gabungKelas()">
                        <option value="" disabled selected>Pilih Jurusan</option>
                        <option value="RPL" style="background:#18181b; color:#fff;">Rekayasa Perangkat Lunak (RPL)</option>
                        <option value="MPLB" style="background:#18181b; color:#fff;">Manajemen Perkantoran (MPLB)</option>
                        <option value="AKL" style="background:#18181b; color:#fff;">Akuntansi (AKL)</option>
                        <option value="PM" style="background:#18181b; color:#fff;">Pemasaran (PM)</option>
                    </select>
                </div>

                <!-- INPUT HIDDEN KELAS GABUNGAN -->
                <input type="hidden" name="kelas_gabungan" id="kelas_gabungan" required>

                <!-- DROPDOWN JENIS KELAMIN -->
                <div class="input-group">
                    <select name="jenis_kelamin" class="auth-input select-custom" required>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="L" style="background:#18181b; color:#fff;">Male (Laki-laki)</option>
