<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const countryCodes = [
    { code: '+57', flag: '🇨🇴', country: 'Colombia' },
    { code: '+54', flag: '🇦🇷', country: 'Argentina' },
    { code: '+52', flag: '🇲🇽', country: 'Mexico' },
    { code: '+55', flag: '🇧🇷', country: 'Brasil' },
    { code: '+51', flag: '🇵🇪', country: 'Peru' },
    { code: '+56', flag: '🇨🇱', country: 'Chile' },
    { code: '+593', flag: '🇪🇨', country: 'Ecuador' },
    { code: '+58', flag: '🇻🇪', country: 'Venezuela' },
    { code: '+598', flag: '🇺🇾', country: 'Uruguay' },
    { code: '+595', flag: '🇵🇾', country: 'Paraguay' },
    { code: '+591', flag: '🇧🇴', country: 'Bolivia' },
    { code: '+506', flag: '🇨🇷', country: 'Costa Rica' },
    { code: '+507', flag: '🇵🇦', country: 'Panama' },
    { code: '+504', flag: '🇭🇳', country: 'Honduras' },
    { code: '+503', flag: '🇸🇻', country: 'El Salvador' },
    { code: '+502', flag: '🇬🇹', country: 'Guatemala' },
    { code: '+505', flag: '🇳🇮', country: 'Nicaragua' },
    { code: '+53', flag: '🇨🇺', country: 'Cuba' },
    { code: '+1', flag: '🇩🇴', country: 'Rep. Dominicana' },
    { code: '+34', flag: '🇪🇸', country: 'Espana' },
    { code: '+1', flag: '🇺🇸', country: 'Estados Unidos' },
];

const props = defineProps<{
    modelValue: string;
    id?: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

function parsePhone(phone: string): { code: string; number: string } {
    if (!phone) return { code: '+57', number: '' };
    const match = countryCodes.find(c => phone.startsWith(c.code));
    if (match) return { code: match.code, number: phone.slice(match.code.length).trim() };
    return { code: '+57', number: phone };
}

const parsed = computed(() => parsePhone(props.modelValue));

function onCodeChange(code: string): void {
    emit('update:modelValue', parsed.value.number ? `${code}${parsed.value.number}` : '');
}

function onNumberChange(number: string): void {
    emit('update:modelValue', number ? `${parsed.value.code}${number}` : '');
}
</script>

<template>
    <div class="flex gap-2">
        <Select :model-value="parsed.code" @update:model-value="onCodeChange">
            <SelectTrigger class="w-[120px] shrink-0">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="c in countryCodes" :key="c.flag + c.code" :value="c.code">
                    {{ c.flag }} {{ c.code }}
                </SelectItem>
            </SelectContent>
        </Select>
        <Input
            :id="id"
            :model-value="parsed.number"
            type="tel"
            inputmode="tel"
            :placeholder="placeholder ?? '300 123 4567'"
            @update:model-value="onNumberChange"
        />
    </div>
</template>
