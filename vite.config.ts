import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
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
            registerType: 'prompt',
            injectRegister: null,
            manifest: {
                name: 'Grandes del Futbol',
                short_name: 'GDF',
                description: 'Gestiona tus partidos y estadisticas de futbol',
                theme_color: '#16a34a',
                background_color: '#0f172a',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/dashboard',
                scope: '/',
                id: '/dashboard',
                lang: 'es',
                dir: 'ltr',
                categories: ['sports', 'utilities'],
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
            workbox: {
                navigateFallback: null,
                runtimeCaching: [
                    {
                        urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp)$/i,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'images',
                            expiration: {
                                maxEntries: 100,
                                maxAgeSeconds: 60 * 60 * 24 * 30,
                            },
                        },
                    },
                    {
                        urlPattern: /\.(?:woff2?|ttf|eot)$/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'fonts',
                            expiration: {
                                maxEntries: 20,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                        },
                    },
                ],
            },
        }),
    ],
});
