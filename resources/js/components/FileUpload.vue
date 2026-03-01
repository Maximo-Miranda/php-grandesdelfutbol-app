<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

defineProps<{
    label?: string;
    accept?: string;
    modelValue?: File | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [file: File | null];
}>();

const fileInput = ref<HTMLInputElement>();
const fileName = ref('');

function handleFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    fileName.value = file?.name ?? '';
    emit('update:modelValue', file);
}

function triggerFileInput() {
    fileInput.value?.click();
}
</script>

<template>
    <div class="space-y-2">
        <Label v-if="label">{{ label }}</Label>
        <div class="flex items-center gap-3">
            <Button type="button" variant="outline" size="sm" @click="triggerFileInput">
                Choose File
            </Button>
            <span class="text-sm text-muted-foreground">{{ fileName || 'No file chosen' }}</span>
        </div>
        <input
            ref="fileInput"
            type="file"
            class="hidden"
            :accept="accept ?? 'image/*'"
            @change="handleFileChange"
        />
    </div>
</template>
