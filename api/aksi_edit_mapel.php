<?php
mysqli_report(MYSQLI_REPORT_OFF); 
require_once 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_mapel'])) {
        $id    = $koneksi->real_escape_string($_POST['id']);
        $nama  = $koneksi->real_escape_string($_POST['nama_mapel']);
        $gid   = empty($_POST['guru_id']) ? "NULL" : "'" . $koneksi->real_escape_string($_POST['guru_id']) . "'";

        $sql = "UPDATE mapel SET nama_mapel = '$nama', guru_id = $gid WHERE id = '$id'";

        if ($koneksi->query($sql)) {
            echo "<script>
                    alert('Data mata pelajaran berhasil diperbarui!'); 
                    window.location.href='daftar_mapel.php';
                  </script>";
            exit;
        } else {
            $error = addslashes($koneksi->error);
            echo "<script>
                    alert('Gagal mengupdate mapel: $error'); 
                    window.location.href='edit_mapel.php?id=$id';
                  </script>";
            exit;
        }
    }
}
?>