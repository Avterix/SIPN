<?php
require 'navbar.php';
require_once 'koneksi.php';

if (!isset($_GET['id'])) {
    header("Location: daftar_mapel.php");
    exit;
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

// Ambil data mapel
$query_mapel = mysqli_query($koneksi, "SELECT * FROM mapel WHERE id = '$id'");
$mapel = mysqli_fetch_assoc($query_mapel);

if (!$mapel) {
    echo "<script>alert('Mata pelajaran tidak ditemukan!'); window.location.href='daftar_mapel.php';</script>";
    exit;
}

// Ambil daftar guru
$query_guru = mysqli_query($koneksi, "SELECT * FROM guru ORDER BY nama ASC");
?>
<head>
    <link rel="stylesheet" href="style1.css">
</head>

<div class="form2" style="margin: 0 auto; width: 40%;">
    <div class="container text-center" style="margin: 0 auto;">
        <div class="form">
            <div class="card" style="margin-top: 20px;">
                <div class="card-form">
                    <h5>Form Edit Mata Pelajaran</h5><br>
                </div>
                
                <form action="aksi_edit_mapel.php" method="POST">
                    <input type="hidden" name="id" value="<?= $mapel['id']; ?>">
                    
                    <div class="form2" style="border: 1px solid #d3d3d3; padding: 15px;">
                        <h6>Detail Mapel</h6><br>

                        <label style="position:relative; left:-180px;">Kode Mapel:</label>
                        <div class="mb-3">
                            <input type="text" name="kode_mapel" class="form-control" value="<?= htmlspecialchars($mapel['kode_mapel']); ?>" readonly style="width: 90%; margin: 0 auto; background-color: #e9ecef;" />
                        </div>
                        <br>

                        <label style="position:relative; left:-180px;">Nama Mapel:</label>
                        <div class="mb-3">
                            <input type="text" name="nama_mapel" class="form-control" value="<?= htmlspecialchars($mapel['nama_mapel']); ?>" required style="width: 90%; margin: 0 auto;" />
                        </div>
                        <br>

                        <label style="position:relative; left:-180px;">Guru Pengampu:</label>
                        <div class="mb-3">
                            <select name="guru_id" class="form-control" style="width: 90%; margin: 0 auto; height: 38px;">
                                <option value="">Tanpa Guru</option>
                                <?php while ($guru = mysqli_fetch_assoc($query_guru)): ?>
                                    <option value="<?= $guru['id']; ?>" <?= ($mapel['guru_id'] == $guru['id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($guru['nama']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <br>
                        
                        <div class="button1">
                            <button type="submit" name="update_mapel" class="btn btn-primary" style="margin-bottom: 20px;">Update Data</button>
                            <button type="button" class="btn btn-danger" style="margin-bottom: 20px;" onclick="window.location.href='daftar_mapel.php';">Batal</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<br><br>