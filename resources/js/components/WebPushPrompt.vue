<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, ChevronRight } from 'lucide-vue-next';
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
        class="flex items-center gap-3 border-b border-primary/20 bg-primary/10 px-4 py-2.5 transition-colors hover:bg-primary/15"
    >
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/20">
            <Bell class="size-4 text-primary" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-foreground">
                Activa las notificaciones push
            </p>
            <p class="text-xs text-muted-foreground">
                Enterate al instante de partidos, invitaciones y novedades de tu club
            </p>
        </div>
        <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
    </Link>
</template>
