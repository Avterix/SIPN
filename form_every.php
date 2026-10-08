<?php
require 'navbar.php';
?>
<head>
    <link rel="stylesheet" href="style1.css">
</head>
<div class="form2" style="margin: 0 auto; width: 40%;">
    <div class="container text-center" style="margin: 0 auto;">
        <div class="form">
            <div class="card" style="margin-top: 20px;">
                <div class="card-form">
                    <h5>&nbspForm Tambah Semua User</h5><br>
                </div>
                
                <form action="aksi_tambah_user.php" method="POST">
                    
                    <div class="form2" style="border: 1px solid #d3d3d3; padding: 15px;">
                        <h6>Kredensial & Pilihan Role</h6><br>
                        
                        <div class="mb-3">
                            <input type="text" name="username" class="form-control" placeholder="Username Akun" required style="width: 90%; margin: 0 auto;" />
                        </div>
                        <br>
                        
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control" placeholder="Password Akun" required style="width: 90%; margin: 0 auto;" />
                        </div>
                        <br>

                        <div class="mb-3">
                            <select name="role" id="roleSelect" class="form-control" required style="width: 90%; margin: 0 auto; padding: 8px;" onchange="tampilkanInputProfil()">
                                <option value="" disabled selected>Role</option>
                                <option value="admin">Admin</option>
                                <option value="guru">Guru</option>
                                <option value="siswa">Siswa</option>
                            </select>
                        </div>
                        <br>

                        <div id="kolomNama" class="mb-3" style="display: none;">
                            <input type="text" name="nama" id="inputNama" class="form-control" placeholder="Nama Lengkap" style="width: 90%; margin: 0 auto;" />
                            <br>
                            <div class="radio" style="width: 90%; margin: 0 auto; text-align: left;">
                                <label>Jenis Kelamin:</label><br>
                                <input type="radio" name="jenis_kelamin" value="L" /> L &nbsp;
                                <input type="radio" name="jenis_kelamin" value="P" /> P
                            </div>
                            <br>
                        </div>

                        <div id="kolomGuru" class="mb-3" style="display: none;">
                            <input type="number" name="nip" id="inputNip" class="form-control" placeholder="NIP Guru | Contoh: 1985..." style="width: 90%; margin: 0 auto;" />
                            <br>
                        </div>

                        <div id="kolomSiswa" class="mb-3" style="display: none;">
                            <input type="number" name="nis" id="inputNis" class="form-control" placeholder="NIS Siswa | Contoh: 12345" style="width: 90%; margin: 0 auto;" />
                            <br>
                            <input type="text" name="kelas" id="inputKelas" class="form-control" placeholder="Kelas | Contoh: X-RPL-1" style="width: 90%; margin: 0 auto;" />
                            <br>
                        </div>
                        
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

<script>
function tampilkanInputProfil() {
    var role = document.getElementById("roleSelect").value;
    
    var kolomNama = document.getElementById("kolomNama");
    var kolomGuru = document.getElementById("kolomGuru");
    var kolomSiswa = document.getElementById("kolomSiswa");
    
    var inputNama = document.getElementById("inputNama");
    var inputNip = document.getElementById("inputNip");
    var inputNis = document.getElementById("inputNis");
    var inputKelas = document.getElementById("inputKelas");

    kolomNama.style.display = "none"; inputNama.required = false;
    kolomGuru.style.display = "none"; inputNip.required = false;
    kolomSiswa.style.display = "none"; inputNis.required = false; inputKelas.required = false;

    if (role === "siswa") {
        kolomNama.style.display = "block"; inputNama.required = true;
        kolomSiswa.style.display = "block"; inputNis.required = true; inputKelas.required = true;
    } else if (role === "guru") {
        kolomNama.style.display = "block"; inputNama.required = true;
        kolomGuru.style.display = "block"; inputNip.required = true;
    } else if (role === "admin") {
    }
}
</script>
