<?php
require 'navbar.php';
require_once 'koneksi.php';

if (!isset($_GET['id'])) {
    header("Location: daftar_user.php");
    exit;
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

$query_user = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$id'");
$user = mysqli_fetch_assoc($query_user);

if (!$user) {
    echo "<script>alert('User tidak ditemukan!'); window.location.href='daftar_user.php';</script>";
    exit;
}

$profil = [];
if ($user['role'] == 'siswa') {
    $q = mysqli_query($koneksi, "SELECT * FROM siswa WHERE user_id = '$id'");
    $profil = mysqli_fetch_assoc($q) ?? [];
} elseif ($user['role'] == 'guru') {
    $q = mysqli_query($koneksi, "SELECT * FROM guru WHERE user_id = '$id'");
    $profil = mysqli_fetch_assoc($q) ?? [];
?>
<head>
    <link rel="stylesheet" href="style1.css">
    <script>
        window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
</head>

<div class="form2" style="margin: 0 auto; width: 40%;">
    <div class="container text-center" style="margin: 0 auto;">
    <div class="form">
    <div class="card" style="margin-top: 20px;">
       <div class="card-form">
                    <h5>Form Edit User (<?= ucfirst($user['role']); ?>)</h5><br>
                </div>
                
            <form action="aksi_edit_user.php" method="POST">

                    <input type="hidden" name="id" value="<?= $user['id']; ?>">
                    <input type="hidden" name="role" value="<?= $user['role']; ?>">
                    
                    <div class="form2" style="border: 1px solid #d3d3d3; padding: 15px;">
                        <h6>Kredensial Akun</h6><br>
                        <label style="position:relative; left:-180px;">Username:</label>
                        <div class="mb-3">
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']); ?>" placeholder="Username Akun" required style="width: 90%; margin: 0 auto;" />
                        </div>
                        <br>
                        <label style="position:relative; left:-180px;">Password:</label>
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" placeholder="Password Baru (Kosongkan jika tidak diubah)" style="width: 90%; margin: 0 auto;" />
                        </div>
                        <br>


                        <?php if ($user['role'] == 'siswa' || $user['role'] == 'guru'): ?>
			<label style="position:relative; left:-160px;">Nama Lengkap:</label>
                        <div class="mb-3">
                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($profil['nama'] ?? ''); ?>" placeholder="Nama Lengkap" required style="width: 90%; margin: 0 auto;" />
                            <br>
                            <div class="radio" style="width: 90%; margin: 0 auto; text-align: left;">
                                <label>Jenis Kelamin:</label><br>
                                <input type="radio" name="jenis_kelamin" value="L" <?= (($profil['jenis_kelamin'] ?? '') == 'L' || ($profil['jenis_kelamin'] ?? '') == 'L') ? 'checked' : ''; ?> required /> L &nbsp;
                                <input type="radio" name="jenis_kelamin" value="P" <?= (($profil['jenis_kelamin'] ?? '') == 'P' || ($profil['jenis_kelamin'] ?? '') == 'P') ? 'checked' : ''; ?> required /> P
                            </div>
                            <br>
                        </div>
                        <?php endif; ?>

                        <?php if ($user['role'] == 'guru'): ?>
                        <div class="mb-3">
			<label style="position:relative; left:-205px;">NIP:</label>
                            <input type="number" name="nip" class="form-control" value="<?= htmlspecialchars($profil['nip'] ?? ''); ?>" placeholder="NIP Guru" required style="width: 90%; margin: 0 auto;" />
                            <br>
                        </div>
                        <?php endif; ?>

                        <?php if ($user['role'] == 'siswa'): ?>
			<label style="position:relative; left:-205px;">NIS:</label>
                        <div class="mb-3">
                            <input type="number" name="nis" class="form-control" value="<?= htmlspecialchars($profil['nis'] ?? ''); ?>" placeholder="NIS Siswa" required style="width: 90%; margin: 0 auto;" />
                            <br>
			<label style="position:relative; left:-200px;">Kelas:</label>
                            <input type="text" name="kelas" class="form-control" value="<?= htmlspecialchars($profil['kelas'] ?? ''); ?>" placeholder="Kelas" required style="width: 90%; margin: 0 auto;" />
                            <br>
                        </div>
                        <?php endif; ?>
                        
                        <div class="button1">
                            <button type="submit" name="update" class="btn btn-primary" style="margin-bottom: 20px;">Update Data</button>
                            <button type="button" class="btn btn-danger" style="margin-bottom: 20px;" onclick="window.location.href='daftar_user.php';">Batal</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<br><br>