<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

ini_set('session.cookie_path', '/');
require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['temp_google'])) {
    session_write_close();
    header('Location: login.php');
    exit;
}

$temp = $_SESSION['temp_google'];
$msg_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_registration'])) {
    csrf_require();
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $nis      = trim($_POST['nis'] ?? '');
    $nama     = trim($_POST['nama'] ?? '');
    $kelas    = trim($_POST['kelas_gabungan'] ?? '');
    $jk       = trim($_POST['jenis_kelamin'] ?? '');

    $g_id     = $temp['google_id'];
    $g_email  = $temp['google_email'];
    $g_avatar = $temp['avatar'];

    // 1. Validasi
    $st = $koneksi->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1');
    $st->bind_param('s', $username);
    $st->execute();
    $st->store_result();
    $username_dipakai = $st->num_rows > 0;
    $st->close();

    if ($username === '' || $nis === '' || $nama === '' || $kelas === '' || !in_array($jk, ['L', 'P'], true)) {
        $msg_error = 'Semua data wajib diisi dengan benar.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $msg_error = 'Username harus 3 sampai 50 karakter.';
    } elseif ($username_dipakai) {
        $msg_error = 'Username ini sudah dipakai, silakan pilih username lain.';
    } elseif (strlen($password) < 6) {
        $msg_error = 'Password minimal 6 karakter!';
    } else {
        $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
        $role = 'siswa';

        // 2. Transaksi: users + siswa
        mysqli_begin_transaction($koneksi);
        try {
            $st = $koneksi->prepare('INSERT INTO users (username, password, role, foto, google_id, google_email) VALUES (?, ?, ?, ?, ?, ?)');
            $st->bind_param('ssssss', $username, $hashed_pass, $role, $g_avatar, $g_id, $g_email);
            if (!$st->execute()) {
                throw new RuntimeException($st->error);
            }
            $new_user_id = $koneksi->insert_id;
            $st->close();

            $st = $koneksi->prepare('INSERT INTO siswa (nis, nama, kelas, jenis_kelamin, user_id) VALUES (?, ?, ?, ?, ?)');
            $st->bind_param('ssssi', $nis, $nama, $kelas, $jk, $new_user_id);
            if (!$st->execute()) {
                throw new RuntimeException($st->error);
            }
            $st->close();

            mysqli_commit($koneksi);

            unset($_SESSION['temp_google']);
            login_user($new_user_id, $username, 'siswa', $g_avatar);
            flash_redirect('dashboard.php', 'success', 'Pendaftaran via Google berhasil! Selamat datang.');
        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            error_log('SIPN complete-reg gagal: ' . $e->getMessage());
            $msg_error = 'Gagal mendaftar. Pastikan NIS belum pernah terdaftar.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Setup - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url ?? ''; ?>/auth-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url ?? ''; ?>/icons/favicon.png">
    
    <style>
        *, *::before, *::after, body, input, button, select, label, h1, h2, h3, h4, p, span, div {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .google-user-card {
            display: flex;
            align-items: center;
            gap: 14px;
            background-color: var(--card-bg, #14161b);
            border: 1px solid var(--input-border, #2a2e39);
            padding: 12px 16px;
            border-radius: var(--radius-sm, 12px);
            margin-bottom: 18px;
        }

        .google-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--input-focus, #6366f1);
            flex-shrink: 0;
        }

        .google-avatar-fallback {
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

        .google-user-info h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary, #ffffff);
            margin-bottom: 2px;
        }

        .google-user-info p {
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
        <!-- SISI KIRI: FORM REGISTRASI SISWA GOOGLE -->
        <div class="auth-form-side">
            <div class="auth-top-logo">
                <i class="fa-solid fa-shapes"></i>
            </div>

            <div class="auth-header-text">
                <span class="auth-pill"><i class="fa-brands fa-google" style="color: #ea4335; margin-right: 4px;"></i> Google Student Account</span>
                <h1>Complete Setup</h1>
                <p>Enter your student details to complete registration</p>
            </div>

            <!-- PREVIEW PROFIL GOOGLE -->
            <div class="google-user-card">
                <?php if (!empty($temp['avatar'])): ?>
                    <img src="<?= htmlspecialchars($temp['avatar']); ?>" class="google-avatar" alt="Google Avatar" referrerpolicy="no-referrer">
                <?php else: ?>
                    <div class="google-avatar-fallback">
                        <?= strtoupper(substr($temp['nama_lengkap'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="google-user-info">
                    <h4><?= htmlspecialchars($temp['nama_lengkap']); ?></h4>
                    <p><?= htmlspecialchars($temp['google_email']); ?></p>
                </div>
            </div>

            <?php if (!empty($msg_error)): ?>
                <div class="auth-alert"><?= htmlspecialchars($msg_error); ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form" id="googleRegForm">
                <?= csrf_field(); ?>
                
                <div class="input-group">
                    <input type="text" name="username" class="auth-input" value="<?= htmlspecialchars($temp['username']); ?>" placeholder="Username" required autocomplete="off">
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
                        <option value="P" style="background:#18181b; color:#fff;">Female (Perempuan)</option>
                    </select>
                </div>

                <button type="submit" name="submit_registration" class="submit-btn" onclick="return validasiKelas()">
                    <span>Complete Pendaftaran</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="auth-footer-link">
                Wrong account? <a href="login.php">Back to Sign In</a>
            </div>
        </div>

        <!-- SISI KANAN: BENTO GRID VISUAL -->
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

    <!-- JAVASCRIPT LOGIKA KELAS & JURUSAN -->
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