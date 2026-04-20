<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Home, MapPin, Settings, Trophy, Users, UsersRound } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';
import { useClubPermissions } from '@/composables/useClubPermissions';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { Club, NavItem } from '@/types';

const page = usePage<{ currentClub: Club | null; features?: { team_standings?: boolean } }>();
const { isCurrentOrParentUrl } = useCurrentUrl();
const { isAdmin } = useClubPermissions();

const currentClub = computed(() => page.props.currentClub);
const teamStandingsEnabled = computed(() => page.props.features?.team_standings !== false);
const navContainer = ref<HTMLElement | null>(null);

const clubNavItems = computed<NavItem[]>(() => {
    const club = currentClub.value;
    if (!club) return [];

    const base = `/clubs/${club.ulid}`;
    const items: NavItem[] = [
        { title: 'Inicio', href: base, icon: Home },
        { title: 'Partidos', href: `${base}/matches`, icon: CalendarDays },
    ];

    if (teamStandingsEnabled.value) {
        items.push({ title: 'Posiciones', href: `${base}/standings`, icon: Trophy });
    } else {
        items.push({ title: 'Jugadores', href: `${base}/players`, icon: UsersRound });
    }

    items.push(
        { title: 'Canchas', href: `${base}/venues`, icon: MapPin },
    );

    if (isAdmin.value) {
        items.push(
            { title: 'Miembros', href: `${base}/members`, icon: Users },
            { title: 'Ajustes', href: `${base}/edit`, icon: Settings },
        );
    }

    return items;
});

function matchesItem(item: NavItem): boolean {
    const club = currentClub.value;
    if (item.title === 'Posiciones' && club) {
        const base = `/clubs/${club.ulid}`;
        if (isCurrentOrParentUrl(`${base}/teams`) || isCurrentOrParentUrl(`${base}/seasons`)) {
            return true;
        }
    }
    return isCurrentOrParentUrl(item.href);
}

function isActive(item: NavItem): boolean {
    if (item.title === 'Inicio') {
        return isCurrentOrParentUrl(item.href) && !clubNavItems.value.filter(i => i !== item).some(matchesItem);
    }
    return matchesItem(item);
}

onMounted(() => {
    nextTick(() => {
        const activeEl = navContainer.value?.querySelector('[data-active="true"]') as HTMLElement | null;
        if (activeEl) {
            activeEl.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'instant' });
        }
    });
});
</script>

<template>
    <nav v-if="currentClub" class="border-b border-border bg-background lg:hidden">
        <div ref="navContainer" class="flex overflow-x-auto scrollbar-none">
            <Link
                v-for="item in clubNavItems"
                :key="item.title"
                :href="item.href"
                :data-active="isActive(item)"
                class="flex shrink-0 items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-colors"
                :class="isActive(item) ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
            >
                <component :is="item.icon" class="size-4" />
                {{ item.title }}
            </Link>
        </div>
    </nav>
</template>
