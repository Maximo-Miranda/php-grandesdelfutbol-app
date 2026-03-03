<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { JERSEY_COLORS } from '@/constants/jerseyColors';

const model = defineModel<string>({ required: true });
</script>

<template>
    <div class="grid grid-cols-5 gap-2">
        <button
            v-for="color in JERSEY_COLORS"
            :key="color.hex"
            type="button"
            class="flex size-10 items-center justify-center rounded-full border-2 transition-transform active:scale-90"
            :class="model === color.hex ? 'border-ring scale-110 ring-2 ring-ring/30' : 'border-transparent hover:scale-105'"
            :style="{ backgroundColor: color.hex }"
            :title="color.label"
            @click="model = color.hex"
        >
            <Check
                v-if="model === color.hex"
                class="size-5"
                :class="color.light ? 'text-gray-800' : 'text-white'"
            />
        </button>
    </div>
</template>
