<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="space-y-3">
        <div v-for="i in 4" :key="i" class="flex gap-3 rounded-lg border border-border p-3">
            <Skeleton class="h-24 w-24 shrink-0 rounded-md" />
            <div class="flex flex-1 flex-col justify-between">
                <div>
                    <Skeleton class="h-4 w-full" />
                    <Skeleton class="mt-2 h-3 w-3/4" />
                </div>
                <div class="mt-2 flex gap-2">
                    <Skeleton class="h-3 w-16" />
                    <Skeleton class="h-3 w-12" />
                </div>
            </div>
        </div>
    </div>
</template>
