<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useLocalStorage } from '@vueuse/core';
import { ChevronDown, Clock, Loader2, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ClubShield from '@/components/ClubShield.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Club } from '@/types';

type ClubItem = Pick<Club, 'id' | 'ulid' | 'name'>;

const page = usePage<{ currentClub: Club | null }>();
const currentClub = computed(() => page.props.currentClub);

const open = ref(false);
const clubs = ref<ClubItem[]>([]);
const loading = ref(false);
const nextPageUrl = ref<string | null>(null);

// Recently visited clubs (max 3, persisted in localStorage)
const recentClubIds = useLocalStorage<number[]>('recent-club-ids', []);
const recentClubs = ref<ClubItem[]>([]);

function trackVisit(clubId: number) {
    const ids = recentClubIds.value.filter(id => id !== clubId);
    ids.unshift(clubId);
    recentClubIds.value = ids.slice(0, 3);
}

async function fetchPage(url: string, append = false) {
    loading.value = true;
    try {
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (append) {
            clubs.value.push(...json.data);
        } else {
            clubs.value = json.data;
        }
        nextPageUrl.value = json.next_page_url;
    } finally {
        loading.value = false;
    }
}

async function fetchRecent() {
    if (recentClubIds.value.length === 0) {
        recentClubs.value = [];
        return;
    }
    try {
        const res = await fetch(`/clubs-search?ids=${recentClubIds.value.join(',')}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const json = await res.json();
        const map = new Map<number, ClubItem>(json.data.map((c: ClubItem) => [c.id, c]));
        recentClubs.value = recentClubIds.value
            .map(id => map.get(id))
            .filter((c): c is ClubItem => !!c);
    } catch {
        recentClubs.value = [];
    }
}

function loadMore() {
    if (nextPageUrl.value) {
        fetchPage(nextPageUrl.value, true);
    }
}

watch(open, (val) => {
    if (val) {
        fetchPage('/clubs-search');
        fetchRecent();
    } else {
        clubs.value = [];
        nextPageUrl.value = null;
    }
});

function selectClub(club: ClubItem) {
    trackVisit(club.id);
    open.value = false;
}
</script>

<template>
    <button class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium hover:bg-accent" @click="open = true">
        <ClubShield v-if="currentClub" :name="currentClub.name" :size="20" />
        <span class="truncate">{{ currentClub?.name ?? 'Seleccionar Club' }}</span>
        <ChevronDown class="size-3.5 text-muted-foreground" />
    </button>

    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[min(70dvh,28rem)] flex-col gap-0 p-0 sm:max-w-sm">
            <DialogHeader class="border-b px-4 pb-3 pt-4">
                <DialogTitle class="text-base">Cambiar Club</DialogTitle>
            </DialogHeader>

            <!-- Recent clubs -->
            <div v-if="recentClubs.length" class="border-b px-3 py-2.5">
                <p class="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                    <Clock class="size-3" />
                    Recientes
                </p>
                <div class="flex gap-2">
                    <Link
                        v-for="club in recentClubs"
                        :key="club.id"
                        :href="`/clubs/${club.ulid}`"
                        class="flex min-w-0 flex-1 flex-col items-center gap-1.5 rounded-lg border border-border/50 px-2 py-2 text-center transition-colors hover:bg-accent"
                        :class="{ 'border-primary/40 bg-accent/50': currentClub?.id === club.id }"
                        @click="selectClub(club)"
                    >
                        <ClubShield :name="club.name" :size="28" />
                        <span class="w-full truncate text-[11px] leading-tight">{{ club.name }}</span>
                    </Link>
                </div>
            </div>

            <!-- Club list -->
            <div class="flex-1 overflow-y-auto overscroll-contain px-1 py-1">
                <div v-if="loading && clubs.length === 0" class="flex justify-center py-6">
                    <Loader2 class="size-5 animate-spin text-muted-foreground" />
                </div>

                <Link
                    v-for="club in clubs"
                    :key="club.id"
                    :href="`/clubs/${club.ulid}`"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                    :class="{ 'bg-accent/50': currentClub?.id === club.id }"
                    @click="selectClub(club)"
                >
                    <ClubShield :name="club.name" :size="28" />
                    <span class="min-w-0 flex-1 truncate">{{ club.name }}</span>
                </Link>

                <!-- Load more button -->
                <button
                    v-if="nextPageUrl"
                    class="mt-1 w-full rounded-lg py-2.5 text-center text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    :disabled="loading"
                    @click="loadMore"
                >
                    <Loader2 v-if="loading" class="mx-auto size-4 animate-spin" />
                    <span v-else>Ver más</span>
                </button>
            </div>

            <!-- Create club -->
            <div class="border-t px-3 py-2">
                <Button as-child variant="ghost" size="sm" class="w-full justify-start gap-2">
                    <Link href="/clubs/create" @click="open = false">
                        <Plus class="size-4" />
                        Crear Club
                    </Link>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
