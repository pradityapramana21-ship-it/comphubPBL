/**
 * Tombol Simpan tanpa memuat ulang halaman.
 *
 * Aset Laravel mengirim XSRF-TOKEN, dan setiap request memakai
 * header Accept: JSON
 * supaya kesalahan validasi kembali sebagai JSON, bukan sebagai halaman
 * HTML seukuran layar penuh.
 */

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

async function toggle(button) {
    if (button.dataset.busy === '1') {
        return;
    }

    button.dataset.busy = '1';
    button.disabled = true;

    const url = button.dataset.bookmarkUrl;
    const saved = button.dataset.saved === '1';

    // Rute simpan dan hapus memakai URI yang sama persis; yang membedakan
    // hanya metodenya. Jadi satu data atribut cukup untuk keduanya.
    const method = saved ? 'DELETE' : 'POST';

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();
        const nowSaved = Boolean(payload.saved);

        // Semua tombol untuk kegiatan ini ikut berubah, karena halaman
        // detail bisa punya lebih dari satu (mis. di header dan di sidebar).
        document.querySelectorAll(`[data-bookmark-url="${url}"]`).forEach((other) => {
            other.dataset.saved = nowSaved ? '1' : '0';
            render(other, nowSaved);
        });

        updateCounter(nowSaved);
    } catch (error) {
        console.error('Gagal menyimpan kegiatan:', error);

        // Kembalikan tombol ke keadaan semula supaya tampilan tidak
        // berbohong. Memuat ulang halaman lebih jujur daripada
        // menampilkan "Tersimpan" padahal tidak.
        window.location.reload();
    } finally {
        button.disabled = false;
        button.dataset.busy = '0';
    }
}

function render(button, saved) {
    const label = button.querySelector('[data-bookmark-label]');
    const icon = button.querySelector('[data-bookmark-icon]');

    if (label) {
        label.textContent = saved ? 'Tersimpan' : 'Simpan';
    }

    if (icon) {
        icon.setAttribute('fill', saved ? 'currentColor' : 'none');
    }

    button.setAttribute('aria-pressed', saved ? 'true' : 'false');
}

function updateCounter(saved) {
    const counter = document.querySelector('[data-bookmark-counter]');

    if (!counter) {
        return;
    }

    const current = Number(counter.textContent || '0');
    counter.textContent = String(Math.max(0, current + (saved ? 1 : -1)));
}

export function initBookmark() {
    document.querySelectorAll('[data-bookmark-url]').forEach((button) => {
        render(button, button.dataset.saved === '1');

        button.addEventListener('click', (event) => {
            event.preventDefault();
            toggle(button);
        });
    });
}
