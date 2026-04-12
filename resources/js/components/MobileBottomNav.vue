<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Film, Newspaper, Shield, UserCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const { isCurrentOrParentUrl } = useCurrentUrl();
const page = usePage();

const tabs = [
    { title: 'Clubes', href: '/clubs', icon: Shield },
    { title: 'Noticias', href: '/news', icon: Newspaper },
    { title: 'Jugadas', href: '/player-card', icon: Film },
    { title: 'Perfil', href: '/settings/profile', icon: UserCircle },
];

function isActive(tab: (typeof tabs)[number]): boolean {
    if (tab.href === '/settings/profile') {
        return isCurrentOrParentUrl('/settings');
    }
    return isCurrentOrParentUrl(tab.href);
}

// Shared prop refreshes automatically on every Inertia navigation and on the
// layout-level 5-minute poll (see AppSidebarLayout.vue), so this stays in sync
// without us running an extra partial reload here that could race with nav.
const newsUnread = computed(() => page.props.newsUnreadCount ?? { count: 0, hasBreaking: false });

const newsBadgeLabel = computed(() => (newsUnread.value.count > 9 ? '9+' : String(newsUnread.value.count)));

const showNewsBadge = computed(
    () => newsUnread.value.count > 0 && !isCurrentOrParentUrl('/news'),
);
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background/95 backdrop-blur-md lg:hidden">
        <div class="flex h-16 items-stretch">
            <Link
                v-for="tab in tabs"
                :key="tab.title"
                :href="tab.href"
                class="relative flex flex-1 flex-col items-center justify-center gap-0.5 text-xs transition-colors"
                :class="isActive(tab) ? 'text-primary' : 'text-muted-foreground'"
            >
                <div class="relative">
                    <component :is="tab.icon" class="size-5" />
                    <span
                        v-if="tab.href === '/news' && showNewsBadge"
                        class="absolute -right-3 -top-2 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[11px] font-bold leading-none text-white shadow-lg shadow-red-500/50"
                        :class="{ 'animate-pulse': newsUnread.hasBreaking }"
                    >
                        {{ newsBadgeLabel }}
                    </span>
                </div>
                <span class="font-medium">{{ tab.title }}</span>
            </Link>
        </div>
        <!-- Safe area for devices with home indicator -->
        <div class="h-[env(safe-area-inset-bottom)]" />
    </nav>
</template>
