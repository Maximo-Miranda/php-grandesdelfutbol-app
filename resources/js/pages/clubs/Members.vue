<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, ClubMember } from '@/types';

type Props = {
    club: Club;
    members: ClubMember[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Members', href: `/clubs/${props.club.id}/members` },
];

function approveMember(member: ClubMember) {
    router.patch(`/clubs/${props.club.id}/members/${member.id}/approve`);
}

function removeMember(member: ClubMember) {
    router.delete(`/clubs/${props.club.id}/members/${member.id}`);
}
</script>

<template>
    <Head :title="`${club.name} Members`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Members" :description="`Manage ${club.name} members`" />

            <Card class="mt-6">
                <CardHeader>
                    <CardTitle>All Members</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-3">
                        <li v-for="member in members" :key="member.id" class="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <p class="font-medium">{{ member.user?.name }}</p>
                                <Badge variant="secondary">{{ member.role }}</Badge>
                                <Badge v-if="member.status === 'pending'" variant="outline" class="ml-1">Pending</Badge>
                            </div>
                            <div class="flex gap-2">
                                <Button v-if="member.status === 'pending'" size="sm" @click="approveMember(member)">Approve</Button>
                                <Button v-if="member.role !== 'owner'" variant="destructive" size="sm" @click="removeMember(member)">Remove</Button>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
