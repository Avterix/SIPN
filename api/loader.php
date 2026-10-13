<?php
/*
 * loader.php — skeleton loader bersama (dashboard + nilai)
 *
 * Cara pakai: di halaman yang meng-include, set dulu halaman aktifnya.
 *   dashboard.php    : <?php $loader_page = 'dashboard'; include "loader.php"; ?>
 *   daftar_nilai.php : <?php $loader_page = 'nilai';     include "loader.php"; ?>
 *
 * - Saat halaman dibuka, skeleton halaman itu sendiri yang tampil.
 * - Saat klik link ke dashboard.php / daftar_nilai.php, skeleton TUJUAN
 *   langsung muncul di halaman sekarang, jadi transisinya nyambung.
 * - Halaman lain (profile, dll) tidak include file ini = tidak ada loader.
 */
$loader_page = $loader_page ?? '';
if (!in_array($loader_page, ['dashboard', 'nilai'], true)) return;
?>
<!-- loader.php -->
<style>
/* 1. Kunci Zoom 0.7 sejak awal */
html {
    zoom: 0.7;
}

#page-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: calc(100vw / 0.7);
    height: calc(100vh / 0.7);
    height: calc(100dvh / 0.7);
    background-color: var(--bg-main, #f4f5f8);
    z-index: 999999;

    padding: 20px;
    box-sizing: border-box;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    overflow: hidden;

    /* muncul cepat (dipakai saat klik menu) */
    transition: opacity 0.15s ease-out, visibility 0s 0s;
}

/* hilang pelan */
#page-loader.loader-hidden {
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.6s ease-out, visibility 0s 0.6s;
}

/* 2. Hentikan animasi asli di dalam loader agar skeleton tidak melompat */
#page-loader * {
    animation: none !important;
    transition: none !important;
}

/* 3. Animasi Pulse Custom untuk balok Skeleton */
@keyframes skPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

#page-loader .sk-pulse {
    background-image: none !important;
    background-color: #cbd5e1 !important;
    color: transparent !important;
    border-color: transparent !important;
    box-shadow: none !important;
    animation: skPulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite !important;
}

#page-loader .sidebar .sk-pulse {
    background-color: #2d3748 !important;
}

#page-loader .sidebar {
    pointer-events: none !important;
}

/* 4. Ganti-ganti view skeleton (dashboard / nilai) */
#page-loader .sk-view { display: none; }
#page-loader .sk-view.active { display: flex; }

/* kartu putih untuk skeleton halaman nilai */
#page-loader .sk-card {
    background: var(--card-bg, #ffffff);
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 16px;
    padding: 24px;
    box-sizing: border-box;
}
</style>

<div id="page-loader">
    <div class="video" style="min-height: 100%; background: transparent; width: 100%;">
        <div class="dashboard-container">

            <!-- SIDEBAR (dipakai bareng) -->
            <aside class="sidebar">
                <div class="sidebar-brand">
                    <div class="brand-logo sk-pulse"></div>
                </div>
                <div class="user-profile-mini">
                    <div class="user-avatar-initial sk-pulse" style="border: none;"></div>
                </div>
                <ul class="sidebar-menu">
                    <li><div class="sk-pulse" style="width: 44px; height: 44px; border-radius: 12px;"></div></li>
                    <li><div class="sk-pulse" style="width: 44px; height: 44px; border-radius: 12px;"></div></li>
                    <li><div class="sk-pulse" style="width: 44px; height: 44px; border-radius: 12px;"></div></li>
                </ul>
                <div class="sidebar-footer">
                    <div class="add-files-box sk-pulse" style="width: 44px; height: 44px; padding: 0; border: none;"></div>
                </div>
            </aside>

            <!-- ================= VIEW: DASHBOARD ================= -->
            <main class="main-content sk-view <?= $loader_page === 'dashboard' ? 'active' : ''; ?>" data-view="dashboard">
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
                        <div class="hero-text" style="display: flex; flex-direction: column; justify-content: flex-start; margin-right: 50px; margin-top: 20px;">
                            <div class="sk-pulse" style="width: 408px; height: 42px; border-radius: 8px; margin-bottom: 8px;"></div>
                            <div class="sk-pulse" style="width: 140px; height: 42px; border-radius: 8px; margin-bottom: 20px;"></div>
                            <div class="sk-pulse" style="width: 340px; height: 18px; border-radius: 4px; margin-bottom: 4px;"></div>
                            <div class="sk-pulse" style="width: 260px; height: 18px; border-radius: 4px;"></div>
                        </div>
                        <div class="folder-card2 sk-pulse"></div>
                        <div class="folder-card sk-pulse"></div>
                        <div class="folder-card sk-pulse"></div>
                        <div class="folder-card sk-pulse"></div>
                    </div>
                </section>

                <section class="bottom-grid" style="margin-top: -18px;">
                    <div class="card sk-pulse" style="height: 280px;"></div>
                    <div class="card sk-pulse" style="height: 280px;"></div>
                </section>

                <?php if (isset($role) && $role === 'siswa'): ?>
                <section class="bottom-grid" style="margin-top: 20px;">
                    <div class="card-badge sk-pulse" style="height: 300px; box-sizing: border-box;"></div>
                    <div class="card-att sk-pulse" style="height: 300px; box-sizing: border-box;"></div>
                </section>
                <?php endif; ?>
            </main>

            <!-- ================= VIEW: NILAI ================= -->
            <main class="main-content sk-view <?= $loader_page === 'nilai' ? 'active' : ''; ?>" data-view="nilai">
                <header class="topbar">
                    <div class="topbar-title">
                        <div class="sk-pulse" style="width: 240px; height: 32px; border-radius: 8px; margin-bottom: 12px"></div>
                        <div class="sk-pulse" style="width: 110px; height: 24px; border-radius: 20px; margin-bottom: 12px"></div>
                    </div>
                    <div class="topbar-actions">
                        <div class="sk-pulse" style="width: 40px; height: 40px; border-radius: 50%;"></div>
                        <div class="sk-pulse" style="width: 90px; height: 40px; border-radius: 7px;"></div>
                    </div>
                </header>

                <div style="display: flex; flex-direction: column; gap: 20px;">

                    <!-- Skeleton card filter -->
                    <div class="sk-card">
                        <div style="display: flex; gap: 16px; align-items: flex-end;">
                            <div style="flex: 1;">
                                <div class="sk-pulse" style="width: 160px; height: 14px; border-radius: 4px; margin-bottom: 8px;"></div>
                                <div class="sk-pulse" style="width: 100%; height: 40px; border-radius: 8px;"></div>
                            </div>
                            <div style="flex: 1;">
                                <div class="sk-pulse" style="width: 90px; height: 14px; border-radius: 4px; margin-bottom: 8px;"></div>
                                <div class="sk-pulse" style="width: 100%; height: 40px; border-radius: 8px;"></div>
                            </div>
                            <div class="sk-pulse" style="width: 170px; height: 40px; border-radius: 8px;"></div>
                        </div>
                    </div>

                    <!-- Skeleton card tabel nilai -->
                    <div class="sk-card">
                        <div class="sk-pulse" style="width: 300px; height: 20px; border-radius: 6px; margin-bottom: 20px;"></div>

                        <!-- header tabel -->
                        <div style="display: flex; gap: 16px; align-items: center; padding: 12px 14px; margin-bottom: 8px;">
                            <div class="sk-pulse" style="width: 30px; height: 16px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="width: 90px; height: 16px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="flex: 1; height: 16px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="width: 70px; height: 16px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="width: 90px; height: 16px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="width: 90px; height: 16px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="width: 70px; height: 16px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="width: 120px; height: 16px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="width: 80px; height: 16px; border-radius: 4px;"></div>
                        </div>

                        <!-- baris tabel -->
                        <?php for ($i = 0; $i < 8; $i++): ?>
                        <div style="display: flex; gap: 16px; align-items: center; padding: 8px 14px;">
                            <div class="sk-pulse" style="width: 30px; height: 18px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="width: 90px; height: 18px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="flex: 1; height: 18px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="width: 70px; height: 30px; border-radius: 6px;"></div>
                            <div class="sk-pulse" style="width: 90px; height: 30px; border-radius: 6px;"></div>
                            <div class="sk-pulse" style="width: 90px; height: 30px; border-radius: 6px;"></div>
                            <div class="sk-pulse" style="width: 70px; height: 18px; border-radius: 4px;"></div>
                            <div class="sk-pulse" style="width: 120px; height: 30px; border-radius: 6px;"></div>
                            <div class="sk-pulse" style="width: 80px; height: 30px; border-radius: 6px;"></div>
                        </div>
                        <?php endfor; ?>
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

    // Peta nama file -> view skeleton
    const VIEWS = {
        "dashboard.php":   "dashboard",
        "daftar_nilai.php": "nilai"
    };

    const showView = (name) => {
        loader.querySelectorAll(".sk-view").forEach(v => {
            v.classList.toggle("active", v.dataset.view === name);
        });
    };

    const hideLoader = () => {
        if (!loader.classList.contains("loader-hidden")) {
            loader.classList.add("loader-hidden");
        }
    };

    // Kalau balik lewat tombol Back (bfcache), pastikan loader tidak nyangkut
    window.addEventListener("pageshow", (e) => {
        if (e.persisted) hideLoader();
    });

    // Sembunyikan loader saat halaman siap
    window.addEventListener("load", () => { setTimeout(hideLoader, 150); });
    if (document.readyState === "complete") { setTimeout(hideLoader, 150); }
    setTimeout(hideLoader, 3000); // Safety fallback

    // Deteksi klik menu: tampilkan skeleton halaman TUJUAN
    document.querySelectorAll("a:not([target='_blank']):not([href^='#']):not([href^='javascript:'])").forEach(link => {
        link.addEventListener("click", function (e) {
            // klik buka tab baru / modifier: jangan munculkan loader
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) return;

            const href = this.getAttribute("href");
            const hasConfirm = this.getAttribute("onclick") && this.getAttribute("onclick").includes("confirm");
            if (!href || hasConfirm) return;

            // ambil nama file saja, abaikan folder, ?query, dan #hash
            const clean = href.split("?")[0].split("#")[0];
            const file  = clean.split("/").pop();

            if (VIEWS[file]) {
                showView(VIEWS[file]);
                loader.classList.remove("loader-hidden");
            }
        });
    });
});
</script>