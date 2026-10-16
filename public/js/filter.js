/**
 * Filter katalog dan form pencarian.
 *
 * Prinsipnya: state filter tinggal di URL. Dengan begitu hasil filter bisa
 * dikirim ke teman, dan tombol "kembali" di peramban bekerja seperti yang
 * orang harapkan — bukan melompat balik ke kondisi sebelum difilter.
 *
 * SERVER tetap sumber kebenaran. Halaman ini hanya menyalin state ke
 * alamat supaya tautannya bisa dibagikan; hasil sebenarnya tetap
 * berasal dari kueri database.
 */

const FORM_IDS = ['filter-katalog', 'filter-admin'];

function currentParams(extra = {}) {
    const params = new URLSearchParams(window.location.search);
    Object.entries(extra).forEach(([key, value]) => {
        if (value === '' || value === null || value === undefined) {
            params.delete(key);
        } else {
            params.set(key, value);
        }
    });
    return params;
}

function submit(form, changes = {}) {
    const data = new FormData(form);
    const params = new URLSearchParams();

    data.forEach((value, key) => {
        if (String(value).trim() !== '') {
            params.set(key, value);
        }
    });

    Object.entries(changes).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            params.delete(key);
        } else {
            params.set(key, value);
        }
    });

    window.location.assign(`${window.location.pathname}?${params.toString()}`);
}

function syncControls(form) {
    const params = new URLSearchParams(window.location.search);

    form.querySelectorAll('[name]').forEach((field) => {
        if (field.type === 'checkbox') {
            field.checked = params.get(field.name) !== null;
            return;
        }

        if (field.type !== 'hidden') {
            field.value = params.get(field.name) ?? '';
        }
    });
}

export function initFilters() {
    FORM_IDS.forEach((id) => {
        const form = document.getElementById(id);

        if (!form) {
            return;
        }

        syncControls(form);

        // Select dan tanggal langsung menerapkan; teks menunggu diketik
        // berhenti supaya tidak satu permintaan per ketikan.
        form.querySelectorAll('select, input[type="date"]').forEach((field) => {
            field.addEventListener('change', () => submit(form));
        });

        const search = form.querySelector('[data-search-input]');
        if (search) {
            let timer = null;
            search.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => submit(form), 450);
            });
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submit(form);
        });

        form.querySelectorAll('[data-filter-reset]').forEach((button) => {
            button.addEventListener('click', () => {
                window.location.assign(window.location.pathname);
            });
        });

        // Kotak chip di atas daftar menampilkan filter aktif, masing-masing
        // dengan tombol hapus. Menghapus satu filter harus mempertahankan
        // filter yang lain — bukan mengembalikan semuanya.
        document.querySelectorAll('[data-filter-remove]').forEach((chip) => {
            chip.addEventListener('click', (event) => {
                event.preventDefault();
                const params = currentParams({ [chip.dataset.filterRemove]: null });
                const query = params.toString();
                window.location.assign(
                    query ? `${window.location.pathname}?${query}` : window.location.pathname,
                );
            });
        });
    });
}

export { currentParams, submit };
