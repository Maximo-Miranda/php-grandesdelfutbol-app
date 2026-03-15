<script setup lang="ts">
import { Form, Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Camera, Shirt, Target, UserCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PlayerProfileController from '@/actions/App/Http/Controllers/PlayerProfileController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem, PlayerProfile } from '@/types';

type PositionOption = { value: string; label: string };
type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    profile: PlayerProfile & { photo_url?: string | null };
    positions: PositionOption[];
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Mi perfil',
        href: edit(),
    },
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

// Player profile form
const playerForm = useForm({
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

const MAX_FILE_SIZE = 10 * 1024 * 1024;
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const photoPreview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

function selectPhoto() {
    fileInput.value?.click();
}

function onPhotoSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    playerForm.clearErrors('photo');

    if (!ALLOWED_TYPES.includes(file.type)) {
        playerForm.setError('photo', 'La foto debe ser JPG, PNG o WebP.');
        return;
    }

    if (file.size > MAX_FILE_SIZE) {
        playerForm.setError('photo', 'La foto no debe superar 10 MB.');
        return;
    }

    playerForm.photo = file;

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

function submitPlayerProfile() {
    playerForm.transform((data) => ({
        ...data,
        gender: noneToNull(data.gender),
        preferred_position: noneToNull(data.preferred_position),
    })).patch(PlayerProfileController.update.url(), {
        forceFormData: true,
        onSuccess: () => {
            photoPreview.value = null;
            playerForm.photo = null;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Mi perfil" />

        <h1 class="sr-only">Mi perfil</h1>

        <SettingsLayout>
            <!-- Hero card with photo -->
            <div class="space-y-6">
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
                                {{ playerForm.nickname || user.name }}
                            </h2>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span
                                    v-if="playerForm.preferred_position && playerForm.preferred_position !== 'none'"
                                    class="inline-flex items-center gap-1 rounded-md bg-primary/15 px-2 py-0.5 text-xs font-bold text-primary"
                                >
                                    <Target class="size-3" />
                                    {{ playerForm.preferred_position }}
                                </span>
                                <span
                                    v-if="playerForm.nationality"
                                    class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                >
                                    {{ playerForm.nationality }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">JPG, PNG o WebP. Min 200x200 px. Max 10 MB.</p>
                            <InputError class="mt-1" :message="playerForm.errors.photo" />
                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <div v-if="playerForm.progress" class="mt-2">
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full bg-primary transition-all"
                                            :style="{ width: `${playerForm.progress.percentage}%` }"
                                        />
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">Subiendo... {{ playerForm.progress.percentage }}%</p>
                                </div>
                            </Transition>
                        </div>
                    </div>
                    <div class="h-0.5 bg-gradient-to-r from-transparent via-primary/40 to-transparent" />
                </div>
            </div>

            <!-- Account info -->
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Informacion de cuenta"
                    description="Actualiza tu nombre y correo electronico"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Nombre</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Nombre completo"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Correo electronico</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Correo electronico"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Tu correo electronico no esta verificado.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Haz clic aqui para reenviar el correo de verificacion.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            Se ha enviado un nuevo enlace de verificacion a tu correo.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Guardar</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Guardado.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <Separator />

            <!-- Player profile -->
            <div class="space-y-6">
                <Heading variant="small" title="Perfil de jugador" description="Tu perfil personal de futbol" />

                <form class="space-y-6" @submit.prevent="submitPlayerProfile">
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onPhotoSelected"
                    />

                    <div class="rounded-xl border border-border bg-card p-5">
                        <h3 class="mb-4 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            <UserCircle class="size-4" />
                            Informacion personal
                        </h3>
                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="nickname">Apodo</Label>
                                <Input id="nickname" v-model="playerForm.nickname" placeholder="Como te conocen en la cancha" />
                                <InputError :message="playerForm.errors.nickname" />
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="gender">Genero</Label>
                                    <Select v-model="playerForm.gender">
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
                                    <Input id="date_of_birth" v-model="playerForm.date_of_birth" type="date" />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label for="nationality">Nacionalidad</Label>
                                <Input id="nationality" v-model="playerForm.nationality" placeholder="Ej: Dominicano" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-border bg-card p-5">
                        <h3 class="mb-4 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            <Shirt class="size-4" />
                            Futbol
                        </h3>
                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="preferred_position">Posicion preferida</Label>
                                <Select v-model="playerForm.preferred_position">
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
                                    v-model="playerForm.bio"
                                    placeholder="Cuenta algo sobre ti como jugador..."
                                    rows="3"
                                    class="resize-none"
                                />
                                <p class="text-xs text-muted-foreground">{{ (playerForm.bio?.length ?? 0) }}/500 caracteres</p>
                                <InputError :message="playerForm.errors.bio" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="playerForm.processing">Guardar perfil</Button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="playerForm.recentlySuccessful" class="text-sm text-emerald-400">
                                Guardado.
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>

            <Separator />

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
