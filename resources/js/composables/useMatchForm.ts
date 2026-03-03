import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { colorLabel } from '@/constants/jerseyColors';
import type { Field, FootballMatch, Venue } from '@/types';

export type MatchFormData = {
    title: string;
    scheduled_at: string;
    field_id: number | null;
    duration_minutes: number;
    arrival_minutes: number;
    max_players: number;
    max_substitutes: number;
    registration_opens_hours: number;
    notes: string;
    team_a_name: string;
    team_b_name: string;
    team_a_color: string;
    team_b_color: string;
};

export const fieldTypeToPlayers: Record<string, number> = {
    '5v5': 10,
    '6v6': 12,
    '7v7': 14,
    '8v8': 16,
    '9v9': 18,
    '10v10': 20,
    '11v11': 22,
};

export function calcRegistrationHours(maxPlayers: number): number {
    return Math.round(maxPlayers * 2.4);
}

// Time options: 30-min intervals from 06:00 to 23:30
export const timeOptions = Array.from({ length: 36 }, (_, i) => {
    const h = Math.floor(i / 2) + 6;
    const m = (i % 2) * 30;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
});

export function formatShortDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    const weekday = date.toLocaleDateString('es', { weekday: 'short' });
    const num = date.getDate();
    const monthName = date.toLocaleDateString('es', { month: 'short' });
    const dayLabel = weekday.charAt(0).toUpperCase() + weekday.slice(1).replace('.', '');
    const monthLabel = monthName.charAt(0).toUpperCase() + monthName.slice(1).replace('.', '');
    return `${dayLabel} ${num} ${monthLabel}`;
}

export function getDefaultDate(): string {
    const target = new Date();
    target.setDate(target.getDate() + 3);

    return `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}-${String(target.getDate()).padStart(2, '0')}`;
}

type UseMatchFormOptions = {
    venues: Venue[];
    match?: FootballMatch;
    autoTitleOnInit?: boolean;
};

export function useMatchForm(options: UseMatchFormOptions) {
    const { venues, match, autoTitleOnInit = true } = options;

    // --- Field list ---
    const allFields = computed(() => {
        const fields: Array<{ label: string; field: Field }> = [];
        for (const venue of venues) {
            for (const field of venue.fields || []) {
                if (field.is_active) {
                    fields.push({
                        label: `${venue.name} - ${field.name} (${field.field_type})`,
                        field,
                    });
                }
            }
        }
        return fields;
    });

    function resolveFieldType(label: string): string | null {
        if (!label || label === 'none') return null;
        return allFields.value.find((f) => f.label === label)?.field.field_type ?? null;
    }

    function resolveFieldId(label: string): number | null {
        if (!label || label === 'none') return null;
        return allFields.value.find((f) => f.label === label)?.field.id ?? null;
    }

    function resolveFieldLabel(fieldId: number | null): string {
        if (!fieldId) return 'none';
        return allFields.value.find((f) => f.field.id === fieldId)?.label ?? 'none';
    }

    // --- Initial values ---
    const isEdit = !!match;
    const initialDate = isEdit ? match.scheduled_at.slice(0, 10) : getDefaultDate();
    const initialTimeFull = isEdit ? match.scheduled_at.slice(11, 16) : '10:00';
    const initialTime = timeOptions.includes(initialTimeFull) ? initialTimeFull : timeOptions[0];
    const initialFieldLabel = isEdit ? resolveFieldLabel(match.field_id) : 'none';
    const initialMaxPlayers = isEdit ? match.max_players : 10;

    // --- Reactive state ---
    const autoTitle = ref(autoTitleOnInit);
    const selectedFieldLabel = ref(initialFieldLabel);
    const selectedDate = ref(initialDate);
    const selectedTime = ref(initialTime);

    // --- Generated title ---
    const generatedTitle = computed(() => {
        const parts: string[] = ['Partido'];
        const fieldType = resolveFieldType(selectedFieldLabel.value);
        if (fieldType) parts.push(fieldType);
        if (selectedDate.value) parts.push(formatShortDate(selectedDate.value));
        return parts.join(' ');
    });

    // --- Form ---
    const form = useForm<MatchFormData>({
        title: isEdit ? match.title : 'Partido',
        scheduled_at: '',
        field_id: isEdit ? match.field_id : null,
        duration_minutes: isEdit ? match.duration_minutes : 60,
        arrival_minutes: isEdit ? match.arrival_minutes : 15,
        max_players: initialMaxPlayers,
        max_substitutes: isEdit ? match.max_substitutes : 4,
        registration_opens_hours: isEdit
            ? (match.registration_opens_hours ?? 24)
            : calcRegistrationHours(initialMaxPlayers),
        notes: isEdit ? (match.notes ?? '') : '',
        team_a_name: isEdit ? (match.team_a_name ?? 'Equipo A') : 'Equipo A',
        team_b_name: isEdit ? (match.team_b_name ?? 'Equipo B') : 'Equipo B',
        team_a_color: isEdit ? (match.team_a_color ?? '#1a1a1a') : '#1a1a1a',
        team_b_color: isEdit ? (match.team_b_color ?? '#facc15') : '#facc15',
    });

    // Initialize auto-title on create
    if (autoTitleOnInit) {
        form.title = generatedTitle.value;
    }

    // --- Watchers ---
    watch(generatedTitle, (val) => {
        if (autoTitle.value) {
            form.title = val;
        }
    });

    watch(selectedFieldLabel, (label) => {
        form.field_id = resolveFieldId(label);

        const fieldType = resolveFieldType(label);
        if (fieldType && fieldTypeToPlayers[fieldType]) {
            form.max_players = fieldTypeToPlayers[fieldType];
            form.registration_opens_hours = calcRegistrationHours(fieldTypeToPlayers[fieldType]);
        }
    });

    // --- Auto team names ---
    const autoTeamA = ref(!isEdit);
    const autoTeamB = ref(!isEdit);

    function generatedTeamName(hex: string): string {
        return `Eq. ${colorLabel(hex)}`;
    }

    if (!isEdit) {
        form.team_a_name = generatedTeamName(form.team_a_color);
        form.team_b_name = generatedTeamName(form.team_b_color);
    }

    watch(() => form.team_a_color, (hex) => {
        if (autoTeamA.value) {
            form.team_a_name = generatedTeamName(hex);
        }
    });

    watch(() => form.team_b_color, (hex) => {
        if (autoTeamB.value) {
            form.team_b_name = generatedTeamName(hex);
        }
    });

    // --- Title toggles ---
    function enableManualTitle() {
        autoTitle.value = false;
    }

    function enableAutoTitle() {
        autoTitle.value = true;
        form.title = generatedTitle.value;
    }

    function enableManualTeamName(team: 'a' | 'b') {
        if (team === 'a') autoTeamA.value = false;
        else autoTeamB.value = false;
    }

    function enableAutoTeamName(team: 'a' | 'b') {
        if (team === 'a') {
            autoTeamA.value = true;
            form.team_a_name = generatedTeamName(form.team_a_color);
        } else {
            autoTeamB.value = true;
            form.team_b_name = generatedTeamName(form.team_b_color);
        }
    }

    // --- Build scheduled_at before submit ---
    function resolveBeforeSubmit() {
        form.scheduled_at = selectedDate.value && selectedTime.value
            ? `${selectedDate.value}T${selectedTime.value}`
            : '';
        form.field_id = resolveFieldId(selectedFieldLabel.value);
    }

    return {
        form,
        allFields,
        autoTitle,
        autoTeamA,
        autoTeamB,
        selectedFieldLabel,
        selectedDate,
        selectedTime,
        generatedTitle,
        enableManualTitle,
        enableAutoTitle,
        enableManualTeamName,
        enableAutoTeamName,
        resolveBeforeSubmit,
    };
}
