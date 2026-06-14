import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    // Khi chạy site qua tunnel/HTTPS (Cloudflare Tunnel, ngrok…): đặt host của
    // dev server Vite vào VITE_TUNNEL_HOST (vd: vite-trucking.dewa.vn) để asset
    // + HMR đi qua https/wss cùng domain, tránh mixed-content. Để trống = dev
    // bình thường ở localhost (build production không bị ảnh hưởng).
    const tunnelHost = (env.VITE_TUNNEL_HOST || '').trim();

    return {
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
                    'resources/js/trucking2/pages/quan-ly-xe.jsx',
                    'resources/js/trucking2/pages/yeu-cau-chi.jsx',
                    'resources/js/trucking2/pages/phi-xe.jsx',
                    'resources/js/trucking2/pages/phi-xe-tao.jsx',
                    'resources/js/trucking2/pages/phi-xe-xem.jsx',
                    'resources/js/trucking2/pages/ke-hoach.jsx',
                    'resources/js/trucking2/pages/ke-hoach-public.jsx',
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
            host: true,   // 0.0.0.0 — cho tunnel/máy khác kết nối tới dev server
            cors: true,   // cho phép trang https domain nạp asset từ dev server
            // Khi qua tunnel: quảng bá URL asset + HMR theo domain https (cổng 443)
            ...(tunnelHost
                ? {
                      origin: `https://${tunnelHost}`,
                      hmr: { host: tunnelHost, protocol: 'wss', clientPort: 443 },
                  }
                : {}),
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
