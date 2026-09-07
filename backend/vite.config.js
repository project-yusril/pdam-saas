import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    // laravel-vite-plugin menolak dev-server di CI (vitest menjalankan server transform);
    // ia tidak diperlukan untuk unit test Vue. Build (`npm run build`) tetap memakainya.
    const isTest = process.env.VITEST === 'true' || mode === 'test';
    return {
        plugins: [
            ...(isTest ? [] : [laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            })]),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },
        build: {
            sourcemap: mode !== 'production',
            minify: mode === 'production' ? 'esbuild' : false,
        },
        test: {
            environment: 'jsdom',
        },
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
            headers: {
                'Cache-Control': 'no-store',
            },
        },
    };
});
