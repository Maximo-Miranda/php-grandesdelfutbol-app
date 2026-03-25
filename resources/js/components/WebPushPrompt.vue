<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';
import { useWebPush } from '@/composables/useWebPush';

const page = usePage<{ auth: { user: unknown }; currentClub?: { ulid: string } }>();
const { isSupported, isSubscribed, permission, needsInstall } = useWebPush();

const visible = computed(() =>
    page.props.auth.user &&
    page.props.currentClub &&
    isSupported.value &&
    !isSubscribed.value &&
    !needsInstall.value &&
    permission.value !== 'denied',
);

const href = computed(() =>
    page.props.currentClub ? `/clubs/${page.props.currentClub.ulid}/notifications` : '#',
);
</script>

<template>
    <Link
        v-if="visible"
        :href="href"
        class="group flex items-center gap-3 border-b border-amber-500/20 bg-gradient-to-r from-amber-500/10 to-amber-400/5 px-4 py-3 transition-all hover:from-amber-500/15 hover:to-amber-400/10"
    >
        <span class="relative flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-500/20">
            <Bell class="size-4 text-amber-500 transition-transform group-hover:rotate-12" />
            <span class="absolute -right-0.5 -top-0.5 flex size-2.5">
                <span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75" />
                <span class="relative inline-flex size-2.5 rounded-full bg-amber-500" />
            </span>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-foreground">
                No te pierdas nada
            </p>
            <p class="text-xs text-muted-foreground">
                Activa las alertas y enterate primero
            </p>
        </div>
        <span class="shrink-0 rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-medium text-amber-600 dark:text-amber-400">
            Activar
        </span>
    </Link>
</template>
