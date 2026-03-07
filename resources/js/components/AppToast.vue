<script setup lang="ts">
import { X } from 'lucide-vue-next';
import type { Toast } from '@/composables/useToast';

defineProps<{
    toast: Toast;
}>();

defineEmits<{
    dismiss: [];
    action: [];
}>();
</script>

<template>
    <div dusk="toast" class="flex items-center gap-3 rounded-lg border border-border bg-card px-4 py-3 text-card-foreground shadow-lg">
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
        <button type="button" dusk="toast-dismiss" class="shrink-0 text-muted-foreground hover:text-foreground" @click="$emit('dismiss')">
            <X class="size-4" />
        </button>
    </div>
</template>
