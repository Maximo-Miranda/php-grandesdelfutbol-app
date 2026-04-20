<script setup lang="ts">
const model = defineModel<boolean>({ default: false });
</script>

<template>
    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-border p-3 transition-colors hover:bg-accent/30">
        <input
            v-model="model"
            type="checkbox"
            class="mt-0.5 size-4 rounded border-border"
        />
        <div class="flex-1">
            <p class="text-sm font-medium">Equipo de torneo</p>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Los jugadores pueden estar en varias plantillas de torneo dentro del club. Los equipos regulares (sin marcar) tienen plantilla exclusiva: un jugador solo puede estar en uno.
            </p>
        </div>
    </label>
</template>
