<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'PDAM SaaS API',
    description: 'API untuk platform manajemen PDAM multi-tenant.\n\n'
        .'**Web/platform:** Sanctum stateful session. Panggil `/sanctum/csrf-cookie`, lalu login dengan '
        .'cookie aktif. Login web tidak mengembalikan token.\n\n'
        .'**Mobile/API device:** kirim `device_name` saat login tenant untuk menerima token Sanctum, lalu '
        .'gunakan `Authorization: Bearer {token}`.\n\n'
        .'**Tenant Context:** Semua endpoint tenant scope otomatis via middleware `SetTenant`\n\n'
        .'**Response Standar:** `{ "success": bool, "data": ..., "meta": {...} }`\n'
        .'  - Error: `{ "success": false, "error": { "code": "...", "message": "...", "details": {...} } }`\n\n'
        .'Setup, troubleshooting, dan kredensial demo: `backend/README.md` dan `backend/SEED_DATA.md`.',
)]
#[OA\Server(url: '/api/v1', description: 'API v1')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
    description: 'Khusus mobile/API device. Token diterbitkan login tenant jika payload menyertakan device_name.'
)]
#[OA\SecurityScheme(
    securityScheme: 'sessionCookie',
    type: 'apiKey',
    in: 'cookie',
    name: 'pdam-saas-session',
    description: 'Khusus browser web/platform setelah bootstrap CSRF dan login stateful.'
)]
#[OA\Tag(name: 'Auth', description: 'Login & manajemen sesi')]
#[OA\Tag(name: 'MFA', description: 'Two-Factor Authentication (TOTP) - PRD 0.3')]
#[OA\Tag(name: 'Platform', description: 'Super-admin — provisioning tenant, katalog modul')]
#[OA\Tag(name: 'RBAC', description: 'Role & Permission management')]
#[OA\Tag(name: 'Zone', description: 'Multi-wilayah / cabang PDAM — Fase 2')]
#[OA\Tag(name: 'Billing', description: 'Generate & lihat tagihan — Fase 1')]
#[OA\Tag(name: 'Accounting', description: 'Laporan akuntansi — Fase 1')]
#[OA\Tag(name: 'Survey', description: 'Pendaftaran pemasangan baru — Fase 3')]
#[OA\Tag(name: 'Installation', description: 'Penjadwalan & aktivasi pemasangan — Fase 3')]
#[OA\Tag(name: 'Meter Routes', description: 'Rute baca meter — Fase 4')]
#[OA\Tag(name: 'Meter Reading', description: 'Pembacaan meter & verifikasi — Fase 4')]
#[OA\Tag(name: 'METX', description: 'Meter analytics & anomali — Fase 6')]
#[OA\Tag(name: 'AST', description: 'Aset tetap & penyusutan — Fase 7')]
#[OA\Tag(name: 'Warehouse', description: 'Pengadaan & transfer stok — Fase 5')]
#[OA\Tag(name: 'Customer Portal', description: 'Portal pelanggan mobile')]
#[OA\Tag(name: 'Lifecycle', description: 'Isolir/sambung/balik nama')]
class SwaggerInfo {}
