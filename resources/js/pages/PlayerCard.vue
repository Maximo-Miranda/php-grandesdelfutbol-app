<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { toPng } from 'html-to-image';
import { Check, Download, Goal, Loader2, Share2, Shield, Sparkles, Trophy } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, PlayerProfile } from '@/types';

type PlayerStats = {
    goals: number;
    matches: number;
    yellowCards: number;
    redCards: number;
};

type Props = {
    playerStats: PlayerStats;
    profile: PlayerProfile & { photo_url?: string | null };
    clubs: Club[];
};

const props = defineProps<Props>();
const page = usePage();
const user = computed(() => page.props.auth.user);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mi Tarjeta', href: '/player-card' },
];

const cardRef = ref<HTMLElement | null>(null);
const isGenerating = ref(false);
const copied = ref(false);
const avatarDataUrl = ref<string | null>(null);

const initials = computed(() =>
    user.value.name
        .split(' ')
        .map((w: string) => w[0])
        .join('')
        .substring(0, 2)
        .toUpperCase(),
);

const displayName = computed(() => props.profile.nickname || user.value.name);
const avatarSrc = computed(() => avatarDataUrl.value || props.profile.photo_url);

// Pre-fetch avatar as data URL to avoid CORS issues during image generation
onMounted(async () => {
    if (!props.profile.photo_url) return;
    try {
        const response = await fetch(props.profile.photo_url);
        const blob = await response.blob();
        const reader = new FileReader();
        reader.onloadend = () => {
            avatarDataUrl.value = reader.result as string;
        };
        reader.readAsDataURL(blob);
    } catch {
        // Fallback to initials if fetch fails
    }
});

async function generateImage(): Promise<Blob | null> {
    if (!cardRef.value) return null;
    isGenerating.value = true;
    try {
        const dataUrl = await toPng(cardRef.value, {
            pixelRatio: 3,
            cacheBust: true,
            skipFonts: true,
        });
        const res = await fetch(dataUrl);
        return await res.blob();
    } catch {
        return null;
    } finally {
        isGenerating.value = false;
    }
}

async function downloadCard() {
    const blob = await generateImage();
    if (!blob) return;

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${displayName.value.replace(/\s+/g, '_')}_GDF.png`;
    a.click();
    URL.revokeObjectURL(url);
}

async function shareCard() {
    const blob = await generateImage();
    if (!blob) return;

    const file = new File([blob], 'mi-tarjeta-gdf.png', { type: 'image/png' });

    if (navigator.share && navigator.canShare?.({ files: [file] })) {
        await navigator.share({ files: [file] });
    } else {
        // Desktop fallback: copy image to clipboard
        try {
            await navigator.clipboard.write([
                new ClipboardItem({ 'image/png': blob }),
            ]);
            copied.value = true;
            setTimeout(() => (copied.value = false), 2000);
        } catch {
            downloadCard();
        }
    }
}
</script>

<template>
    <Head title="Mi Tarjeta" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-md flex-col items-center px-4 py-6">
            <!-- Header with CTA -->
            <div class="mb-6 flex w-full items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Mi Tarjeta</h1>
                    <p class="flex items-center gap-1 text-sm text-muted-foreground">
                        <Sparkles class="size-3.5 text-primary" />
                        Comparte tu camino con tus amigos
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        @click="downloadCard"
                        :disabled="isGenerating"
                        title="Descargar imagen"
                    >
                        <Loader2 v-if="isGenerating" class="size-4 animate-spin" />
                        <Download v-else class="size-4" />
                    </Button>
                    <Button
                        @click="shareCard"
                        :disabled="isGenerating"
                        size="icon"
                        :title="copied ? 'Copiado!' : 'Compartir en redes'"
                    >
                        <Loader2 v-if="isGenerating" class="size-4 animate-spin" />
                        <Check v-else-if="copied" class="size-4 text-green-500" />
                        <Share2 v-else class="size-4" />
                    </Button>
                </div>
            </div>

            <!-- Card preview (this gets captured as image) -->
            <div class="w-full">
                <div
                    ref="cardRef"
                    class="relative overflow-hidden rounded-2xl"
                    style="aspect-ratio: 9/16; background: linear-gradient(160deg, #0f172a 0%, #1e293b 40%, #0f4c3a 70%, #064e3b 100%); font-family: 'Public Sans', system-ui, -apple-system, sans-serif;"
                >
                    <!-- Subtle pattern overlay -->
                    <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 20px 20px;" />

                    <!-- Decorative glow -->
                    <div class="absolute left-1/2 top-1/3 size-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-emerald-500/10 blur-3xl" />

                    <div class="relative z-10 flex h-full flex-col items-center justify-between p-8">
                        <!-- Top: GDF Logo -->
                        <div class="flex items-center gap-2">
                            <AppLogoIcon class="size-7 text-emerald-400" />
                            <span class="text-sm font-bold tracking-wider text-white/80">GRANDES DEL FUTBOL</span>
                        </div>

                        <!-- Center: Player info -->
                        <div class="flex flex-col items-center">
                            <!-- Avatar -->
                            <div class="mb-5 flex size-32 items-center justify-center overflow-hidden rounded-full border-4 border-emerald-400/30 bg-white/10 shadow-xl shadow-emerald-500/20 ring-2 ring-emerald-400/10 ring-offset-2 ring-offset-transparent">
                                <img
                                    v-if="avatarSrc"
                                    :src="avatarSrc"
                                    alt="Foto"
                                    class="size-full object-cover"
                                />
                                <span v-else class="text-5xl font-bold text-white/60">{{ initials }}</span>
                            </div>

                            <!-- Name -->
                            <h2 class="mb-1 text-center text-2xl font-extrabold uppercase tracking-tight text-white">
                                {{ displayName }}
                            </h2>
                            <p v-if="profile.preferred_position" class="mb-8 rounded-full bg-emerald-500/20 px-4 py-1 text-xs font-bold uppercase tracking-wider text-emerald-400">
                                {{ profile.preferred_position }}
                            </p>

                            <!-- Stats grid -->
                            <div class="grid w-full max-w-xs grid-cols-3 gap-3">
                                <div class="flex flex-col items-center rounded-xl bg-white/5 p-3 backdrop-blur-sm">
                                    <Trophy class="mb-1 size-5 text-emerald-400" />
                                    <span class="text-3xl font-extrabold text-white">{{ playerStats.matches }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Partidos</span>
                                </div>
                                <div class="flex flex-col items-center rounded-xl bg-white/5 p-3 backdrop-blur-sm">
                                    <Goal class="mb-1 size-5 text-green-400" />
                                    <span class="text-3xl font-extrabold text-white">{{ playerStats.goals }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Goles</span>
                                </div>
                                <div class="flex flex-col items-center rounded-xl bg-white/5 p-3 backdrop-blur-sm">
                                    <Shield class="mb-1 size-5 text-blue-400" />
                                    <span class="text-3xl font-extrabold text-white">{{ clubs.length }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Clubes</span>
                                </div>
                            </div>

                            <!-- Cards row -->
                            <div class="mt-3 flex gap-3">
                                <div class="flex items-center gap-2 rounded-xl bg-white/5 px-4 py-2.5 backdrop-blur-sm">
                                    <div class="size-4 rounded-sm bg-yellow-400"></div>
                                    <span class="text-lg font-bold text-white">{{ playerStats.yellowCards }}</span>
                                </div>
                                <div class="flex items-center gap-2 rounded-xl bg-white/5 px-4 py-2.5 backdrop-blur-sm">
                                    <div class="size-4 rounded-sm bg-red-500"></div>
                                    <span class="text-lg font-bold text-white">{{ playerStats.redCards }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom: watermark -->
                        <p class="text-[10px] font-medium tracking-widest text-white/30">grandesdelfutbol.com</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
