<!-- loader.php -->
<?php
// Deteksi halaman saat ini (dipakai HANYA untuk fallback skeleton statis
// kalau halaman belum pernah dikunjungi / belum ada cache bentuknya)
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

/* ---- RESPONSIVE (samain breakpoint style1.css: 1400 / 1024 / 768) ---- */
/* <=768px: style1.css matiin zoom (zoom:1), body padding jadi 10px -> loader harus ikut */
@media (max-width: 768px) {
    html { zoom: 1 !important; }
    #page-loader {
        width: 100vw; height: 100vh; height: 100dvh;
        padding: 10px;
    }
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
   SKELETON OTOMATIS: kotak-kotak hasil "belajar" dari halaman asli
   ========================================================= */
#skel-auto { position: absolute; top: 0; left: 0; width: 100%; pointer-events: none; }
#skel-auto .sk-box { position: absolute; box-sizing: border-box; }
#page-loader #skel-auto .sk-box.sk-n { background-color: #b9c4d3 !important; }  /* card di dalam card */
#page-loader #skel-auto .sk-box.sk-t { background-color: #94a3b8 !important; }  /* teks / icon di dalam card */

/* =========================================================
   FALLBACK (skeleton statis lama): Mengunci bentuk Grid/Flex agar tidak hancur 
   saat dipanggil dari file PHP yang berbeda CSS-nya
   ========================================================= */
#page-loader .page-container { display: flex; flex-direction: column; gap: 20px; width: 100%; }
#page-loader .card-box { width: 100%; border-radius: 16px; }
#page-loader .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; width: 100%; }
#page-loader .folders-slider { display: flex; gap: 20px; width: 100%; }
#page-loader .bottom-grid { display: grid; grid-template-columns: 320px 1fr; gap: 20px; width: 100%; }

/* Nama brand di navbar atas (muncul pas sidebar berubah jadi navbar, <=1024px) */
#page-loader .sk-brand-name { display: none; width: 56px; height: 14px; border-radius: 6px; }
@media (max-width: 1024px) {
    #page-loader .sidebar-brand { display: flex; align-items: center; gap: 12px; }
    #page-loader .sk-brand-name { display: block; }
}

/* Biar skeleton statis (fallback) nggak keluar layar */
#page-loader .main-content .sk-pulse { max-width: 100%; }

/* Fallback statis responsif */
@media (max-width: 900px) {
    #page-loader .profile-grid { grid-template-columns: 1fr !important; }
    #page-loader .profile-card-left { width: 100% !important; height: 380px !important; }
    #page-loader .form-grid-2 { grid-template-columns: 1fr !important; }
}
@media (max-width: 768px) {
    #page-loader .stats-grid { grid-template-columns: repeat(2, 1fr); }
    #page-loader .hero-text { grid-column: 1 / -1; margin-right: 0 !important; width: 100%; }
    #page-loader .page-container .card-box { max-width: 100%; }
}

/* Penahan Grid Profil Baru */
#page-loader .profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; width: 100%; }
#page-loader .profile-card-left { display: flex; flex-direction: column; align-items: center; background-color: #ffffff; border-radius: 16px; padding: 24px; }
#page-loader .right-content-wrapper { display: flex; flex-direction: column; gap: 20px; width: 100%; }
#page-loader .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
</style>

<div id="page-loader">
    <div class="video" style="min-height: 100%; background: transparent; width: 100%;">
        <div class="dashboard-container">
            
            <!-- REPLIKA SIDEBAR (GLOBAL) -->
            <aside class="sidebar">
                <div class="sidebar-brand"><div class="brand-logo sk-pulse"></div><div class="sk-brand-name sk-pulse"></div></div>
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
            <main class="main-content" id="skel-main">

                <!-- 0. SKELETON OTOMATIS (diisi JS dari cache bentuk halaman asli) -->
                <div id="skel-auto" style="display: none;"></div>
                
                <!-- 1. SKELETON KHUSUS DASHBOARD (FALLBACK) -->
                <div id="skel-dash" style="display: <?= $is_dashboard ? 'block' : 'none' ?>; width: 100%;">
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
                        <div class="card sk-pulse" style="height: 280px; width: 100%; border-radius: 20px;"></div>
                        <div class="card sk-pulse" style="height: 280px; width: 100%; border-radius: 20px;"></div>
                    </section>

                    <?php if (isset($role) && $role === 'siswa'): ?>
                    <section class="bottom-grid" style="margin-top: 20px;">
                        <div class="card-badge sk-pulse" style="height: 300px; box-sizing: border-box; border-radius: 20px;"></div>
                        <div class="card-att sk-pulse" style="height: 300px; box-sizing: border-box; border-radius: 20px;"></div>
                    </section>
                    <?php endif; ?>
                </div>

                <!-- 2. SKELETON KHUSUS DAFTAR NILAI (FALLBACK) -->
                <div id="skel-nilai" style="display: <?= $is_nilai ? 'block' : 'none' ?>; width: 100%;">
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
                        <div class="card-box sk-pulse" style="height: 120px; margin-bottom: 0px;"></div>
                        
                        <section class="stats-grid">
                            <div class="stat-card sk-pulse" style="height: 90px; border-radius: 12px;"></div>
                            <div class="stat-card sk-pulse" style="height: 90px; border-radius: 12px;"></div>
                            <div class="stat-card sk-pulse" style="height: 90px; border-radius: 12px;"></div>
                            <div class="stat-card sk-pulse" style="height: 90px; border-radius: 12px;"></div>
                        </section> 
                        
                        <div class="card-box sk-pulse" style="height: 267px;"></div>
                    </div>
                </div>

                <!-- 3. SKELETON KHUSUS PROFILE (FALLBACK) -->
                <div id="skel-profile" style="display: <?= $is_profile ? 'block' : 'none' ?>; width: 100%;">
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
                        <div class="profile-card-left sk-pulse" style="height: 737px; width: 320px; display: flex; align-items: center; padding-top: 30px;">
                            <div class="sk-pulse" style="width: 100px; height: 100px; border-radius: 50%; margin-bottom: 20px; background-color: #94a3b8 !important;"></div>
                            <div class="sk-pulse" style="width: 160px; height: 18px; border-radius: 6px; margin-bottom: 12px; background-color: #94a3b8 !important;"></div>
                            <div class="sk-pulse" style="width: 120px; height: 12px; border-radius: 6px; margin-bottom: 30px; background-color: #94a3b8 !important;"></div>
                            <div class="sk-pulse" style="width: 80%; height: 8px; border-radius: 6px; margin-bottom: 40px; background-color: #94a3b8 !important;"></div>
                            <div class="sk-pulse" style="width: 90%; height: 14px; border-radius: 4px; margin-bottom: 16px; background-color: #94a3b8 !important;"></div>
                            <div class="sk-pulse" style="width: 90%; height: 14px; border-radius: 4px; margin-bottom: 16px; background-color: #94a3b8 !important;"></div>
                            <div class="sk-pulse" style="width: 90%; height: 14px; border-radius: 4px; margin-bottom: 16px; background-color: #94a3b8 !important;"></div>
                        </div>

                        <div class="right-content-wrapper">
                            <div class="card-box sk-pulse" style="height: 210px; border-radius: 16px;"></div>
                            <div class="card-box sk-pulse" style="height: 502px; border-radius: 16px; margin-top: -18px;"></div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
</div>

<script>
/* =========================================================
   AUTO SKELETON
   - capture(): ukur bentuk asli halaman ini -> simpan di localStorage
   - renderAuto(): bangun skeleton dari hasil ukur halaman tujuan
   Semua koordinat horizontal disimpan dalam % lebar .main-content,
   vertikal dalam px (sudah dikoreksi zoom 0.7), jadi tetap pas
   walau ukuran layar beda.
   ========================================================= */
(function () {
    var PREFIX = 'skelcache:v4:';
    var MAX_ITEMS = 700;
    var STATIC_IDS = ['skel-dash', 'skel-nilai', 'skel-profile'];
    var SKIP_TAGS = { SCRIPT:1, STYLE:1, LINK:1, TEMPLATE:1, NOSCRIPT:1, BR:1, HR:1, META:1 };
    var REPLACED_TAGS = { IMG:1, SVG:1, INPUT:1, SELECT:1, TEXTAREA:1, BUTTON:1, CANVAS:1, VIDEO:1, I:1 };

    /* ---------- cache ---------- */
    // Layout beda di tiap breakpoint style1.css (1400 / 1024 / 768),
    // jadi bentuk skeleton disimpan terpisah per tier.
    function tier() {
        var w = window.innerWidth;
        if (w <= 768)  return 'sm';   // HP: zoom 1, navbar atas, 1 kolom
        if (w <= 1024) return 'md';   // tablet: navbar atas, container column
        if (w <= 1400) return 'lg';   // laptop kecil: container max 1200
        return 'xl';                  // desktop: container max 1380
    }
    function cacheKeys(url) {
        var t = tier() + ':';
        return [PREFIX + t + url.pathname + url.search, PREFIX + t + url.pathname];
    }
    function readCache(url) {
        var ks = cacheKeys(url);
        for (var i = 0; i < ks.length; i++) {
            try {
                var raw = localStorage.getItem(ks[i]);
                if (!raw) continue;
                var d = JSON.parse(raw);
                if (d && d.items && d.items.length) return d;
            } catch (e) {}
        }
        return null;
    }
    function writeCache(data) {
        try {
            var s = JSON.stringify(data);
            var t = tier() + ':';
            localStorage.setItem(PREFIX + t + location.pathname + location.search, s);
            localStorage.setItem(PREFIX + t + location.pathname, s);
        } catch (e) {}
    }

    /* ---------- render ---------- */
    function showStatic(activeId) {
        STATIC_IDS.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.style.display = (id === activeId) ? 'block' : 'none';
        });
        var auto = document.getElementById('skel-auto');
        if (auto) auto.style.display = 'none';
        var main = document.getElementById('skel-main');
        if (main) main.style.minHeight = '';
    }

    function renderAuto(data) {
        var host = document.getElementById('skel-auto');
        var main = document.getElementById('skel-main');
        if (!host || !main || !data || !data.items) return false;

        if (getComputedStyle(main).position === 'static') main.style.position = 'relative';

        var frag = document.createDocumentFragment();
        data.items.forEach(function (it) {
            var d = document.createElement('div');
            d.className = 'sk-box sk-pulse' + (it.c === 't' ? ' sk-t' : (it.c === 'n' ? ' sk-n' : ''));
            d.style.left = it.x + '%';
            d.style.width = it.w + '%';
            d.style.top = it.y + 'px';
            d.style.height = it.h + 'px';
            d.style.borderRadius = it.r;
            frag.appendChild(d);
        });

        host.innerHTML = '';
        host.appendChild(frag);
        host.style.height = data.h + 'px';
        host.style.display = 'block';

        // Halaman asli: body pakai flex + align-items:center + min-height 142.68vh,
        // jadi kalau konten pendek (mis. profile) .main-content duduk di TENGAH, bukan di atas.
        // Loader ini align-items:flex-start (selalu di atas), jadi selisihnya kita kompensasi di sini.
        var pl = document.getElementById('page-loader');
        var lm = main.getBoundingClientRect();
        var lr = pl.getBoundingClientRect();
        var lk = (main.offsetWidth ? lm.width / main.offsetWidth : 1) || 1;
        var mainTopInLoader = (lm.top - lr.top) / lk;
        host.style.top = ((data.ty || 0) - mainTopInLoader) + 'px';
        main.style.minHeight = data.h + 'px';

        STATIC_IDS.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
        return true;
    }

    /* ---------- capture ---------- */
    function findRealMain() {
        var list = document.querySelectorAll('.main-content');
        for (var i = 0; i < list.length; i++) {
            if (!list[i].closest('#page-loader')) return list[i];
        }
        return null;
    }

    function isTransparent(c) {
        return !c || c === 'transparent' || /,\s*0\)$/.test(c) || /\/\s*0\)$/.test(c);
    }

    function capture() {
        var main = findRealMain();
        if (!main || !main.offsetWidth) return;

        var mr = main.getBoundingClientRect();
        if (!mr.width) return;
        // k = faktor skala (zoom). Dipakai biar angka px tetap benar
        // di browser yang melaporkan rect dalam koordinat ter-zoom.
        var k = mr.width / main.offsetWidth || 1;

        // Jarak atas .main-content dari ujung atas body (sudah termasuk efek "ketengah" dari flex body)
        var bodyRect = document.body.getBoundingClientRect();
        var ty = (mr.top - bodyRect.top) / k;

        var items = [];

        // kind: 'c' = card/elemen biasa, 'n' = card di dalam card, 't' = teks/icon di dalam card
        function push(r, radius, kind, shrink) {
            if (items.length >= MAX_ITEMS) return;
            var w = r.width / k, h = r.height / k;
            if (w < 4 || h < 4) return;
            var top = ((r.top - mr.top) / k) + main.scrollTop;
            if (shrink) { top += h * 0.15; h = h * 0.7; }   // bar teks dibuat sedikit lebih tipis dari line box
            items.push({
                x: +(((r.left - mr.left) / mr.width) * 100).toFixed(3),
                w: +((r.width / mr.width) * 100).toFixed(3),
                y: +top.toFixed(1),
                h: +h.toFixed(1),
                r: radius,
                c: kind
            });
        }

        function looksLikeCard(cs, allowBorder) {
            if (!isTransparent(cs.backgroundColor)) return true;
            if (cs.backgroundImage && cs.backgroundImage !== 'none') return true;
            if (cs.boxShadow && cs.boxShadow !== 'none') return true;
            if (allowBorder && parseFloat(cs.borderTopWidth) > 0 && cs.borderTopStyle !== 'none' && !isTransparent(cs.borderTopColor)) return true;
            return false;
        }

        // Teks langsung milik elemen ini -> satu bar per BARIS (paragraf panjang jadi beberapa bar)
        function ownText(el, inCard) {
            for (var n = el.firstChild; n; n = n.nextSibling) {
                if (n.nodeType !== 3 || !n.nodeValue.trim()) continue;
                var range = document.createRange();
                range.selectNodeContents(n);
                var rects = range.getClientRects();
                for (var j = 0; j < rects.length; j++) {
                    push(rects[j], '4px', inCard ? 't' : 'c', true);
                }
            }
        }

        function radiusOf(cs, fallback) {
            return (cs.borderRadius && cs.borderRadius !== '0px') ? cs.borderRadius : fallback;
        }

        // depth = jumlah card yang membungkus elemen ini
        function walk(parent, depth) {
            var kids = parent.children;
            for (var i = 0; i < kids.length; i++) {
                if (items.length >= MAX_ITEMS) return;
                var el = kids[i];
                var tag = el.tagName.toUpperCase();
                if (SKIP_TAGS[tag]) continue;
                if (el.hasAttribute('hidden')) continue;

                var cs = getComputedStyle(el);
                if (cs.display === 'none' || cs.visibility === 'hidden') continue;
                if (cs.position === 'fixed') continue;          // modal, toast, dll
                if (parseFloat(cs.opacity) === 0) continue;

                var r = el.getBoundingClientRect();
                if (r.width / k < 4 || r.height / k < 4) {
                    walk(el, depth);                             // wrapper kosong? cek anaknya
                    continue;
                }

                var isBig = (r.width >= mr.width * 0.9) && (r.height >= (main.scrollHeight * k) * 0.8);

                // 1) Card / kotak ber-background -> slab, TAPI tetap turun ke dalamnya buat teks & isi
                if (!isBig && r.width / k >= 16 && r.height / k >= 16 && looksLikeCard(cs, depth === 0)) {
                    push(r, radiusOf(cs, '0px'), depth ? 'n' : 'c', false);
                    ownText(el, true);
                    walk(el, depth + 1);
                    continue;
                }

                // 2) Elemen "replaced" (img, input, button, icon, dll)
                if (REPLACED_TAGS[tag]) {
                    push(r, radiusOf(cs, '6px'), depth ? 't' : 'c', false);
                    continue;
                }

                // 3) Elemen biasa: ambil teks langsungnya, lalu turun ke anak-anaknya
                ownText(el, depth > 0);
                walk(el, depth);
            }
        }

        walk(main, 0);
        if (!items.length) return;

        writeCache({
            h: +Math.max(main.scrollHeight, mr.height / k).toFixed(1),
            ty: +ty.toFixed(1),
            items: items,
            t: Date.now()
        });
    }

    /* ---------- expose ---------- */
    window.__skel = { readCache: readCache, renderAuto: renderAuto, showStatic: showStatic, capture: capture };

    /* ---------- render awal (sinkron, sebelum konten halaman ke-parse) ---------- */
    var first = readCache(new URL(location.href));
    if (first) renderAuto(first);
})();
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const loader = document.getElementById("page-loader");
    if (!loader) return;

    const hideLoader = () => {
        loader.classList.remove("page-transitioning");
        loader.classList.add("loader-hidden");
    };

    // Simpan bentuk halaman ini setelah semuanya (gambar, font, data) settle
    const doCapture = () => { try { window.__skel.capture(); } catch (e) {} };
    window.addEventListener("load", () => setTimeout(doCapture, 700));
    window.addEventListener("beforeunload", doCapture); // update terakhir sebelum pindah halaman

    // Layar di-resize / HP diputar -> simpan ulang biar breakpoint barunya langsung kecatat
    let resizeTimer;
    window.addEventListener("resize", () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(doCapture, 500);
    });

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

            // 1) Coba skeleton otomatis dari bentuk asli halaman tujuan
            let usedAuto = false;
            try {
                const dest = new URL(href, location.href);
                const cached = window.__skel.readCache(dest);
                if (cached) usedAuto = window.__skel.renderAuto(cached);
            } catch (err) {}

            // 2) Belum pernah dikunjungi -> fallback skeleton statis lama
            if (!usedAuto) {
                const isToDashboard = href.includes("dashboard.php");
                const isToProfile = href.includes("profile.php");
                const active = isToDashboard ? "skel-dash" : (isToProfile ? "skel-profile" : "skel-nilai");
                window.__skel.showStatic(active);
            }

            loader.classList.remove("loader-hidden");
            loader.classList.add("page-transitioning");

            setTimeout(() => {
                window.location.href = href;
            }, 250); 
        }
    });
});
</script>