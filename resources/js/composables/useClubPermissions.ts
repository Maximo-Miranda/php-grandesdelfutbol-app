import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const roleLabels: Record<string, string> = { super_admin: 'Super Admin', owner: 'Dueño', admin: 'Admin', player: 'Jugador' };

export function roleLabel(role: string): string {
    return roleLabels[role] ?? role;
}

const roleBadgeClasses: Record<string, string> = {
    super_admin: 'bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/30',
    owner: 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30',
    admin: 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/30',
};

export function roleBadgeClass(role: string): string {
    return roleBadgeClasses[role] ?? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30';
}

export function useClubPermissions() {
    const page = usePage();
    const role = computed(() => page.props.currentMemberRole);
    const roleDisplay = computed(() => (role.value ? roleLabel(role.value) : ''));
    const isSuperAdmin = computed(() => role.value === 'super_admin');
    const isAdmin = computed(() => ['admin', 'owner', 'super_admin'].includes(role.value as string));
    const isOwner = computed(() => ['owner', 'super_admin'].includes(role.value as string));

    return { role, roleDisplay, isSuperAdmin, isAdmin, isOwner };
}
