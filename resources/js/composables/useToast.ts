import { ref } from 'vue';

export interface Toast {
    id: number;
    message: string;
    actionLabel?: string;
    onAction?: () => void;
    duration: number;
}

const toasts = ref<Toast[]>([]);
let nextId = 0;

export function useToast() {
    function showToast(options: {
        message: string;
        actionLabel?: string;
        onAction?: () => void;
        duration?: number;
    }): number {
        const id = nextId++;
        const duration = options.duration ?? (options.actionLabel ? 0 : 8000);

        const toast: Toast = {
            id,
            message: options.message,
            actionLabel: options.actionLabel,
            onAction: options.onAction,
            duration,
        };

        toasts.value.push(toast);

        if (duration > 0) {
            setTimeout(() => dismissToast(id), duration);
        }

        return id;
    }

    function dismissToast(id: number): void {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }

    return { toasts, showToast, dismissToast };
}
