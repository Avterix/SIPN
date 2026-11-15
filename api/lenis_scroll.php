<!-- Lenis Smooth Scroll - SIPN Core -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lenis@1.1.18/dist/lenis.css">
<style>
    html.lenis,
    html.lenis body {
        height: auto;
    }
    .lenis.lenis-smooth {
        scroll-behavior: auto !important;
    }
    .lenis.lenis-smooth [data-lenis-prevent] {
        overscroll-behavior: contain;
    }
    .lenis.lenis-stopped {
        overflow: hidden;
    }
    .lenis.lenis-smooth iframe {
        pointer-events: none;
    }
    /* Pastikan area Catatan Guru & list rapor bisa di-scroll mulus */
    .rp-notes,
    .rp-note-body,
    .rp-lines,
    .rp-people {
        overscroll-behavior: contain;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/lenis@1.1.18/dist/lenis.min.js"></script>
<script>
(function () {
    // Hormati pengaturan aksesibilitas sistem jika user mematikan animasi
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    function initSipnLenis() {
        if (typeof Lenis === 'undefined' || window.sipnLenis) {
            return;
        }

        // Deteksi otomatis apakah halaman scroll di .main-content atau di window
        var mainEl = document.querySelector('.main-content');
        var useMainWrapper = false;
        if (mainEl) {
            var style = window.getComputedStyle(mainEl);
            var ovY = style.overflowY;
            if ((ovY === 'auto' || ovY === 'scroll') && mainEl.scrollHeight > mainEl.clientHeight + 4) {
                useMainWrapper = true;
            }
        }

        // Lindungi area scroll internal (Catatan guru, ketuntasan, modal, tabel lebar, checklist, textarea)
        var preventSelectors = [
            '.rp-notes',
            '.rp-panel-notes',
            '.rp-note-body',
            '.rp-lines',
            '.rp-people',
            '.rp-scroll',
            '.rp-table-wrap',
            '.modal-card',
            '.rp-modal-box',
            '.siswa-checklist',
            '.sidebar-menu',
            'textarea',
            'select[multiple]'
        ];
        document.querySelectorAll(preventSelectors.join(',')).forEach(function (el) {
            el.setAttribute('data-lenis-prevent', '');
        });

        // Auto-tandai semua elemen lain yang memang punya overflow scroll/auto
        document.querySelectorAll('*').forEach(function (el) {
            if (el === document.documentElement || el === document.body || (useMainWrapper && el === mainEl)) {
                return;
            }
            var st = window.getComputedStyle(el);
            if (st.overflowY === 'auto' || st.overflowY === 'scroll' || st.overflowX === 'auto' || st.overflowX === 'scroll') {
                el.setAttribute('data-lenis-prevent', '');
            }
        });

        var lenisOptions = {
            duration: 1.05,
            easing: function (t) {
                return Math.min(1, 1.001 - Math.pow(2, -10 * t));
            },
            orientation: 'vertical',
            gestureOrientation: 'vertical',
            smoothWheel: true,
            wheelMultiplier: 1,
            touchMultiplier: 1.5,
            // Jangan bajak scroll jika kursor berada di dalam area yang bisa di-scroll sendiri
            prevent: function (node) {
                if (!node || node === document.body || node === document.documentElement) {
                    return false;
                }
                if (useMainWrapper && node === mainEl) {
                    return false;
                }
                if (node.hasAttribute && node.hasAttribute('data-lenis-prevent')) {
                    return true;
                }
                var st = window.getComputedStyle(node);
                var canScrollY = (st.overflowY === 'auto' || st.overflowY === 'scroll') && node.scrollHeight > node.clientHeight + 1;
                var canScrollX = (st.overflowX === 'auto' || st.overflowX === 'scroll') && node.scrollWidth > node.clientWidth + 1;
                return canScrollY || canScrollX;
            }
        };

        if (useMainWrapper) {
            lenisOptions.wrapper = mainEl;
            lenisOptions.content = mainEl;
        }

        var lenis = new Lenis(lenisOptions);
        window.sipnLenis = lenis;

        // Jika .rp-notes pakai scroll horizontal (overflow-x: auto), izinkan scroll pakai roda mouse biasa
        document.querySelectorAll('.rp-notes').forEach(function (notesEl) {
            notesEl.addEventListener('wheel', function (e) {
                var st = window.getComputedStyle(notesEl);
                var canY = (st.overflowY === 'auto' || st.overflowY === 'scroll') && notesEl.scrollHeight > notesEl.clientHeight + 1;
                var canX = (st.overflowX === 'auto' || st.overflowX === 'scroll') && notesEl.scrollWidth > notesEl.clientWidth + 1;

                if (canX && !canY && Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                    var atLeft  = notesEl.scrollLeft <= 0 && e.deltaY < 0;
                    var atRight = notesEl.scrollLeft + notesEl.clientWidth >= notesEl.scrollWidth - 1 && e.deltaY > 0;
                    if (!atLeft && !atRight) {
                        e.preventDefault();
                        notesEl.scrollLeft += e.deltaY;
                    }
                }
            }, { passive: false });
        });

        function raf(time) {
            lenis.raf(time);
            requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);

        // Otomatis pause Lenis saat modal terbuka & jalankan lagi saat modal ditutup
        function syncModalScrollLock() {
            var hasOpenModal = document.querySelector('.modal-backdrop.is-open, .rp-modal-bg.is-open');
            if (hasOpenModal) {
                lenis.stop();
            } else {
                lenis.start();
            }
        }

        var observer = new MutationObserver(syncModalScrollLock);
        document.querySelectorAll('.modal-backdrop, .rp-modal-bg').forEach(function (modal) {
            observer.observe(modal, { attributes: true, attributeFilter: ['class', 'style'] });
        });

        // Smooth scroll untuk link anchor (#pengumpulan-tugas, #panel-penilaian, #rekap-rapor, dll.)
        document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
            anchor.addEventListener('click', function (e) {
                var targetId = anchor.getAttribute('href');
                if (!targetId || targetId === '#') return;
                var targetEl = document.querySelector(targetId);
                if (targetEl) {
                    e.preventDefault();
                    lenis.scrollTo(targetEl, { offset: -20 });
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSipnLenis);
    } else {
        initSipnLenis();
    }
})();
</script>