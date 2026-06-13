import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Trucking v2 — mỗi trang 1 entry (build sẵn, không Babel in-browser)
                'resources/js/trucking2/pages/lo-hang.jsx',
                'resources/js/trucking2/pages/bang-gia.jsx',
                'resources/js/trucking2/pages/bang-ke.jsx',
                'resources/js/trucking2/pages/bang-ke-tao.jsx',
                'resources/js/trucking2/pages/bang-ke-xem.jsx',
                'resources/js/trucking2/pages/cai-dat.jsx',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    // JSX cho .jsx do esbuild xử lý (classic transform — các module đều `import React`)
    esbuild: {
        jsx: 'transform',
        jsxFactory: 'React.createElement',
        jsxFragment: 'React.Fragment',
    },
    resolve: {
        alias: {
            '@trk': fileURLToPath(new URL('./resources/js/trucking2', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
