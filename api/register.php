<?php
ini_set('session.cookie_path', '/');
require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['user_id'])) {
    session_write_close();
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nis      = trim($_POST['nis'] ?? '');
    $nama     = trim($_POST['nama'] ?? '');
    $kelas    = trim($_POST['kelas_gabungan'] ?? '');
    $jk       = trim($_POST['jenis_kelamin'] ?? '');

    if ($username === '' || $nis === '' || $nama === '' || $kelas === '' || !in_array($jk, ['L', 'P'], true)) {
        $error = 'Semua data wajib diisi dengan benar.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Username harus 3 sampai 50 karakter.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        $st = $koneksi->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1');
        $st->bind_param('s', $username);
        $st->execute();
        $st->store_result();
        $taken = $st->num_rows > 0;
        $st->close();

        if ($taken) {
            $error = 'Username is already taken!';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            mysqli_begin_transaction($koneksi);
            try {
                $role = 'siswa';
                $st = $koneksi->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
                $st->bind_param('sss', $username, $hashed_password, $role);
                if (!$st->execute()) {
                    throw new RuntimeException($st->error);
                }
                $user_id = $koneksi->insert_id;
                $st->close();

                $st = $koneksi->prepare('INSERT INTO siswa (nis, nama, kelas, jenis_kelamin, user_id) VALUES (?, ?, ?, ?, ?)');
                $st->bind_param('ssssi', $nis, $nama, $kelas, $jk, $user_id);
                if (!$st->execute()) {
                    throw new RuntimeException($st->error);
                }
                $st->close();

                mysqli_commit($koneksi);
                $success = 'Registration successful! You can now login.';
            } catch (Throwable $e) {
                mysqli_rollback($koneksi);
                error_log('SIPN register gagal: ' . $e->getMessage());
                $error = 'Registration failed. Pastikan NIS belum pernah terdaftar.';
            }
        }
    }
}

// Titik sinar radial dekoratif (selaras rapor.css)
$reg_burst = [];
for ($bi = 0; $bi < 12; $bi++) {
    $ang = (2 * M_PI * $bi / 12) - M_PI / 2;
    $len = ($bi % 3 === 0) ? 44 : (($bi % 2 === 0) ? 34 : 24);
    $reg_burst[] = [
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
    <title>Sign Up - SIPN</title>
    <link rel="stylesheet" href="<?= $base_url ?? ''; ?>/auth-edtech.css?v=1.1">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url ?? ''; ?>/icons/favicon.png">
    <!-- Vercel Speed Insights -->
    <script>
      window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
    <!-- Vercel Web Analytics -->
    <script>
      window.va = window.va || function () { (window.vaq = window.vaq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/analytics/script.js"></script>
</head>
<body>
<?php include __DIR__ . '/lenis_scroll.php'; ?>

<main class="au-reg-shell">
    <!-- KIRI: PITA KREM RAIL & CAROUSEL -->
    <aside class="au-reg-cream-col">
        <div>
            <div class="au-brand-bar" style="margin-bottom: 14px;">
                <a href="login.php" class="au-brand-mark">
                    <span class="au-brand-badge">S</span>
                    <span>SIPN Core</span>
                </a>
                <a href="login.php" class="au-pill au-pill-dark">Sign In</a>
            </div>
            <h2 class="au-reg-hero-title">Aktivasi <span class="au-dim">akun</span><br>siswa baru</h2>
            <p class="au-reg-hero-sub">Lengkapi identitas NIS, tingkat kelas, dan kompetensi keahlian untuk mengakses rapor serta jadwal pelajaran.</p>
        </div>

        <!-- Carousel Showcase -->
        <div class="showcase">
            <div class="slide s2" data-title="Pengolahan Nilai Cepat" data-sub="Rekap data nilai dan jadwal pelajaran terintegrasi dalam satu sistem."></div>
            <div class="slide s1" data-title="Rapor Digital Terstruktur" data-sub="Pantau capaian pengetahuan, keterampilan, dan KKM secara transparan."></div>
            <div class="slide s4" data-title="Ringkasan Mudah Dibaca" data-sub="Visualisasi progres belajar dengan indikator predikat yang jelas."></div>
            <div class="slide s3" data-title="Data Akademik Terpusat" data-sub="Siswa, rombel kelas, dan guru pengampu tertata rapi."></div>

            <div class="show-top">
                <b>SMK SAKUCI</b>
            </div>
            <div class="dots" id="dots"></div>
            <div class="show-bottom">
                <div class="cap" id="cap">
                    <div class="avatar"><i class="status" aria-hidden="true"></i></div>
                    <div><strong id="capTitle"></strong><span id="capSub"></span></div>
                </div>
                <div class="arrows">
                    <button type="button" class="arrow" id="prev" aria-label="Slide sebelumnya"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="arrow" id="next" aria-label="Slide berikutnya"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <!-- 3 Kartu Tahapan -->
        <div class="au-reg-steps">
            <div class="au-step-card is-dark">
                <span class="au-tile-n">01</span>
                <span class="au-tile-l">Buat akun & kredensial login</span>
            </div>
            <div class="au-step-card">
                <span class="au-tile-n">02</span>
                <span class="au-tile-l">Tautkan NIS & rombel kelas</span>
            </div>
            <div class="au-step-card is-burst">
                <svg viewBox="0 0 100 100" role="img" aria-label="Diagram radial registrasi siswa">
                    <g stroke="#161616" stroke-width="1.4" stroke-linecap="round">
                        <?php foreach ($reg_burst as $bp): ?>
                            <line x1="<?= $bp[0]; ?>" y1="<?= $bp[1]; ?>" x2="<?= $bp[2]; ?>" y2="<?= $bp[3]; ?>"/>
                        <?php endforeach; ?>
                    </g>
                </svg>
            </div>
        </div>
    </aside>

    <!-- KANAN: PANEL FORM REGISTRASI 2 KOLOM -->
    <section class="au-reg-form-col">
        <div>
            <div class="au-reg-top">
                <div class="au-reg-heading">
                    <h1 class="reveal" style="--i:0">Buat <span class="au-dim">akun</span> baru</h1>
                    <p class="reveal" style="--i:1">Isi data personal dan akademik dengan benar untuk memulai.</p>
                </div>
                <span class="au-pill"><span class="au-dot-live"></span> Role Siswa</span>
            </div>

            <?php if (!empty($error)): ?>
                <div class="auth-alert" role="alert"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="auth-alert auth-success" role="status"><?= htmlspecialchars($success); ?> <a href="login.php">Login di sini</a></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form" id="registerForm">
                <?= csrf_field(); ?>

                <div class="au-section-tag reveal" style="--i:2">
                    <span class="au-section-num">01</span>
                    <span>Identitas akun & siswa</span>
                </div>

                <div class="au-reg-grid">
                    <div class="au-field reveal" style="--i:2">
                        <label for="regUser" class="au-label">Username</label>
                        <div class="input-group">
                            <input type="text" id="regUser" name="username" class="auth-input" placeholder="Username (3-50 karakter)" required autocomplete="off">
                        </div>
                    </div>

                    <div class="au-field reveal" style="--i:2">
                        <label for="regNis" class="au-label">NIS (Nomor Induk Siswa)</label>
                        <div class="input-group">
                            <input type="text" id="regNis" name="nis" class="auth-input" placeholder="Nomor Induk Siswa" required>
                        </div>
                    </div>

                    <div class="au-field full reveal" style="--i:3">
                        <label for="regNama" class="au-label">Nama Lengkap</label>
                        <div class="input-group">
                            <input type="text" id="regNama" name="nama" class="auth-input" placeholder="Nama lengkap sesuai data sekolah" required>
                        </div>
                    </div>
                </div>

                <div class="au-section-tag reveal" style="--i:4">
                    <span class="au-section-num">02</span>
                    <span>Kelas & keamanan</span>
                </div>

                <div class="au-reg-grid">
                    <div class="au-field reveal" style="--i:4">
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

                    <div class="au-field reveal" style="--i:4" id="jurusan_container">
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

                    <input type="hidden" name="kelas_gabungan" id="kelas_gabungan">

                    <div class="au-field reveal" style="--i:5">
                        <label for="regGender" class="au-label">Jenis Kelamin</label>
                        <div class="input-group">
                            <select id="regGender" name="jenis_kelamin" class="auth-input select-custom" required>
                                <option value="" disabled selected>Pilih Gender</option>
                                <option value="L">Laki-laki (Male)</option>
                                <option value="P">Perempuan (Female)</option>
                            </select>
                        </div>
                    </div>

                    <div class="au-field reveal" style="--i:5">
                        <label for="regPass" class="au-label">Password</label>
                        <div class="input-group">
                            <input type="password" id="regPass" name="password" class="auth-input" placeholder="Minimal 6 karakter" required>
                            <i class="fa-regular fa-eye-slash toggle-password" aria-label="Tampilkan password"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn reveal" style="--i:6" onclick="return validasiKelas()">
                    <span>Daftar Sekarang</span>
                    <i class="sign" aria-hidden="true"></i>
                </button>
            </form>

            <div class="auth-divider reveal" style="--i:7"><span>atau daftar cepat dengan</span></div>

            <div class="social-auth-grid reveal" style="--i:8">
                <a href="google-login.php" class="social-btn btn-google"><i class="google" aria-hidden="true"></i> Google</a>
                <a href="github-login.php" class="social-btn btn-github"><i class="github" aria-hidden="true"></i> GitHub</a>
            </div>
        </div>

        <div class="auth-footer-link reveal" style="--i:9">
            <span>Sudah punya akun SIPN?</span>
            <a href="login.php">Masuk di sini</a>
        </div>
    </section>
</main>

<script src="<?= $base_url ?? ''; ?>/auth.js?v=1.0"></script>
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
        if (document.getElementById("kelas_gabungan").value === "") {
            alert("Harap pilih Tingkat Kelas dan Jurusan secara lengkap!");
            return false;
        }
        return true;
    }
</script>
</body>
</html>