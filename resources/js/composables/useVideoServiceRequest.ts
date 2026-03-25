import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { getCsrfToken } from '@/lib/utils';

type MatchContext = {
    clubName: string;
    fieldName?: string;
    scheduledAt?: string;
    matchUlid?: string;
};

export function useVideoServiceRequest() {
    const page = usePage();
    const showDialog = ref(false);
    const plan = ref('recocha');
    const phone = ref(page.props.auth.user?.player_profile?.phone ?? '');
    const message = ref('');
    const errors = ref<Record<string, string[]>>({});
    const submitting = ref(false);
    const success = ref(false);

    let matchContext: MatchContext = { clubName: '' };

    function open(context: MatchContext): void {
        matchContext = context;
        showDialog.value = true;
    }

    function close(): void {
        showDialog.value = false;
        success.value = false;
    }

    async function submit(): Promise<void> {
        submitting.value = true;
        errors.value = {};
        const user = page.props.auth.user;

        try {
            const res = await fetch('/video-service-request', {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': getCsrfToken(),
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: user?.name ?? '',
                    email: user?.email ?? '',
                    phone: phone.value,
                    club_name: matchContext.clubName,
                    venue_address: matchContext.fieldName ?? '',
                    preferred_date: matchContext.scheduledAt?.split('T')[0] ?? '',
                    preferred_time: matchContext.scheduledAt?.split('T')[1]?.substring(0, 5) ?? '',
                    selected_plan: plan.value,
                    message: message.value || null,
                    match_ulid: matchContext.matchUlid ?? null,
                }),
            });

            if (res.ok) {
                success.value = true;
            } else if (res.status === 422) {
                const data = await res.json();
                errors.value = data.errors ?? {};
            }
        } finally {
            submitting.value = false;
        }
    }

    return {
        showDialog,
        plan,
        phone,
        message,
        errors,
        submitting,
        success,
        open,
        close,
        submit,
    };
}
