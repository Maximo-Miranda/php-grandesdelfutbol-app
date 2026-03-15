<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IdCard, Shield, UserCircle } from 'lucide-vue-next';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const { isCurrentOrParentUrl } = useCurrentUrl();

const tabs = [
    { title: 'Clubes', href: '/clubs', icon: Shield },
    { title: 'Mi Tarjeta', href: '/player-card', icon: IdCard },
    { title: 'Perfil', href: '/settings/profile', icon: UserCircle },
];

function isActive(tab: (typeof tabs)[number]): boolean {
    if (tab.href === '/settings/profile') {
        return isCurrentOrParentUrl('/settings');
    }
    return isCurrentOrParentUrl(tab.href);
}
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background/95 backdrop-blur-md lg:hidden">
        <div class="flex h-16 items-stretch">
            <Link
                v-for="tab in tabs"
                :key="tab.title"
                :href="tab.href"
                class="flex flex-1 flex-col items-center justify-center gap-0.5 text-xs transition-colors"
                :class="isActive(tab) ? 'text-primary' : 'text-muted-foreground'"
            >
                <component :is="tab.icon" class="size-5" />
                <span class="font-medium">{{ tab.title }}</span>
            </Link>
        </div>
        <!-- Safe area for devices with home indicator -->
        <div class="h-[env(safe-area-inset-bottom)]" />
    </nav>
</template>
