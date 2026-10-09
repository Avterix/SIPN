<?php
session_start();
include "koneksi.php";

if (isset($_SESSION['user_id'])) {
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
    
    // MENERIMA DATA KELAS GABUNGAN DARI JAVASCRIPT
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
    <!-- Memakai file CSS terpisah khusus Auth -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/auth-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">  
  <style>
        /* CSS tambahan sedikit biar tampilan dropdown jurusan bisa disembunyikan/ditampilkan secara dinamis */
        #jurusan_container {
            display: none; /* Sembunyikan jurusan awalnya */
        }
    </style>
</head>
<body>

    <div class="auth-wrapper">
        <!-- SISI KIRI: FORM REGISTER -->
        <div class="auth-form-side">
            <div class="auth-top-logo">
                <i class="fa-solid fa-shapes"></i>
            </div>

            <div class="auth-header-text">
                <span class="auth-pill">Create your unique design</span>
                <h1>Sign up account</h1>
                <p>Enter your personal data to create your account</p>
            </div>

            <div class="social-auth-grid">
                <button type="button" class="social-btn"><i class="fa-brands fa-google"></i></button>
                <button type="button" class="social-btn"><i class="fa-brands fa-github"></i></button>
            </div>

            <div class="auth-divider"><span>or</span></div>

            <?php if (!empty($error)): ?>
                <div class="auth-alert"><?= $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="auth-alert auth-success"><?= $success; ?> <a href="login.php" style="color:#fff; text-decoration:underline;">Login here</a></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form" id="registerForm">
                <div class="input-group">
                    <input type="text" name="username" class="auth-input" placeholder="Username" required autocomplete="off">
                </div>
                <div class="input-group">
                    <input type="text" name="nis" class="auth-input" placeholder="Student ID (NIS)" required>
                </div>
                <div class="input-group">
                    <input type="text" name="nama" class="auth-input" placeholder="Full Name" required>
                </div>
                
                <!-- DROP DOWN TINGKAT KELAS -->
                <div class="input-group">
                    <select id="tingkat_kelas" class="auth-input select-custom" required onchange="tampilkanJurusan()">
                        <option value="" disabled selected>Pilih Tingkat Kelas</option>
                        <option value="X" style="background:#18181b; color:#fff;">Kelas X (Sepuluh)</option>
                        <option value="XI" style="background:#18181b; color:#fff;">Kelas XI (Sebelas)</option>
                        <option value="XII" style="background:#18181b; color:#fff;">Kelas XII (Dua Belas)</option>
                    </select>
                </div>

                <!-- DROP DOWN JURUSAN (Muncul setelah tingkat kelas dipilih) -->
                <div class="input-group" id="jurusan_container">
                    <select id="jurusan" class="auth-input select-custom" onchange="gabungKelas()">
                        <option value="" disabled selected>Pilih Jurusan</option>
                        <option value="RPL" style="background:#18181b; color:#fff;">Rekayasa Perangkat Lunak (RPL)</option>
                        <option value="MPLB" style="background:#18181b; color:#fff;">Manajemen Perkantoran (MPLB)</option>
                        <option value="AKL" style="background:#18181b; color:#fff;">Akuntansi (AKL)</option>
                        <option value="PM" style="background:#18181b; color:#fff;">Pemasaran (PM)</option>
                    </select>
                </div>

                <!-- INPUT HIDDEN: Menyimpan gabungan Kelas & Jurusan buat dikirim ke PHP -->
                <input type="hidden" name="kelas_gabungan" id="kelas_gabungan" required>

                <div class="input-group">
                    <select name="jenis_kelamin" class="auth-input select-custom" required>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="L" style="background:#18181b; color:#fff;">Male (Laki-laki)</option>
                        <option value="P" style="background:#18181b; color:#fff;">Female (Perempuan)</option>
                    </select>
                </div>
                <div class="input-group">
                    <input type="password" name="password" class="auth-input" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="submit-btn" onclick="return validasiKelas()">
                    <span>Sign up</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="auth-footer-link">
                Already have an account? <a href="login.php">Log in</a>
            </div>
        </div>

        <!-- SISI KANAN: GRID VISUAL ESTETIK -->
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

    <!-- JAVASCRIPT UNTUK LOGIKA KELAS & JURUSAN -->
    <script>
        function tampilkanJurusan() {
            var tingkat = document.getElementById("tingkat_kelas").value;
            var jurusanContainer = document.getElementById("jurusan_container");
            var jurusan = document.getElementById("jurusan");
            
            // Tampilkan dropdown jurusan kalau tingkat kelas udah dipilih
            if (tingkat !== "") {
                jurusanContainer.style.display = "block";
                jurusan.required = true;
            } else {
                jurusanContainer.style.display = "none";
                jurusan.required = false;
            }
            
            // Reset jurusan & kelas gabungan tiap kali tingkat kelas diganti
            jurusan.value = "";
            document.getElementById("kelas_gabungan").value = "";
        }

        function gabungKelas() {
            var tingkat = document.getElementById("tingkat_kelas").value;
            var jurusan = document.getElementById("jurusan").value;
            
            // Gabung jadi format "X RPL", "XI MPLB", dll
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