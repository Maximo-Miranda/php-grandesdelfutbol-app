<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import TextLink from '@/components/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Player } from '@/types';

type Props = { club: Club; player: Player };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Players', href: `/clubs/${props.club.id}/players` },
    { title: props.player.name, href: `/clubs/${props.club.id}/players/${props.player.id}` },
];
</script>

<template>
    <Head :title="player.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <div class="mb-6 flex items-center justify-between">
                <Heading :title="player.name" :description="player.position ?? undefined" />
                <TextLink :href="`/clubs/${club.id}/players/${player.id}/edit`">Edit</TextLink>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Info</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <p v-if="player.jersey_number"><strong>Jersey:</strong> #{{ player.jersey_number }}</p>
                        <p><strong>Status:</strong> <Badge :variant="player.is_active ? 'default' : 'secondary'">{{ player.is_active ? 'Active' : 'Inactive' }}</Badge></p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Stats</CardTitle></CardHeader>
                    <CardContent class="space-y-1 text-sm">
                        <p>Matches: {{ player.matches_played }}</p>
                        <p>Goals: {{ player.goals }}</p>
                        <p>Assists: {{ player.assists }}</p>
                        <p>Yellow Cards: {{ player.yellow_cards }}</p>
                        <p>Red Cards: {{ player.red_cards }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
