<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { ChevronDown, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ClubShield from '@/components/ClubShield.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { Club } from '@/types';

const page = usePage<{ userClubs: Pick<Club, 'id' | 'ulid' | 'name'>[]; currentClub: Club | null }>();
const clubs = computed(() => page.props.userClubs ?? []);
const currentClub = computed(() => page.props.currentClub);

const open = ref(false);
const search = ref('');
const PAGE_SIZE = 20;
const visibleCount = ref(PAGE_SIZE);
const isMobile = useMediaQuery('(max-width: 768px)');

const filtered = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return clubs.value;
    return clubs.value.filter(c => c.name.toLowerCase().includes(q));
});

const visible = computed(() => filtered.value.slice(0, visibleCount.value));
const hasMore = computed(() => visibleCount.value < filtered.value.length);

watch(search, () => {
    visibleCount.value = PAGE_SIZE;
});

watch(open, (val) => {
    if (!val) {
        search.value = '';
        visibleCount.value = PAGE_SIZE;
    }
});

function loadMore() {
    visibleCount.value += PAGE_SIZE;
}
</script>

<template>
    <button class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium hover:bg-accent" @click="open = true">
        <ClubShield v-if="currentClub" :name="currentClub.name" :size="20" />
        <span class="truncate">{{ currentClub?.name ?? 'Seleccionar Club' }}</span>
        <ChevronDown class="size-3.5 text-muted-foreground" />
    </button>

    <!-- Mobile: bottom sheet -->
    <Sheet v-if="isMobile" v-model:open="open">
        <SheetContent side="bottom" class="flex max-h-[70dvh] flex-col gap-0 rounded-t-xl p-0">
            <SheetHeader class="border-b px-4 pb-3 pt-4">
                <SheetTitle class="text-base">Cambiar Club</SheetTitle>
            </SheetHeader>

            <div class="border-b px-3 py-2">
                <div class="relative">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Buscar club..."
                        class="pl-8"
                    />
                </div>
            </div>

            <div class="flex-1 overflow-y-auto overscroll-contain px-1 py-1">
                <p v-if="!filtered.length" class="px-3 py-6 text-center text-sm text-muted-foreground">
                    No se encontraron clubs
                </p>

                <Link
                    v-for="club in visible"
                    :key="club.id"
                    :href="`/clubs/${club.ulid}`"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                    :class="{ 'bg-accent/50': currentClub?.id === club.id }"
                    @click="open = false"
                >
                    <ClubShield :name="club.name" :size="28" />
                    <span class="min-w-0 flex-1 truncate">{{ club.name }}</span>
                </Link>

                <button
                    v-if="hasMore"
                    class="mt-1 w-full rounded-lg py-2 text-center text-xs text-muted-foreground hover:bg-accent"
                    @click="loadMore"
                >
                    Mostrar más ({{ filtered.length - visibleCount }} restantes)
                </button>
            </div>

            <div class="border-t px-3 py-2">
                <Button as-child variant="ghost" size="sm" class="w-full justify-start gap-2">
                    <Link href="/clubs/create" @click="open = false">
                        <Plus class="size-4" />
                        Crear Club
                    </Link>
                </Button>
            </div>
        </SheetContent>
    </Sheet>

    <!-- Desktop: centered dialog -->
    <Dialog v-else v-model:open="open">
        <DialogContent class="flex max-h-[80dvh] flex-col gap-0 p-0 sm:max-w-sm">
            <DialogHeader class="border-b px-4 pb-3 pt-4">
                <DialogTitle class="text-base">Cambiar Club</DialogTitle>
            </DialogHeader>

            <div class="border-b px-3 py-2">
                <div class="relative">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        placeholder="Buscar club..."
                        class="pl-8"
                        autofocus
                    />
                </div>
            </div>

            <div class="flex-1 overflow-y-auto overscroll-contain px-1 py-1">
                <p v-if="!filtered.length" class="px-3 py-6 text-center text-sm text-muted-foreground">
                    No se encontraron clubs
                </p>

                <Link
                    v-for="club in visible"
                    :key="club.id"
                    :href="`/clubs/${club.ulid}`"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                    :class="{ 'bg-accent/50': currentClub?.id === club.id }"
                    @click="open = false"
                >
                    <ClubShield :name="club.name" :size="28" />
                    <span class="min-w-0 flex-1 truncate">{{ club.name }}</span>
                </Link>

                <button
                    v-if="hasMore"
                    class="mt-1 w-full rounded-lg py-2 text-center text-xs text-muted-foreground hover:bg-accent"
                    @click="loadMore"
                >
                    Mostrar más ({{ filtered.length - visibleCount }} restantes)
                </button>
            </div>

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
