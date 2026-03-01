<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Mail, Settings, UsersRound } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, ClubMember, FootballMatch } from '@/types';

type Props = {
    club: Club & { members: ClubMember[]; members_count: number; upcoming_matches?: FootballMatch[]; matches_count?: number };
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
];

const base = `/clubs/${props.club.id}`;

const userRole = props.club.members?.find(m => m.role === 'owner')?.role ?? 'member';
</script>

<template>
    <Head :title="club.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <!-- Club header -->
            <div class="mb-8 flex items-center gap-4">
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

            <!-- Upcoming matches -->
            <div class="mb-6 rounded-lg border border-border p-6 text-center">
                <CalendarDays class="mx-auto mb-2 size-8 text-muted-foreground" />
                <p class="font-medium">Sin partidos proximos</p>
                <p class="text-sm text-muted-foreground">Crea un partido para empezar a organizar.</p>
            </div>

            <!-- Quick nav cards -->
            <div class="grid grid-cols-2 gap-3">
                <Link
                    :href="`${base}/players`"
                    class="flex items-center gap-3 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <UsersRound class="size-5" />
                    </div>
                    <div>
                        <p class="font-medium">Jugadores</p>
                        <p class="text-sm text-muted-foreground">{{ club.members_count ?? 0 }} en el club</p>
                    </div>
                </Link>

                <Link
                    :href="`${base}/matches`"
                    class="flex items-center gap-3 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <CalendarDays class="size-5" />
                    </div>
                    <div>
                        <p class="font-medium">Partidos</p>
                        <p class="text-sm text-muted-foreground">{{ club.matches_count ?? 0 }} jugados</p>
                    </div>
                </Link>

                <Link
                    :href="`${base}/invite`"
                    class="flex items-center gap-3 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <Mail class="size-5" />
                    </div>
                    <div>
                        <p class="font-medium">Invitar</p>
                        <p class="text-sm text-muted-foreground">Suma amigos al club</p>
                    </div>
                </Link>

                <Link
                    :href="`${base}/edit`"
                    class="flex items-center gap-3 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <Settings class="size-5" />
                    </div>
                    <div>
                        <p class="font-medium">Configuracion</p>
                        <p class="text-sm text-muted-foreground">Logo, acceso, invitacion</p>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
