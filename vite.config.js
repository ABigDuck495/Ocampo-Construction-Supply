import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pt210-printer.js',
                'resources/css/sidebar.css',
                'resources/css/deliveries.css',
                'resources/css/pos.css',
                'resources/js/pages/pos.js',
                'resources/js/pages/deliveries.js',
                'resources/js/pages/sidebar.js',
                'resources/js/pages/inventory.js',
                'resources/css/inventory.css',

            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
