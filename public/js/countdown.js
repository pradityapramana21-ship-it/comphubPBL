/**
 * Penghitung mundur tenggat pendaftaran.
 *
 * Elemennya ditulis sebagai:
 *   <span data-countdown="2026-11-12T09:00:00+07:00" data-deadline-tone="warning">
 *
 * Server yang menentukan SUDAH atau belum lewat — ia yang punya timezone
 * yang benar. JavaScript di sini hanya menampilkan hitungan yang
 * tersisa, dan selalu menyebut tanggal lengkap supaya tidak menyesatkan
 * ketika hitungannya nol.
 */

const HOUR = 3600;
const DAY = 86400;

function pad(value) {
    return String(value).padStart(2, '0');
}

export function formatRemaining(ms) {
    if (ms <= 0) {
        return 'Pendaftaran ditutup';
    }

    const totalMinutes = Math.floor(ms / 60000);
    const days = Math.floor(totalMinutes / (60 * 24));
    const hours = Math.floor((totalMinutes % (60 * 24)) / 60);
    const minutes = totalMinutes % 60;

    if (days >= 1) {
        return `${days} hari ${pad(hours)} jam lagi`;
    }

    if (totalMinutes < HOUR / 60) {
        return `${Math.max(minutes, 1)} menit lagi`;
    }

    return `${pad(hours)} jam ${pad(minutes)} menit lagi`;
}

function humanDate(iso) {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(date);
}

export function initCountdown() {
    const nodes = document.querySelectorAll('[data-countdown]');
    if (nodes.length === 0) {
        return;
    }

    const tick = () => {
        const now = Date.now();

        nodes.forEach((node) => {
            const iso = node.dataset.countdown;
            const target = new Date(iso).getTime();

            if (Number.isNaN(target)) {
                node.textContent = '—';
                return;
            }

            const remaining = target - now;
            const full = humanDate(iso);

            node.textContent = remaining > 0 ? formatRemaining(remaining) : 'Pendaftaran ditutup';

            // Tanggal lengkap selalu ada sebagai teks nyata, bukan cuma
            // atribut title: sebagian pembaca layar dan sebagian orang
            // tidak pernah mouse-over sebuah span.
            const hint = node.querySelector('[data-countdown-hint]');
            if (hint) {
                hint.textContent = full;
                hint.hidden = remaining > 0;
            }

            if (remaining <= 0) {
                node.dataset.expired = '1';
                if (node.dataset.toneOnExpire) {
                    node.className = `deadline deadline--${node.dataset.toneOnExpire}`;
                }
                node.dataset.countdown = 'done';
            }
        });
    };

    tick();
    // Minute awal memakai detik yang bulat supaya penghitung tidak terlihat
    // berkedip setiap detik dan mengganggu pembaca layar.
    setInterval(tick, 30000);
}

export { DAY, HOUR };
