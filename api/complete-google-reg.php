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

// Titik sinar radial dekoratif (selaras rapor.css)
$gg_burst = [];
for ($bi = 0; $bi < 12; $bi++) {
    $ang = (2 * M_PI * $bi / 12) - M_PI / 2;
    $len = ($bi % 3 === 0) ? 44 : (($bi % 2 === 0) ? 34 : 24);
    $gg_burst[] = [
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
    <title>Complete Setup - Google SIPN</title>
    <link rel="stylesheet" href="<?= $base_url ?? ''; ?>/auth-edtech.css?v=1.2">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url ?? ''; ?>/icons/favicon.png">
</head>
<body>
<?php include __DIR__ . '/lenis_scroll.php'; ?>

<main class="au-oauth-shell">
    <!-- BAGIAN ATAS: PITA KREM IDENTITAS GOOGLE -->
    <section class="au-oauth-band">
        <div>
            <div class="au-oauth-eyebrow">
                <span class="au-pill"><i class="google" aria-hidden="true"></i> Google OAuth 2.0</span>
                <span class="au-pill"><span class="au-dot-live"></span> Tahap 2 dari 2</span>
            </div>
            <h1 class="au-oauth-title">Lengkapi <span class="au-dim">profil</span><br>siswa Google</h1>
            <p class="au-oauth-sub">Tautkan data NIS, rombel kelas, dan kata sandi cadangan untuk mengaktifkan akun SIPN.</p>
        </div>

        <div class="au-provider-card">
            <div class="au-provider-left">
                <?php if (!empty($temp['avatar'])): ?>
                    <img src="<?= htmlspecialchars($temp['avatar']); ?>" class="au-provider-ava" alt="Google Avatar" referrerpolicy="no-referrer">
                <?php else: ?>
                    <div class="au-provider-fallback">
                        <?= strtoupper(substr($temp['nama_lengkap'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div class="au-provider-meta">
                    <strong><?= htmlspecialchars($temp['nama_lengkap']); ?></strong>
                    <span><?= htmlspecialchars($temp['google_email']); ?></span>
                </div>
            </div>
            <a href="login.php" class="au-pill au-pill-line">Ganti akun</a>
        </div>
    </section>

    <!-- BAGIAN BAWAH: SIDEBAR RINGKASAN + FORM 2 KOLOM -->
    <section class="au-oauth-body">
        <aside class="au-oauth-aside">
            <div class="au-oauth-aside-head">
                <h2>Status aktivasi</h2>
                <p>Akun Google sudah terverifikasi. Isi formulir di samping untuk membuat profil siswa.</p>
            </div>

            <div class="au-oauth-tiles">
                <div class="au-tile is-dark">
                    <span class="au-tile-n">OAuth</span>
                    <span class="au-tile-l">Google terhubung</span>
                </div>
                <div class="au-tile">
                    <span class="au-tile-n">Siswa</span>
                    <span class="au-tile-l">Role otomatis</span>
                </div>
                <div class="au-tile is-burst">
                    <svg viewBox="0 0 100 100" role="img" aria-label="Diagram radial aktivasi akun Google">
                        <g stroke="#161616" stroke-width="1.4" stroke-linecap="round">
                            <?php foreach ($gg_burst as $bp): ?>
                                <line x1="<?= $bp[0]; ?>" y1="<?= $bp[1]; ?>" x2="<?= $bp[2]; ?>" y2="<?= $bp[3]; ?>"/>
                            <?php endforeach; ?>
                        </g>
                    </svg>
                </div>
            </div>

            <div class="au-oauth-note">
                <strong>Catatan keamanan:</strong> Password baru tetap diperlukan agar akun tetap bisa masuk secara manual selain lewat tombol Google.
            </div>
        </aside>

        <div class="au-oauth-form-wrap">
            <?php if (!empty($msg_error)): ?>
                <div class="auth-alert" role="alert"><?= htmlspecialchars($msg_error); ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form" id="googleRegForm">
                <?= csrf_field(); ?>

                <div class="au-section-tag" style="margin-top: 0;">
                    <span class="au-section-num">01</span>
                    <span>Kredensial & identitas siswa</span>
                </div>

                <div class="au-reg-grid">
                    <div class="au-field">
                        <label for="ggUser" class="au-label">Username SIPN</label>
                        <div class="input-group">
                            <input type="text" id="ggUser" name="username" class="auth-input" value="<?= htmlspecialchars($temp['username']); ?>" placeholder="Username" required autocomplete="off">
                        </div>
                    </div>

                    <div class="au-field">
                        <label for="ggPass" class="au-label">Buat Password</label>
                        <div class="input-group">
                            <input type="password" id="ggPass" name="password" class="auth-input" placeholder="Minimal 6 karakter" required>
                            <i class="fa-regular fa-eye-slash toggle-password" aria-label="Tampilkan password"></i>
                        </div>
                    </div>

                    <div class="au-field">
                        <label for="ggNis" class="au-label">NIS (Nomor Induk Siswa)</label>
                        <div class="input-group">
                            <input type="text" id="ggNis" name="nis" class="auth-input" placeholder="Nomor Induk Siswa" required>
                        </div>
                    </div>

                    <div class="au-field">
                        <label for="ggNama" class="au-label">Nama Lengkap</label>
                        <div class="input-group">
                            <input type="text" id="ggNama" name="nama" class="auth-input" value="<?= htmlspecialchars($temp['nama_lengkap']); ?>" placeholder="Nama lengkap" required>
                        </div>
                    </div>
                </div>

                <div class="au-section-tag">
                    <span class="au-section-num">02</span>
                    <span>Rombel kelas & gender</span>
                </div>

                <div class="au-reg-grid">
                    <div class="au-field">
                        <label for="tingkat_kelas" class="au-label">Tingkat Kelas</label>
                        <div class="input-group">
                            <select id="tingkat_kelas" class="auth-input select-custom" required onchange="tampilkanJurusan()">
                                <option value="" disabled selected>Pilih Tingkat Kelas</option>
                                <option value="X">Kelas X (Sepuluh)</option>
                                <option value="XI">Kelas XI (Sebelas)</option>
                                <option value="XII">Kelas XII (Dua Belas)</option>
                            </select>
                        </div>
                    </div>

                    <div class="au-field" id="jurusan_container">
                        <label for="jurusan" class="au-label">Kompetensi Keahlian</label>
                        <div class="input-group">
                            <select id="jurusan" class="auth-input select-custom" onchange="gabungKelas()">
                                <option value="" disabled selected>Pilih Jurusan</option>
                                <option value="RPL">Rekayasa Perangkat Lunak (RPL)</option>
                                <option value="MPLB">Manajemen Perkantoran (MPLB)</option>
                                <option value="AKL">Akuntansi (AKL)</option>
                                <option value="PM">Pemasaran (PM)</option>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" name="kelas_gabungan" id="kelas_gabungan" required>

                    <div class="au-field full">
                        <label for="ggGender" class="au-label">Jenis Kelamin</label>
                        <div class="input-group">
                            <select id="ggGender" name="jenis_kelamin" class="auth-input select-custom" required>
                                <option value="" disabled selected>Pilih Gender</option>
                                <option value="L">Laki-laki (Male)</option>
                                <option value="P">Perempuan (Female)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" name="submit_registration" class="submit-btn" onclick="return validasiKelas()">
                    <span>Selesaikan Pendaftaran</span>
                    <i class="sign" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </section>
</main>

<script>
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
        var kelasGabungan = document.getElementById("kelas_gabungan").value;
        if (kelasGabungan === "") {
            alert("Harap pilih Tingkat Kelas dan Jurusan secara lengkap!");
            return false;
        }
        return true;
    }

    document.querySelectorAll(".toggle-password").forEach(function (icon) {
        icon.addEventListener("click", function () {
            var input = this.previousElementSibling;
            if (!input) return;
            var isPass = input.type === "password";
            input.type = isPass ? "text" : "password";
            this.classList.toggle("fa-eye", isPass);
            this.classList.toggle("fa-eye-slash", !isPass);
        });
    });
</script>
</body>
</html>