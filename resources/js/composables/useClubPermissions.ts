import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const roleLabels: Record<string, string> = { owner: 'Dueño', admin: 'Admin', player: 'Jugador' };

export function roleLabel(role: string): string {
    return roleLabels[role] ?? role;
}

export function roleBadgeVariant(role: string): 'default' | 'secondary' | 'outline' {
    if (role === 'owner') return 'default';
    if (role === 'admin') return 'secondary';
    return 'outline';
}

export function roleBadgeClass(role: string): string {
    if (role === 'owner') return 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30';
    if (role === 'admin') return 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/30';
    return 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30';
}

export function useClubPermissions() {
    const page = usePage();
    const role = computed(() => page.props.currentMemberRole);
    const roleDisplay = computed(() => (role.value ? roleLabel(role.value) : ''));
    const roleBadge = computed(() => roleBadgeVariant(role.value ?? 'player'));
    const isAdmin = computed(() => role.value === 'admin' || role.value === 'owner');
    const isOwner = computed(() => role.value === 'owner');

    return { role, roleDisplay, roleBadge, isAdmin, isOwner };
}
