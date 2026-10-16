/**
 * Validasi form di sisi peramban.
 *
 * PENTING: ini hanya kenyamanan. Setiap aturan yang benar-benar menentukan
 * data DIVALIDASI ULANG DI SERVER oleh Form Request. Meltrustvalidasi
 * peramban untuk sebuah aturan bisnis berarti siapa pun bisa melewati
 * aturan itu dengan mematikan JavaScript (Panduan §4.5).
 *
 * Yang dilakukan file ini:
 *  1. Menandai field yang kosong/wajib dengan pesan jelas.
 *  2. Menampilkan jumlah karakter tersisa di bawah textarea.
 *  3. Memeriksa urutan tanggal: tenggat tidak boleh melewati mulai.
 */

const MESSAGES = {
    required: 'Kolom ini wajib diisi.',
    email: 'Format email belum benar.',
    minLength: (n) => `Minimal ${n} karakter.`,
    maxLength: (n) => `Maksimal ${n} karakter.`,
    pastDate: 'Tanggal ini sudah lewat.',
    dateOrder: 'Tenggat pendaftaran tidak boleh melewati waktu kegiatan dimulai.',
    url: 'Tautan harus diawali http:// atau https://',
};

function fieldWrap(field) {
    return field.closest('.form-field') ?? field.parentElement;
}

function showError(field, message) {
    field.classList.add('is-invalid');
    field.setAttribute('aria-invalid', 'true');

    const wrap = fieldWrap(field);
    if (!wrap) {
        return;
    }

    let holder = wrap.querySelector('[data-error-for]');
    if (!holder) {
        holder = document.createElement('p');
        holder.className = 'form-error';
        holder.setAttribute('data-error-for', field.name);
        wrap.appendChild(holder);
    }
    holder.textContent = message;
}

function clearError(field) {
    field.classList.remove('is-invalid');
    field.removeAttribute('aria-invalid');
    fieldWrap(field)?.querySelector('[data-error-for]')?.remove();
}

function validateField(field) {
    clearError(field);

    const value = String(field.value ?? '').trim();

    if (field.required && value === '') {
        showError(field, MESSAGES.required);
        return false;
    }

    if (value === '') {
        return true;
    }

    if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
        showError(field, MESSAGES.email);
        return false;
    }

    if (field.dataset.min && value.length < Number(field.dataset.min)) {
        showError(field, MESSAGES.minLength(field.dataset.min));
        return false;
    }

    if (field.maxLength > 0 && value.length > field.maxLength) {
        showError(field, MESSAGES.maxLength(field.maxLength));
        return false;
    }

    if (field.type === 'date' && new Date(value) < new Date(new Date().toDateString())) {
        showError(field, MESSAGES.pastDate);
        return false;
    }

    if (field.type === 'url' && !/^https?:\/\/.+/i.test(value)) {
        showError(field, MESSAGES.url);
        return false;
    }

    return true;
}

export function initValidation() {
    document.querySelectorAll('[data-validate]').forEach((form) => {
        const fields = form.querySelectorAll('input, select, textarea');

        fields.forEach((field) => {
            field.addEventListener('blur', () => validateField(field));

            // Perbaiki begitu diketik lagi — pesan error yang sudah usang
            // lebih membingungkan daripada tidak ada pesan sama sekali.
            field.addEventListener('input', () => {
                if (field.classList.contains('is-invalid')) {
                    validateField(field);
                }
            });
        });

        form.addEventListener('submit', (event) => {
            let firstBad = null;

            fields.forEach((field) => {
                if (!validateField(field) && !firstBad) {
                    firstBad = field;
                }
            });

            // Urutan tanggal
            const start = form.querySelector('[name="event_start_at"]');
            const end = form.querySelector('[name="event_end_at"]');
            const deadline = form.querySelector('[name="registration_deadline"]');

            if (start && end && start.value && end.value && new Date(end.value) < new Date(start.value)) {
                showError(end, 'Waktu selesai tidak boleh sebelum waktu mulai.');
                firstBad = firstBad ?? end;
            }

            if (start && deadline && start.value && deadline.value
                && new Date(deadline.value) > new Date(start.value)) {
                showError(deadline, MESSAGES.dateOrder);
                firstBad = firstBad ?? deadline;
            }

            if (firstBad) {
                event.preventDefault();
                firstBad.focus();
            }
        });
    });

    // Penghitung karakter untuk textarea
    document.querySelectorAll('textarea[maxlength][data-counter]').forEach((area) => {
        const counter = document.getElementById(area.dataset.counter);
        if (!counter) {
            return;
        }

        const update = () => {
            counter.textContent = `${area.value.length} / ${area.maxLength}`;
        };

        area.addEventListener('input', update);
        update();
    });
}

export { validateField };
