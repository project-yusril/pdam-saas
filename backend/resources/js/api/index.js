import axios from 'axios';
import { reportApiError } from './errors.js';

const api = axios.create({
    baseURL: '/api/v1',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    withCredentials: true,
    withXSRFToken: true,
});

export async function initializeCsrf() {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

api.interceptors.response.use(
    (response) => response,
    (error) => {
        reportApiError(error);
        if (error.response?.status === 401 && !error.config?.skipAuthRedirect) {
            window.location.hash = '/login';
        }
        return Promise.reject(error);
    },
);

export default api;
