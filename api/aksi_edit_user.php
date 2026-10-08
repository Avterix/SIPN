<?php
mysqli_report(MYSQLI_REPORT_OFF); 
require_once 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id       = $_POST['id'];
    $username = $_POST['username'];
    $role     = $_POST['role'];
    $password = $_POST['password'];

    if (!empty($password)) {
        $pass_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql_users = "UPDATE users SET username='$username', password='$pass_hash' WHERE id='$id'";
    } else {
        $sql_users = "UPDATE users SET username='$username' WHERE id='$id'";
    }

    $query_users = mysqli_query($koneksi, $sql_users);

    if ($query_users) {
        $query_profil = true;

        if ($role == 'siswa') {
            $nis           = $_POST['nis'] ?? '';
            $nama          = $_POST['nama'] ?? '';
            $kelas         = $_POST['kelas'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';

            $sql_siswa = "UPDATE siswa SET nis='$nis', nama='$nama', kelas='$kelas', jenis_kelamin='$jenis_kelamin' WHERE user_id='$id'";
            $query_profil = mysqli_query($koneksi, $sql_siswa);

        } elseif ($role == 'guru') {
            $nip           = $_POST['nip'] ?? '';
            $nama          = $_POST['nama'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';

            $sql_guru = "UPDATE guru SET nip='$nip', nama='$nama', jenis_kelamin='$jenis_kelamin' WHERE user_id='$id'";
            $query_profil = mysqli_query($koneksi, $sql_guru);
        }

        if ($query_profil) {
            echo "<script>
                    alert('Data user berhasil diperbarui!'); 
                    window.location.href='daftar_user.php';
                  </script>";
            exit;
        } else {
            $error_profil = addslashes(mysqli_error($koneksi));
            echo "<script>
                    alert('Gagal mengupdate profil: $error_profil'); 
                    window.location.href='edit_user.php?id=$id';
                  </script>";
            exit;
        }
    } else {
        $error_users = addslashes(mysqli_error($koneksi));
        echo "<script>
                alert('Gagal mengupdate users: $error_users'); 
                window.location.href='edit_user.php?id=$id';
              </script>";
        exit;
    }
}
?>