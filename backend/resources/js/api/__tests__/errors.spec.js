import { describe, expect, it } from 'vitest';

import { normalizeApiError } from '../errors.js';

describe('normalizeApiError', () => {
    it('distinguishes module, permission, and server failures', () => {
        expect(normalizeApiError({ response: { status: 403, data: { error_code: 'MODULE_LOCKED' } } }).title)
            .toBe('Modul tidak aktif');
        expect(normalizeApiError({ response: { status: 403, data: { error_code: 'PERMISSION_DENIED' } } }).title)
            .toBe('Akses ditolak');
        expect(normalizeApiError({ response: { status: 500, data: {} } }).title)
            .toBe('Gangguan server');
    });
});
