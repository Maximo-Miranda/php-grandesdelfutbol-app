<script setup lang="ts">
import { BarChart3, ChevronRight, Clock, Film, Video } from 'lucide-vue-next';
import { computed } from 'vue';

type Props = {
    status?: 'pending' | 'contacted' | 'completed' | 'rejected' | null;
    compact?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    status: null,
    compact: false,
});

const emit = defineEmits<{
    request: [];
}>();

const isConfirmed = computed(() => props.status === 'completed');
const isInProgress = computed(() => props.status === 'pending' || props.status === 'contacted');
const showCta = computed(() => !props.status || props.status === 'rejected');
</script>

<template>
    <!-- Confirmed: service hired -->
    <div
        v-if="isConfirmed"
        class="relative overflow-hidden rounded-xl border border-emerald-500/20 bg-gradient-to-br from-emerald-950/60 via-emerald-900/30 to-zinc-900/50 px-4"
        :class="compact ? 'py-2.5' : 'py-3.5'"
    >
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_var(--tw-gradient-stops))] from-emerald-500/8 via-transparent to-transparent" />
        <div class="relative flex items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/15">
                <Video class="size-5 text-emerald-500" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Servicio de grabación confirmado</p>
                <p v-if="!compact" class="text-xs text-muted-foreground">Nuestro equipo estará en tu partido</p>
            </div>
            <div class="flex shrink-0 items-center gap-2 text-emerald-400/60">
                <Film class="size-5" />
                <BarChart3 class="size-5" />
            </div>
        </div>
    </div>

    <!-- In progress: pending or contacted -->
    <div
        v-else-if="isInProgress"
        class="flex items-center gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 px-4"
        :class="compact ? 'py-2.5' : 'py-3'"
    >
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-500/15">
            <Clock class="size-4 text-amber-500" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Grabación de video solicitada</p>
            <p v-if="!compact" class="text-xs text-muted-foreground">Un miembro del club solicitó grabar este partido</p>
        </div>
    </div>

    <!-- CTA: request service -->
    <button
        v-else-if="showCta"
        type="button"
        class="group relative w-full overflow-hidden rounded-xl border border-emerald-500/20 bg-gradient-to-r from-emerald-500/40 via-emerald-400/20 to-emerald-500/40 p-px transition-all hover:border-emerald-500/30"
        @click="emit('request')"
    >
        <div
            class="flex items-center gap-3 rounded-[11px] bg-gradient-to-br from-emerald-950/70 via-card/95 to-card/90 px-4"
            :class="compact ? 'py-2.5' : 'py-3.5'"
        >
            <span class="relative flex shrink-0 items-center justify-center rounded-full bg-emerald-500/20" :class="compact ? 'size-8' : 'size-10'">
                <Video class="text-emerald-400" :class="compact ? 'size-4' : 'size-5'" />
                <span class="absolute -right-0.5 -top-0.5 flex" :class="compact ? 'size-2.5' : 'size-3'">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-red-400 opacity-75" />
                    <span class="relative inline-flex rounded-full bg-red-500" :class="compact ? 'size-2.5' : 'size-3'" />
                </span>
            </span>
            <div class="min-w-0 flex-1 text-left">
                <p class="text-sm font-bold text-white">Grabamos tu partido en HD</p>
                <template v-if="!compact">
                    <p class="text-xs text-emerald-300/70">Video, stats y reels</p>
                    <p class="text-[11px] text-emerald-300/60">
                        Desde <span class="font-semibold text-emerald-300/90">$30k</span> en cancha aliada
                        · <span class="font-semibold text-emerald-300/90">$60k</span> en otras
                    </p>
                </template>
            </div>
            <ChevronRight class="size-5 shrink-0 text-emerald-400/60 transition-transform group-hover:translate-x-0.5" />
        </div>
    </button>
</template>
