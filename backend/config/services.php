<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],

    // Provider pembayaran kedua (PRD §23 adapter Xendit). Default: PDAM_PAYMENT_PROVIDER=xendit
    // atau field gateway saat POST /payments.
    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        'is_production' => env('XENDIT_IS_PRODUCTION', false),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'google_cloud' => [
        'vision_api_key' => env('GOOGLE_CLOUD_VISION_API_KEY'),
    ],

    // ── Stack peta OSM gratis (geocoding + routing) ──────────────────────
    // Default = server publik (fair-use: max 1 req/s & wajib User-Agent).
    // Produksi: self-host OSRM/Photon via Docker atau isi env *_BASE_URL —
    // tidak perlu ubah kode (lihat SEED_DATA.md bagian "Peta Pelanggan").
    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'PDAM-Admin/1.0 (dashboard internal; ganti dgn kontak Anda)'),
        'countrycodes' => env('NOMINATIM_COUNTRYCODES', 'id'),
    ],

    'osrm' => [
        'base_url' => env('OSRM_BASE_URL', 'https://router.project-osrm.org'),
        'timeout' => (int) env('OSRM_TIMEOUT', 15),
        'max_points' => (int) env('OSRM_MAX_POINTS', 16),
    ],

];
