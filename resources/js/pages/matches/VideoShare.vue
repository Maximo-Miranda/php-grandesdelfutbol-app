<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import VideoPlayer from '@/components/match/VideoPlayer.vue';
import { formatDate } from '@/lib/utils';

type Props = {
    match: {
        title: string;
        scheduled_at: string;
        club: { name: string };
    };
    youtubeEmbedUrl: string | null;
    s3VideoUrl: string | null;
};

const props = defineProps<Props>();

const formattedDate = computed(() =>
    formatDate(props.match.scheduled_at, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
        .replace(/^\w/, (c) => c.toUpperCase()),
);
</script>

<template>
    <Head :title="`${match.title} - Video`" />
    <div class="min-h-screen bg-background">
        <div class="mx-auto max-w-3xl px-4 py-10">
            <!-- Club name -->
            <p class="text-center text-xs font-medium tracking-wider text-muted-foreground uppercase">
                {{ match.club.name }}
            </p>

            <!-- Match title -->
            <h1 class="mt-2 text-center text-2xl font-bold text-foreground">
                {{ match.title }}
            </h1>

            <!-- Video -->
            <div class="mt-6">
                <div v-if="youtubeEmbedUrl" class="aspect-video w-full overflow-hidden rounded-xl border border-border">
                    <iframe
                        :src="youtubeEmbedUrl"
                        class="h-full w-full"
                        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    />
                </div>
                <VideoPlayer v-else-if="s3VideoUrl" :src="s3VideoUrl" />
            </div>

            <!-- Date -->
            <p class="mt-4 text-center text-sm text-muted-foreground">
                {{ formattedDate }}
            </p>

            <!-- Footer -->
            <div class="mt-10 text-center text-[10px] text-muted-foreground/50">
                Grandes del Futbol &middot; grandesdelfutbol.com
            </div>
        </div>
    </div>
</template>
