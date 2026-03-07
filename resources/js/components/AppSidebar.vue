<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    Home,
    LayoutGrid,
    MapPin,
    Settings,
    Shield,
    UserCircle,
    Users,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useClubPermissions } from '@/composables/useClubPermissions';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { Club, NavItem } from '@/types';

const page = usePage<{ currentClub: Club | null }>();
const { isCurrentOrParentUrl } = useCurrentUrl();
const { isAdmin } = useClubPermissions();

const currentClub = computed(() => page.props.currentClub);

const globalNavItems: NavItem[] = [
    { title: 'Inicio', href: '/dashboard', icon: LayoutGrid },
    { title: 'Mis Clubes', href: '/clubs', icon: Shield },
    { title: 'Perfil de Jugador', href: '/player-profile', icon: UserCircle },
];

const clubNavItems = computed<NavItem[]>(() => {
    const club = currentClub.value;
    if (!club) return [];

    const base = `/clubs/${club.ulid}`;
    const items: NavItem[] = [
        { title: 'Inicio', href: base, icon: Home },
        { title: 'Partidos', href: `${base}/matches`, icon: CalendarDays },
        { title: 'Jugadores', href: `${base}/players`, icon: UsersRound },
        { title: 'Canchas', href: `${base}/venues`, icon: MapPin },
    ];

    if (isAdmin.value) {
        items.push(
            { title: 'Miembros', href: `${base}/members`, icon: Users },
            { title: 'Ajustes', href: `${base}/edit`, icon: Settings },
        );
    }

    return items;
});

const navItems = computed(() => {
    if (!currentClub.value) return globalNavItems;
    return [
        { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
        ...clubNavItems.value,
    ];
});

function isActive(item: NavItem, allItems: NavItem[]): boolean {
    if (item.title === 'Inicio' || item.title === 'Dashboard') {
        return isCurrentOrParentUrl(item.href) && !allItems.filter(i => i !== item).some(i => isCurrentOrParentUrl(i.href));
    }
    return isCurrentOrParentUrl(item.href);
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/clubs" class="flex items-center gap-3">
                            <AppLogoIcon class="size-8 shrink-0 text-primary" />
                            <span class="text-lg font-bold tracking-tight">GDF</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in navItems" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="isActive(item, navItems)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
