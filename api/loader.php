<!-- loader.php -->
<?php
// Deteksi halaman saat ini biar skeleton yang muncul langsung sesuai
$current_page = basename($_SERVER['PHP_SELF']);
$is_dashboard = ($current_page === 'dashboard.php');
$is_nilai     = ($current_page === 'daftar_nilai.php' || $current_page === 'lihat_nilai.php');
$is_profile   = ($current_page === 'profile.php');

// Default ke dashboard kalau nggak ada yang cocok
if (!$is_dashboard && !$is_nilai && !$is_profile) {
    $is_dashboard = true; 
}
?>
<style>
/* 1. Kunci Zoom 0.7 sejak awal */
html { zoom: 0.7; }

#page-loader {
    position: fixed; top: 0; left: 0;
    width: calc(100vw / 0.7); height: calc(100vh / 0.7); height: calc(100dvh / 0.7);
    background-color: var(--bg-main, #f4f5f8);
    z-index: 999999;
    padding: 20px; box-sizing: border-box;
    display: flex; justify-content: center; align-items: flex-start; overflow: hidden;
    
    opacity: 1;
    visibility: visible;
    transition: opacity 0.4s ease-out, visibility 0.4s ease-out;
}

#page-loader.loader-hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

#page-loader.page-transitioning {
    opacity: 1;
    visibility: visible;
    pointer-events: all;
    transition: opacity 0.2s ease-in;
}

#page-loader * { animation: none !important; transition: none !important; }

@keyframes skPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

#page-loader .sk-pulse {
    background-image: none !important; background-color: #cbd5e1 !important;
    color: transparent !important; border-color: transparent !important;
    box-shadow: none !important;
    animation: skPulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite !important;
}
#page-loader .sidebar .sk-pulse { background-color: #2d3748 !important; }
#page-loader .sidebar { pointer-events: none !important; }

/* =========================================================
   FIX TATA LETAK: Menggunakan Flex/Grid otomatis (Auto-Stretch)
   ========================================================= */
#page-loader .page-container { display: flex; flex-direction: column; gap: 20px; width: 100%; flex: 1; }
#page-loader .card-box { width: 100%; border-radius: 16px; min-height: 150px; } /* Pakai min-height, biar flex yg nentuin max nya */
#page-loader .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; width: 100%; }
#page-loader .folders-slider { display: flex; gap: 20px; width: 100%; }
#page-loader .bottom-grid { display: grid; grid-template-columns: 320px 1fr; gap: 20px; width: 100%; flex: 1;} /* stretch bottom grid */

/* Grid Profil Baru (Auto Stretch) */
#page-loader .profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; width: 100%; align-items: stretch; flex: 1; } /* align-items: stretch kunci utamanya */
#page-loader .profile-card-left { display: flex; flex-direction: column; align-items: center; background-color: #ffffff; border-radius: 16px; padding: 24px; min-height: 400px; height: 100%; } /* height: 100% agar ngikut konten terpanjang di kanan */
#page-loader .right-content-wrapper { display: flex; flex-direction: column; gap: 20px; width: 100%; height: 100%; }
#page-loader .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }

/* Tambahan agar main-content stretch full height */
#page-loader .main-content {
    display: flex;
    flex-direction: column;
    height: 100%; 
}
#skel-dash, #skel-nilai, #skel-profile {
    display: flex;
    flex-direction: column;
    height: 100%;
}
</style>

<div id="page-loader">
    <div class="video" style="min-height: 100%; background: transparent; width: 100%;">
        <div class="dashboard-container" style="height: 100%;">
            
            <!-- REPLIKA SIDEBAR (GLOBAL) -->
            <aside class="sidebar">
                <div class="sidebar-brand"><div class="brand-logo sk-pulse"></div></div>
                <div class="user-profile-mini"><div class="user-avatar-initial sk-pulse" style="border: none;"></div></div>
                <ul class="sidebar-menu">
                    <li><div class="sk-pulse" style="width: 44px; height: 44px; border-radius: 12px;"></div></li>
                    <li><div class="sk-pulse" style="width: 44px; height: 44px; border-radius: 12px;"></div></li>
                    <li><div class="sk-pulse" style="width: 44px; height: 44px; border-radius: 12px;"></div></li>
                </ul>
                <div class="sidebar-footer">
                    <div class="add-files-box sk-pulse" style="width: 44px; height: 44px; padding: 0; border: none;"></div>
                </div>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="main-content">
                
                <!-- 1. SKELETON KHUSUS DASHBOARD -->
                <div id="skel-dash" style="display: <?= $is_dashboard ? 'flex' : 'none' ?>; width: 100%;">
                    <header class="topbar">
                        <div class="topbar-title">
                            <div class="sk-pulse" style="width: 140px; height: 32px; border-radius: 8px; margin-bottom: 12px"></div>
                            <div class="sk-pulse" style="width: 120px; height: 24px; border-radius: 20px; margin-bottom: 12px"></div>
                        </div>
                        <div class="topbar-actions">
                            <div class="sk-pulse" style="width: 200px; height: 40px; border-radius: 20px;"></div>
                            <div class="sk-pulse" style="width: 40px; height: 40px; border-radius: 50%;"></div>
                            <div class="sk-pulse" style="width: 40px; height: 40px; border-radius: 50%;"></div>
                            <div class="sk-pulse" style="width: 90px; height: 40px; border-radius: 7px;"></div>
                        </div>
                    </header>

                    <section class="hero-section">
                        <div class="folders-slider">
                            <div class="hero-text" style="display: flex; flex-direction: column; justify-content: flex-start; margin-right: 43px; margin-top: 20px;">
                                <div class="sk-pulse" style="width: 408px; height: 48px; border-radius: 8px; margin-bottom: 8px;"></div>
                                <div class="sk-pulse" style="width: 140px; height: 38px; border-radius: 8px; margin-bottom: 20px;"></div>
                                <div class="sk-pulse" style="width: 340px; height: 8px; border-radius: 4px; margin-bottom: 4px;"></div>
                                <div class="sk-pulse" style="width: 130px; height: 8px; border-radius: 4px; margin-bottom: 18px;"></div>
                            </div>
                            <div class="folder-card2 sk-pulse"></div>
                            <div class="folder-card sk-pulse" style="width: 130px; height: 170px; margin-right: -5px;"></div>
                            <div class="folder-card sk-pulse" style="width: 130px; height: 170px; margin-right: -5px;"></div>
                            <div class="folder-card sk-pulse" style="width: 130px; height: 170px; margin-right: -5px;"></div>
                        </div>
                    </section>

                    <section class="bottom-grid" style="margin-top: -15px;">
                        <div class="card sk-pulse" style="height: 100%; width: 100%; border-radius: 20px;"></div>
                        <div class="card sk-pulse" style="height: 100%; width: 100%; border-radius: 20px;"></div>
                    </section>

                    <?php if (isset($role) && $role === 'siswa'): ?>
                    <section class="bottom-grid" style="margin-top: 20px; flex:1;">
                        <div class="card-badge sk-pulse" style="height: 100%; box-sizing: border-box; border-radius: 20px;"></div>
                        <div class="card-att sk-pulse" style="height: 100%; box-sizing: border-box; border-radius: 20px;"></div>
                    </section>
                    <?php endif; ?>
                </div>

                <!-- 2. SKELETON KHUSUS DAFTAR NILAI -->
                <div id="skel-nilai" style="display: <?= $is_nilai ? 'flex' : 'none' ?>; width: 100%;">
                    <header class="topbar">
                        <div class="topbar-title">
                            <div class="sk-pulse" style="width: 230px; height: 32px; border-radius: 8px; margin-bottom: 2px"></div>
                            <div class="sk-pulse" style="width: 90px; height: 24px; border-radius: 20px; margin-bottom: 2px"></div>
                        </div>
                        <div class="topbar-actions">
			    <div class="sk-pulse" style="width: 200px; height: 40px; border-radius: 20px;"></div>
                            <div class="sk-pulse" style="width: 40px; height: 40px; border-radius: 50%;"></div>
                            <div class="sk-pulse" style="width: 40px; height: 40px; border-radius: 50%;"></div>
                            <div class="sk-pulse" style="width: 90px; height: 40px; border-radius: 7px;"></div>
                        </div>
                    </header>

                    <div class="page-container" style="margin-top: 5px;">
                        <div class="card-box sk-pulse" style="height: 120px; margin-bottom: 0px; flex: none;"></div>
                        
                        <section class="stats-grid">
                            <div class="stat-card sk-pulse" style="height: 90px; border-radius: 12px;"></div>
                            <div class="stat-card sk-pulse" style="height: 90px; border-radius: 12px;"></div>
                            <div class="stat-card sk-pulse" style="height: 90px; border-radius: 12px;"></div>
                            <div class="stat-card sk-pulse" style="height: 90px; border-radius: 12px;"></div>
                        </section> 
                        
                        <div class="card-box sk-pulse" style="flex: 1;"></div> <!-- Ini yang bikin otomatis manjang sisa ruang -->
                    </div>
                </div>

                <!-- 3. SKELETON KHUSUS PROFILE BARU -->
                <div id="skel-profile" style="display: <?= $is_profile ? 'flex' : 'none' ?>; width: 100%;">
                    <!-- TOPBAR PROFILE (Meniru struktur asli profile) -->
                    <header class="topbar">
                        <div class="topbar-title">
                            <div class="sk-pulse" style="width: 140px; height: 32px; border-radius: 8px; margin-bottom: 12px"></div>
                            <div class="sk-pulse" style="width: 120px; height: 24px; border-radius: 20px; margin-bottom: 12px"></div>
                        </div>
                        <div class="topbar-actions">
                            <div class="sk-pulse" style="width: 200px; height: 40px; border-radius: 20px;"></div>
                            <div class="sk-pulse" style="width: 40px; height: 40px; border-radius: 50%;"></div>
                            <div class="sk-pulse" style="width: 40px; height: 40px; border-radius: 50%;"></div>
                            <div class="sk-pulse" style="width: 90px; height: 40px; border-radius: 7px;"></div>
                        </div>
                    </header>

                    <div class="profile-grid" style="margin-top: 15px;">
                        <!-- Kiri: Kartu Identitas Profil -->
                        <div class="profile-card-left sk-pulse" style="display: flex; align-items: center; padding-top: 30px;">
                            <!-- Lingkaran Avatar -->
                            <div class="sk-pulse" style="width: 100px; height: 100px; border-radius: 50%; margin-bottom: 20px; background-color: #94a3b8 !important;"></div>
                            <!-- Baris Teks -->
                            <div class="sk-pulse" style="width: 160px; height: 18px; border-radius: 6px; margin-bottom: 12px; background-color: #94a3b8 !important;"></div>
                            <div class="sk-pulse" style="width: 120px; height: 12px; border-radius: 6px; margin-bottom: 30px; background-color: #94a3b8 !important;"></div>
                            
                            <!-- Progress Bar Mock -->
                            <div class="sk-pulse" style="width: 80%; height: 8px; border-radius: 6px; margin-bottom: 40px; background-color: #94a3b8 !important;"></div>
                            
                            <!-- Detail Meta Data -->
                            <div class="sk-pulse" style="width: 90%; height: 14px; border-radius: 4px; margin-bottom: 16px; background-color: #94a3b8 !important;"></div>
                            <div class="sk-pulse" style="width: 90%; height: 14px; border-radius: 4px; margin-bottom: 16px; background-color: #94a3b8 !important;"></div>
                            <div class="sk-pulse" style="width: 90%; height: 14px; border-radius: 4px; margin-bottom: 16px; background-color: #94a3b8 !important;"></div>
                        </div>

                        <!-- Kanan: Form Data Profil & Github -->
                        <div class="right-content-wrapper">
                            <!-- Kartu Github Integration -->
                            <div class="card-box sk-pulse" style="height: 180px; flex: none;"></div>
                            
                            <!-- Kartu Form Input (Bawah) otomatis manjang -->
                            <div class="card-box sk-pulse" style="flex: 1; border-radius: 16px; margin-top: -18px;"></div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const loader = document.getElementById("page-loader");
    if (!loader) return;

    const hideLoader = () => {
        loader.classList.remove("page-transitioning");
        loader.classList.add("loader-hidden");
    };

    if (document.readyState === 'complete') {
        hideLoader(); 
    } else {
        window.addEventListener("load", hideLoader); 
    }

    setTimeout(hideLoader, 10000); 

    document.body.addEventListener("click", function(e) {
        const link = e.target.closest("a");
        if (!link) return;

        const href = link.getAttribute("href");
        const target = link.getAttribute("target");
        const onclickAttr = link.getAttribute("onclick");

        const targetPages = ["dashboard.php", "daftar_nilai.php", "lihat_nilai.php", "profile.php", "daftar_siswa.php", "daftar_guru.php", "daftar_mapel.php", "daftar_user.php"];
        
        let isTargetValid = false;
        if (href) {
            isTargetValid = targetPages.some(page => href.includes(page));
        }

        const isSpecialLink = target === "_blank" || (href && href.startsWith("#")) || (href && href.startsWith("javascript:"));
        const hasConfirm = onclickAttr && onclickAttr.includes("confirm");

        if (isTargetValid && !isSpecialLink && !hasConfirm) {
            e.preventDefault(); 

            // Cek rute tujuan
            const isToDashboard = href.includes("dashboard.php");
            const isToProfile = href.includes("profile.php");
            
            // Atur Visibilitas Skeleton
            document.getElementById("skel-dash").style.display = isToDashboard ? "flex" : "none";
            document.getElementById("skel-profile").style.display = isToProfile ? "flex" : "none";
            document.getElementById("skel-nilai").style.display = (!isToDashboard && !isToProfile) ? "flex" : "none";

            loader.classList.remove("loader-hidden");
            loader.classList.add("page-transitioning");

            setTimeout(() => {
                window.location.href = href;
            }, 250); 
        }
    });
});
</script>