<?php
require 'navbar.php';
require_once 'koneksi.php';
?>
<head>
    <link rel="stylesheet" href="style1.css">
</head>
<div class="form2" style="margin: 0 auto; width: 30%;">
    <div class="container text-center" style="margin: 0 auto;">
        <div class="form">
            <div class="card" style="margin-top: 20px;">
                <div class="card-form">
                    <h2>Form Tambah Mapel</h2><br>
                </div>

                <form action="aksi_tambah_mapel.php" method="POST">
                    <div class="card-body">
                        <h1>Data Mapel</h1>
                    </div>
                    
                    <label class="label-01">&nbsp&nbsp&nbsp&nbsp&nbsp&nbspNama Mapel:</label>
                    <div class="mb-3">
                        <input type="text" name="nama_mapel" class="form-control" placeholder="Nama Mapel | Contoh: Bahasa Indonesia" required style="width: 90%; margin: 0 auto;" />
                    </div>
                    <br>
                    
                    <label class="label-01">ID Guru:</label>
                    <div class="mb-3">
                        <input type="number" name="guru_id" class="form-control" placeholder="ID Guru | Contoh: 1" style="width: 90%; margin: 0 auto;" />
                    </div>
                    <br>
                    
                    <div class="button1">
                        <button type="submit" name="add_mapel" class="btn btn-primary" style="margin-bottom: 20px;">Simpan Data</button>
                        <button type="button" class="btn btn-danger" style="margin-bottom: 20px;" onclick="window.history.back();">Kembali</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<br><br>