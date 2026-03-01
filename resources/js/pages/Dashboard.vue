<script setup lang="ts">
import { Head, WhenVisible } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Heading from '@/components/Heading.vue';
import TextLink from '@/components/TextLink.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, FootballMatch } from '@/types';

type Props = {
    upcomingMatches?: FootballMatch[];
    recentMatches?: FootballMatch[];
    clubCount: number;
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <Heading title="Dashboard" />

            <div class="mt-6 grid gap-6 md:grid-cols-3">
                <Card>
                    <CardHeader><CardTitle>Clubs</CardTitle></CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ clubCount }}</p>
                        <TextLink href="/clubs" class="mt-2 text-sm">View all clubs</TextLink>
                    </CardContent>
                </Card>
            </div>

            <div class="mt-8 grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Upcoming Matches</CardTitle></CardHeader>
                    <CardContent>
                        <WhenVisible :data="['upcomingMatches']" :fallback="'Loading...'">
                            <ul v-if="upcomingMatches?.length" class="space-y-3">
                                <li v-for="match in upcomingMatches" :key="match.id" class="flex items-center justify-between rounded border p-2">
                                    <div>
                                        <TextLink :href="`/clubs/${match.club_id}/matches/${match.id}`">
                                            {{ match.title }}
                                        </TextLink>
                                        <p class="text-xs text-muted-foreground">
                                            {{ new Date(match.scheduled_at).toLocaleDateString() }}
                                        </p>
                                    </div>
                                    <Badge variant="outline">{{ match.attendances_count ?? 0 }} players</Badge>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">No upcoming matches.</p>
                        </WhenVisible>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Recent Matches</CardTitle></CardHeader>
                    <CardContent>
                        <WhenVisible :data="['recentMatches']" :fallback="'Loading...'">
                            <ul v-if="recentMatches?.length" class="space-y-3">
                                <li v-for="match in recentMatches" :key="match.id" class="flex items-center justify-between rounded border p-2">
                                    <div>
                                        <TextLink :href="`/clubs/${match.club_id}/matches/${match.id}`">
                                            {{ match.title }}
                                        </TextLink>
                                        <p class="text-xs text-muted-foreground">
                                            {{ match.ended_at ? new Date(match.ended_at).toLocaleDateString() : 'Completed' }}
                                        </p>
                                    </div>
                                    <Badge>completed</Badge>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">No recent matches.</p>
                        </WhenVisible>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
