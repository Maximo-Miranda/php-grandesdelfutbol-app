<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Settings, UserPlus, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useClubPermissions } from '@/composables/useClubPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, ClubMember, FootballMatch } from '@/types';

type Props = {
    club: Club & { members: ClubMember[]; members_count: number; matches_count?: number };
    nextMatch?: FootballMatch & { attendances_count?: number };
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
];

const base = `/clubs/${props.club.ulid}`;

const { role: userRole, isAdmin } = useClubPermissions();

const confirmedCount = computed(() => {
    if (!props.nextMatch?.attendances) return 0;
    return props.nextMatch.attendances.filter(a => a.status === 'confirmed').length;
});

function formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es', { weekday: 'short', day: 'numeric', month: 'short' })
        + ' a las '
        + d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head :title="club.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <!-- Club header -->
            <div class="mb-6 flex items-center gap-4">
                <div
                    v-if="club.logo_url"
                    class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted"
                >
                    <img :src="club.logo_url" :alt="club.name" class="size-full object-cover" />
                </div>
                <div
                    v-else
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-muted text-xl font-bold text-muted-foreground"
                >
                    {{ club.name.charAt(0) }}
                </div>
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold">
                        {{ club.name }}
                        <Badge variant="outline" class="text-xs capitalize">{{ userRole }}</Badge>
                    </h1>
                    <p v-if="club.description" class="text-sm text-muted-foreground">{{ club.description }}</p>
                </div>
            </div>

            <!-- Next match card -->
            <Link
                v-if="nextMatch"
                :href="`${base}/matches/${nextMatch.ulid}`"
                class="mb-4 block rounded-lg border border-border p-4 transition-colors hover:bg-accent"
            >
                <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-primary">Proximo partido</p>
                <p class="text-lg font-bold">{{ nextMatch.title }}</p>
                <p class="text-sm text-muted-foreground">
                    {{ formatDate(nextMatch.scheduled_at) }}
                    <span v-if="nextMatch.field"> &middot; {{ nextMatch.field.name }}</span>
                </p>
                <p class="mt-1 text-sm font-medium text-primary">
                    {{ confirmedCount }}/{{ nextMatch.max_players }} confirmados
                </p>
            </Link>

            <div v-else class="mb-4 rounded-lg border border-border p-4 text-center">
                <CalendarDays class="mx-auto mb-2 size-8 text-muted-foreground" />
                <p class="font-medium">Sin partidos proximos</p>
                <p class="text-sm text-muted-foreground">Crea un partido para empezar a organizar.</p>
            </div>

            <!-- Quick nav cards - full width on mobile, 2 cols on md+ -->
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Link
                    :href="`${base}/players`"
                    class="flex items-center gap-4 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <UsersRound class="size-5" />
                    </div>
                    <div>
                        <p class="font-semibold">Jugadores</p>
                        <p class="text-sm text-muted-foreground">{{ club.members_count ?? 0 }} en el club</p>
                    </div>
                </Link>

                <Link
                    :href="`${base}/matches`"
                    class="flex items-center gap-4 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500">
                        <CalendarDays class="size-5" />
                    </div>
                    <div>
                        <p class="font-semibold">Partidos</p>
                        <p class="text-sm text-muted-foreground">{{ club.matches_count ?? 0 }} jugados</p>
                    </div>
                </Link>

                <Link
                    v-if="isAdmin"
                    :href="`${base}/invite`"
                    class="flex items-center gap-4 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <UserPlus class="size-5" />
                    </div>
                    <div>
                        <p class="font-semibold">Invitar</p>
                        <p class="text-sm text-muted-foreground">Suma amigos al club</p>
                    </div>
                </Link>

                <Link
                    v-if="isAdmin"
                    :href="`${base}/edit`"
                    class="flex items-center gap-4 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                        <Settings class="size-5" />
                    </div>
                    <div>
                        <p class="font-semibold">Configuracion</p>
                        <p class="text-sm text-muted-foreground">Logo, acceso, invitacion</p>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
