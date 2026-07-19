import { describe, expect, it } from 'vitest';

import { routes } from '../index.js';

describe('tenant router registry', () => {
    it('registers supported business views and excludes phantom routes', () => {
        const paths = routes.map((route) => route.path);

        expect(paths).toEqual(expect.arrayContaining([
            '/complaints', '/assets', '/gis', '/employees',
            '/employee-self-service', '/call-center', '/tenders',
            '/dashboard/director', '/dashboard/finance',
            '/dashboard/technical', '/dashboard/warehouse',
        ]));
        expect(paths).not.toContain('/prospects/:id');
        expect(paths).not.toContain('/installments');
    });
});
