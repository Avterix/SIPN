<?php
require 'navbar.php';
?>
<head>
    <link rel="stylesheet" href="style1.css">
    <script>
        window.si = window.si || function () { (window.siq = window.siq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/speed-insights/script.js"></script>
</head>
<div class="form2" style="margin: 0 auto; width: 30%;">
    <div class="container text-center" style="margin: 0 auto;">
        <div class="form">
            <div class="card" style="margin-top: 20px;">
                <div class="card-form">
                    <h2>Form Tambah Siswa</h2><br>
                </div>

                <form action="aksi_tambah_user.php" method="POST">
                    <div class="card-body">
                        <h1>Data Profil Siswa</h1>
                    </div>
                    
                    <label class="label-01">NIS:</label>
                    <div class="mb-3">
                        <input type="number" name="nis" class="form-control" id="exampleFormControlInput1" placeholder="Nomor Induk Siswa | Contoh:12345678" required style="width: 90%; margin: 0 auto;" />
                    </div>
                    <br>
                    
                    <label class="label-01">NAMA:</label>
                    <div class="mb-3">
                        <input type="text" name="nama" class="form-control" id="exampleFormControlInput1" placeholder="Nama Lengkap Siswa | Contoh:Ujang Asep" required style="width: 90%; margin: 0 auto;" />
                    </div>
                    <br>
                    
                    <label class="label-01">KELAS:</label>
                    <div class="mb-3">
                        <input type="text" name="kelas" class="form-control" id="exampleFormControlInput1" placeholder="Kelas Siswa | Contoh:X-RPL-1" required style="width: 90%; margin: 0 auto;" />
                    </div>
                    <br>
                    
                    <div class="radio">
                        Jenis Kelamin:
                        <br>
                        <input type="radio" name="jenis_kelamin" value="Laki-laki" required />
                        <label for="Laki-laki">Laki-laki</label>
                        <br>
                        <input type="radio" name="jenis_kelamin" value="Perempuan" id="Perempuan" required />
                        <label for="Perempuan">Perempuan</label>
                    </div>
                    <br>
                    
                    <div class="form2" style="border: 1px solid #d3d3d3;">
                        <h3>Data Kredensial Akun (Tabel <br> Users)</h3><br>
                        
                        <div class="mb-3">
                            <input type="text" name="username" class="form-control" id="exampleFormControlInput1" placeholder="Username Akun | Contoh:ujang123" required style="width: 90%; margin: 0 auto;" />
                        </div>
                        <br>
                        
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" id="exampleFormControlInput1" placeholder="Password Akun | Contoh:password123" required style="width: 90%; margin: 0 auto;" />
                        </div>
                        
                        <input type="hidden" name="role" value="siswa">
                        <br>
                        
                        <div class="button1">
                            <button type="submit" name="kirim" class="btn btn-primary" style="margin-bottom: 20px;">Simpan Data</button>
                            <button type="button" class="btn btn-danger" style="margin-bottom: 20px;" onclick="window.history.back();">Kembali</button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<br><br>