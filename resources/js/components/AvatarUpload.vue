<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    label?: string;
    currentUrl?: string | null;
    modelValue?: File | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [file: File | null];
}>();

const fileInput = ref<HTMLInputElement>();
const previewUrl = ref<string | null>(null);

const displayUrl = computed(() => previewUrl.value ?? props.currentUrl ?? null);

function handleFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (file) {
        previewUrl.value = URL.createObjectURL(file);
    }

    emit('update:modelValue', file);
}

function triggerFileInput() {
    fileInput.value?.click();
}
</script>

<template>
    <div class="space-y-2">
        <Label v-if="label">{{ label }}</Label>
        <div class="flex items-center gap-4">
            <div
                class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-muted"
                @click="triggerFileInput"
            >
                <img v-if="displayUrl" :src="displayUrl" alt="Avatar" class="h-full w-full object-cover" />
                <span v-else class="text-xl text-muted-foreground">?</span>
            </div>
            <Button type="button" variant="outline" size="sm" @click="triggerFileInput">
                {{ displayUrl ? 'Change' : 'Upload' }}
            </Button>
        </div>
        <input
            ref="fileInput"
            type="file"
            class="hidden"
            accept="image/*"
            @change="handleFileChange"
        />
    </div>
</template>
