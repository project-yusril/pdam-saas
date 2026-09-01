import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

const { api, initializeCsrf } = vi.hoisted(() => ({
    api: { get: vi.fn(), post: vi.fn() },
    initializeCsrf: vi.fn(),
}));

vi.mock('../../api/index.js', () => ({
    default: api,
    initializeCsrf,
}));

import { useAuthStore } from '../auth.js';

describe('web auth store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
        vi.clearAllMocks();
    });

    it('authenticates from a token-free web response without localStorage persistence', async () => {
        api.post.mockResolvedValue({
            data: {
                user: { id: 7, name: 'Web User', is_tenant_admin: true },
                organization: { id: 3, code: 'PDAM003', name: 'PDAM Test' },
            },
        });
        const setItem = vi.spyOn(Storage.prototype, 'setItem');

        const auth = useAuthStore();
        await auth.login('PDAM003', 'web@example.test', 'password');

        expect(initializeCsrf).toHaveBeenCalledOnce();
        expect(api.post).toHaveBeenCalledWith('/login', {
            pdam_code: 'PDAM003',
            email: 'web@example.test',
            password: 'password',
        });
        expect(auth.isAuthenticated).toBe(true);
        expect(localStorage.getItem('auth_token')).toBeNull();
        expect(setItem).not.toHaveBeenCalled();
    });

    it('captures active_modules from the login response', async () => {
        api.post.mockResolvedValue({
            data: {
                user: { id: 7, name: 'Web User', is_tenant_admin: true },
                organization: { id: 3, code: 'PDAM003', name: 'PDAM Test' },
                active_modules: ['CORE', 'FIN+', 'WH', 'ZONE'],
            },
        });

        const auth = useAuthStore();
        await auth.login('PDAM003', 'web@example.test', 'password');

        expect(auth.isTenantAdmin).toBe(true);
        expect(auth.activeModules).toEqual(['CORE', 'FIN+', 'WH', 'ZONE']);
    });
});
