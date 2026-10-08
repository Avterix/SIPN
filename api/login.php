<?php
session_start();
include "koneksi.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Clean input & buang spasi di awal/akhir
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $username_clean = mysqli_real_escape_string($koneksi, $username);

    // 2. Query kebal spasi & kebal kapital/kecil (LOWER & TRIM)
    $query  = "SELECT * FROM users WHERE LOWER(TRIM(username)) = LOWER('$username_clean')";
    $result = mysqli_query($koneksi, $query);

    if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        
        // Cek password (hash atau plain)
        if (password_verify($password, $row['password']) || $password === $row['password']) {
            $_SESSION['user_id']  = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role']     = strtolower($row['role']);
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Username not found! (Input: '$username')";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - SIPN</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/auth-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.vercel-insights.com/v1/script.js"></script>
</head>
<body>

    <div class="auth-wrapper">
        <!-- SISI KIRI: FORM LOGIN -->
        <div class="auth-form-side">
            <div class="auth-top-logo">
                <i class="fa-solid fa-shapes"></i>
            </div>

            <div class="auth-header-text">
                <span class="auth-pill">Welcome back</span>
                <h1>Sign in to account</h1>
                <p>Enter your credentials to access your dashboard</p>
            </div>

            <div class="social-auth-grid">
                <button type="button" class="social-btn"><i class="fa-brands fa-google"></i></button>
                <button type="button" class="social-btn"><i class="fa-brands fa-github"></i></button>
            </div>

            <div class="auth-divider"><span>or</span></div>

            <?php if (!empty($error)): ?>
                <div class="auth-alert"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form">
                <div class="input-group">
                    <input type="text" name="username" class="auth-input" placeholder="Username" required autocomplete="off">
                </div>
                <div class="input-group">
                    <input type="password" name="password" id="passInput" class="auth-input" placeholder="Enter your password" required>
                    <i class="fa-regular fa-eye-slash toggle-password" id="togglePass" style="cursor:pointer;"></i>
                </div>

                <button type="submit" class="submit-btn">
                    <span>Sign In</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="auth-footer-link">
                Don't have an account? <a href="register.php">Sign up</a>
            </div>
        </div>

        <!-- SISI KANAN: GRID VISUAL -->
        <div class="auth-visual-side">
            <div class="bento-grid">
                <div class="bento-cell cell-image-1"></div>
                <div class="bento-cell cell-image-2"></div>
                <div class="bento-cell cell-accent-yellow">
                    <h3>Maximum Customization</h3>
                    <p>Tailor every aspect of your system design to your absolute specifications.</p>
                    <div class="bento-icon-corner"><i class="fa-solid fa-cube"></i></div>
                </div>
                <div class="bento-cell cell-accent-green"></div>
                <div class="bento-cell cell-text-dark">
                    <h4>Fast Generation</h4>
                    <p>Process academic reports and data entities in seconds.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Intip Password -->
    <script>
        const togglePass = document.getElementById('togglePass');
        const passInput = document.getElementById('passInput');

        if (togglePass && passInput) {
            togglePass.addEventListener('click', function () {
                const type = passInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
    </script>
</body>
</html>