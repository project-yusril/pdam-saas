// Konfigurasi resource workbench untuk modul yang dilayani halaman generik
// /modules/:code. SATU sumber kebenaran untuk endpoint + form create + aksi
// status per modul — harus disinkronkan dengan routes/api.php + store()
// controller terkait. `fields`: key/label/type/required/options; type 'json'
// menerima textarea ter-parse (items/credentials/tags).
//
// Aksi row: method default POST; `path: (row) => \`/x/\${row.id}/approve\``.

export const RESOURCE_CONFIG = {
    WH: {
        list: '/purchase-orders',
        searchable: true,
        paginated: true,
        create: {
            title: 'Buat Purchase Order',
            fields: [
                { key: 'supplier_id', label: 'ID Supplier', type: 'number' },
                { key: 'urgency', label: 'Urgensi', type: 'select', options: ['normal', 'urgent'] },
                { key: 'items', label: 'Item JSON [{material_id,qty,price}]', type: 'json', required: true },
            ],
            submitError: 'PO belum bisa dibuat — cek material id & harga per item.',
        },
        rowActions: [
            { key: 'approve', label: 'Approve', select: { label: 'Level', key: 'level', options: ['tech', 'dir', 'fin'] }, path: (row) => `/purchase-orders/${row.id}/approve`, when: (row) => ['draft', 'tech_approved', 'dir_approved'].includes(row.status) },
            { key: 'purchase', label: 'Purchase', path: (row) => `/purchase-orders/${row.id}/purchase`, when: (row) => row.status === 'dir_approved_fin' || row.status === 'approved' },
            { key: 'receive', label: 'Receive', path: (row) => `/purchase-orders/${row.id}/receive`, when: (row) => row.status === 'purchased' },
        ],
    },

    'BILL+': {
        list: '/bill-adjustments',
        paginated: true,
        create: {
            title: 'Ajukan Penyesuaian Tagihan',
            fields: [
                { key: 'bill_id', label: 'ID Tagihan', type: 'number', required: true },
                { key: 'type', label: 'Jenis', type: 'select', options: ['correction', 'waiver', 'manual_penalty', 'batch'], required: true },
                { key: 'new_amount', label: 'Nilai Baru (Rp)', type: 'number', required: true },
                { key: 'reason', label: 'Alasan', type: 'textarea', required: true },
            ],
        },
        rowActions: [
            { key: 'approve', label: 'Approve', path: (row) => `/bill-adjustments/${row.id}/approve`, when: (row) => row.status === 'pending' },
            { key: 'reject', label: 'Reject', path: (row) => `/bill-adjustments/${row.id}/reject`, when: (row) => row.status === 'pending' },
            { key: 'post', label: 'Posting', path: (row) => `/bill-adjustments/${row.id}/post`, when: (row) => row.status === 'approved' },
        ],
    },

    FSM: {
        list: '/work-orders',
        searchable: true,
        paginated: true,
        create: {
            title: 'Buat Work Order',
            fields: [
                { key: 'type', label: 'Jenis', type: 'select', options: ['repair', 'installation', 'disconnect', 'reconnect', 'meter_change', 'inspection', 'leakage'], required: true },
                { key: 'priority', label: 'Prioritas', type: 'select', options: ['low', 'medium', 'high', 'urgent'] },
                { key: 'customer_id', label: 'ID Pelanggan', type: 'number' },
                { key: 'zone_id', label: 'ID Zona', type: 'number' },
                { key: 'address', label: 'Alamat', type: 'text' },
                { key: 'description', label: 'Deskripsi', type: 'textarea', required: true },
            ],
        },
        rowActions: [
            { key: 'start', label: 'Start', path: (row) => `/work-orders/${row.id}/start`, when: (row) => ['open', 'assigned'].includes(row.status) },
            { key: 'complete', label: 'Selesai', path: (row) => `/work-orders/${row.id}/complete`, when: (row) => row.status === 'in_progress' },
        ],
    },

    CHEM: {
        list: '/chem/chemicals',
        paginated: true,
        create: {
            title: 'Tambah Bahan Kimia',
            fields: [
                { key: 'code', label: 'Kode (unik)', type: 'text', required: true },
                { key: 'name', label: 'Nama', type: 'text', required: true },
                { key: 'unit', label: 'Satuan (kg/liter)', type: 'text', required: true },
                { key: 'standard_dosage', label: 'Dosis Standar', type: 'number' },
                { key: 'safety_threshold', label: 'Ambang Aman', type: 'number' },
            ],
        },
    },

    INT: {
        list: '/integrations',
        create: {
            title: 'Daftarkan Integrasi',
            fields: [
                { key: 'name', label: 'Nama', type: 'text', required: true },
                { key: 'provider', label: 'Provider', type: 'select', options: ['whatsapp', 'sms', 'email', 'midtrans', 'fcm', 'google_vision', 'custom'], required: true },
                { key: 'config', label: 'Config JSON', type: 'json' },
            ],
        },
        rowActions: [
            { key: 'toggle', label: 'Aktif/Nonaktif', path: (row) => `/integrations/${row.id}/toggle` },
        ],
    },

    MNT: {
        list: '/maintenance/records',
        paginated: true,
        create: {
            title: 'Catat Eksekusi maintenance',
            fields: [
                { key: 'schedule_id', label: 'ID Jadwal', type: 'number', required: true },
                { key: 'execution_date', label: 'Tanggal Eksekusi', type: 'date', required: true },
                { key: 'technician_id', label: 'ID Teknisi', type: 'number' },
                { key: 'outcome', label: 'Hasil', type: 'select', options: ['completed', 'deferred', 'escalated'] },
                { key: 'cost_labor', label: 'Biaya Tenaga Kerja', type: 'number' },
                { key: 'cost_material', label: 'Biaya Material', type: 'number' },
                { key: 'findings', label: 'Temuan Lapangan', type: 'textarea' },
            ],
        },
    },

    DMS: {
        list: '/documents',
        paginated: true,
        // Upload file hanya lewat halaman khusus; modal create dibatasi metadata
        create: {
            title: 'Unggah Dokumen',
            fileField: { key: 'file', label: 'File (pdf/doc/xls/jpg/png ≤10MB)' },
            fields: [
                { key: 'title', label: 'Judul', type: 'text', required: true },
                { key: 'category', label: 'Kategori', type: 'text', required: true },
                { key: 'tags', label: 'Tags JSON', type: 'json' },
            ],
        },
        rowActions: [
            { key: 'approve', label: 'Approve', path: (row) => `/documents/${row.id}/approve`, when: (row) => row.status === 'pending_review' || row.status === 'draft' },
        ],
    },

    APP: {
        list: '/chats',
        create: {
            title: 'Buat Percakapan',
            fields: [
                { key: 'customer_id', label: 'ID Pelanggan', type: 'number' },
                { key: 'subject', label: 'Subjek', type: 'text', required: true },
            ],
        },
    },

    METX: {
        list: '/meter-anomalies',
        paginated: true,
        actions: [
            { key: 'scan', label: 'Scan Anomali', method: 'POST', path: '/meter-anomalies/scan' },
        ],
    },

    IOT: { list: '/iot/dashboard', kind: 'kpi' },
    PROD: { list: '/production/dashboard', kind: 'kpi' },
    DIST: { list: '/distribution/dma-dashboard', kind: 'kpi' },
    NRW: { list: '/nrw/dashboard', kind: 'kpi' },
    AI: { list: '', kind: 'info' },
};
