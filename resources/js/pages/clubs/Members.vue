<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { LogOut } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useClubPermissions } from '@/composables/useClubPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, ClubMember } from '@/types';

type Props = {
    club: Club;
    members: ClubMember[];
};

const props = defineProps<Props>();
const { role, isAdmin, isOwner } = useClubPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Members', href: `/clubs/${props.club.ulid}/members` },
];

function canManage(member: ClubMember): boolean {
    if (!role.value) return false;
    const rankMap: Record<string, number> = { owner: 2, admin: 1, player: 0 };
    return (rankMap[role.value] ?? 0) > (rankMap[member.role] ?? 0);
}

function approveMember(member: ClubMember) {
    router.patch(`/clubs/${props.club.ulid}/members/${member.ulid}/approve`);
}

function removeMember(member: ClubMember) {
    if (!confirm('Eliminar este miembro?')) return;
    router.delete(`/clubs/${props.club.ulid}/members/${member.ulid}`);
}

function leaveClub() {
    if (!confirm('Salir del club? Esta accion no se puede deshacer.')) return;
    router.post(`/clubs/${props.club.ulid}/leave`);
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
                        <li v-for="member in members" :key="member.ulid" class="flex items-center justify-between rounded-lg border p-3">
                            <div>
                                <p class="font-medium">{{ member.user?.name }}</p>
                                <Badge variant="secondary">{{ member.role }}</Badge>
                                <Badge v-if="member.status === 'pending'" variant="outline" class="ml-1">Pending</Badge>
                            </div>
                            <div class="flex gap-2">
                                <template v-if="isAdmin">
                                    <Button v-if="member.status === 'pending'" size="sm" @click="approveMember(member)">Approve</Button>
                                    <Button v-if="canManage(member)" variant="destructive" size="sm" @click="removeMember(member)">Remove</Button>
                                </template>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Leave club button -->
            <div v-if="!isOwner" class="mt-6 flex justify-center">
                <Button variant="outline" class="text-destructive" @click="leaveClub">
                    <LogOut class="mr-2 size-4" />
                    Salir del club
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
