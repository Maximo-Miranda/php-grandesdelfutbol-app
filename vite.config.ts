import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

const devServerPort = 5173;
const appServerPort = 8000;

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devServerHost = env.VITE_DEV_HOST || 'localhost';
    const devServerProtocol = env.VITE_DEV_PROTOCOL || 'http';
    const isSecure = devServerProtocol === 'https';
    const hmrProtocol = isSecure ? 'wss' : 'ws';

    return {
    server: {
        host: '0.0.0.0',
        port: devServerPort,
        strictPort: true,
        origin: `${devServerProtocol}://${devServerHost}:${devServerPort}`,
        cors: {
            origin: `${devServerProtocol}://${devServerHost}:${appServerPort}`,
        },
        hmr: {
            protocol: hmrProtocol,
            host: devServerHost,
            clientPort: devServerPort,
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.ts',
            registerType: 'prompt',
            injectRegister: null,
            scope: '/',
            injectManifest: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
            },
            manifest: {
                name: 'Grandes del Futbol',
                short_name: 'GDF',
                description:
                    'Organiza partidos con tus amigos, controla asistencia y sigue estadisticas de cada jugador',
                theme_color: '#16a34a',
                background_color: '#0f172a',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/dashboard',
                scope: '/',
                id: '/dashboard',
                lang: 'es',
                dir: 'ltr',
                categories: ['sports'],
                prefer_related_applications: false,
                icons: [
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
                shortcuts: [
                    {
                        name: 'Dashboard',
                        url: '/dashboard',
                        icons: [{ src: '/pwa-192x192.png', sizes: '192x192' }],
                    },
                    {
                        name: 'Mis Clubes',
                        url: '/clubs',
                        icons: [{ src: '/pwa-192x192.png', sizes: '192x192' }],
                    },
                ],
                screenshots: [
                    {
                        src: '/screenshots/dashboard-wide.png',
                        sizes: '1280x720',
                        type: 'image/png',
                        form_factor: 'wide',
                    },
                    {
                        src: '/screenshots/dashboard-narrow.png',
                        sizes: '750x1334',
                        type: 'image/png',
                        form_factor: 'narrow',
                    },
                ],
            },
        }),
    ],
    };
});
