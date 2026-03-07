<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MapPin, Plus } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useClubPermissions } from '@/composables/useClubPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Venue } from '@/types';

type Props = { club: Club; venues: Venue[] };
const props = defineProps<Props>();
const { isAdmin } = useClubPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Canchas', href: `/clubs/${props.club.ulid}/venues` },
];
</script>

<template>
    <Head :title="`${club.name} - Canchas`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">Canchas</h1>
                <Link v-if="isAdmin" :href="`/clubs/${club.ulid}/venues/create`">
                    <Button><Plus class="mr-2 size-4" />Crear</Button>
                </Link>
            </div>

            <div v-if="venues.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-muted-foreground">No hay canchas.</p>
            </div>

            <div v-else class="space-y-3">
                <Link
                    v-for="venue in venues"
                    :key="venue.id"
                    :href="`/clubs/${club.ulid}/venues/${venue.ulid}`"
                    class="flex items-center gap-3 rounded-lg border border-border p-4 transition-colors hover:bg-accent"
                >
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <MapPin class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ venue.name }}</p>
                        <p v-if="venue.address" class="text-sm text-muted-foreground">{{ venue.address }}</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <Badge
                            v-for="field in (venue.fields ?? [])"
                            :key="field.id"
                            variant="outline"
                            class="text-xs"
                        >
                            {{ field.field_type }}
                        </Badge>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
