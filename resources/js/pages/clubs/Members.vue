<script setup lang="ts">
import { Head, InfiniteScroll, router, useForm } from '@inertiajs/vue3';
import { Check, EllipsisVertical, LogOut, Mail, Search, Send, ShieldCheck, ShieldMinus, UserMinus, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
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
import { Separator } from '@/components/ui/separator';
import UserAvatar from '@/components/UserAvatar.vue';
import { roleBadgeClass, roleLabel, useClubPermissions } from '@/composables/useClubPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/utils';
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
    search: string;
    pendingMembers: ClubMember[];
    members: PaginatedMembers;
    invitations: PaginatedInvitations | never[];
};

const props = defineProps<Props>();
const { role, isAdmin, isOwner } = useClubPermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Miembros', href: `/clubs/${props.club.ulid}/members` },
];

const form = useForm({ email: '' });

// --- Search ---
const searchQuery = ref(props.search ?? '');
let searchTimeout: ReturnType<typeof setTimeout>;

watch(searchQuery, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(`/clubs/${props.club.ulid}/members`, val ? { search: val } : {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['members', 'search'],
        });
    }, 300);
});

// --- Helpers ---
function canManage(member: ClubMember): boolean {
    if (!role.value) return false;
    const rankMap: Record<string, number> = { owner: 2, admin: 1, player: 0 };
    return (rankMap[role.value] ?? 0) > (rankMap[member.role] ?? 0);
}


function invitationStatus(inv: Invitation): { label: string; variant: 'default' | 'secondary' | 'outline' | 'destructive' } {
    if (inv.status === 'accepted') return { label: 'Aceptada', variant: 'default' };
    if (inv.status === 'pending' && new Date(inv.expires_at) < new Date()) return { label: 'Expirada', variant: 'destructive' };
    if (inv.status === 'pending') return { label: 'Pendiente', variant: 'outline' };
    if (inv.status === 'declined') return { label: 'Rechazada', variant: 'secondary' };
    return { label: inv.status, variant: 'outline' };
}

function formatShortDate(dateStr: string): string {
    return formatDate(dateStr, { day: 'numeric', month: 'short' });
}

// --- Actions ---
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

function updateRole(member: ClubMember, newRole: 'admin' | 'player'): void {
    router.patch(`/clubs/${props.club.ulid}/members/${member.ulid}/role`, { role: newRole });
}

// --- Confirm dialogs ---
const showRemoveDialog = ref(false);
const memberToRemove = ref<ClubMember | null>(null);
const removingMember = ref(false);

function confirmRemove(member: ClubMember) {
    memberToRemove.value = member;
    showRemoveDialog.value = true;
}

function removeMember() {
    if (!memberToRemove.value) return;
    removingMember.value = true;
    router.delete(`/clubs/${props.club.ulid}/members/${memberToRemove.value.ulid}`, {
        onFinish: () => {
            removingMember.value = false;
            showRemoveDialog.value = false;
            memberToRemove.value = null;
        },
    });
}

const showLeaveDialog = ref(false);
const leavingClub = ref(false);

function leaveClub() {
    leavingClub.value = true;
    router.post(`/clubs/${props.club.ulid}/leave`, {}, {
        onFinish: () => { leavingClub.value = false; },
    });
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
                <form class="flex gap-2" @submit.prevent="submitInvite">
                    <div class="min-w-0 flex-1">
                        <div class="relative">
                            <Mail class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input id="email" v-model="form.email" type="email" required placeholder="amigo@email.com" class="pl-10" />
                        </div>
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>
                    <Button type="submit" :disabled="form.processing" class="shrink-0">
                        <Send class="mr-2 size-4" />
                        Invitar
                    </Button>
                </form>
            </div>

            <!-- Pending members -->
            <div v-if="pendingMembers.length > 0 && isAdmin" class="mb-6">
                <div class="mb-3 flex items-center justify-center gap-4 text-xs font-semibold uppercase tracking-wider text-yellow-500">
                    <span class="h-px flex-1 bg-yellow-500/30" />
                    <span>Solicitudes pendientes ({{ pendingMembers.length }})</span>
                    <span class="h-px flex-1 bg-yellow-500/30" />
                </div>

                <div class="space-y-2">
                    <div
                        v-for="member in pendingMembers"
                        :key="member.ulid"
                        class="rounded-lg border border-yellow-500/30 bg-yellow-500/5 p-3"
                    >
                        <div class="flex items-center gap-3">
                            <UserAvatar
                                :src="member.user?.player_profile?.photo_url"
                                :name="member.user?.name ?? '?'"
                                class="size-9"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ member.user?.name }}</p>
                                <span class="text-xs text-muted-foreground">{{ member.user?.email }}</span>
                            </div>
                        </div>
                        <div class="mt-2 flex gap-2">
                            <Button variant="outline" size="sm" class="flex-1 text-green-600 hover:bg-green-500/10 hover:text-green-500" @click="approveMember(member)">
                                <Check class="mr-1 size-3.5" />
                                Aprobar
                            </Button>
                            <Button variant="outline" size="sm" class="flex-1 text-red-600 hover:bg-red-500/10 hover:text-red-500" @click="rejectMember(member)">
                                <X class="mr-1 size-3.5" />
                                Rechazar
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Members section -->
            <div class="mb-3 flex items-center justify-center gap-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                <span class="h-px flex-1 bg-border" />
                <span>Miembros</span>
                <span class="h-px flex-1 bg-border" />
            </div>

            <!-- Search -->
            <div class="relative mb-4">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Buscar por nombre o email..."
                    class="pl-10"
                />
            </div>

            <div v-if="members.data.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                <p v-if="searchQuery" class="text-muted-foreground">No se encontraron miembros con "{{ searchQuery }}".</p>
                <p v-else class="text-muted-foreground">No hay miembros.</p>
            </div>

            <template v-else>
                <InfiniteScroll data="members" only-next>
                    <div class="space-y-2">
                        <div
                            v-for="member in members.data"
                            :key="member.ulid"
                            class="flex items-center gap-3 rounded-lg border border-border p-3 transition-colors"
                        >
                            <UserAvatar
                                :src="member.user?.player_profile?.photo_url"
                                :name="member.user?.name ?? '?'"
                                class="size-9"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ member.user?.name }}</p>
                                <Badge variant="outline" :class="['text-[10px]', roleBadgeClass(member.role)]">{{ roleLabel(member.role) }}</Badge>
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
                                        Hacer administrador
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="isOwner && member.role === 'admin'" class="gap-2" @click="updateRole(member, 'player')">
                                        <ShieldMinus class="size-4" />
                                        Quitar administrador
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="gap-2 text-destructive" @click="confirmRemove(member)">
                                        <UserMinus class="size-4" />
                                        Expulsar
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

            <!-- Invitations sent (admins only) -->
            <div v-if="isAdmin && 'data' in invitations && invitations.data.length > 0" class="mt-8">
                <Separator class="mb-6" />

                <div class="mb-4 flex items-center justify-center gap-4 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    <span class="h-px flex-1 bg-border" />
                    <span>Invitaciones enviadas</span>
                    <span class="h-px flex-1 bg-border" />
                </div>

                <InfiniteScroll data="invitations" only-next>
                    <div class="space-y-2">
                        <div v-for="inv in (invitations as PaginatedInvitations).data" :key="inv.id" class="flex items-center gap-3 rounded-lg border border-border p-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm">
                                <Mail class="size-4 text-muted-foreground" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ inv.email }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="inv.inviter">por {{ inv.inviter.name }}</span>
                                    <span v-if="inv.inviter"> &middot; </span>
                                    {{ formatShortDate(inv.created_at) }}
                                </p>
                            </div>
                            <Badge :variant="invitationStatus(inv).variant" class="shrink-0 text-xs">{{ invitationStatus(inv).label }}</Badge>
                        </div>
                    </div>

                    <template #loading>
                        <div class="flex justify-center py-3">
                            <div class="size-5 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                        </div>
                    </template>
                </InfiniteScroll>
            </div>

            <!-- Leave club button -->
            <div v-if="!isOwner" class="mt-8 flex justify-center">
                <Button variant="outline" class="text-destructive" @click="showLeaveDialog = true">
                    <LogOut class="mr-2 size-4" />
                    Salir del club
                </Button>
            </div>
        </div>

        <!-- Remove member dialog -->
        <ConfirmDialog
            v-model:open="showRemoveDialog"
            title="Expulsar miembro"
            :description="`Se expulsará a ${memberToRemove?.user?.name ?? ''} del club. Ya no tendrá acceso a los partidos ni estadísticas.`"
            confirm-label="Expulsar"
            :destructive="true"
            :processing="removingMember"
            @confirm="removeMember"
        />

        <!-- Leave club dialog -->
        <ConfirmDialog
            v-model:open="showLeaveDialog"
            title="Salir del club"
            description="Esta acción no se puede deshacer. Perderás acceso al club y sus partidos."
            confirm-label="Salir del club"
            :destructive="true"
            :processing="leavingClub"
            @confirm="leaveClub"
        />
    </AppLayout>
</template>
