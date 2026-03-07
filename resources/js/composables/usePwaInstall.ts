import { useEventListener } from '@vueuse/core';
import { computed, ref } from 'vue';

interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const DISMISS_KEY = 'pwa-install-dismissed-at';
const COOLDOWN_MS = 24 * 60 * 60 * 1000;

const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
const dismissed = ref(false);

export function usePwaInstall() {
    if (typeof window !== 'undefined') {
        const stored = localStorage.getItem(DISMISS_KEY);
        if (stored && Date.now() - Number(stored) < COOLDOWN_MS) {
            dismissed.value = true;
        }
    }

    const isStandalone = computed(() => {
        if (typeof window === 'undefined') return false;
        return (
            (navigator as any).standalone === true ||
            window.matchMedia('(display-mode: standalone)').matches
        );
    });

    const isIos = computed(() => {
        if (typeof navigator === 'undefined') return false;
        return /iPad|iPhone|iPod/.test(navigator.userAgent);
    });

    const canInstall = computed(() => deferredPrompt.value !== null);

    useEventListener(window, 'beforeinstallprompt', (e: Event) => {
        e.preventDefault();
        deferredPrompt.value = e as BeforeInstallPromptEvent;
    });

    async function promptInstall(): Promise<void> {
        if (!deferredPrompt.value) return;
        await deferredPrompt.value.prompt();
        const { outcome } = await deferredPrompt.value.userChoice;
        if (outcome === 'accepted') {
            deferredPrompt.value = null;
        }
    }

    function dismiss(): void {
        dismissed.value = true;
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
    }

    return { canInstall, isIos, isStandalone, dismissed, promptInstall, dismiss };
}
