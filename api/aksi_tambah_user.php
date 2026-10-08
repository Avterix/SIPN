<?php
mysqli_report(MYSQLI_REPORT_OFF); 

require_once 'koneksi.php';
?>
<head>
    <link rel="stylesheet" href="style1.css">
    <script>
        window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
</head>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = $_POST['role'];


    $sql_users = "INSERT INTO users (username, password, role, created_at) 
                  VALUES ('$username', '$password', '$role', NOW())";
    $query_users = mysqli_query($koneksi, $sql_users);

    if ($query_users) {
        $id_terakhir = mysqli_insert_id($koneksi);
        $query_profil = true;


        if ($role == 'siswa') {
            $nis           = $_POST['nis'] ?? '';
            $nama          = $_POST['nama'] ?? '';
            $kelas         = $_POST['kelas'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';

            $sql_siswa = "INSERT INTO siswa (nis, nama, kelas, jenis_kelamin, user_id) 
                          VALUES ('$nis', '$nama', '$kelas', '$jenis_kelamin', '$id_terakhir')";
            $query_profil = mysqli_query($koneksi, $sql_siswa);

        } elseif ($role == 'guru') {
            $nip           = $_POST['nip'] ?? '';
            $nama          = $_POST['nama'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';

            $sql_guru = "INSERT INTO guru (nip, nama, jenis_kelamin, user_id) 
                         VALUES ('$nip', '$nama', '$jenis_kelamin', '$id_terakhir')";
            $query_profil = mysqli_query($koneksi, $sql_guru);

        } elseif ($role == 'admin') {

            $query_profil = true;
        }


        if ($query_profil) {
            echo "<script>
                    alert('Berhasil! Data $role tersimpan.'); 
                    window.location.href='daftar_user.php';
                  </script>";
            exit; 
        } else {
            $error_profil = addslashes(mysqli_error($koneksi));
            echo "<script>
                    alert('Gagal menyimpan profil $role: $error_profil');
                    window.location.href='form.php';
                  </script>";
            exit;
        }
    } else {
        $error_users = addslashes(mysqli_error($koneksi));
        echo "<script>
                alert('Gagal menyimpan data kedalam tabel users: $error_users'); 
                window.location.href='form.php';
              </script>";
        exit;
    }
}
?>