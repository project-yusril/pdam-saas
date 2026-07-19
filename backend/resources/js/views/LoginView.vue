<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="w-full max-w-md mx-auto p-6">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-blue-700">PDAM SaaS</h1>
                <p class="text-gray-500 mt-1">Sistem Manajemen PDAM</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <!-- Toggle mode login -->
                <div class="flex rounded-lg bg-gray-100 p-1 mb-5">
                    <button type="button" @click="mode = 'tenant'"
                        :class="['flex-1 py-2 text-sm font-medium rounded-md transition', mode === 'tenant' ? 'bg-white shadow text-blue-700' : 'text-gray-500']">
                        Login PDAM
                    </button>
                    <button type="button" @click="mode = 'platform'"
                        :class="['flex-1 py-2 text-sm font-medium rounded-md transition', mode === 'platform' ? 'bg-white shadow text-blue-700' : 'text-gray-500']">
                        Super Admin
                    </button>
                </div>

                <form @submit.prevent="handleLogin" class="space-y-4">
                    <div v-if="mode === 'tenant'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode PDAM</label>
                        <input v-model="form.pdam_code" type="text" :required="mode === 'tenant'" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="pdam-canada" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input v-model="form.email" type="email" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" :placeholder="mode === 'platform' ? 'superadmin@gmail.com' : 'admin_tenant@gmail.com'" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input v-model="form.password" type="password" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="••••••••" />
                    </div>
                    <div v-if="error" class="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ error }}</div>
                    <button type="submit" :disabled="loading" class="w-full py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50">
                        {{ loading ? 'Loading...' : 'Masuk' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { getApiErrorMessage } from '../api/errors.js';

const router = useRouter();
const auth = useAuthStore();
const mode = ref('tenant');
const form = reactive({ pdam_code: '', email: '', password: '' });
const loading = ref(false);
const error = ref('');

async function handleLogin() {
    loading.value = true;
    error.value = '';
    try {
        if (mode.value === 'platform') {
            await auth.platformLogin(form.email, form.password);
            router.push('/platform');
        } else {
            await auth.login(form.pdam_code, form.email, form.password);
            router.push('/dashboard');
        }
    } catch (e) {
        error.value = getApiErrorMessage(e, 'Login gagal');
    } finally {
        loading.value = false;
    }
}
</script>
