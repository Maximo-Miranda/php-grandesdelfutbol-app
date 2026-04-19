<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Flag, MoreVertical, Pencil } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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

const MATCHES_OPTIONS = [7, 9, 11, 13, 15, 17, 19, 21];

const editing = ref<Season | null>(null);
const closingSeason = ref<Season | null>(null);

const form = useForm({ name: '', matches_count: 15 });

function openEdit(s: Season): void {
    editing.value = s;
    form.name = s.name;
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

function openCloseConfirm(s: Season): void {
    closingSeason.value = s;
}

function confirmClose(): void {
    if (!closingSeason.value) return;
    router.post(`/clubs/${props.club.ulid}/seasons/${closingSeason.value.ulid}/close`, {}, {
        preserveScroll: true,
        onFinish: () => { closingSeason.value = null; },
    });
}

function fmt(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}

const minMatchesCount = computed(() => editing.value ? Math.max(1, editing.value.completed) : 1);

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
                    Las temporadas se crean automáticamente al crear partidos. Puedes renombrarlas, ajustar cuántos partidos dura la activa, o cerrarla para empezar una nueva.
                </p>
            </div>

            <div v-if="seasons.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                Aún no hay temporadas. Se creará una automáticamente al crear el primer partido.
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="s in seasons"
                    :key="s.ulid"
                    class="rounded-lg border border-border p-3 sm:p-4"
                    :class="s.is_active ? 'border-emerald-500/30 bg-emerald-500/5' : ''"
                >
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <h3 class="truncate font-semibold">{{ s.name }}</h3>
                                <span
                                    v-if="s.is_active"
                                    class="inline-flex items-center gap-1 rounded-full border border-emerald-500/40 bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-500"
                                >
                                    <span class="size-1.5 rounded-full bg-emerald-500" />
                                    Activa
                                </span>
                                <span v-else class="inline-flex items-center gap-1 rounded-full border border-muted bg-muted/40 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    <CheckCircle2 class="size-3" />
                                    Completada
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ fmt(s.starts_on) }} — {{ fmt(s.ends_on) }}
                            </p>
                        </div>

                        <DropdownMenu v-if="isAdmin">
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" size="icon" class="-mr-1 size-8 shrink-0" title="Acciones">
                                    <MoreVertical class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-48">
                                <DropdownMenuItem @click="openEdit(s)">
                                    <Pencil class="mr-2 size-4" />
                                    Editar
                                </DropdownMenuItem>
                                <template v-if="s.is_active">
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-amber-600 focus:bg-amber-500/10 focus:text-amber-600 dark:text-amber-400 dark:focus:text-amber-400"
                                        @click="openCloseConfirm(s)"
                                    >
                                        <Flag class="mr-2 size-4" />
                                        Cerrar temporada
                                    </DropdownMenuItem>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full bg-primary transition-all"
                                :style="{ width: `${Math.min(100, Math.round((s.completed / Math.max(s.matches_count, 1)) * 100))}%` }"
                            />
                        </div>
                        <span class="shrink-0 text-xs text-muted-foreground tabular-nums">
                            <span class="font-semibold text-foreground">{{ s.completed }}</span>/{{ s.matches_count }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Edit dialog -->
            <Dialog :open="editing !== null" @update:open="editing = null">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Editar temporada</DialogTitle>
                        <DialogDescription v-if="editing && !editing.is_active">
                            Esta temporada está completada. Solo puedes cambiar su nombre.
                        </DialogDescription>
                    </DialogHeader>

                    <form class="space-y-4" @submit.prevent="save">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Nombre</label>
                            <Input v-model="form.name" maxlength="50" placeholder="Ej: Apertura 2026" />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div v-if="editing?.is_active">
                            <label class="mb-2 block text-sm font-medium">Número de partidos</label>
                            <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                                <button
                                    v-for="n in MATCHES_OPTIONS"
                                    :key="n"
                                    type="button"
                                    :disabled="n < minMatchesCount"
                                    class="rounded-md border px-2 py-2 text-sm font-semibold tabular-nums transition disabled:cursor-not-allowed disabled:opacity-40"
                                    :class="form.matches_count === n
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border hover:border-muted-foreground/50'"
                                    @click="form.matches_count = n"
                                >{{ n }}</button>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Solo números impares — garantiza un ganador sin empates en la tabla.
                                <span v-if="editing.completed > 0" class="block mt-0.5">
                                    Ya se jugaron <strong class="text-foreground">{{ editing.completed }}</strong>; no puedes elegir un valor menor.
                                </span>
                            </p>
                            <p v-if="form.errors.matches_count" class="mt-1 text-xs text-destructive">{{ form.errors.matches_count }}</p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="editing = null">Cancelar</Button>
                            <Button type="submit" :disabled="form.processing">Guardar</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Close confirmation dialog -->
            <Dialog :open="closingSeason !== null" @update:open="closingSeason = null">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cerrar {{ closingSeason?.name }}</DialogTitle>
                        <DialogDescription>
                            <span class="block">
                                Se marcará como <strong>Completada</strong> y se creará inmediatamente una nueva temporada activa.
                            </span>
                            <span class="mt-2 block">
                                Todos los partidos nuevos que crees desde ahora quedarán en la nueva temporada.
                            </span>
                            <span v-if="closingSeason && closingSeason.completed < closingSeason.matches_count" class="mt-3 block rounded-md border border-amber-500/40 bg-amber-500/10 p-2 text-xs text-amber-600 dark:text-amber-400">
                                ⚠ Esta temporada solo lleva <strong>{{ closingSeason.completed }}</strong> de <strong>{{ closingSeason.matches_count }}</strong> partidos. Los partidos faltantes quedarán sin jugar.
                            </span>
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closingSeason = null">Cancelar</Button>
                        <Button type="button" @click="confirmClose">
                            <Flag class="mr-1.5 size-4" />
                            Cerrar y empezar nueva
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
