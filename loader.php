<!-- loader.php -->
<div id="page-loader">
    <div class="loader"></div>
</div>

<style>
#page-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 125vw;
    height: 125vh;
    background-color: #f4f5f8; /* Menyesuaikan background dark web lu */
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 999999;
    transition: opacity 0.5s ease, visibility 0.5s ease;
}

/* Animasi loader bawaan lu */
.loader {
    width: 50px;
    aspect-ratio: 1;
    border-radius: 50%;
    border: 8px solid;
    border-color: #08090d #0000; /* Warna border disesuaiin sama warna aksen (#ccff00) */
    animation: l1 1s infinite linear;
}

@keyframes l1 {
    to { transform: rotate(.5turn); }
}

/* Class buat ngilangin loader pas udah selesai load */
#page-loader.loader-hidden {
    opacity: 0;
    visibility: hidden;
}
</style>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const loader = document.getElementById("page-loader");

        // Hide loader pas page selesai di-load
        window.addEventListener("load", () => {
            if (loader) loader.classList.add("loader-hidden");
        });

        // Auto trigger loader pas form di-submit
        document.querySelectorAll("form").forEach(form => {
            form.addEventListener("submit", () => {
                if (form.checkValidity() && loader) {
                    loader.classList.remove("loader-hidden");
                }
            });
        });

        // Auto trigger loader pas klik link/pindah halaman
        document.querySelectorAll("a:not([target='_blank']):not([href^='#']):not([href^='javascript:'])").forEach(link => {
            link.addEventListener("click", function() {
                const hasConfirm = this.getAttribute("onclick") && this.getAttribute("onclick").includes("confirm");
                if (!hasConfirm && loader && this.getAttribute("href")) {
                    loader.classList.remove("loader-hidden");
                }
            });
        });
    });
</script>