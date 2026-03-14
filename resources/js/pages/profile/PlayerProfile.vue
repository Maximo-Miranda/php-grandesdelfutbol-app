<script setup lang="ts">
import { Head, usePage, useForm } from '@inertiajs/vue3';
import { Camera, Shirt, Target, UserCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PlayerProfileController from '@/actions/App/Http/Controllers/PlayerProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/player-profile';
import type { BreadcrumbItem, PlayerProfile } from '@/types';

type PositionOption = { value: string; label: string };
type Props = {
    profile: PlayerProfile & { photo_url?: string | null };
    positions: PositionOption[];
};
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Perfil de jugador', href: edit() },
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
    photo: null as File | null,
});

const genderOptions = [
    { value: 'male', label: 'Masculino' },
    { value: 'female', label: 'Femenino' },
    { value: 'other', label: 'Otro' },
];

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const photoPreview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

function selectPhoto() {
    fileInput.value?.click();
}

function onPhotoSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) {
        return;
    }

    form.clearErrors('photo');

    if (!ALLOWED_TYPES.includes(file.type)) {
        form.setError('photo', 'La foto debe ser JPG, PNG o WebP.');
        return;
    }

    if (file.size > MAX_FILE_SIZE) {
        form.setError('photo', 'La foto no debe superar 10 MB.');
        return;
    }

    form.photo = file;

    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
}

const currentPhotoUrl = computed(() => photoPreview.value || props.profile.photo_url || null);

function noneToNull(value: string): string | null {
    return value === 'none' ? null : value;
}

function submit() {
    form.transform((data) => ({
        ...data,
        gender: noneToNull(data.gender),
        preferred_position: noneToNull(data.preferred_position),
    })).patch(PlayerProfileController.update.url(), {
        forceFormData: true,
        onSuccess: () => {
            photoPreview.value = null;
            form.photo = null;
        },
    });
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
                        <div class="shrink-0">
                            <button
                                type="button"
                                class="group relative flex size-20 items-center justify-center overflow-hidden rounded-full border-2 border-primary/30 bg-primary/10 text-2xl font-bold text-primary transition hover:border-primary/60"
                                @click="selectPhoto"
                            >
                                <img
                                    v-if="currentPhotoUrl"
                                    :src="currentPhotoUrl"
                                    alt="Foto de perfil"
                                    class="size-full object-cover"
                                />
                                <span v-else>{{ initials }}</span>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100">
                                    <Camera class="size-5 text-white" />
                                </div>
                            </button>
                            <p class="mt-1 text-center text-[10px] text-muted-foreground">Cambiar foto</p>
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
                            <p class="mt-1 text-xs text-muted-foreground">JPG, PNG o WebP. Min 200x200 px. Max 10 MB.</p>
                            <InputError class="mt-1" :message="form.errors.photo" />
                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <div v-if="form.progress" class="mt-2">
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full bg-primary transition-all"
                                            :style="{ width: `${form.progress.percentage}%` }"
                                        />
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">Subiendo... {{ form.progress.percentage }}%</p>
                                </div>
                            </Transition>
                        </div>
                    </div>
                    <div class="h-0.5 bg-gradient-to-r from-transparent via-primary/40 to-transparent" />
                </div>

                <!-- Form -->
                <form class="space-y-6" @submit.prevent="submit">
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onPhotoSelected"
                    />

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
