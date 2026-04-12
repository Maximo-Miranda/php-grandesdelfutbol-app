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
import type { Club, NavItem } from '@/types';

const page = usePage<{ currentClub: Club | null }>();
const { isCurrentOrParentUrl } = useCurrentUrl();
const { isAdmin } = useClubPermissions();

const currentClub = computed(() => page.props.currentClub);

const newsUnread = computed(() => page.props.newsUnreadCount ?? { count: 0, hasBreaking: false });
const newsBadgeLabel = computed(() => (newsUnread.value.count > 9 ? '9+' : String(newsUnread.value.count)));
const showNewsBadge = computed(() => newsUnread.value.count > 0 && !isCurrentOrParentUrl('/news'));

const globalNavItems: NavItem[] = [
    { title: 'Mis Clubes', href: '/clubs', icon: Shield },
    { title: 'Mis Jugadas', href: '/player-card', icon: Film },
    { title: 'Noticias', href: '/news', icon: Newspaper },
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

function isActive(item: NavItem, allItems: NavItem[]): boolean {
    if (item.title === 'Inicio') {
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
                            :is-active="isActive(item, globalNavItems)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
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
                                <span>{{ item.title }}</span>
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
                                :is-active="isActive(item, clubNavItems)"
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
            </template>
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
