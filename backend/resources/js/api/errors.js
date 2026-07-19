export const API_ERROR_EVENT = 'pdam:api-error';

export function getApiErrorMessage(error, fallback = 'Permintaan gagal.') {
    const payload = error?.response?.data;
    const details = payload?.error?.details ?? payload?.errors;
    const firstDetail = details && Object.values(details).flat().find(Boolean);

    return firstDetail || payload?.error?.message || payload?.message || fallback;
}

export function normalizeApiError(error) {
    const status = error?.response?.status;
    const payload = error?.response?.data;
    const code = payload?.error?.code || payload?.error_code;

    if (status === 401) return { title: 'Sesi berakhir', message: 'Silakan login kembali.' };
    if (code === 'MODULE_LOCKED') return { title: 'Modul tidak aktif', message: payload?.message || 'Modul ini belum aktif untuk PDAM Anda.' };
    if (code === 'PERMISSION_DENIED' || status === 403) return { title: 'Akses ditolak', message: payload?.message || 'Anda tidak memiliki izin untuk fitur ini.' };
    if (status === 404) return { title: 'Data tidak ditemukan', message: 'Fitur atau data yang diminta tidak tersedia.' };
    if (status === 422) return { title: 'Data tidak valid', message: payload?.error?.message || 'Periksa kembali data yang dikirim.' };
    if (status === 429) return { title: 'Terlalu banyak permintaan', message: 'Tunggu sebentar lalu coba kembali.' };
    if (status >= 500) return { title: 'Gangguan server', message: 'Server gagal memproses permintaan.' };
    return { title: 'Gangguan koneksi', message: 'Permintaan gagal. Periksa koneksi lalu coba kembali.' };
}

export function reportApiError(error) {
    window.dispatchEvent(new CustomEvent(API_ERROR_EVENT, { detail: normalizeApiError(error) }));
}
