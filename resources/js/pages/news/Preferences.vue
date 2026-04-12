<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Loader2, Settings2, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, UserNewsPreference } from '@/types';

type CompetitionOption = {
    key: string;
    label: string;
};

const props = defineProps<{
    preference: UserNewsPreference | null;
    availableCompetitions: CompetitionOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Noticias', href: '/news' },
    { title: 'Preferencias', href: '/news/preferences' },
];

// Form state
const selectedCompetitions = ref<string[]>(props.preference?.competitions ?? []);
const freeText = ref(props.preference?.free_text_input ?? '');
const saving = ref(false);

function toggleCompetition(key: string) {
    const index = selectedCompetitions.value.indexOf(key);

    if (index >= 0) {
        selectedCompetitions.value.splice(index, 1);
    } else {
        selectedCompetitions.value.push(key);
    }
}

function isSelected(key: string): boolean {
    return selectedCompetitions.value.includes(key);
}

function savePreferences() {
    saving.value = true;

    router.post(
        '/news/preferences',
        {
            competitions: selectedCompetitions.value,
            free_text_input: freeText.value || null,
        },
        {
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Preferencias de Noticias" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl px-4 py-6">
            <div class="mb-6 flex items-center gap-3">
                <Settings2 class="size-6 text-primary" />
                <h1 class="text-lg font-bold">Preferencias de Noticias</h1>
            </div>

            <!-- Competition chips -->
            <div class="mb-6">
                <h2 class="mb-3 text-sm font-semibold text-foreground">Ligas y competiciones que te interesan</h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="comp in availableCompetitions"
                        :key="comp.key"
                        class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="
                            isSelected(comp.key)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-card text-muted-foreground hover:border-primary/50 hover:text-foreground'
                        "
                        @click="toggleCompetition(comp.key)"
                    >
                        {{ comp.label }}
                    </button>
                </div>
            </div>

            <!-- Free text -->
            <div class="mb-6">
                <h2 class="mb-1 text-sm font-semibold text-foreground">
                    <Sparkles class="mr-1 inline size-3.5 text-primary" />
                    Describe qué más te interesa
                </h2>
                <p class="mb-3 text-xs text-muted-foreground">
                    La IA analizará tu texto para extraer equipos, ligas y temas de interés.
                </p>
                <Textarea
                    v-model="freeText"
                    :maxlength="500"
                    rows="4"
                    placeholder="Ej: Me interesa el Real Madrid, el Atlético Nacional de Colombia, las noticias de fichajes y los partidos de la selección colombiana"
                />
                <p class="mt-1 text-right text-xs text-muted-foreground">{{ freeText.length }}/500</p>
            </div>

            <!-- Save -->
            <Button :disabled="saving" class="w-full gap-2" @click="savePreferences">
                <Loader2 v-if="saving" class="size-4 animate-spin" />
                {{ saving ? 'Guardando...' : 'Guardar preferencias' }}
            </Button>

            <p v-if="freeText" class="mt-3 text-center text-xs text-muted-foreground">
                El texto libre será analizado por IA en segundo plano para enriquecer tus preferencias.
            </p>
        </div>
    </AppLayout>
</template>
