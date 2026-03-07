<script setup lang="ts">
import { Head, InfiniteScroll, router, useForm } from '@inertiajs/vue3';
import { Check, EllipsisVertical, LogOut, Mail, Send, ShieldCheck, ShieldMinus, UserMinus, X } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClubPermissions } from '@/composables/useClubPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, ClubMember } from '@/types';

type Invitation = {
    id: number;
    email: string;
    status: string;
    expires_at: string;
    inviter?: { name: string };
    created_at: string;
};

type PaginatedMembers = { data: ClubMember[] };
type PaginatedInvitations = { data: Invitation[] };

type Props = {
    club: Club;
    pendingMembers: ClubMember[];
    members: PaginatedMembers;
    invitations: PaginatedInvitations | never[];
};

const props = defineProps<Props>();
const { role, isAdmin, isOwner } = useClubPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Miembros', href: `/clubs/${props.club.ulid}/members` },
];

const form = useForm({ email: '' });

function canManage(member: ClubMember): boolean {
    if (!role.value) return false;
    const rankMap: Record<string, number> = { owner: 2, admin: 1, player: 0 };
    return (rankMap[role.value] ?? 0) > (rankMap[member.role] ?? 0);
}

function roleLabel(r: string): string {
    const labels: Record<string, string> = { owner: 'Dueno', admin: 'Admin', player: 'Jugador' };
    return labels[r] ?? r;
}

function roleBadgeVariant(r: string): 'default' | 'secondary' | 'outline' {
    if (r === 'owner') return 'default';
    if (r === 'admin') return 'secondary';
    return 'outline';
}

function invitationStatus(inv: Invitation): { label: string; variant: 'default' | 'secondary' | 'outline' | 'destructive' } {
    if (inv.status === 'accepted') return { label: 'Aceptada', variant: 'default' };
    if (inv.status === 'pending' && new Date(inv.expires_at) < new Date()) return { label: 'Expirada', variant: 'destructive' };
    if (inv.status === 'pending') return { label: 'Pendiente', variant: 'outline' };
    if (inv.status === 'declined') return { label: 'Rechazada', variant: 'secondary' };
    return { label: inv.status, variant: 'outline' };
}

function submitInvite(): void {
    form.post(`/clubs/${props.club.ulid}/invite`, {
        onSuccess: () => form.reset(),
    });
}

function approveMember(member: ClubMember): void {
    router.patch(`/clubs/${props.club.ulid}/members/${member.ulid}/approve`);
}

function rejectMember(member: ClubMember): void {
    router.delete(`/clubs/${props.club.ulid}/members/${member.ulid}/reject`);
}

function removeMember(member: ClubMember): void {
    if (!confirm('Eliminar este miembro?')) return;
    router.delete(`/clubs/${props.club.ulid}/members/${member.ulid}`);
}

function updateRole(member: ClubMember, newRole: 'admin' | 'player'): void {
    router.patch(`/clubs/${props.club.ulid}/members/${member.ulid}/role`, { role: newRole });
}

function leaveClub(): void {
    if (!confirm('Salir del club? Esta accion no se puede deshacer.')) return;
    router.post(`/clubs/${props.club.ulid}/leave`);
}
</script>

<template>
    <Head :title="`${club.name} - Miembros`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Miembros</h1>
                <p class="mt-1 text-sm text-muted-foreground">Gestiona los miembros de {{ club.name }}.</p>
            </div>

            <!-- Invite form (admins only) -->
            <div v-if="isAdmin" class="mb-6 rounded-lg border border-border p-4">
                <p class="mb-4 font-medium">Invitar por email</p>
                <form class="space-y-4" @submit.prevent="submitInvite">
                    <div class="grid gap-2">
                        <Label for="email">Email del jugador</Label>
                        <div class="relative">
                            <Mail class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input id="email" v-model="form.email" type="email" required placeholder="amigo@email.com" class="pl-10" />
                        </div>
                        <InputError :message="form.errors.email" />
                    </div>
                    <Button type="submit" :disabled="form.processing" class="w-full">
                        <Send class="mr-2 size-4" />
                        Enviar invitacion
                    </Button>
                </form>
            </div>

            <!-- Pending members -->
            <div v-if="pendingMembers.length > 0 && isAdmin" class="mb-6">
                <div class="mb-3 flex items-center justify-center gap-4 text-xs font-semibold uppercase tracking-wider text-yellow-500">
                    <span class="h-px flex-1 bg-yellow-500/30" />
                    <span>Solicitudes pendientes</span>
                    <span class="h-px flex-1 bg-yellow-500/30" />
                </div>

                <div class="space-y-2">
                    <div
                        v-for="member in pendingMembers"
                        :key="member.ulid"
                        class="flex items-center gap-3 rounded-lg border border-yellow-500/30 bg-yellow-500/5 p-3"
                    >
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-yellow-500/20 text-sm font-bold text-yellow-500">
                            {{ member.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ member.user?.name }}</p>
                            <span class="text-xs text-muted-foreground">{{ member.user?.email }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <Button variant="outline" size="sm" class="text-green-600 hover:bg-green-500/10 hover:text-green-500" @click="approveMember(member)">
                                <Check class="mr-1 size-3.5" />
                                Aprobar
                            </Button>
                            <Button variant="outline" size="sm" class="text-red-600 hover:bg-red-500/10 hover:text-red-500" @click="rejectMember(member)">
                                <X class="mr-1 size-3.5" />
                                Rechazar
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invitations sent (admins only) -->
            <div v-if="isAdmin && 'data' in invitations && invitations.data.length > 0" class="mb-6">
                <div class="mb-3 flex items-center justify-center gap-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <span class="h-px flex-1 bg-border" />
                    <span>Invitaciones enviadas</span>
                    <span class="h-px flex-1 bg-border" />
                </div>

                <InfiniteScroll data="invitations" only-next>
                    <div class="space-y-2">
                        <div v-for="inv in (invitations as PaginatedInvitations).data" :key="inv.id" class="flex items-center justify-between rounded-lg border border-border p-3">
                            <div class="min-w-0 flex-1">
                                <span class="text-sm">{{ inv.email }}</span>
                                <span v-if="inv.inviter" class="ml-2 text-xs text-muted-foreground">por {{ inv.inviter.name }}</span>
                            </div>
                            <Badge :variant="invitationStatus(inv).variant" class="text-xs">{{ invitationStatus(inv).label }}</Badge>
                        </div>
                    </div>

                    <template #loading>
                        <div class="flex justify-center py-3">
                            <div class="size-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                        </div>
                    </template>
                </InfiniteScroll>
            </div>

            <!-- Approved members -->
            <div v-if="members.data.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p class="text-muted-foreground">No hay miembros.</p>
            </div>

            <template v-else>
                <div class="mb-4 flex items-center justify-center gap-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <span class="h-px flex-1 bg-border" />
                    <span>Miembros</span>
                    <span class="h-px flex-1 bg-border" />
                </div>

                <InfiniteScroll data="members" only-next>
                    <div class="space-y-2">
                        <div
                            v-for="member in members.data"
                            :key="member.ulid"
                            class="flex items-center gap-3 rounded-lg border border-border p-3 transition-colors"
                        >
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-bold">
                                {{ member.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ member.user?.name }}</p>
                                <Badge :variant="roleBadgeVariant(member.role)" class="text-[10px]">{{ roleLabel(member.role) }}</Badge>
                            </div>
                            <DropdownMenu v-if="isAdmin && canManage(member)">
                                <DropdownMenuTrigger as-child>
                                    <button class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground">
                                        <EllipsisVertical class="size-4" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48">
                                    <DropdownMenuItem v-if="isOwner && member.role === 'player'" class="gap-2" @click="updateRole(member, 'admin')">
                                        <ShieldCheck class="size-4" />
                                        Promover a Admin
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="isOwner && member.role === 'admin'" class="gap-2" @click="updateRole(member, 'player')">
                                        <ShieldMinus class="size-4" />
                                        Cambiar a Jugador
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="gap-2 text-destructive" @click="removeMember(member)">
                                        <UserMinus class="size-4" />
                                        Eliminar
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <template #loading>
                        <div class="flex justify-center py-3">
                            <div class="size-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                        </div>
                    </template>
                </InfiniteScroll>
            </template>

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
