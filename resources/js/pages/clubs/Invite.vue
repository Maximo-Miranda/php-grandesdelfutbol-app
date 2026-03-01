<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Mail, Send } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type Invitation = {
    id: number;
    email: string;
    status: string;
    inviter?: { name: string };
    created_at: string;
};

type Props = {
    club: Club;
    invitations: Invitation[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.id}` },
    { title: 'Invitar', href: `/clubs/${props.club.id}/invite` },
];

const form = useForm({ email: '' });

function submit() {
    form.post(`/clubs/${props.club.id}/invite`, {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="`Invitar Jugadores`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl px-4 py-6">
            <h1 class="text-2xl font-bold">Invitar Jugadores</h1>
            <p class="mt-1 text-sm text-muted-foreground">Suma amigos a tu club.</p>

            <div class="mt-6 rounded-lg border border-border p-4">
                <p class="mb-4 font-medium">Invitar por email</p>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="email">Email del jugador</Label>
                        <div class="relative">
                            <Mail class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input id="email" v-model="form.email" type="email" required placeholder="amigo@email.com" class="pl-10" />
                        </div>
                        <InputError :message="form.errors.email" />
                    </div>
                    <Button type="submit" :disabled="form.processing" class="w-full">
                        <Send class="mr-2 size-4" />
                        Enviar invitacion
                    </Button>
                </form>
            </div>

            <div v-if="invitations.length > 0" class="mt-6">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Invitaciones enviadas</h3>
                <div class="space-y-2">
                    <div v-for="inv in invitations" :key="inv.id" class="flex items-center justify-between rounded-lg border border-border p-3">
                        <span class="text-sm">{{ inv.email }}</span>
                        <Badge :variant="inv.status === 'pending' ? 'outline' : 'default'" class="text-xs">{{ inv.status }}</Badge>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
