<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    Film,
    Home,
    MapPin,
    Newspaper,
    Settings,
    Shield,
    Trophy,
    Users,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useClubPermissions } from '@/composables/useClubPermissions';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useNewsBadge } from '@/composables/useNewsBadge';
import type { Club, NavItem } from '@/types';

const page = usePage<{ currentClub: Club | null; features?: { team_standings?: boolean } }>();
const { isCurrentOrParentUrl } = useCurrentUrl();
const { isAdmin } = useClubPermissions();
const { newsUnread, badgeLabel: newsBadgeLabel, showBadge: showNewsBadge } = useNewsBadge();

const currentClub = computed(() => page.props.currentClub);
const teamStandingsEnabled = computed(() => page.props.features?.team_standings !== false);

const globalNavItems: NavItem[] = [
    { title: 'Mis Clubes', description: 'Clubes a los que perteneces', href: '/clubs', icon: Shield },
    { title: 'Mis Jugadas', description: 'Tus reels y momentos', href: '/player-card', icon: Film },
    { title: 'Noticias', description: 'Fútbol en español', href: '/news', icon: Newspaper },
];

const clubNavItems = computed<NavItem[]>(() => {
    const club = currentClub.value;
    if (!club) return [];

    const base = `/clubs/${club.ulid}`;
    const items: NavItem[] = [
        { title: 'Inicio', description: 'Resumen del club', href: base, icon: Home },
        { title: 'Partidos', description: 'Calendario y resultados', href: `${base}/matches`, icon: CalendarDays },
    ];

    if (teamStandingsEnabled.value) {
        items.push({ title: 'Posiciones', description: 'Equipos, jugadores y roster', href: `${base}/standings`, icon: Trophy });
    } else {
        items.push({ title: 'Jugadores', description: 'Roster del club', href: `${base}/players`, icon: UsersRound });
    }

    items.push(
        { title: 'Canchas', description: 'Lugares de juego', href: `${base}/venues`, icon: MapPin },
    );

    if (isAdmin.value) {
        items.push(
            { title: 'Miembros', description: 'Usuarios del club', href: `${base}/members`, icon: Users },
            { title: 'Ajustes', description: 'Configuración del club', href: `${base}/edit`, icon: Settings },
        );
    }

    return items;
});

function matchesItem(item: NavItem, club: Club | null): boolean {
    if (item.title === 'Posiciones' && club) {
        const base = `/clubs/${club.ulid}`;
        if (isCurrentOrParentUrl(`${base}/teams`) || isCurrentOrParentUrl(`${base}/seasons`)) {
            return true;
        }
    }
    return isCurrentOrParentUrl(item.href);
}

function isActive(item: NavItem, allItems: NavItem[]): boolean {
    const club = currentClub.value;
    if (item.title === 'Inicio') {
        return isCurrentOrParentUrl(item.href) && !allItems.filter(i => i !== item).some(i => matchesItem(i, club));
    }
    return matchesItem(item, club);
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/clubs" class="flex items-center gap-3">
                            <AppLogoIcon class="!size-10 shrink-0 text-primary" />
                            <span class="text-lg font-bold tracking-tight">GDF</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- Global nav -->
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in globalNavItems" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            size="lg"
                            :is-active="isActive(item, globalNavItems)"
                            :tooltip="item.title"
                            :class="{ '!overflow-visible': item.href === '/news' && showNewsBadge }"
                        >
                            <Link :href="item.href" class="flex items-center gap-2">
                                <div class="relative shrink-0">
                                    <component :is="item.icon" />
                                    <span
                                        v-if="item.href === '/news' && showNewsBadge"
                                        class="absolute -right-1.5 -top-1.5 flex h-[16px] min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white shadow-sm shadow-red-500/50"
                                        :class="{ 'animate-pulse': newsUnread.hasBreaking }"
                                    >
                                        {{ newsBadgeLabel }}
                                    </span>
                                </div>
                                <div class="flex min-w-0 flex-col leading-tight">
                                    <span class="truncate text-sm font-medium">{{ item.title }}</span>
                                    <span v-if="item.description" class="truncate text-[11px] text-muted-foreground">{{ item.description }}</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Club nav -->
            <template v-if="currentClub">
                <SidebarSeparator />
                <SidebarGroup class="px-2 py-0">
                    <SidebarGroupLabel>{{ currentClub.name }}</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in clubNavItems" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                size="lg"
                                :is-active="isActive(item, clubNavItems)"
                                :tooltip="item.title"
                            >
                                <Link :href="item.href" class="flex items-center gap-2">
                                    <component :is="item.icon" class="shrink-0" />
                                    <div class="flex min-w-0 flex-col leading-tight">
                                        <span class="truncate text-sm font-medium">{{ item.title }}</span>
                                        <span v-if="item.description" class="truncate text-[11px] text-muted-foreground">{{ item.description }}</span>
                                    </div>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </template>
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
