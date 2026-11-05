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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - SIPN</title>
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
        <div class="slide s1" data-title="Maximum Customization" data-sub="Tailor every aspect of your system to your needs."></div>
        <div class="slide s2" data-title="Fast Generation" data-sub="Process academic reports and data in seconds."></div>
        <div class="slide s3" data-title="Everything in One Place" data-sub="Students, classes and records, neatly organized."></div>
        <div class="slide s4" data-title="Clear Reports" data-sub="Track results with simple, readable summaries."></div>

        <div class="show-top">
            <b>Selected Works</b>
            <a href="register.php" class="pill-btn">Sign Up</a>
        </div>
        <div class="dots" id="dots"></div>
        <div class="show-bottom">
            <div class="cap" id="cap">
                <div class="avatar"><i class="status"></i></div>
                <div><strong id="capTitle"></strong><span id="capSub"></span></div>
            </div>
            <div class="arrows">
                <button type="button" class="arrow" id="prev" aria-label="Previous"><</button>
                <button type="button" class="arrow" id="next" aria-label="Next">></button>
            </div>
        </div>
    </div>

    <!-- KANAN: FORM LOGIN -->
    <div class="panel">
        <div class="panel-top">
            <span class="brand">SIPN</span>
            <span class="pill">EN</span>
        </div>
        <div class="panel-body">
            <div class="panel-inner">
                <h1 class="reveal">Hi Student</h1>
                <p class="sub reveal">Welcome back to SIPN</p>

                <?php if (!empty($error)): ?>
                    <div class="auth-alert"><?= htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="" method="POST" class="auth-form">
                    <?= csrf_field(); ?>
                    <div class="input-group reveal">
                        <input type="text" name="username" class="auth-input" placeholder="Username" required autocomplete="off">
                    </div>
                    <div class="input-group reveal">
                        <input type="password" name="password" id="passInput" class="auth-input" placeholder="Password" required>
                        <i class="fa-regular fa-eye-slash toggle-password"></i>
                    </div>
                    <button type="submit" class="submit-btn reveal">
                        <span>Sign In</span>
                        <i class="sign"></i>
                    </button>
                </form>

                <div class="auth-divider reveal"><span>or</span></div>

                <div class="social-auth-grid reveal">
                    <a href="google-login.php" class="social-btn btn-google"><i class="google"></i> Google</a>
                    <a href="github-login.php" class="social-btn btn-github"><i class="github"></i> GitHub</a>
                </div>

                <div class="auth-footer-link reveal">
                    Don't have an account? <a href="register.php">Sign up</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= $base_url ?? ''; ?>/auth.js?v=1.0"></script>
</body>
</html>