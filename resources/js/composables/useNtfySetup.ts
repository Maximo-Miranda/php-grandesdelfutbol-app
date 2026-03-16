import { computed } from 'vue';

export function useNtfySetup() {
    const isAndroid = computed(() => {
        if (typeof navigator === 'undefined') return false;
        return /Android/i.test(navigator.userAgent);
    });

    const isIos = computed(() => {
        if (typeof navigator === 'undefined') return false;
        return (
            /iPad|iPhone|iPod/.test(navigator.userAgent) ||
            (navigator.userAgent.includes('Macintosh') && navigator.maxTouchPoints > 1)
        );
    });

    const appStoreUrl = computed(() => {
        if (isAndroid.value) {
            return 'https://play.google.com/store/apps/details?id=io.heckel.ntfy';
        }
        if (isIos.value) {
            return 'https://apps.apple.com/app/ntfy/id1625396347';
        }
        return 'https://ntfy.sh/app';
    });

    const platformLabel = computed(() => {
        if (isAndroid.value) return 'Google Play';
        if (isIos.value) return 'App Store';
        return 'ntfy Web App';
    });

    return {
        isAndroid,
        isIos,
        appStoreUrl,
        platformLabel,
    };
}
