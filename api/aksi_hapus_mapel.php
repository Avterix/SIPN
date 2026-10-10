<?php
mysqli_report(MYSQLI_REPORT_OFF); 
require_once 'koneksi.php';

if (isset($_GET['id'])) {
    $id = $koneksi->real_escape_string($_GET['id']);

    $sql = "DELETE FROM mapel WHERE id = '$id'";

    if ($koneksi->query($sql)) {
        echo "<script>
                alert('Mata pelajaran berhasil dihapus!'); 
                window.location.href='daftar_mapel.php';
              </script>";
        exit;
    } else {
        $error = addslashes($koneksi->error);
        echo "<script>
                alert('Gagal menghapus mata pelajaran: $error'); 
                window.location.href='daftar_mapel.php';
              </script>";
        exit;
    }
} else {
    header("Location: daftar_mapel.php");
    exit;
}
?>
