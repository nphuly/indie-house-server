import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
            fonts: [
                // The listener UI is Vietnamese-first, so both families must ship
                // the `vietnamese` subset; otherwise diacritics fall back mid-word.
                // Chakra Petch (Cadson Demak, SIL OFL 1.1) tops out at 700.
                bunny('Chakra Petch', {
                    weights: [400, 500, 600, 700],
                    subsets: ['latin', 'latin-ext', 'vietnamese'],
                    preload: [{ weight: 400 }, { weight: 700 }],
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 500],
                    subsets: ['latin', 'vietnamese'],
                    preload: false,
                    fallbacks: ['ui-monospace', 'SFMono-Regular', 'monospace'],
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
