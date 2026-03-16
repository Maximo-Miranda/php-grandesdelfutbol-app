<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { FootballMatch } from '@/types';

type Props = { match: FootballMatch & { club: { name: string } } };
defineProps<Props>();
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
                    <CardHeader><CardTitle>Detalles</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p><strong>Fecha:</strong> {{ new Date(match.scheduled_at).toLocaleString() }}</p>
                        <p><strong>Duración:</strong> {{ match.duration_minutes }} min</p>
                        <p v-if="match.field"><strong>Cancha:</strong> {{ match.field.name }}</p>
                        <p v-if="match.notes"><strong>Notas:</strong> {{ match.notes }}</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Jugadores ({{ match.attendances?.filter(a => a.status === 'confirmed').length ?? 0 }})</CardTitle></CardHeader>
                    <CardContent>
                        <ul v-if="match.attendances?.length" class="space-y-1">
                            <li v-for="att in match.attendances" :key="att.id" class="flex items-center justify-between text-sm">
                                <span>{{ att.player?.name }}</span>
                                <div class="flex gap-1">
                                    <Badge :variant="att.status === 'confirmed' ? 'default' : 'secondary'" class="text-xs">{{ att.status }}</Badge>
                                    <Badge v-if="att.team" variant="outline" class="text-xs">Equipo {{ att.team.toUpperCase() }}</Badge>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">Aún no hay inscripciones.</p>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="match.events?.length" class="mt-6">
                <CardHeader><CardTitle>Eventos del Partido</CardTitle></CardHeader>
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
                Compartido vía Grandes del Fútbol
            </div>
        </div>
    </div>
</template>
