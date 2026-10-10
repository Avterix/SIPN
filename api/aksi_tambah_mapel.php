<?php
mysqli_report(MYSQLI_REPORT_OFF); 
require_once 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_mapel'])) {
        $nama = $koneksi->real_escape_string($_POST['nama_mapel']);
        $gid  = empty($_POST['guru_id']) ? "NULL" : "'" . $koneksi->real_escape_string($_POST['guru_id']) . "'";

        $query_last = mysqli_query($koneksi, "SELECT kode_mapel FROM mapel ORDER BY id DESC LIMIT 1");
        $data_last  = mysqli_fetch_assoc($query_last);

        if ($data_last && !empty($data_last['kode_mapel'])) {
            $angka_terakhir = (int) substr($data_last['kode_mapel'], 2);
            $angka_baru     = $angka_terakhir + 1;
        } else {
            $angka_baru = 1;
        }

        $kode_mapel = sprintf("MP%02d", $angka_baru);

        $sql = "INSERT INTO mapel (kode_mapel, nama_mapel, guru_id) VALUES ('$kode_mapel', '$nama', $gid)";

        if ($koneksi->query($sql)) {
            echo "<script>
                    alert('Berhasil menambah mapel dengan kode: $kode_mapel'); 
                    window.location.href='daftar_mapel.php';
                  </script>";
            exit;
        } else {
            $error = addslashes($koneksi->error);
            echo "<script>
                    alert('Gagal simpan: $error'); 
                    window.history.back();
                  </script>";
            exit;
        }
    }
}
?>