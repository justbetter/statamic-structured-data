import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    build: {
        cssCodeSplit: false,
    },
    plugins: [
        laravel({
            input: [
                'resources/js/statamic-structured-data.js'
            ],
            publicDirectory: 'resources/dist',
        }),
        statamic(),
        tailwindcss(),
    ],
});
