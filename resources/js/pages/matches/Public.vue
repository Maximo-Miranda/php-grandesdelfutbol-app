<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { FootballMatch, MatchEvent } from '@/types';

type Props = { match: FootballMatch & { club: { name: string } } };
const props = defineProps<Props>();

function eventsByType(type: string): MatchEvent[] {
    return props.match.events?.filter(e => e.event_type === type) ?? [];
}

const goals = [...eventsByType('goal'), ...eventsByType('penalty_scored'), ...eventsByType('own_goal')];
</script>

<template>
    <Head :title="match.title" />
    <div class="min-h-screen bg-background">
        <div class="mx-auto max-w-3xl p-6">
            <div class="mb-6 text-center">
                <p class="text-sm text-muted-foreground">{{ match.club.name }}</p>
                <h1 class="text-2xl font-bold">{{ match.title }}</h1>
                <Badge class="mt-2">{{ match.status.replace('_', ' ') }}</Badge>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Details</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p><strong>Date:</strong> {{ new Date(match.scheduled_at).toLocaleString() }}</p>
                        <p><strong>Duration:</strong> {{ match.duration_minutes }} min</p>
                        <p v-if="match.field"><strong>Field:</strong> {{ match.field.name }}</p>
                        <p v-if="match.notes"><strong>Notes:</strong> {{ match.notes }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Players ({{ match.attendances?.filter(a => a.status === 'confirmed').length ?? 0 }})</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="match.attendances?.length" class="space-y-1">
                            <li v-for="att in match.attendances" :key="att.id" class="flex items-center justify-between text-sm">
                                <span>{{ att.player?.name }}</span>
                                <div class="flex gap-1">
                                    <Badge :variant="att.status === 'confirmed' ? 'default' : 'secondary'" class="text-xs">{{ att.status }}</Badge>
                                    <Badge v-if="att.team" variant="outline" class="text-xs">Team {{ att.team.toUpperCase() }}</Badge>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">No registrations yet.</p>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="match.events?.length" class="mt-6">
                <CardHeader><CardTitle>Match Events</CardTitle></CardHeader>
                <CardContent>
                    <ul class="space-y-1">
                        <li v-for="event in match.events" :key="event.id" class="flex items-center justify-between text-sm">
                            <span>{{ event.player?.name }}</span>
                            <div>
                                <Badge variant="outline">{{ event.event_type.replace(/_/g, ' ') }}</Badge>
                                <span class="ml-1 text-muted-foreground">{{ event.minute }}'</span>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <div class="mt-8 text-center text-xs text-muted-foreground">
                Shared via Grandes del Futbol
            </div>
        </div>
    </div>
</template>
