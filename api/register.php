<?php
ini_set('session.cookie_path', '/');
session_start();
include "koneksi.php";

if (isset($_SESSION['user_id'])) {
    session_write_close();
    header("Location: dashboard.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = $_POST['password'];
    $nis = mysqli_real_escape_string($koneksi, $_POST['nis']);
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $kelas = mysqli_real_escape_string($koneksi, $_POST['kelas_gabungan']);
    $jk = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);

    $cek_user = mysqli_query($koneksi, "SELECT id FROM users WHERE username='$username'");

    if (mysqli_num_rows($cek_user) > 0) {
        $error = "Username is already taken!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        mysqli_begin_transaction($koneksi);
        try {
            $query_user = "INSERT INTO users (username, password, role) VALUES ('$username', '$hashed_password', 'siswa')";
            mysqli_query($koneksi, $query_user);
            $user_id = mysqli_insert_id($koneksi);

            $query_siswa = "INSERT INTO siswa (nis, nama, kelas, jenis_kelamin, user_id) VALUES ('$nis', '$nama', '$kelas', '$jk', '$user_id')";
            mysqli_query($koneksi, $query_siswa);

            mysqli_commit($koneksi);
            $success = "Registration successful! You can now login.";
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - SIPN</title>
    <link rel="stylesheet" href="<?= $base_url ?? ''; ?>/auth-style3.css?v=1.1">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url ?? ''; ?>/icons/favicon.png">
</head>
<body>
<div class="bg-split">
    <video autoplay muted playsinline id="bg-video">
        <source src="<?= $base_url ?? ''; ?>/background/auth.mp4" type="video/mp4">
    </video>
</div>

<div class="auth-card">
    <!-- KIRI: SHOWCASE CAROUSEL -->
    <div class="showcase">
        <div class="slide s2" data-title="Fast Generation" data-sub="Process academic reports and data in seconds."></div>
        <div class="slide s1" data-title="Maximum Customization" data-sub="Tailor every aspect of your system to your needs."></div>
        <div class="slide s4" data-title="Clear Reports" data-sub="Track results with simple, readable summaries."></div>
        <div class="slide s3" data-title="Everything in One Place" data-sub="Students, classes and records, neatly organized."></div>

        <div class="show-top">
            <b>Join SIPN</b>
            <a href="login.php" class="pill-btn">Sign In</a>
        </div>
        <div class="dots" id="dots"></div>
        <div class="show-bottom">
            <div class="cap" id="cap">
                <div class="avatar"><i class="status"></i></div>
                <div><strong id="capTitle"></strong><span id="capSub"></span></div>
            </div>
            <div class="arrows">
                <button type="button" class="arrow" id="prev" aria-label="Previous"><i class="fa-solid fa-arrow-left"></i></button>
                <button type="button" class="arrow" id="next" aria-label="Next"><i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>
    </div>

    <!-- KANAN: FORM REGISTER -->
    <div class="panel">
        <div class="panel-top">
            <span class="brand">SIPN</span>
            <span class="pill">EN</span>
        </div>
        <div class="panel-body">
            <div class="panel-inner">
                <h1 class="sm reveal">Create account</h1>
                <p class="sub reveal">Enter your personal data to get started</p>

                <?php if (!empty($error)): ?>
                    <div class="auth-alert"><?= htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="auth-alert auth-success"><?= htmlspecialchars($success); ?> <a href="login.php">Login here</a></div>
                <?php endif; ?>

                <form action="" method="POST" class="auth-form" id="registerForm">
                    <div class="form-grid">
                        <div class="input-group reveal">
                            <input type="text" name="username" class="auth-input" placeholder="Username" required autocomplete="off">
                        </div>
                        <div class="input-group reveal">
                            <input type="text" name="nis" class="auth-input" placeholder="Student ID (NIS)" required>
                        </div>
                        <div class="input-group full reveal">
                            <input type="text" name="nama" class="auth-input" placeholder="Full Name" required>
                        </div>

                        <div class="input-group reveal">
                            <select id="tingkat_kelas" class="auth-input select-custom" required onchange="tampilkanJurusan()">
                                <option value="" disabled selected>Pilih Tingkat Kelas</option>
                                <option value="X">Kelas X (Sepuluh)</option>
                                <option value="XI">Kelas XI (Sebelas)</option>
                                <option value="XII">Kelas XII (Dua Belas)</option>
                            </select>
                        </div>
			<div class="id="jurusan_container">
                        <div class="input-group reveal">
                            <select id="jurusan" class="auth-input select-custom" onchange="gabungKelas()">
                                <option value="" disabled selected>Pilih Jurusan</option>
                                <option value="RPL">Rekayasa Perangkat Lunak (RPL)</option>
                                <option value="MPLB">Manajemen Perkantoran (MPLB)</option>
                                <option value="AKL">Akuntansi (AKL)</option>
                                <option value="PM">Pemasaran (PM)</option>
                            </select>
                        </div>
			</div>

                        <input type="hidden" name="kelas_gabungan" id="kelas_gabungan">

                        <div class="input-group reveal">
                            <select name="jenis_kelamin" class="auth-input select-custom" required>
                                <option value="" disabled selected>Select Gender</option>
                                <option value="L">Male (Laki-laki)</option>
                                <option value="P">Female (Perempuan)</option>
                            </select>
                        </div>
                        <div class="input-group reveal">
                            <input type="password" name="password" class="auth-input" placeholder="Password" required>
                            <i class="fa-regular fa-eye-slash toggle-password"></i>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn reveal" onclick="return validasiKelas()">
                        <span>Sign up</span>
                        <i class="sign"></i>
                    </button>
                </form>

                <div class="auth-divider reveal"><span>or</span></div>

                <div class="social-auth-grid reveal">
                    <a href="google-login.php" class="social-btn btn-google"><i class="google"></i> Google</a>
                    <a href="github-login.php" class="social-btn btn-github"><i class="github"></i> GitHub</a>
                </div>

                <div class="auth-footer-link reveal">
                    Already have an account? <a href="login.php">Log in</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base_url ?? ''; ?>/auth.js?v=1.0"></script>
<script>
    // LOGIC KELAS & JURUSAN (sama seperti sebelumnya)
    function tampilkanJurusan() {
        var tingkat = document.getElementById("tingkat_kelas").value;
        var container = document.getElementById("jurusan_container");
        var jurusan = document.getElementById("jurusan");

        container.classList.toggle("show", tingkat !== "");
        jurusan.required = (tingkat !== "");

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
        if (document.getElementById("kelas_gabungan").value === "") {
            alert("Harap pilih Tingkat Kelas dan Jurusan secara lengkap!");
            return false;
        }
        return true;
    }
</script>
</body>
</html>