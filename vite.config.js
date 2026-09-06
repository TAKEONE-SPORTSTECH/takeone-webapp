import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // MQTT for the sealed event surface, which loads none of the
                // platform shell — see resources/js/realtime-only.js.
                'resources/js/realtime-only.js',
                // React islands (each is its own entry; loaded only by the
                // view that mounts it, and only when its feature flag is on).
                'resources/js/islands/schedule.jsx',
                // The BJJ mat screen and scoring table (features.react_scoreboard).
                'resources/js/islands/scoreboard-board.jsx',
                'resources/js/islands/scoreboard-console.jsx',
                'resources/js/islands/entry.jsx',
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
