<!-- loader_nilai.php -->
<style>
  /* 1. Kunci Zoom 0.7 sejak awal */
  html { zoom: 0.7; }
  
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
    opacity: 1;
    visibility: visible;
  }

  /* PERBAIKAN UTAMA CSS ANTI-STUCK: Langsung hilangkan total */
  #page-loader.loader-hidden {
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
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
</style>

<!-- TAG TRIGGER LOADER -->
<div id="page-loader">
  <div class="video" style="min-height: 100%; background: transparent; width: 100%;">
    <div class="dashboard-container">
      <!-- REPLIKA SIDEBAR -->
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
      
      <!-- REPLIKA MAIN CONTENT -->
      <main class="main-content">
        <!-- TOPBAR -->
        <header class="topbar">
          <div class="topbar-title">
            <div class="sk-pulse" style="width: 140px; height: 32px; border-radius: 8px; margin-bottom: 12px"></div>
            <div class="sk-pulse" style="width: 120px; height: 24px; border-radius: 20px; margin-bottom: 12px"></div>
          </div>
          <div class="topbar-actions">
            <div class="sk-pulse" style="width: 40px; height: 40px; border-radius: 50%;"></div>
            <div class="sk-pulse" style="width: 90px; height: 40px; border-radius: 7px;"></div>
          </div>
        </header>
        <div class="page-container" style="margin-top: 20px;">
          <!-- CARD FILTER -->
          <div class="card-box sk-pulse" style="height: 120px; margin-bottom: 20px;"></div>
          <!-- TABLE INPUT NILAI -->
          <div class="card-box sk-pulse" style="height: 400px;"></div>
        </div>
      </main>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const loader = document.getElementById("page-loader");
  if (!loader) return;

  // Cek apakah URL saat ini mengandung halaman nilai
  const currentUrl = window.location.href;
  const isNilaiPage = currentUrl.includes("daftar_nilai.php") || currentUrl.includes("lihat_nilai.php");

  const hideLoader = () => {
    if (!loader.classList.contains("loader-hidden")) {
      loader.classList.add("loader-hidden");
    }
  };

  // 1. Logika Pemuatan Halaman (Anti-Stuck & Cache Friendly)
  if (isNilaiPage) {
    if (document.readyState === 'complete') {
      setTimeout(hideLoader, 500);
    } else {
      window.addEventListener("load", () => {
        setTimeout(hideLoader, 500);
      });
    }
    // Fail-safe darurat (Maksimal 3 detik loader harus hilang apapun yang terjadi)
    setTimeout(hideLoader, 3000);
  } else {
    // Jika tidak di halaman nilai, langsung hilangkan saat DOM siap
    hideLoader();
  }

  // 2. Logika Munculkan Loader Saat Pindah Halaman Menuju Halaman Nilai
  document.body.addEventListener("click", function(e) {
    // Menggunakan Event Delegation agar mendeteksi tag <a> yang dinamis/di dalam tabel
    const link = e.target.closest("a");
    if (!link) return;

    const href = link.getAttribute("href");
    const target = link.getAttribute("target");
    const onclickAttr = link.getAttribute("onclick");

    // Validasi kecocokan URL tujuan
    const isTargetNilai = href && (href.includes("daftar_nilai.php") || href.includes("lihat_nilai.php"));
    const isSpecialLink = target === "_blank" || href.startsWith("#") || href.startsWith("javascript:");
    const hasConfirm = onclickAttr && onclickAttr.includes("confirm");

    if (isTargetNilai && !isSpecialLink && !hasConfirm) {
      loader.classList.remove("loader-hidden");
    }
  });
});
</script>
