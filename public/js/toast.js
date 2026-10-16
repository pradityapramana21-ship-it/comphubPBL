/**
 * Pesan singkat yang muncul lalu hilang.
 *
 * SERVER yang menentukan pesan dan jenisnya (flash message di session).
 * JavaScript ini hanya menampilkannya — supaya pesan penting seperti
 * "penolakan butuh alasan" tidak pernah hilang sebelum dibaca.
 */

const DISPLAY_MS = 6000;

export function initToasts() {
    const stack = document.querySelector('[data-toast-stack]');
    if (!stack) {
        return;
    }

    stack.querySelectorAll('[data-toast]').forEach((toast) => {
        const dismiss = toast.querySelector('[data-toast-dismiss]');

        dismiss?.addEventListener('click', () => toast.remove());

        const timer = setTimeout(() => toast.remove(), DISPLAY_MS);

        // Membaca dengan mouse atau keyboard menunda hilangnya pesan.
        // Seseorang yang sedang membaca tidak boleh kehilangan pesan
        // sebelum sempat dibaca.
        ['mouseenter', 'focusin'].forEach((evt) => {
            toast.addEventListener(evt, () => clearTimeout(timer));
        });
    });
}
