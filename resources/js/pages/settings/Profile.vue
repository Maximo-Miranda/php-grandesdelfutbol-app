<script setup lang="ts">
import { Form, Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Camera, Shirt, UserCircle } from 'lucide-vue-next';
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
    date_of_birth: props.profile.date_of_birth?.substring(0, 10) ?? '',
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

const nationalityOptions = [
    'Colombiano', 'Argentino', 'Mexicano', 'Brasileno', 'Peruano',
    'Chileno', 'Ecuatoriano', 'Venezolano', 'Uruguayo', 'Paraguayo',
    'Boliviano', 'Costarricense', 'Panameno', 'Hondureno', 'Salvadoreno',
    'Guatemalteco', 'Nicaraguense', 'Cubano', 'Dominicano', 'Puertorriqueno',
    'Espanol', 'Estadounidense', 'Otro',
];

const countryCodes = [
    { code: '+57', flag: '🇨🇴', country: 'Colombia' },
    { code: '+54', flag: '🇦🇷', country: 'Argentina' },
    { code: '+52', flag: '🇲🇽', country: 'Mexico' },
    { code: '+55', flag: '🇧🇷', country: 'Brasil' },
    { code: '+51', flag: '🇵🇪', country: 'Peru' },
    { code: '+56', flag: '🇨🇱', country: 'Chile' },
    { code: '+593', flag: '🇪🇨', country: 'Ecuador' },
    { code: '+58', flag: '🇻🇪', country: 'Venezuela' },
    { code: '+598', flag: '🇺🇾', country: 'Uruguay' },
    { code: '+595', flag: '🇵🇾', country: 'Paraguay' },
    { code: '+591', flag: '🇧🇴', country: 'Bolivia' },
    { code: '+506', flag: '🇨🇷', country: 'Costa Rica' },
    { code: '+507', flag: '🇵🇦', country: 'Panama' },
    { code: '+504', flag: '🇭🇳', country: 'Honduras' },
    { code: '+503', flag: '🇸🇻', country: 'El Salvador' },
    { code: '+502', flag: '🇬🇹', country: 'Guatemala' },
    { code: '+505', flag: '🇳🇮', country: 'Nicaragua' },
    { code: '+53', flag: '🇨🇺', country: 'Cuba' },
    { code: '+1', flag: '🇩🇴', country: 'Rep. Dominicana' },
    { code: '+34', flag: '🇪🇸', country: 'Espana' },
    { code: '+1', flag: '🇺🇸', country: 'Estados Unidos' },
];

function parsePhone(phone: string): { code: string; number: string } {
    if (!phone) return { code: '+57', number: '' };
    const match = countryCodes.find(c => phone.startsWith(c.code));
    if (match) return { code: match.code, number: phone.slice(match.code.length).trim() };
    return { code: '+57', number: phone };
}

const parsed = parsePhone(props.profile.phone ?? '');
const phoneCode = ref(parsed.code);
const phoneNumber = ref(parsed.number);

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
        phone: phoneNumber.value ? `${phoneCode.value}${phoneNumber.value}` : null,
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

                    <!-- Photo upload -->
                    <div class="rounded-xl border border-border bg-card p-5">
                        <h3 class="mb-4 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            <Camera class="size-4" />
                            Foto de perfil
                        </h3>
                        <div class="flex items-center gap-4">
                            <button
                                type="button"
                                class="group relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-primary/30 bg-primary/10 text-2xl font-bold text-primary transition hover:border-primary/60"
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
                            <div class="min-w-0 flex-1">
                                <Button type="button" variant="outline" size="sm" @click="selectPhoto">
                                    Cambiar foto
                                </Button>
                                <p class="mt-1.5 text-xs text-muted-foreground">JPG, PNG o WebP. Min 200x200 px. Max 10 MB.</p>
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
                    </div>

                    <div class="rounded-xl border border-border bg-card p-5">
                        <h3 class="mb-4 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            <UserCircle class="size-4" />
                            Información personal
                        </h3>
                        <div class="space-y-4">
                            <div class="grid gap-2">
                                <Label for="nickname">Apodo</Label>
                                <Input id="nickname" v-model="playerForm.nickname" placeholder="Como te conocen en la cancha" />
                                <InputError :message="playerForm.errors.nickname" />
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="gender">Género</Label>
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
                                <Select v-model="playerForm.nationality">
                                    <SelectTrigger id="nationality">
                                        <SelectValue placeholder="Seleccionar" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="nat in nationalityOptions" :key="nat" :value="nat">
                                            {{ nat }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="phone">WhatsApp</Label>
                                <div class="flex gap-2">
                                    <Select v-model="phoneCode">
                                        <SelectTrigger class="w-[130px] shrink-0">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="c in countryCodes" :key="c.flag + c.code" :value="c.code">
                                                {{ c.flag }} {{ c.code }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Input
                                        id="phone"
                                        v-model="phoneNumber"
                                        type="tel"
                                        inputmode="tel"
                                        placeholder="300 123 4567"
                                    />
                                </div>
                                <InputError :message="playerForm.errors.phone" />
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
                                        <SelectValue placeholder="Seleccionar posición" />
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

            <!-- Account info -->
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Información de cuenta"
                    description="Actualiza tu nombre y correo electrónico"
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
                        <Label for="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Correo electrónico"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Tu correo electrónico no está verificado.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Haz clic aquí para reenviar el correo de verificación.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            Se ha enviado un nuevo enlace de verificación a tu correo.
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

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
