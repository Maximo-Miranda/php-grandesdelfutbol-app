<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

defineProps<{
    class?: HTMLAttributes['class'];
}>();

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <input
            v-bind="$attrs"
            :type="visible ? 'text' : 'password'"
            data-slot="input"
            :class="
                cn(
                    'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 pr-9 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                    $props.class,
                )
            "
        />
        <button
            type="button"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex items-center px-2 text-muted-foreground hover:text-foreground"
            @click="visible = !visible"
        >
            <EyeOff v-if="visible" class="size-4" />
            <Eye v-else class="size-4" />
        </button>
    </div>
</template>
