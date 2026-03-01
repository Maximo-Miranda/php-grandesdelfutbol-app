<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type Props = {
    clubs: Club[];
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Clubs',
        href: '/clubs',
    },
];
</script>

<template>
    <Head title="My Clubs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl p-4">
            <div class="mb-6 flex items-center justify-between">
                <Heading title="My Clubs" description="Manage your football clubs" />
                <Link href="/clubs/create">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Create Club
                    </Button>
                </Link>
            </div>

            <div v-if="clubs.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-muted-foreground">You don't have any clubs yet.</p>
                <Link href="/clubs/create" class="mt-4 inline-block">
                    <Button variant="outline">Create your first club</Button>
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <Link v-for="club in clubs" :key="club.id" :href="`/clubs/${club.id}`">
                    <Card class="transition-shadow hover:shadow-md">
                        <CardHeader>
                            <CardTitle>{{ club.name }}</CardTitle>
                            <CardDescription v-if="club.description">{{ club.description }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground">
                                {{ club.members_count }} {{ club.members_count === 1 ? 'member' : 'members' }}
                            </p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
