/**
 * Inisialisasi umum: yang dipakai di semua halaman.
 *
 * Semua berkas di public/js dimuat sebagai modul terpisah dengan
 * type="module", lalu diekspor sebagai fungsi init yang dipanggil di
 * bawah. Tidak ada listener yang dipasang dua kali kalau halaman
 * dimuat ulang lewat navigasi parsial.
 */
import { initCountdown } from './countdown.js';
import { initModal } from './modal.js';
import { initToasts } from './toast.js';
import { initFilters } from './filter.js';
import { initValidation } from './form-validation.js';
import { initBookmark } from './bookmark.js';

/* ------------------------------------------------------------ Navbar --- */

function initNavbar() {
    const toggle = document.querySelector('[data-navbar-toggle]');
    const menu = document.querySelector('[data-navbar-menu]');

    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            const open = menu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Menu akun: tutup saat klik di luar atau tekan Escape.
    const dropdown = document.querySelector('[data-dropdown]');

    if (dropdown) {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const panel = dropdown.querySelector('[data-dropdown-panel]');

        trigger?.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = panel.classList.toggle('is-open');
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', (event) => {
            if (!dropdown.contains(event.target)) {
                panel?.classList.remove('is-open');
                trigger?.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                panel?.classList.remove('is-open');
                trigger?.setAttribute('aria-expanded', 'false');
                trigger?.focus();
            }
        });
    }
}

/* ------------------------------------------------- Konfirmasi tindakan --- */

/**
 * Setiap aksi yang merusak data memakai tombol yang membuka modal
 * konfirmasi, bukan confirm() bawaan browser.
 *
 * confirm() tidak bisa ditata gayanya, tidak konsisten antarperamban, dan
 * memblokir seluruh halaman — jadi dipakai sebagai cadangan saja kalau
 * JavaScript gagal dimuat.
 */
function initConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') {
                return;
            }

            const message = form.dataset.confirm || 'Lanjutkan tindakan ini?';

            if (!window.confirm(message)) {
                event.preventDefault();
            } else {
                form.dataset.confirmed = '1';
            }
        });
    });
}

/* --------------------------------------------------------------- Jalan --- */

function boot() {
    initNavbar();
    initConfirmForms();
    initCountdown();
    initModal();
    initToasts();
    initFilters();
    initValidation();
    initBookmark();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

export { boot };
