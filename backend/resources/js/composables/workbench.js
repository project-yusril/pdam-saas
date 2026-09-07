// Pure helpers untuk halaman workbench modul generik (/modules/:code).
// Dipisah supaya bisa diuji Vitest tanpa mount komponen berat.

export function normalizeListEnvelope(payload) {
    if (Array.isArray(payload)) {
        return { rows: payload, meta: null };
    }
    const data = payload?.data;
    if (Array.isArray(data)) {
        return { rows: data, meta: payload.meta || null };
    }
    return { rows: [], meta: null };
}

export function buildListParams(cfg, state) {
    const params = { page: state.page, per_page: state.perPage };
    if (cfg.searchable && state.q) params.q = state.q;
    if (state.sort) params.sort = state.sort;

    return params;
}

export function totalPages(meta) {
    if (!meta || !meta.last_page) return 1;

    return Number(meta.last_page);
}

export function validateCreateFields(fields, values) {
    const errors = {};
    const payload = {};

    for (const field of fields) {
        const raw = values[field.key];
        if (field.type === 'json') {
            if (raw === '' || raw === null || raw === undefined) {
                if (field.required) errors[field.key] = 'wajib diisi';

                continue;
            }
            try {
                payload[field.key] = JSON.parse(raw);
            } catch {
                errors[field.key] = 'JSON tidak valid';
            }

            continue;
        }
        if (field.required && (raw === '' || raw === null || raw === undefined)) {
            errors[field.key] = 'wajib diisi';

            continue;
        }
        if (raw === '' || raw === null || raw === undefined) continue;
        payload[field.key] = field.type === 'number' ? Number(raw) : raw;
    }

    return { valid: Object.keys(errors).length === 0, errors, payload };
}
