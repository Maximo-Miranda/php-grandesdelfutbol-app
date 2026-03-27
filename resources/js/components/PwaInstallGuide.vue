<script setup lang="ts">
import { Check, ChevronLeft, ChevronRight, Copy, Ellipsis, Share, SquarePlus } from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { usePwaInstall } from '@/composables/usePwaInstall';

const open = defineModel<boolean>('open', { default: false });

const { browserName, dismiss, markGuideDone } = usePwaInstall();

const currentStep = ref(0);

interface Step {
    icon: Component;
    title: string;
    description: string;
}

const safariSteps: Step[] = [
    { icon: Ellipsis, title: 'Tocá los tres puntos', description: 'Están en la esquina inferior derecha del navegador' },
    { icon: Share, title: 'Tocá "Compartir"', description: 'Es la primera opción del menú que aparece' },
    { icon: Ellipsis, title: 'Tocá "..." (más opciones)', description: 'Está en la fila de íconos de abajo del menú de compartir' },
    { icon: SquarePlus, title: 'Tocá "Agregar a inicio"', description: 'Buscalo en la lista que aparece' },
    { icon: Check, title: 'Tocá "Agregar"', description: 'Confirmá el nombre y listo, queda en tu pantalla' },
];

const chromeSteps: Step[] = [
    { icon: Share, title: 'Tocá el ícono de compartir', description: 'Está arriba a la derecha, al lado de la barra de dirección' },
    { icon: Ellipsis, title: 'Tocá "Ver más"', description: 'Está en la segunda fila de íconos del menú que aparece' },
    { icon: SquarePlus, title: 'Tocá "Agregar a inicio"', description: 'Buscalo en la lista que aparece abajo' },
    { icon: Check, title: 'Tocá "Agregar"', description: 'Confirmá el nombre y listo, queda en tu pantalla' },
];

const steps = computed(() => (browserName.value === 'chrome' ? chromeSteps : safariSteps));
const currentStepData = computed(() => steps.value[currentStep.value]);
const isOtherBrowser = computed(() => browserName.value === 'other');

function next() {
    if (currentStep.value < steps.value.length - 1) {
        currentStep.value++;
    }
}

function prev() {
    if (currentStep.value > 0) {
        currentStep.value--;
    }
}

function verifyInstalled() {
    markGuideDone();
    open.value = false;
}

function postpone() {
    dismiss();
    open.value = false;
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :show-close-button="false"
            class="max-w-sm"
            @pointer-down-outside.prevent
            @escape-key-down.prevent
        >
            <!-- Header with logo -->
            <DialogHeader class="items-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-primary/10">
                    <AppLogoIcon class="size-8 text-primary" />
                </div>
                <DialogTitle class="text-center text-lg">Instalá Grandes del Futbol</DialogTitle>
                <DialogDescription class="text-center">
                    Agregá la app a tu pantalla de inicio para entrar más fácil
                </DialogDescription>
            </DialogHeader>

            <!-- Other browser: tell them to use Safari -->
            <template v-if="isOtherBrowser">
                <div class="space-y-4 text-center">
                    <p class="text-sm text-muted-foreground">
                        Para instalar la app, abrí esta página en <strong>Safari</strong>
                    </p>
                    <Button variant="outline" class="w-full gap-2" @click="copyLink">
                        <Copy class="size-4" />
                        Copiar link
                    </Button>
                    <button type="button" class="text-sm text-muted-foreground underline" @click="postpone">
                        Cerrar
                    </button>
                </div>
            </template>

            <!-- Step-by-step guide for Safari / Chrome -->
            <template v-else>
                <!-- Step indicator dots -->
                <div class="flex justify-center gap-2">
                    <div
                        v-for="(_, i) in steps"
                        :key="i"
                        class="size-2 rounded-full transition-colors"
                        :class="i <= currentStep ? 'bg-primary' : 'bg-muted-foreground/30'"
                    />
                </div>

                <!-- Step content -->
                <div class="flex flex-col items-center gap-4 py-2">
                    <div class="flex size-20 items-center justify-center rounded-full bg-primary/10">
                        <component :is="currentStepData.icon" class="size-10 text-primary" />
                    </div>
                    <div class="text-center">
                        <p class="text-base font-semibold">
                            Paso {{ currentStep + 1 }}: {{ currentStepData.title }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ currentStepData.description }}
                        </p>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex flex-col gap-2">
                    <!-- Last step: verify button -->
                    <template v-if="currentStep === steps.length - 1">
                        <div class="flex gap-2">
                            <Button variant="outline" class="shrink-0" @click="prev">
                                <ChevronLeft class="size-4" />
                                Anterior
                            </Button>
                            <Button class="flex-1 gap-2" @click="verifyInstalled">
                                <Check class="size-4" />
                                Ya la instalé
                            </Button>
                        </div>
                    </template>

                    <!-- Middle steps -->
                    <template v-else-if="currentStep > 0">
                        <div class="flex gap-2">
                            <Button variant="outline" class="shrink-0" @click="prev">
                                <ChevronLeft class="size-4" />
                                Anterior
                            </Button>
                            <Button class="flex-1 gap-2" @click="next">
                                Siguiente
                                <ChevronRight class="size-4" />
                            </Button>
                        </div>
                    </template>

                    <!-- First step -->
                    <template v-else>
                        <Button class="w-full gap-2" @click="next">
                            Siguiente
                            <ChevronRight class="size-4" />
                        </Button>
                    </template>

                    <!-- Postpone link (only on first step) -->
                    <button
                        v-if="currentStep === 0"
                        type="button"
                        class="mt-1 text-center text-sm text-muted-foreground underline"
                        @click="postpone"
                    >
                        Ahora no
                    </button>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
