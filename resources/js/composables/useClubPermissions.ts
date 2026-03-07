import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useClubPermissions() {
    const page = usePage();
    const role = computed(() => page.props.currentMemberRole);
    const isAdmin = computed(() => role.value === 'admin' || role.value === 'owner');
    const isOwner = computed(() => role.value === 'owner');

    return { role, isAdmin, isOwner };
}
