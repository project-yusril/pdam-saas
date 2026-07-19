import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api, { initializeCsrf } from '../api/index.js';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const organization = ref(null);
    const admin = ref(null);
    const isPlatform = ref(false);
    const initialized = ref(false);

    const isAuthenticated = computed(() => !!user.value || !!admin.value);
    const isTenantAdmin = computed(() => user.value?.is_tenant_admin ?? false);
    const isSuperAdmin = computed(() => isPlatform.value);

    // ── Login tenant (PDAM) — butuh kode PDAM + email + password ──────────
    async function login(pdam_code, email, password) {
        await initializeCsrf();
        const { data } = await api.post('/login', { pdam_code, email, password });
        user.value = data.user;
        organization.value = data.organization;
        admin.value = null;
        isPlatform.value = false;
        initialized.value = true;
    }

    // ── Login Super-Admin (platform) — hanya email + password ─────────────
    async function platformLogin(email, password) {
        await initializeCsrf();
        const { data } = await api.post('/platform/login', { email, password });
        admin.value = data.admin;
        user.value = null;
        organization.value = null;
        isPlatform.value = true;
        initialized.value = true;
    }

    async function initialize() {
        if (initialized.value) return;

        try {
            const { data } = await api.get('/session', { skipAuthRedirect: true });
            isPlatform.value = data.type === 'platform';
            user.value = data.user ?? null;
            organization.value = data.organization ?? null;
            admin.value = data.admin ?? null;
        } catch {
            user.value = null;
            organization.value = null;
            admin.value = null;
            isPlatform.value = false;
        } finally {
            initialized.value = true;
        }
    }

    async function logout() {
        const endpoint = isPlatform.value ? '/platform/logout' : '/logout';
        try {
            await api.post(endpoint);
        } catch {
            // Credentials must still be cleared when the server session is unavailable.
        }
        user.value = null;
        organization.value = null;
        admin.value = null;
        isPlatform.value = false;
    }

    return { user, organization, admin, isPlatform, initialized, isAuthenticated, isTenantAdmin, isSuperAdmin, initialize, login, platformLogin, logout };
});
