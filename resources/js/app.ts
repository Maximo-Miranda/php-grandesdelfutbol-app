import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';
import { useToast } from '@/composables/useToast';

const appName = import.meta.env.VITE_APP_NAME || 'Grandes del Fútbol';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Register PWA service worker...
if (typeof window !== 'undefined') {
    import('virtual:pwa-register').then(({ registerSW }) => {
        const { showToast, dismissToast, toasts } = useToast();

        // Expose for Dusk browser tests...
        (window as any).__toast = { showToast, dismissToast, toasts };

        const updateSW = registerSW({
            immediate: true,

            onNeedRefresh() {
                const toastId = showToast({
                    message: 'Nueva version disponible',
                    actionLabel: 'Actualizar',
                    onAction: () => {
                        dismissToast(toastId);
                        showToast({ message: 'Actualizando...', duration: 0 });
                        void updateSW(true);
                    },
                });
            },

            onRegisteredSW(_swUrl, registration) {
                if (!registration) return;

                // Polling every hour (registration.update() fetches the SW script internally)
                setInterval(() => {
                    if (registration.installing) return;
                    if ('connection' in navigator && !navigator.onLine) return;

                    registration.update().catch(() => {});
                }, 60 * 60 * 1000);

                // Check when returning from background (iOS main trigger)
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        registration.update();
                    }
                });
            },
        });
    });
}
