import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
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
}));
