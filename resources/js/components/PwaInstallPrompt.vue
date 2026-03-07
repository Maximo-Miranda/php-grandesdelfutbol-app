<script setup lang="ts">
import { Download, Share, X } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePwaInstall } from '@/composables/usePwaInstall';

const { canInstall, isIos, isStandalone, dismissed, promptInstall, dismiss } = usePwaInstall();

const visible = computed(() => {
    if (isStandalone.value || dismissed.value) return false;
    return canInstall.value || isIos.value;
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-full opacity-0"
        >
            <div v-if="visible" dusk="pwa-install-prompt" class="fixed inset-x-0 bottom-0 z-50 p-4">
                <div
                    class="mx-auto flex max-w-md items-center gap-3 rounded-lg border border-border bg-card px-4 py-3 text-card-foreground shadow-lg"
                >
                    <AppLogoIcon class="size-8 shrink-0 text-primary" />

                    <template v-if="canInstall">
                        <p class="flex-1 text-sm">Instala Grandes del Futbol para una mejor experiencia</p>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            @click="promptInstall"
                        >
                            <Download class="size-4" />
                            Instalar
                        </button>
                    </template>

                    <template v-else-if="isIos">
                        <p class="flex-1 text-sm">
                            Para instalar, toca
                            <Share class="inline size-4 align-text-bottom" />
                            y luego "Agregar a inicio"
                        </p>
                    </template>

                    <button type="button" dusk="pwa-install-dismiss" class="shrink-0 text-muted-foreground hover:text-foreground" @click="dismiss">
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
