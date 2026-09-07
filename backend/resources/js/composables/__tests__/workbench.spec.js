import { describe, expect, it } from 'vitest';
import { buildListParams, normalizeListEnvelope, totalPages, validateCreateFields } from '../workbench.js';
import { MODULE_CATALOG } from '../../config/modules.js';
import { RESOURCE_CONFIG } from '../../config/resources.js';

describe('workbench helpers', () => {
    it('normalizes plain arrays and paginated envelopes', () => {
        expect(normalizeListEnvelope([{ id: 1 }])).toEqual({ rows: [{ id: 1 }], meta: null });
        const env = normalizeListEnvelope({ data: [{ id: 2 }], meta: { current_page: 1, last_page: 3 } });
        expect(env.rows).toHaveLength(1);
        expect(totalPages(env.meta)).toBe(3);
        expect(totalPages(null)).toBe(1);
    });

    it('builds list params including search q and sort', () => {
        const cfg = { searchable: true };
        const p = buildListParams(cfg, { page: 2, perPage: 50, q: 'joko', sort: '-id' });
        expect(p).toMatchObject({ page: 2, per_page: 50, q: 'joko', sort: '-id' });
        const off = buildListParams({}, { page: 1, perPage: 25, q: 'x' });
        expect(off.q).toBeUndefined();
    });

    it('validates required + json fields', () => {
        const fields = [
            { key: 'name', required: true },
            { key: 'items', type: 'json', required: true },
        ];
        const bad = validateCreateFields(fields, { name: '', items: '{oops' });
        expect(bad.valid).toBe(false);
        expect(bad.errors).toMatchObject({ name: 'wajib diisi', items: 'JSON tidak valid' });

        const good = validateCreateFields(fields, { name: 'PO', items: '[{"material_id":1,"qty":2,"price":100}]' });
        expect(good.valid).toBe(true);
        expect(Array.isArray(good.payload.items)).toBe(true);
    });
});

describe('RESOURCE_CONFIG konsistensi katalog', () => {
    const genericCodes = MODULE_CATALOG.filter((m) => String(m.route).startsWith('/modules/')).map((m) => m.code);

    it('setiap modul generic punya entri resource config', () => {
        for (const code of genericCodes) {
            expect(RESOURCE_CONFIG[code], ` Resource config hilang untuk ${code}`).toBeDefined();
        }
    });

    it('create config minimal punya fields valid', () => {
        for (const [code, cfg] of Object.entries(RESOURCE_CONFIG)) {
            if (!cfg.create) continue;
            expect(Array.isArray(cfg.create.fields), code).toBe(true);
            expect(cfg.create.fields.length, code).toBeGreaterThan(0);
            for (const f of cfg.create.fields) {
                expect(f.key, `${code}.${f.key ?? '?'} key`).toBeTruthy();
            }
        }
    });
});
