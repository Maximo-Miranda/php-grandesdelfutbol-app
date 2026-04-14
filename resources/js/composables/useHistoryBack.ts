import { router } from '@inertiajs/vue3';

export function useHistoryBack(fallbackPath: string): () => void {
    return function goBack(): void {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            router.visit(fallbackPath);
        }
    };
}
