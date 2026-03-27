import { useEventListener } from '@vueuse/core';
import { computed, ref } from 'vue';

interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const DISMISS_KEY = 'pwa-install-dismissed-at';
const GUIDE_DONE_KEY = 'pwa-install-guide-done';
const COOLDOWN_MS = 24 * 60 * 60 * 1000;

const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);

function isWithinCooldown(key: string): boolean {
    const stored = localStorage.getItem(key);
    return stored !== null && Date.now() - Number(stored) < COOLDOWN_MS;
}

const dismissed = ref(isWithinCooldown(DISMISS_KEY));
const guideDone = ref(isWithinCooldown(GUIDE_DONE_KEY));

export function usePwaInstall() {

    const isStandalone = computed(() => {
        return (
            (navigator as any).standalone === true ||
            window.matchMedia('(display-mode: standalone)').matches
        );
    });

    const isIos = computed(() => {
        // iPadOS 13+ reports as "Macintosh" — detect via touch support
        return /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.userAgent.includes('Macintosh') && navigator.maxTouchPoints > 1);
    });

    const canInstall = computed(() => deferredPrompt.value !== null);

    const browserName = computed<'safari' | 'chrome' | 'other'>(() => {
        const ua = navigator.userAgent;
        if (/CriOS/.test(ua)) return 'chrome';
        if (/Safari/.test(ua) && !/CriOS|FxiOS|OPiOS|EdgiOS/.test(ua)) return 'safari';
        return 'other';
    });

    const shouldShowInstallGuide = computed(() => {
        return isIos.value && !isStandalone.value && !guideDone.value && !dismissed.value;
    });

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

    function markGuideDone(): void {
        guideDone.value = true;
        localStorage.setItem(GUIDE_DONE_KEY, String(Date.now()));
    }

    return { canInstall, isIos, isStandalone, dismissed, browserName, shouldShowInstallGuide, guideDone, promptInstall, dismiss, markGuideDone };
}
