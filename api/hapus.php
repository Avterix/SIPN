<?php
mysqli_report(MYSQLI_REPORT_OFF); 
require_once 'koneksi.php';

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);

    $query_user = mysqli_query($koneksi, "SELECT role FROM users WHERE id = '$id'");
    $user = mysqli_fetch_assoc($query_user);

    if ($user) {
        $role = $user['role'];


        if ($role == 'siswa') {
            mysqli_query($koneksi, "DELETE FROM siswa WHERE user_id = '$id'");
        } elseif ($role == 'guru') {
            mysqli_query($koneksi, "DELETE FROM guru WHERE user_id = '$id'");
        }

        $delete = mysqli_query($koneksi, "DELETE FROM users WHERE id = '$id'");

        if ($delete) {
            echo "<script>
                    alert('User berhasil dihapus!'); 
                    window.location.href='daftar_user.php';
                  </script>";
            exit;
        } else {
            $error = addslashes(mysqli_error($koneksi));
            echo "<script>
                    alert('Gagal menghapus user: $error'); 
                    window.location.href='daftar_user.php';
                  </script>";
            exit;
        }
    } else {
        echo "<script>alert('User tidak ditemukan!'); window.location.href='daftar_user.php';</script>";
        exit;
    }
} else {
    header("Location: daftar_user.php");
    exit;
}
?>