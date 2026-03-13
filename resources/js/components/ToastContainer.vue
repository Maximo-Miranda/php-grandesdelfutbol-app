<script setup lang="ts">
import AppToast from '@/components/AppToast.vue';
import { useToast } from '@/composables/useToast';

const { toasts, dismissToast } = useToast();
</script>

<template>
    <Teleport to="body">
        <div dusk="toast-container" class="fixed bottom-20 right-4 z-50 flex w-80 max-sm:left-4 max-sm:right-4 max-sm:w-auto flex-col gap-2">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-y-4 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-4 opacity-0"
            >
                <AppToast
                    v-for="toast in toasts"
                    :key="toast.id"
                    :toast="toast"
                    @dismiss="dismissToast(toast.id)"
                    @action="toast.onAction?.()"
                />
            </TransitionGroup>
        </div>
    </Teleport>
</template>
