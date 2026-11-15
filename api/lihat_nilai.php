<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
$role     = strtolower($_SESSION['role'] ?? '');

// 1. AMBIL DATA USER & FOTO
$q_user = mysqli_query($koneksi, "SELECT * FROM users WHERE id = '$user_id'");
$user_data = ($q_user && mysqli_num_rows($q_user) > 0) ? mysqli_fetch_assoc($q_user) : [];

$username     = $user_data['username'] ?? $_SESSION['username'] ?? 'User';
$foto_db      = $user_data['foto'] ?? '';
$gh_connected = !empty($user_data['github_connected']) && $user_data['github_connected'] == 1;
$gh_avatar    = $user_data['github_avatar'] ?? '';

// Hirarki Foto Profil (LOGIKA DIPISAH TOTAL)
$avatar_src = '';
if (!empty($foto_db)) {
    if (strpos($foto_db, 'data:image') === 0 || filter_var($foto_db, FILTER_VALIDATE_URL)) {
        $avatar_src = $foto_db;
    } elseif (file_exists('uploads/avatars/' . $foto_db)) {
        $avatar_src = 'uploads/avatars/' . $foto_db;
    }
}
if (empty($avatar_src) && $gh_connected && !empty($gh_avatar)) {
    $avatar_src = $gh_avatar;
}

// Ambil Profil Siswa
$q_siswa = mysqli_query($koneksi, "SELECT * FROM siswa WHERE user_id = '$user_id'");
$siswa_data = ($q_siswa && mysqli_num_rows($q_siswa) > 0) ? mysqli_fetch_assoc($q_siswa) : [];

$siswa_id     = $siswa_data['id'] ?? 0;
$nama_lengkap = $siswa_data['nama'] ?? $username;
$nis          = $siswa_data['nis'] ?? '-';
$kelas        = $siswa_data['kelas'] ?? '-';

// 2. QUERY FETCH NILAI RAPOR
$sql_nilai = "SELECT nr.*, m.nama_mapel, m.kode_mapel, g.nama AS nama_guru
              FROM nilai_rapor nr
              JOIN mapel m ON nr.mapel_id = m.id
              LEFT JOIN guru g ON m.guru_id = g.id
              WHERE nr.siswa_id = '$siswa_id'
              ORDER BY m.nama_mapel ASC";

$q_nilai = mysqli_query($koneksi, $sql_nilai);

$list_nilai   = [];
$total_nilai  = 0;
$count_mapel  = 0;
$max_nilai    = 0;
$min_nilai    = 100;
$tuntas_count = 0;

if ($q_nilai && mysqli_num_rows($q_nilai) > 0) {
    while ($row = mysqli_fetch_assoc($q_nilai)) {
        $kkm          = $row['nilai_kkm'] ?? 75;
        $pengetahuan  = $row['nilai_pengetahuan'] ?? 0;
        $keterampilan = $row['nilai_keterampilan'] ?? 0;

        $nilai_akhir = round(($pengetahuan + $keterampilan) / 2, 1);

        if ($nilai_akhir >= 90) $predikat = 'A';
        elseif ($nilai_akhir >= 80) $predikat = 'B';
        elseif ($nilai_akhir >= 70) $predikat = 'C';
        else $predikat = 'D';

        $is_tuntas = ($nilai_akhir >= $kkm);
        if ($is_tuntas) $tuntas_count++;

        $row['kkm_val']         = $kkm;
        $row['pengetahuan_val'] = $pengetahuan;
        $row['keterampilan_val'] = $keterampilan;
        $row['computed_akhir']  = $nilai_akhir;
        $row['computed_predikat'] = $predikat;
        $row['is_tuntas']       = $is_tuntas;

        $list_nilai[] = $row;

        $total_nilai += $nilai_akhir;
        $count_mapel++;
        if ($nilai_akhir > $max_nilai) $max_nilai = $nilai_akhir;
        if ($nilai_akhir < $min_nilai) $min_nilai = $nilai_akhir;
    }
}

$rata_rata = $count_mapel > 0 ? round($total_nilai / $count_mapel, 1) : 0;
if ($count_mapel == 0) $min_nilai = 0;

// ---------- Tambahan khusus tampilan (tidak mengubah perhitungan di atas) ----------
$pred_rata = '–';
if ($count_mapel > 0) {
    if ($rata_rata >= 90) $pred_rata = 'A';
    elseif ($rata_rata >= 80) $pred_rata = 'B';
    elseif ($rata_rata >= 70) $pred_rata = 'C';
    else $pred_rata = 'D';
}
$belum_tuntas = $count_mapel - $tuntas_count;

function rp_initial($s)
{
    $s = trim((string) $s);
    if ($s === '') return '?';
    return function_exists('mb_substr') ? mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($s, 0, 1));
}
function rp_pct($v)
{
    return number_format(max(0, min(100, (float) $v)), 2, '.', '');
}

// Daftar guru pengajar (satu baris per mapel, tanpa duplikat)
$guru_list = [];
$seen_mapel = [];
foreach ($list_nilai as $row) {
    $g   = trim((string) ($row['nama_guru'] ?? ''));
    $key = $row['mapel_id'] ?? $row['nama_mapel'];
    if ($g === '' || isset($seen_mapel[$key])) continue;
    $seen_mapel[$key] = true;
    $guru_list[] = ['nama' => $g, 'mapel' => $row['nama_mapel'], 'nilai' => $row['computed_akhir']];
}

// Catatan guru (kolom keterangan yang terisi)
$notes = [];
foreach ($list_nilai as $row) {
    $k = trim((string) ($row['keterangan'] ?? ''));
    if ($k === '' || $k === '-') continue;
    $g = trim((string) ($row['nama_guru'] ?? ''));
    $notes[] = [
        'guru'  => $g !== '' ? $g : 'Guru mapel',
        'mapel' => $row['nama_mapel'],
        'teks'  => $k,
        'akhir' => $row['computed_akhir'],
        'pred'  => $row['computed_predikat'],
        'kkm'   => $row['kkm_val'],
    ];
}

// Titik sinar diagram radial (satu sinar per mapel, panjang = nilai akhir)
$burst = [];
foreach ($list_nilai as $i => $row) {
    $ang = (2 * M_PI * $i / max(1, $count_mapel)) - M_PI / 2;
    $len = 12 + (max(0, min(100, (float) $row['computed_akhir'])) / 100) * 34;
    $burst[] = [
        round(50 + 10 * cos($ang), 2), round(50 + 10 * sin($ang), 2),
        round(50 + $len * cos($ang), 2), round(50 + $len * sin($ang), 2),
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Report Card Grades - SIPN Core</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/style1.css?v=1.3">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="<?= $base_url; ?>/icons/favicon.png">
    
    <link rel="stylesheet" href="<?= $base_url; ?>/rapor.css?v=1">
</head>
<body>
<?php include "loader.php"; ?>
<div class="video">
    <div class="dashboard-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo"><i class="home"></i></div>
                <h2>SIPN Core</h2>
            </div>

            <div class="user-profile-mini">
                <?php if (!empty($avatar_src)): ?>
                    <img src="<?= htmlspecialchars($avatar_src); ?>" class="user-avatar-initial" style="object-fit: cover;">
                <?php else: ?>
                    <div class="user-avatar-initial"><?= strtoupper(substr($username, 0, 1)); ?></div>
                <?php endif; ?>
                <div class="user-meta-mini">
                    <span class="user-name"><?= htmlspecialchars($username); ?></span>
                    <span class="user-role-badge"><?= htmlspecialchars($role); ?></span>
                </div>
            </div>

            <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="dashboard"></i> <span class="menu-label">Dashboard</span></a></li>
        <?php if ($role === 'siswa'): ?>
        <li><a href="lihat_nilai.php" class="active"><i class="report"></i> <span class="menu-label">Report Grades</span></a></li>
        <?php endif; ?>
        <li><a href="profile.php"><i class="profile"></i> <span class="menu-label">Profile Settings</span></a></li>
    </ul>

                <div class="sidebar-footer">
        <a href="logout.php" class="add-files-box" style="text-decoration: none; color: inherit;">
            <div class="icon-plus"><i class="logout"></i></div>
            <div class="add-text">
                <strong>System Session</strong>
                <span>Logout Account</span>
            </div>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <header class="topbar">
                <div class="topbar-title">
                    <h1>Report Card Grades</h1>
                    <span class="storage-badge">Role: <?= strtoupper($role); ?></span>
                </div>
                <div class="topbar-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search report card...">
                    </div>
                    <button class="icon-btn notification-btn"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/bell.webp"/></button>
                    <a href="profile.php" class="icon-btn settings-btn" title="Settings"><img class="icons" src="<?= $base_url; ?>/icons/dashboard/settings.webp"/></a>
                    <a href="logout.php" class="upgrade-btn" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Sign Out</a>
                </div>
            </header>

            <div class="rapor-container">
                <div class="rp-card">

                    <!-- ===== Pita atas (krem) ===== -->
                    <section class="rp-band">
                        <div class="rp-hero">
                            <!-- Ringkasan utama -->
                            <div class="rp-intro">
                                <h2 class="rp-h1">Hasil <span class="rp-dim">belajar</span><br><?= e($nama_lengkap); ?></h2>
                                <p class="rp-caption">Rata-rata nilai akhir</p>
                                <?php if ($count_mapel > 0): ?>
                                    <div class="rp-grade"><span aria-hidden="true"><?= e($pred_rata); ?>, <?= e($rata_rata); ?><small>/100</small></span><span class="rp-sr">Rata-rata <?= e($rata_rata); ?>, predikat <?= e($pred_rata); ?></span></div>
                                <?php else: ?>
                                    <div class="rp-grade"><span aria-hidden="true">–</span><span class="rp-sr">Belum ada nilai</span></div>
                                <?php endif; ?>
                                <p class="rp-meta">
                                    <span>NIS <strong><?= e($nis); ?></strong></span>
                                    <span>Kelas <strong><?= e($kelas); ?></strong></span>
                                    <span>Status <strong>Aktif</strong></span>
                                </p>
                            </div>

                            <!-- Grafik -->
                            <div class="rp-chart-col rp-no-print">
                                <div class="rp-head">
                                    <h3 class="rp-title">Nilai per mata pelajaran</h3>
                                    <div class="rp-legend">
                                        <span><i class="rp-sw rp-sw-kkm"></i>KKM</span>
                                        <span><i class="rp-sw rp-sw-nilai"></i>Nilai akhir</span>
                                    </div>
                                </div>

                                <?php if ($count_mapel > 0): ?>
                                    <div class="rp-chart" role="group" aria-label="Grafik nilai akhir dibanding KKM untuk setiap mata pelajaran">
                                        <div class="rp-axis" aria-hidden="true">
                                            <span style="bottom:90%">A</span>
                                            <span style="bottom:80%">B</span>
                                            <span style="bottom:70%">C</span>
                                            <span style="bottom:0">D</span>
                                        </div>
                                        <div class="rp-scroll">
                                            <div class="rp-plot">
                                                <div class="rp-gls" aria-hidden="true">
                                                    <i class="rp-gl" style="bottom:90%"></i>
                                                    <i class="rp-gl" style="bottom:80%"></i>
                                                    <i class="rp-gl" style="bottom:70%"></i>
                                                </div>
                                                <ul class="rp-bars">
                                                    <?php foreach ($list_nilai as $i => $row): ?>
                                                        <li class="rp-grp" tabindex="0"
                                                            style="--i:<?= (int) $i; ?>;--h:<?= rp_pct($row['computed_akhir']); ?>"
                                                            title="<?= e($row['nama_mapel']); ?>: nilai akhir <?= e($row['computed_akhir']); ?>, KKM <?= e($row['kkm_val']); ?>">
                                                            <span class="rp-bar rp-bar-kkm" style="height:<?= rp_pct($row['kkm_val']); ?>%"></span>
                                                            <span class="rp-bar rp-bar-nilai" style="height:<?= rp_pct($row['computed_akhir']); ?>%"></span>
                                                            <span class="rp-val"><?= e($row['computed_akhir']); ?></span>
                                                            <span class="rp-x"><?= e($row['kode_mapel'] ?? '-'); ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="rp-empty">Belum ada nilai untuk ditampilkan di grafik.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Guru pengajar -->
                            <aside class="rp-teachers rp-no-print">
                                <h3 class="rp-title">Guru pengajar</h3>
                                <p class="rp-sub">Guru pengampu setiap mata pelajaran di rapor ini.</p>
                                <?php if (!empty($guru_list)): ?>
                                    <ul class="rp-people" id="rpPeople">
                                        <?php foreach ($guru_list as $gi => $g): ?>
                                            <li class="rp-person<?= $gi >= 3 ? ' rp-extra' : ''; ?>">
                                                <span class="rp-ava"><?= e(rp_initial($g['nama'])); ?></span>
                                                <div>
                                                    <strong><?= e($g['nama']); ?></strong>
                                                    <span class="rp-person-sub"><?= e($g['mapel']); ?></span>
                                                </div>
                                                <span class="rp-person-val" title="Nilai akhir"><?= e($g['nilai']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (count($guru_list) > 3): ?>
                                        <button type="button" class="rp-pill" id="rpPeopleToggle" aria-expanded="false">Lihat semua</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="rp-empty">Belum ada guru yang ditautkan ke mata pelajaran ini.</p>
                                <?php endif; ?>
                            </aside>
                        </div>
                    </section>

                    <!-- ===== Bagian bawah (putih) ===== -->
                    <section class="rp-lower rp-no-print">
                        <!-- Ringkasan -->
                        <div class="rp-panel">
                            <div class="rp-head">
                                <h3 class="rp-title">Ringkasan nilai</h3>
                                <button type="button" class="rp-pill rp-pill-line" onclick="window.print()">
                                    <i class="fa-solid fa-print" aria-hidden="true"></i> Cetak rapor
                                </button>
                            </div>
                            <div class="rp-tiles">
                                <div class="rp-tile">
                                    <span class="rp-tile-n"><?= e($min_nilai); ?></span>
                                    <span class="rp-tile-l">Terendah</span>
                                </div>
                                <div class="rp-tile is-dark">
                                    <span class="rp-tile-n"><?= e($max_nilai); ?></span>
                                    <span class="rp-tile-l">Tertinggi</span>
                                </div>
                                <div class="rp-tile is-dark">
                                    <span class="rp-tile-n"><?= e($tuntas_count); ?>/<?= e($count_mapel); ?></span>
                                    <span class="rp-tile-l">Tuntas</span>
                                </div>
                                <div class="rp-tile">
                                    <span class="rp-tile-n"><?= e($count_mapel); ?></span>
                                    <span class="rp-tile-l">Total mapel</span>
                                </div>
                                <div class="rp-tile">
                                    <span class="rp-tile-n"><?= e($belum_tuntas); ?></span>
                                    <span class="rp-tile-l">Belum tuntas</span>
                                </div>
                                <div class="rp-tile is-burst">
                                    <svg viewBox="0 0 100 100" role="img" aria-label="Diagram radial: satu garis untuk setiap mata pelajaran, makin panjang makin tinggi nilainya">
                                        <g stroke="#161616" stroke-width="1.4" stroke-linecap="round">
                                            <?php foreach ($burst as $bp): ?>
                                                <line x1="<?= $bp[0]; ?>" y1="<?= $bp[1]; ?>" x2="<?= $bp[2]; ?>" y2="<?= $bp[3]; ?>"/>
                                            <?php endforeach; ?>
                                        </g>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Ketuntasan per mapel -->
                        <div class="rp-panel">
                            <div class="rp-head">
                                <h3 class="rp-title">Ketuntasan per mapel</h3>
                            </div>
                            <?php if (!empty($list_nilai)): ?>
                                <ul class="rp-lines">
                                    <?php foreach ($list_nilai as $row): ?>
                                        <li class="rp-line">
                                            <span class="rp-line-meta">KKM <?= e($row['kkm_val']); ?></span>
                                            <span class="rp-line-name">
                                                <?= e($row['nama_mapel']); ?>
                                                <?php if ($row['is_tuntas']): ?>
                                                    <span class="rp-mark is-ok" aria-hidden="true">✔</span><span class="rp-sr">tuntas</span>
                                                <?php else: ?>
                                                    <span class="rp-mark is-no" aria-hidden="true">✕</span><span class="rp-sr">belum tuntas</span>
                                                <?php endif; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="rp-empty">Belum ada data nilai rapor untuk siswa ini.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Catatan guru -->
                        <div class="rp-panel rp-panel-notes">
                            <div class="rp-head">
                                <h3 class="rp-title">Catatan guru</h3>
                            </div>
                            <?php if (!empty($notes)): ?>
                                <div class="rp-notes">
                                    <?php foreach ($notes as $n): ?>
                                        <article class="rp-note">
                                            <div class="rp-note-head">
                                                <span class="rp-ava rp-ava-sm"><?= e(rp_initial($n['guru'])); ?></span>
                                                <div>
                                                    <strong><?= e($n['guru']); ?></strong>
                                                    <span class="rp-person-sub">Guru <?= e($n['mapel']); ?></span>
                                                </div>
                                            </div>
                                            <p class="rp-note-body" title="<?= e($n['teks']); ?>"><?= e($n['teks']); ?></p>
                                            <div class="rp-note-foot">
                                                <span class="rp-chip is-dark">Nilai akhir <?= e($n['akhir']); ?></span>
                                                <span class="rp-chip">Predikat <?= e($n['pred']); ?></span>
                                                <span class="rp-pill">KKM <?= e($n['kkm']); ?></span>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="rp-empty">Belum ada catatan dari guru.</p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- ===== Rincian nilai ===== -->
                    <section class="rp-detail">
                        <div class="rp-head">
                            <div>
                                <h3 class="rp-title">Rincian nilai</h3>
                                <p class="rp-sub">Nilai akhir adalah rata-rata nilai pengetahuan dan keterampilan.</p>
                            </div>
                        </div>

                        <div class="rp-table-wrap">
                            <table class="rp-table">
                                <thead>
                                    <tr>
                                        <th class="rp-c" style="width:50px">No</th>
                                        <th>Mata Pelajaran</th>
                                        <th class="rp-c">KKM</th>
                                        <th class="rp-c">Nilai Pengetahuan</th>
                                        <th class="rp-c">Nilai Keterampilan</th>
                                        <th class="rp-c">Nilai Akhir</th>
                                        <th class="rp-c">Predikat</th>
                                        <th class="rp-c">Keterangan</th>
                                        <th class="rp-c">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($list_nilai)): ?>
                                        <?php foreach ($list_nilai as $index => $row): ?>
                                            <tr>
                                                <td class="rp-c"><?= $index + 1; ?></td>
                                                <td>
                                                    <span class="rp-strong"><?= e($row['nama_mapel']); ?></span>
                                                    <small>
                                                        <?= e($row['kode_mapel'] ?? '-'); ?>
                                                        <?php if (!empty($row['semester'])): ?>, semester <?= e($row['semester']); ?><?php endif; ?>
                                                    </small>
                                                </td>
                                                <td class="rp-c rp-strong"><?= e($row['kkm_val']); ?></td>
                                                <td class="rp-c"><?= e($row['pengetahuan_val']); ?></td>
                                                <td class="rp-c"><?= e($row['keterampilan_val']); ?></td>
                                                <td class="rp-c rp-strong"><?= e($row['computed_akhir']); ?></td>
                                                <td class="rp-c">
                                                    <span class="rp-badge is-<?= strtolower(e($row['computed_predikat'])); ?>"><?= e($row['computed_predikat']); ?></span>
                                                </td>
                                                <td class="rp-c rp-ket"><?= e(($row['keterangan'] ?? '') !== '' ? $row['keterangan'] : '-'); ?></td>
                                                <td class="rp-c">
                                                    <?php if ($row['is_tuntas']): ?>
                                                        <span class="rp-mark is-ok" title="Tuntas" aria-hidden="true">✔</span><span class="rp-sr">Tuntas</span>
                                                    <?php else: ?>
                                                        <span class="rp-mark is-no" title="Belum tuntas" aria-hidden="true">✕</span><span class="rp-sr">Belum tuntas</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="rp-empty-row">Belum ada data nilai rapor untuk siswa ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                </div>
            </div>
        </main>
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('rpPeopleToggle');
    var list = document.getElementById('rpPeople');
    if (btn && list) {
        btn.addEventListener('click', function () {
            var open = list.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.textContent = open ? 'Lihat lebih sedikit' : 'Lihat semua';
        });
    }
})();
</script>
</body>
</html>