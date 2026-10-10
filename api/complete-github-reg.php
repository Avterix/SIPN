<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include "koneksi.php";

if (!isset($_SESSION['temp_github'])) {
    header("Location: login.php");
    exit;
}

$temp = $_SESSION['temp_github'];
$msg_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_registration'])) {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $nis      = mysqli_real_escape_string($koneksi, trim($_POST['nis']));
    $nama     = mysqli_real_escape_string($koneksi, trim($_POST['nama']));
    $kelas    = mysqli_real_escape_string($koneksi, trim($_POST['kelas_gabungan']));
    $jk       = mysqli_real_escape_string($koneksi, trim($_POST['jenis_kelamin']));

    $gh_id     = $temp['github_id'];
    $gh_login  = $temp['github_username'];
    $gh_avatar = $temp['github_avatar'];
    $gh_email  = $temp['email'];

    // Cek username ketersediaan
    $q_chk = mysqli_query($koneksi, "SELECT id FROM users WHERE username = '$username'");
    if ($q_chk && mysqli_num_rows($q_chk) > 0) {
        $msg_error = "Username '$username' sudah dipakai, silakan pilih username lain.";
    } else {
        $random_pass = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);

        mysqli_begin_transaction($koneksi);
        try {
            // 1. Insert ke tabel users
            $sql_ins = "INSERT INTO users (username, email, password, role, foto, github_id, github_username, github_avatar, github_connected) 
                        VALUES ('$username', '$gh_email', '$random_pass', 'siswa', '$gh_avatar', '$gh_id', '$gh_login', '$gh_avatar', 1)";
            mysqli_query($koneksi, $sql_ins);
            $new_user_id = mysqli_insert_id($koneksi);

            // 2. Insert ke tabel siswa
            $sql_siswa = "INSERT INTO siswa (nis, nama, kelas, jenis_kelamin, user_id) 
                         VALUES ('$nis', '$nama', '$kelas', '$jk', '$new_user_id')";
            mysqli_query($koneksi, $sql_siswa);

            mysqli_commit($koneksi);

            unset($_SESSION['temp_github']);

            $_SESSION['user_id']  = $new_user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role']     = 'siswa';
            $_SESSION['foto']     = $gh_avatar;

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
    <link rel="stylesheet" href="<?php echo $base_url; ?>/auth-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    <style>
        .github-user-card {
            display: flex; align-items: center; gap: 14px;
            background-color: var(--card-bg, #14161b);
            border: 1px solid var(--input-border, #2a2e39);
            padding: 12px 16px; border-radius: 12px; margin-bottom: 18px;
        }
        .github-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #6366f1; }
        .github-user-info h4 { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 2px; }
        .github-user-info p { font-size: 12px; color: #9ca3af; }
        #jurusan_container { display: none; }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-form-side">
            <div class="auth-top-logo"><i class="fa-solid fa-shapes"></i></div>
            <div class="auth-header-text">
                <span class="auth-pill"><i class="fa-brands fa-github"></i> GitHub Student Registration</span>
                <h1>Complete Setup</h1>
                <p>Lengkapi data siswa untuk menyelesaikan pendaftaran</p>
            </div>

            <div class="github-user-card">
                <img src="<?= htmlspecialchars($temp['github_avatar']); ?>" class="github-avatar" alt="GitHub Avatar">
                <div class="github-user-info">
                    <h4><?= htmlspecialchars($temp['nama_lengkap']); ?> (@<?= htmlspecialchars($temp['github_username']); ?>)</h4>
                    <p><?= htmlspecialchars($temp['email'] ?: 'No public email'); ?></p>
                </div>
            </div>

            <?php if (!empty($msg_error)): ?>
                <div class="auth-alert"><?= htmlspecialchars($msg_error); ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form" id="githubRegForm">
                <div class="input-group">
                    <input type="text" name="username" class="auth-input" value="<?= htmlspecialchars($temp['github_username']); ?>" placeholder="Username" required autocomplete="off">
                </div>
                <div class="input-group">
                    <input type="text" name="nis" class="auth-input" placeholder="Student ID (NIS)" required>
                </div>
                <div class="input-group">
                    <input type="text" name="nama" class="auth-input" value="<?= htmlspecialchars($temp['nama_lengkap']); ?>" placeholder="Full Name" required>
                </div>

                <div class="input-group">
                    <select id="tingkat_kelas" class="auth-input select-custom" required onchange="tampilkanJurusan()">
                        <option value="" disabled selected>Pilih Tingkat Kelas</option>
                        <option value="X" style="background:#18181b; color:#fff;">Kelas X (Sepuluh)</option>
                        <option value="XI" style="background:#18181b; color:#fff;">Kelas XI (Sebelas)</option>
                        <option value="XII" style="background:#18181b; color:#fff;">Kelas XII (Dua Belas)</option>
                    </select>
                </div>

                <div class="input-group" id="jurusan_container">
                    <select id="jurusan" class="auth-input select-custom" onchange="gabungKelas()">
                        <option value="" disabled selected>Pilih Jurusan</option>
                        <option value="RPL" style="background:#18181b; color:#fff;">Rekayasa Perangkat Lunak (RPL)</option>
                        <option value="MPLB" style="background:#18181b; color:#fff;">Manajemen Perkantoran (MPLB)</option>
                        <option value="AKL" style="background:#18181b; color:#fff;">Akuntansi (AKL)</option>
                        <option value="PM" style="background:#18181b; color:#fff;">Pemasaran (PM)</option>
                    </select>
                </div>

                <input type="hidden" name="kelas_gabungan" id="kelas_gabungan" required>

                <div class="input-group">
                    <select name="jenis_kelamin" class="auth-input select-custom" required>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="L" style="background:#18181b; color:#fff;">Male (Laki-laki)</option>
                        <option value="P" style="background:#18181b; color:#fff;">Female (Perempuan)</option>
                    </select>
                </div>

                <button type="submit" name="submit_registration" class="submit-btn" onclick="return validasiKelas()">
                    <span>Complete Registration</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="auth-footer-link">
                Wrong account? <a href="login.php">Back to Login</a>
            </div>
        </div>

        <div class="auth-visual-side">
            <div class="bento-grid">
                <div class="bento-cell cell-image-1"></div>
                <div class="bento-cell cell-image-2"></div>
                <div class="bento-cell cell-accent-yellow">
                    <h3>Maximum Customization</h3>
                    <p>Tailor every aspect of your 3D object to your specifications.</p>
                    <div class="bento-icon-corner"><i class="fa-solid fa-cube"></i></div>
                </div>
                <div class="bento-cell cell-accent-green"></div>
                <div class="bento-cell cell-text-dark">
                    <h4>Fast Generation</h4>
                    <p>Create unique 3D objects in seconds.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function tampilkanJurusan() {
            var tingkat = document.getElementById("tingkat_kelas").value;
            var jurusanContainer = document.getElementById("jurusan_container");
            var jurusan = document.getElementById("jurusan");
            
            if (tingkat !== "") {
                jurusanContainer.style.display = "block";
                jurusan.required = true;
            } else {
                jurusanContainer.style.display = "none";
                jurusan.required = false;
            }
            jurusan.value = "";
            document.getElementById("kelas_gabungan").value = "";
        }

        function gabungKelas() {
            var tingkat = document.getElementById("tingkat_kelas").value;
            var jurusan = document.getElementById("jurusan").value;
            if (tingkat !== "" && jurusan !== "") {
                document.getElementById("kelas_gabungan").value = tingkat + " " + jurusan;
            }
        }

        function validasiKelas() {
            var kelasGabungan = document.getElementById("kelas_gabungan").value;
            if (kelasGabungan === "") {
                alert("Harap pilih Tingkat Kelas dan Jurusan secara lengkap!");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>