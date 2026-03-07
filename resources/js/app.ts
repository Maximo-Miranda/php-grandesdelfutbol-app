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
                showToast({
                    message: 'Nueva version disponible',
                    actionLabel: 'Actualizar',
                    onAction: () => updateSW(true),
                });
            },
            onRegisteredSW(_url, registration) {
                if (!registration) return;
                setInterval(
                    () => {
                        if (document.visibilityState === 'visible') {
                            registration.update();
                        }
                    },
                    60 * 60 * 1000,
                );
            },
        });
    });
}
