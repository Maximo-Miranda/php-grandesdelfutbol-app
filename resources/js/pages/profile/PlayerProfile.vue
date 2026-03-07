<script setup lang="ts">
import { Head, usePage, useForm } from '@inertiajs/vue3';
import { Shirt, Target, UserCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem, PlayerProfile } from '@/types';

type PositionOption = { value: string; label: string };
type Props = { profile: PlayerProfile; positions: PositionOption[] };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Perfil de jugador', href: '/player-profile' },
];

const page = usePage();
const user = computed(() => page.props.auth.user);

const initials = computed(() =>
    user.value.name
        .split(' ')
        .map((w: string) => w[0])
        .join('')
        .substring(0, 2)
        .toUpperCase(),
);

const form = useForm({
    nickname: props.profile.nickname ?? '',
    gender: props.profile.gender ?? 'none',
    date_of_birth: props.profile.date_of_birth ?? '',
    nationality: props.profile.nationality ?? 'Colombiano',
    bio: props.profile.bio ?? '',
    preferred_position: props.profile.preferred_position ?? 'none',
});

const genderOptions = [
    { value: 'male', label: 'Masculino' },
    { value: 'female', label: 'Femenino' },
    { value: 'other', label: 'Otro' },
];

function submit() {
    form.transform((data) => ({
        ...data,
        gender: data.gender === 'none' ? null : data.gender,
        preferred_position: data.preferred_position === 'none' ? null : data.preferred_position,
    })).patch('/player-profile');
}
</script>

<template>
    <Head title="Perfil de jugador" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <div class="space-y-6">
                <Heading variant="small" title="Perfil de jugador" description="Tu perfil personal de futbol" />

                <!-- Hero card -->
                <div class="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-card via-card to-primary/10">
                    <div class="relative z-10 flex items-center gap-5 p-6">
                        <div class="flex size-16 shrink-0 items-center justify-center rounded-full border-2 border-primary/30 bg-primary/10 text-xl font-bold text-primary">
                            {{ initials }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-xl font-extrabold uppercase tracking-tight">
                                {{ form.nickname || user.name }}
                            </h2>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span
                                    v-if="form.preferred_position && form.preferred_position !== 'none'"
                                    class="inline-flex items-center gap-1 rounded-md bg-primary/15 px-2 py-0.5 text-xs font-bold text-primary"
                                >
                                    <Target class="size-3" />
                                    {{ form.preferred_position }}
                                </span>
                                <span
                                    v-if="form.nationality"
                                    class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                >
                                    {{ form.nationality }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="h-0.5 bg-gradient-to-r from-transparent via-primary/40 to-transparent" />
                </div>

                <!-- Form -->
                <form class="space-y-6" @submit.prevent="submit">
                    <!-- Info personal -->
                    <div class="rounded-xl border border-border bg-card p-5">
                        <h3 class="mb-4 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            <UserCircle class="size-4" />
                            Informacion personal
                        </h3>
                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="nickname">Apodo</Label>
                                <Input id="nickname" v-model="form.nickname" placeholder="Como te conocen en la cancha" />
                                <InputError :message="form.errors.nickname" />
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="gender">Genero</Label>
                                    <Select v-model="form.gender">
                                        <SelectTrigger id="gender">
                                            <SelectValue placeholder="Seleccionar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Sin especificar</SelectItem>
                                            <SelectItem v-for="opt in genderOptions" :key="opt.value" :value="opt.value">
                                                {{ opt.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="date_of_birth">Fecha de nacimiento</Label>
                                    <Input id="date_of_birth" v-model="form.date_of_birth" type="date" />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label for="nationality">Nacionalidad</Label>
                                <Input id="nationality" v-model="form.nationality" placeholder="Ej: Dominicano" />
                            </div>
                        </div>
                    </div>

                    <!-- Futbol -->
                    <div class="rounded-xl border border-border bg-card p-5">
                        <h3 class="mb-4 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            <Shirt class="size-4" />
                            Futbol
                        </h3>
                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="preferred_position">Posicion preferida</Label>
                                <Select v-model="form.preferred_position">
                                    <SelectTrigger id="preferred_position">
                                        <SelectValue placeholder="Seleccionar posicion" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin preferencia</SelectItem>
                                        <SelectItem v-for="pos in positions" :key="pos.value" :value="pos.value">
                                            {{ pos.label }} ({{ pos.value }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="bio">Bio</Label>
                                <Textarea
                                    id="bio"
                                    v-model="form.bio"
                                    placeholder="Cuenta algo sobre ti como jugador..."
                                    rows="3"
                                    class="resize-none"
                                />
                                <p class="text-xs text-muted-foreground">{{ (form.bio?.length ?? 0) }}/500 caracteres</p>
                                <InputError :message="form.errors.bio" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="form.processing">Guardar perfil</Button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-emerald-400">
                                Guardado.
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
