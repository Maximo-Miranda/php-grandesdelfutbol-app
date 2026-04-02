import { useForm } from '@inertiajs/vue3';
import type { MaybeRefOrGetter } from 'vue';
import { computed, ref, toValue, watch } from 'vue';
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
    is_recurring: boolean;
    recurrence_days: number;
    auto_cancel: boolean;
    min_players_required: number;
};

const recurrencePresets = ['8', '15', '30'];

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

// Time options: 30-min intervals from 00:00 to 23:30
export const timeOptions = Array.from({ length: 48 }, (_, i) => {
    const h = Math.floor(i / 2);
    const m = (i % 2) * 30;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
});

function capitalize(str: string): string {
    return str.charAt(0).toUpperCase() + str.slice(1).replace('.', '');
}

export function formatShortDate(dateStr: string): string {
    const [year, month, day] = dateStr.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    const weekday = capitalize(date.toLocaleDateString('es', { weekday: 'short' }));
    const monthName = capitalize(date.toLocaleDateString('es', { month: 'short' }));
    return `${weekday} ${date.getDate()} ${monthName}`;
}

export function getDefaultDate(): string {
    const target = new Date();
    target.setDate(target.getDate() + 3);

    return `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}-${String(target.getDate()).padStart(2, '0')}`;
}

type UseMatchFormOptions = {
    venues: MaybeRefOrGetter<Venue[]>;
    match?: FootballMatch;
    autoTitleOnInit?: boolean;
};

export function useMatchForm(options: UseMatchFormOptions) {
    const { venues: venuesSource, match, autoTitleOnInit = true } = options;

    // --- Field list ---
    const allFields = computed(() => {
        const venues = toValue(venuesSource);
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

    // Parse UTC ISO string to local date/time parts (Carbon serializes to UTC)
    function toLocalParts(isoString: string): { date: string; time: string } {
        const d = new Date(isoString);
        const date = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        const time = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
        return { date, time };
    }

    const editParts = isEdit ? toLocalParts(match.scheduled_at) : null;
    const initialDate = editParts ? editParts.date : getDefaultDate();
    const initialTimeFull = editParts ? editParts.time : '10:00';
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
        notes: match?.notes ?? '',
        team_a_name: match?.team_a_name ?? 'Equipo A',
        team_b_name: match?.team_b_name ?? 'Equipo B',
        team_a_color: match?.team_a_color ?? '#1a1a1a',
        team_b_color: match?.team_b_color ?? '#facc15',
        is_recurring: match?.is_recurring ?? true,
        recurrence_days: match?.recurrence_days ?? 8,
        auto_cancel: match?.auto_cancel ?? true,
        min_players_required: match?.min_players_required ?? initialMaxPlayers,
    });

    // --- Recurrence ---
    const selectedRecurrenceOption = ref(
        isEdit && !recurrencePresets.includes(String(match.recurrence_days)) ? 'custom' : String(match?.recurrence_days ?? 8),
    );

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
        const playerCount = fieldType ? fieldTypeToPlayers[fieldType] : undefined;
        if (playerCount) {
            form.max_players = playerCount;
            form.registration_opens_hours = calcRegistrationHours(playerCount);
            form.min_players_required = playerCount;
        }
    });

    watch(() => form.max_players, (val) => {
        if (form.min_players_required > val) {
            form.min_players_required = val;
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

    watch(selectedRecurrenceOption, (option) => {
        if (option !== 'custom') {
            form.recurrence_days = Number(option);
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

    // --- Past-match detection ---
    const isPastMatch = computed(() => {
        if (!selectedDate.value || !selectedTime.value) return false;
        const selected = new Date(`${selectedDate.value}T${selectedTime.value}`);
        return selected < new Date();
    });

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
        isPastMatch,
        selectedRecurrenceOption,
        resolveBeforeSubmit,
    };
}
