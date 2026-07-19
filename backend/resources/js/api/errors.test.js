import { describe, expect, it } from 'vitest';
import { getApiErrorMessage } from './errors.js';

describe('getApiErrorMessage', () => {
    it('returns the first validation detail from the standard API error envelope', () => {
        const error = {
            response: {
                data: {
                    error: {
                        message: 'Data yang dikirim tidak valid.',
                        details: { pdam_code: ['PDAM tidak ditemukan.'] },
                    },
                },
            },
        };

        expect(getApiErrorMessage(error, 'Login gagal')).toBe('PDAM tidak ditemukan.');
    });

    it('falls back to the envelope message when validation details are absent', () => {
        const error = {
            response: { data: { error: { message: 'Terlalu banyak percobaan.' } } },
        };

        expect(getApiErrorMessage(error, 'Login gagal')).toBe('Terlalu banyak percobaan.');
    });
});
