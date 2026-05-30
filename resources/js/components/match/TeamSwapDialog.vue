<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowLeftRight, Check, Sparkles } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import UserAvatar from '@/components/UserAvatar.vue';
import type { MatchAttendance } from '@/types';

type Candidate = {
    attendance_id: number;
    player_id: number;
    player_name: string | null;
    position: string | null;
    photo_url: string | null;
    score: number;
    same_position_group: boolean;
    recommended: boolean;
};

const props = defineProps<{
    open: boolean;
    clubUlid: string;
    matchUlid: string;
    source: MatchAttendance | null;
    sourceTeamLabel: string;
    oppositeTeamLabel: string;
}>();

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

const candidates = ref<Candidate[]>([]);
const selectedId = ref<number | null>(null);
const loading = ref(false);
const submitting = ref(false);

watch(
    () => [props.open, props.source?.id] as const,
    async ([isOpen, sourceId]) => {
        if (!isOpen || !sourceId || !props.source) {
            candidates.value = [];
            selectedId.value = null;
            return;
        }
        loading.value = true;
        try {
            const url = `/clubs/${props.clubUlid}/matches/${props.matchUlid}/attendance/${props.source.ulid}/swap-candidates`;
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Failed to load candidates');
            const payload = (await response.json()) as { candidates: Candidate[] };
            candidates.value = payload.candidates;
            selectedId.value = candidates.value[0]?.attendance_id ?? null;
        } catch {
            candidates.value = [];
        } finally {
            loading.value = false;
        }
    },
);

function close() {
    emit('update:open', false);
}

function submit() {
    if (!props.source || !selectedId.value) return;
    submitting.value = true;
    router.post(
        `/clubs/${props.clubUlid}/matches/${props.matchUlid}/attendance/swap`,
        {
            source_attendance_id: props.source.id,
            target_attendance_id: selectedId.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => close(),
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ArrowLeftRight class="size-4" />
                    Intercambiar jugadores
                </DialogTitle>
                <DialogDescription v-if="source?.player">
                    Mover a <strong>{{ source.player.display_name }}</strong> de
                    {{ sourceTeamLabel }} a {{ oppositeTeamLabel }}. Elige un jugador
                    de {{ oppositeTeamLabel }} para enviar a {{ sourceTeamLabel }}.
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="py-6 text-center text-sm text-muted-foreground">
                Cargando candidatos…
            </div>

            <div v-else-if="!candidates.length" class="py-6 text-center text-sm text-muted-foreground">
                No hay candidatos disponibles del otro equipo con el mismo rol.
            </div>

            <div v-else class="max-h-[55vh] space-y-1.5 overflow-y-auto py-1">
                <button
                    v-for="candidate in candidates"
                    :key="candidate.attendance_id"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg border p-3 text-left transition-colors"
                    :class="
                        selectedId === candidate.attendance_id
                            ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                            : 'border-border hover:bg-accent'
                    "
                    @click="selectedId = candidate.attendance_id"
                >
                    <UserAvatar :src="candidate.photo_url" :name="candidate.player_name ?? '?'" class="size-9" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="truncate text-sm font-medium">{{ candidate.player_name }}</span>
                            <Badge v-if="candidate.recommended" variant="secondary" class="gap-1 text-xs">
                                <Sparkles class="size-3" />
                                Recomendado
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="candidate.position">{{ candidate.position.toUpperCase() }} · </span>
                            <span>Skill {{ candidate.score.toFixed(1) }}</span>
                            <span v-if="!candidate.same_position_group" class="ml-1 text-amber-600">· otra zona</span>
                        </p>
                    </div>
                    <Check v-if="selectedId === candidate.attendance_id" class="size-4 text-primary" />
                </button>
            </div>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button variant="outline" @click="close">Cancelar</Button>
                <Button :disabled="!selectedId || submitting" @click="submit">
                    <ArrowLeftRight class="size-4" />
                    Intercambiar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
