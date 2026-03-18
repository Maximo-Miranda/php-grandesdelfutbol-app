import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

/**
 * Wait for the service worker to be ready, with a timeout.
 * Returns null if no SW becomes available within the timeout.
 */
function waitForSW(timeoutMs = 10000): Promise<ServiceWorkerRegistration | null> {
    return Promise.race([
        navigator.serviceWorker.ready,
        new Promise<null>((resolve) => setTimeout(() => resolve(null), timeoutMs)),
    ]);
}

export function useWebPush() {
    const page = usePage<{ vapidPublicKey: string }>();
    const isSubscribed = ref(false);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    const isSupported = computed(() => {
        if (typeof window === 'undefined') return false;
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    });

    const permission = computed(() => {
        if (typeof window === 'undefined') return 'default' as NotificationPermission;
        if (!('Notification' in window)) return 'denied' as NotificationPermission;
        return Notification.permission;
    });

    const isStandalone = computed(() => {
        if (typeof window === 'undefined') return false;
        return (
            window.matchMedia('(display-mode: standalone)').matches ||
            (navigator as unknown as { standalone?: boolean }).standalone === true
        );
    });

    const isIos = computed(() => {
        if (typeof navigator === 'undefined') return false;
        return (
            /iPad|iPhone|iPod/.test(navigator.userAgent) ||
            (navigator.userAgent.includes('Macintosh') && navigator.maxTouchPoints > 1)
        );
    });

    const needsInstall = computed(() => isIos.value && !isStandalone.value);

    async function checkSubscription(): Promise<void> {
        if (!isSupported.value) return;
        try {
            const registration = await navigator.serviceWorker.getRegistration();
            if (!registration) return;
            const subscription = await registration.pushManager.getSubscription();
            isSubscribed.value = subscription !== null;
        } catch {
            // Silently fail — SW might not be ready yet
        }
    }

    async function subscribe(): Promise<boolean> {
        if (!isSupported.value) return false;
        error.value = null;
        isLoading.value = true;
        try {
            const registration = await waitForSW();
            if (!registration) {
                error.value = 'El service worker no está disponible. Ejecuta npm run build para habilitarlo.';
                return false;
            }

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(page.props.vapidPublicKey),
            });

            const json = subscription.toJSON();
            const xsrfToken = document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];

            const response = await fetch('/web-push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
                },
                body: JSON.stringify({
                    endpoint: json.endpoint,
                    keys: json.keys,
                    content_encoding: (PushManager as unknown as { supportedContentEncodings?: string[] })
                        .supportedContentEncodings?.[0] ?? 'aesgcm',
                }),
            });

            if (!response.ok) {
                error.value = 'Error al registrar la suscripción. Intenta de nuevo.';
                console.error('[WebPush] Server rejected subscription:', response.status);
            }

            isSubscribed.value = response.ok;
            return response.ok;
        } catch (e) {
            error.value = 'No se pudo activar las notificaciones. Intenta de nuevo.';
            console.error('[WebPush] Subscribe failed:', e);
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    async function unsubscribe(): Promise<boolean> {
        if (!isSupported.value) return false;
        error.value = null;
        isLoading.value = true;
        try {
            const registration = await waitForSW();
            if (!registration) return false;

            const subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                isSubscribed.value = false;
                return true;
            }

            const xsrfToken = document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];

            await fetch('/web-push/subscribe', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
                },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });

            await subscription.unsubscribe();
            isSubscribed.value = false;
            return true;
        } catch (e) {
            console.error('[WebPush] Unsubscribe failed:', e);
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    // Check on init (non-blocking, won't wait for SW)
    checkSubscription();

    return {
        isSupported,
        isSubscribed,
        isLoading,
        permission,
        isIos,
        isStandalone,
        needsInstall,
        error,
        subscribe,
        unsubscribe,
        checkSubscription,
    };
}
