import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fontsource } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
            // Self-hosted from @fontsource, with metric-matched fallbacks to avoid layout shift.
            fonts: [
                fontsource('Big Shoulders Display', {
                    weights: [800],
                    variable: '--ff-display',
                    fallbacks: ['Arial Narrow', 'sans-serif'],
                }),
                fontsource('Public Sans', {
                    weights: [400, 600, 700],
                    variable: '--ff-body',
                    preload: [{ weight: 400, style: 'normal' }],
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
