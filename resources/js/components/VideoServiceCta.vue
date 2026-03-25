<script setup lang="ts">
import { Check, ChevronRight, Clock, Video } from 'lucide-vue-next';
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
        class="flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4"
        :class="compact ? 'py-2.5' : 'py-3'"
    >
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/20">
            <Check class="size-4 text-emerald-500" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Grabacion confirmada</p>
            <p v-if="!compact" class="text-xs text-muted-foreground">Nuestro equipo estara en tu partido</p>
        </div>
        <Video class="size-4 shrink-0 text-emerald-500/60" />
    </div>

    <!-- In progress: waiting for confirmation -->
    <div
        v-else-if="isInProgress"
        class="flex items-center gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 px-4"
        :class="compact ? 'py-2.5' : 'py-3'"
    >
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-500/15">
            <Clock class="size-4 text-amber-500" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Solicitud en proceso</p>
            <p v-if="!compact" class="text-xs text-muted-foreground">Te contactaremos pronto para confirmar</p>
        </div>
    </div>

    <!-- CTA: request service -->
    <button
        v-else-if="showCta"
        type="button"
        class="group relative w-full overflow-hidden rounded-xl bg-gradient-to-r from-emerald-500/15 via-emerald-400/10 to-transparent p-px transition-all hover:from-emerald-500/25 hover:via-emerald-400/15"
        @click="emit('request')"
    >
        <div
            class="flex items-center gap-3 rounded-[11px] bg-card/90 px-4 backdrop-blur-sm"
            :class="compact ? 'py-2.5' : 'py-3.5'"
        >
            <span class="relative flex shrink-0 items-center justify-center rounded-full bg-emerald-500/15" :class="compact ? 'size-8' : 'size-10'">
                <Video class="text-emerald-500" :class="compact ? 'size-4' : 'size-5'" />
                <span class="absolute -right-0.5 -top-0.5 flex" :class="compact ? 'size-2.5' : 'size-3'">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-red-400 opacity-75" />
                    <span class="relative inline-flex rounded-full bg-red-500" :class="compact ? 'size-2.5' : 'size-3'" />
                </span>
            </span>
            <div class="min-w-0 flex-1 text-left">
                <p class="text-sm font-semibold">Grabamos tu partido en HD</p>
                <p v-if="!compact" class="text-xs text-muted-foreground">
                    Revive cada jugada
                    <span class="font-medium text-emerald-500 dark:text-emerald-400"> · Desde $60.000</span>
                </p>
            </div>
            <ChevronRight class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
        </div>
    </button>
</template>
