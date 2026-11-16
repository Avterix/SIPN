<?php
ini_set('session.cookie_path', '/');
require_once __DIR__ . '/auth.php';

// Jika sudah login, langsung ke dashboard
if (!empty($_SESSION['user_id'])) {
    session_write_close();
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Query berparameter, kebal spasi & huruf besar/kecil
    $st = $koneksi->prepare('SELECT id, username, password, role, foto FROM users WHERE LOWER(TRIM(username)) = LOWER(?) LIMIT 1');
    $st->bind_param('s', $username);
    $st->execute();
    $st->bind_result($uid, $uname, $hash, $urole, $ufoto);

    if ($st->fetch() && password_verify($password, (string) $hash)) {
        $st->close();
        login_user($uid, $uname, $urole, $ufoto ?? '');
        session_write_close();
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Username atau password salah.';
}

// Titik sinar radial dekoratif (selaras rapor.css)
$au_burst = [];
for ($bi = 0; $bi < 12; $bi++) {
    $ang = (2 * M_PI * $bi / 12) - M_PI / 2;
    $len = ($bi % 3 === 0) ? 44 : (($bi % 2 === 0) ? 34 : 24);
    $au_burst[] = [
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
    <title>Sign In - SIPN</title>
    <link rel="stylesheet" href="<?= $base_url ?? ''; ?>/auth-edtech.css?v=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url ?? ''; ?>/icons/favicon.png">
<<<<<<< HEAD
    <!-- Vercel Speed Insights -->
    <script>
      window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
=======
    <style>
        .input-group {
            position: relative;
        }

        .du-eye-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--du-muted);
            padding: 4px 6px;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .du-eye-btn:hover {
            color: var(--du-ink);
        }

        .unpw {
            display: inline-block;
            width: 16px;
            height: 16px;
            background: url('<?= $base_url ?? ''; ?>/icons/master_data/daftar_user/eye.png') center/contain no-repeat;
            flex-shrink: 0;
        }

        .pw {
            display: inline-block;
            width: 16px;
            height: 16px;
            background: url('<?= $base_url ?? ''; ?>/icons/master_data/daftar_user/invisible.png') center/contain no-repeat;
            flex-shrink: 0;
        }
    </style>
>>>>>>> 96c83e4 (maintenance update)
</head>
<body>
<div class="au-backdrop">
    <video autoplay muted loop playsinline id="bg-video">
        <source src="<?= $base_url ?? ''; ?>/background/auth.mp4" type="video/mp4">
    </video>
</div>

<main class="au-login-shell">
    <!-- KIRI: PANEL FORM PUTIH -->
    <section class="au-login-form-col">
        <div class="au-brand-bar">
            <a href="login.php" class="au-brand-mark">
                <span class="au-brand-badge">S</span>
                <span>SIPN Core</span>
            </a>
            <span class="au-pill"><span class="au-dot-live"></span> Portal Aktif</span>
        </div>

        <div class="au-form-box">
            <h1 class="au-title reveal" style="--i:0">Masuk ke <span class="au-dim">portal</span><br>akademik</h1>
            <p class="au-subtitle reveal" style="--i:1">Gunakan akun siswa, guru, atau operator SMK Sangkuriang 1 Cimahi.</p>

            <?php if (!empty($error)): ?>
                <div class="auth-alert" role="alert"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form">
                <?= csrf_field(); ?>

                <div class="au-field reveal" style="--i:2">
                    <label for="loginUser" class="au-label">Username</label>
                    <div class="input-group">
                        <input type="text" id="loginUser" name="username" class="auth-input" placeholder="Masukkan username" required autocomplete="off">
                    </div>
                </div>

                <div class="au-field reveal" style="--i:3">
                    <label for="password" class="au-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="auth-input" placeholder="••••••••" style="padding-right: 38px;" required>
                        <button type="button" class="du-eye-btn" onclick="togglePassVisibility()" title="Tampilkan / Sembunyikan Password">
                            <i class="unpw" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit-btn reveal" style="--i:4">
                    <span>Sign In</span>
                    <i class="sign" aria-hidden="true"></i>
                </button>
            </form>

            <div class="auth-divider reveal" style="--i:5"><span>atau lanjutkan dengan</span></div>

            <div class="social-auth-grid reveal" style="--i:6">
                <a href="google-login.php" class="social-btn btn-google"><i class="google" aria-hidden="true"></i> Google</a>
                <a href="github-login.php" class="social-btn btn-github"><i class="github" aria-hidden="true"></i> GitHub</a>
            </div>
        </div>

        <div class="auth-footer-link reveal" style="--i:7">
            <span>Belum punya akun siswa?</span>
            <a href="register.php">Daftar sekarang</a>
        </div>
    </section>

    <!-- KANAN: PITA KREM SHOWCASE & RINGKASAN SISTEM -->
    <aside class="au-login-cream-col">
        <div class="au-cream-top">
            <span class="au-pill">SMK Sangkuriang 1 Cimahi</span>
            <a href="register.php" class="au-pill au-pill-dark">Buat Akun <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>

        <!-- Carousel Showcase -->
        <div class="showcase">
            <div class="slide s1" data-title="Rapor Digital Terstruktur" data-sub="Pantau capaian pengetahuan, keterampilan, dan KKM secara transparan."></div>
            <div class="slide s2" data-title="Pengolahan Nilai Cepat" data-sub="Rekap data nilai dan jadwal pelajaran terintegrasi dalam satu sistem."></div>
            <div class="slide s3" data-title="Data Akademik Terpusat" data-sub="Siswa, rombel kelas, dan guru pengampu tertata rapi."></div>
            <div class="slide s4" data-title="Ringkasan Mudah Dibaca" data-sub="Visualisasi progres belajar dengan indikator predikat yang jelas."></div>

            <div class="show-top">
                <b>SIPN 2026/2027</b>
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

        <!-- 3 Tile Ringkasan EdTech -->
        <div class="au-cream-tiles">
            <div class="au-tile is-dark">
                <span class="au-tile-n">100%</span>
                <span class="au-tile-l">Akses rapor & jadwal terintegrasi</span>
            </div>
            <div class="au-tile">
                <span class="au-tile-n">OAuth</span>
                <span class="au-tile-l">Dukungan masuk Google & GitHub</span>
            </div>
            <div class="au-tile is-burst">
                <svg viewBox="0 0 100 100" role="img" aria-label="Diagram radial sistem akademik">
                    <g stroke="#161616" stroke-width="1.4" stroke-linecap="round">
                        <?php foreach ($au_burst as $bp): ?>
                            <line x1="<?= $bp[0]; ?>" y1="<?= $bp[1]; ?>" x2="<?= $bp[2]; ?>" y2="<?= $bp[3]; ?>"/>
                        <?php endforeach; ?>
                    </g>
                </svg>
            </div>
        </div>
    </aside>
</main>

<script src="<?= $base_url ?? ''; ?>/auth.js?v=1.1"></script>
</body>
</html>