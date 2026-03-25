<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

defineProps<{
    open: boolean;
    plan: string;
    phone: string;
    message: string;
    errors: Record<string, string[]>;
    submitting: boolean;
    success: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'update:plan': [value: string];
    'update:phone': [value: string];
    'update:message': [value: string];
    submit: [];
    close: [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Solicitar grabacion</DialogTitle>
                <DialogDescription>Selecciona el plan y te contactamos para coordinar.</DialogDescription>
            </DialogHeader>

            <div v-if="success" class="py-6 text-center">
                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-emerald-500/10">
                    <Check class="size-6 text-emerald-500" />
                </div>
                <p class="font-semibold">Solicitud enviada</p>
                <p class="mt-1 text-sm text-muted-foreground">Te contactaremos pronto.</p>
                <Button class="mt-4" variant="outline" @click="emit('close')">Cerrar</Button>
            </div>

            <form v-else class="space-y-4" @submit.prevent="emit('submit')">
                <div class="grid gap-1.5">
                    <Label for="vsr-plan">Tipo de servicio</Label>
                    <select
                        id="vsr-plan"
                        :value="plan"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        @change="emit('update:plan', ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="recocha">Recocha — $60.000/partido</option>
                        <option value="profesional">Profesional — $130.000/partido</option>
                        <option value="mensual">Mensual — desde $200.000/mes</option>
                    </select>
                    <InputError v-if="errors.selected_plan" :message="errors.selected_plan[0]" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="vsr-phone">WhatsApp</Label>
                    <PhoneInput
                        id="vsr-phone"
                        :model-value="phone"
                        @update:model-value="emit('update:phone', $event)"
                    />
                    <InputError v-if="errors.phone" :message="errors.phone[0]" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="vsr-message">Mensaje (opcional)</Label>
                    <Textarea id="vsr-message" :model-value="message" rows="2" @update:model-value="emit('update:message', $event)" />
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="submitting" class="w-full">
                        {{ submitting ? 'Enviando...' : 'Enviar solicitud' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
