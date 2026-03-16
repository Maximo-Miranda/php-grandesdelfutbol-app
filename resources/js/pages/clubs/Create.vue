<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: 'Crear', href: '/clubs/create' },
];

const form = useForm({
    name: '',
    description: '',
});

function submit() {
    form.post('/clubs');
}
</script>

<template>
    <Head title="Crear Club" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Crear Club" description="Configura un nuevo club de fútbol" />

            <form class="mt-6 space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Nombre del Club</Label>
                    <Input id="name" v-model="form.name" required placeholder="Ingresa el nombre del club" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Descripción</Label>
                    <Input id="description" v-model="form.description" placeholder="Descripción opcional" />
                    <InputError :message="form.errors.description" />
                </div>

                <Button type="submit" :disabled="form.processing">Crear Club</Button>
            </form>
        </div>
    </AppLayout>
</template>
