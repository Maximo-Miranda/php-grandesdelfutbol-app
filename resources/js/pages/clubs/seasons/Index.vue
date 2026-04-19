<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type Season = {
    ulid: string;
    name: string;
    matches_count: number;
    status: string;
    completed_at: string | null;
    is_active: boolean;
    played: number;
    completed: number;
    starts_on: string | null;
    ends_on: string | null;
};

const props = defineProps<{
    club: Club;
    isAdmin: boolean;
    seasons: Season[];
}>();

const editing = ref<Season | null>(null);

const form = useForm({ matches_count: 15 });

function openEdit(s: Season): void {
    editing.value = s;
    form.matches_count = s.matches_count;
    form.clearErrors();
}

function save(): void {
    if (!editing.value) return;
    form.patch(`/clubs/${props.club.ulid}/seasons/${editing.value.ulid}`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}

function fmt(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Posiciones', href: `/clubs/${props.club.ulid}/standings` },
    { title: 'Temporadas', href: `/clubs/${props.club.ulid}/seasons` },
];
</script>

<template>
    <Head :title="`${club.name} - Temporadas`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Temporadas</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    El sistema administra automáticamente las temporadas. Solo puedes editar el número de partidos de la temporada activa.
                </p>
            </div>

            <div v-if="seasons.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                Aún no hay temporadas. Se creará una automáticamente al crear el primer partido.
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="s in seasons"
                    :key="s.ulid"
                    class="rounded-lg border border-border p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold">{{ s.name }}</h3>
                                <span
                                    v-if="s.is_active"
                                    class="rounded-full border border-emerald-500/40 bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-500"
                                >Activa</span>
                                <span v-else class="rounded-full border border-muted bg-muted/40 px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">Completada</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ fmt(s.starts_on) }} — {{ fmt(s.ends_on) }}
                            </p>
                        </div>
                        <Button v-if="isAdmin && s.is_active" variant="outline" size="sm" @click="openEdit(s)">
                            <Pencil class="mr-1.5 size-3.5" />
                            Editar
                        </Button>
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full bg-primary transition-all"
                                :style="{ width: `${Math.min(100, Math.round((s.completed / Math.max(s.matches_count, 1)) * 100))}%` }"
                            />
                        </div>
                        <span class="text-xs text-muted-foreground">{{ s.completed }}/{{ s.matches_count }}</span>
                    </div>
                </div>
            </div>

            <Dialog :open="editing !== null" @update:open="editing = null">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Editar {{ editing?.name }}</DialogTitle>
                    </DialogHeader>

                    <form class="space-y-4" @submit.prevent="save">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Número de partidos</label>
                            <Input v-model.number="form.matches_count" type="number" min="1" max="99" step="2" />
                            <p class="mt-1 text-xs text-muted-foreground">Debe ser un número impar (7, 9, 11, 13, 15...) para garantizar un ganador.</p>
                            <p v-if="form.errors.matches_count" class="mt-1 text-xs text-destructive">{{ form.errors.matches_count }}</p>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="editing = null">Cancelar</Button>
                            <Button type="submit" :disabled="form.processing">Guardar</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
