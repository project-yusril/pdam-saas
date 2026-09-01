export function formatRp(value) {
    const n = Number(value || 0);
    const sign = n < 0 ? '-' : '';
    return `${sign}Rp ${Math.abs(n).toLocaleString('id-ID')}`;
}
