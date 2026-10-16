/**
 * Modal konfirmasi.
 *
 * Dipakai untuk aksi yang merusak atau tidak bisa dibatalkan. Fokus
 * dikembalikan ke pemicu setelah ditutup, dan Escape menutup — dua hal
 * yang wajib untuk dialog yang bisa dioperasikan keyboard (Panduan WCAG).
 */

let lastTrigger = null;

function focusableIn(container) {
    return container.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    );
}

export function openModal(backdrop) {
    if (!backdrop) {
        return;
    }

    lastTrigger = document.activeElement;
    backdrop.classList.add('is-open');
    backdrop.setAttribute('aria-hidden', 'false');

    const first = focusableIn(backdrop)[0];
    first?.focus();
}

export function closeModal(backdrop) {
    if (!backdrop) {
        return;
    }

    backdrop.classList.remove('is-open');
    backdrop.setAttribute('aria-hidden', 'true');
    lastTrigger?.focus();
    lastTrigger = null;
}

export function initModal() {
    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openModal(document.getElementById(trigger.dataset.modalOpen));
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.modal-backdrop-lk')));
    });

    document.querySelectorAll('.modal-backdrop-lk').forEach((backdrop) => {
        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) {
                closeModal(backdrop);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.modal-backdrop-lk.is-open').forEach(closeModal);
    });
}
