<script setup lang="ts">
import { AlertCircle, X } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Toast } from '@/composables/useToast';

const props = defineProps<{
    toast: Toast;
}>();

defineEmits<{
    dismiss: [];
    action: [];
}>();

const containerCls = computed(() =>
    props.toast.variant === 'error'
        ? 'border-rose-500/40 bg-rose-500/10 text-rose-500'
        : 'border-border bg-card text-card-foreground',
);
</script>

<template>
    <div
        dusk="toast"
        class="flex items-center gap-3 rounded-lg border px-4 py-3 shadow-lg"
        :class="containerCls"
    >
        <AlertCircle v-if="toast.variant === 'error'" class="size-4 shrink-0" />
        <p class="flex-1 text-sm">{{ toast.message }}</p>
        <button
            v-if="toast.actionLabel"
            type="button"
            dusk="toast-action"
            class="shrink-0 text-sm font-medium text-primary hover:text-primary/80"
            @click="$emit('action')"
        >
            {{ toast.actionLabel }}
        </button>
        <button type="button" dusk="toast-dismiss" class="shrink-0 opacity-60 hover:opacity-100" @click="$emit('dismiss')">
            <X class="size-4" />
        </button>
    </div>
</template>
