/// <reference types="vitest/config" />
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.ts', 'resources/js/admin/location-picker.ts'],
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
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        // MapLibre with the map style is one chunk, loaded only by the game page and the admin picker.
        chunkSizeWarningLimit: 1300,
        rolldownOptions: {
            output: {
                codeSplitting: {
                    groups: [{ name: 'map', test: /node_modules[\\/]maplibre-gl|resources[\\/]js[\\/](lib[\\/]maplibre|config[\\/]map)/ }],
                },
            },
        },
    },
    server: {
        host: '0.0.0.0',
        hmr: { host: 'localhost' },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.spec.ts'],
    },
});
