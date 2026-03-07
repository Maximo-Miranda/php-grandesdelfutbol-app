<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { AlertCircle, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as loginStore } from '@/routes/login';
import { store as registerStore } from '@/routes/register';

type Props = {
    club: { name: string; description: string | null };
    token: string;
    requiresApproval: boolean;
};

const props = defineProps<Props>();

const mode = ref<'register' | 'login'>('register');

const registerForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    join_token: props.token,
});

const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

function submitRegister() {
    registerForm.post(registerStore().url);
}

function submitLogin() {
    loginForm.post(loginStore().url);
}
</script>

<template>
    <Head :title="`Unirse a ${club.name}`" />

    <div class="flex min-h-svh flex-col items-center justify-center bg-background p-4">
        <div class="w-full max-w-sm">
            <!-- Header con branding -->
            <div class="mb-8 flex flex-col items-center gap-4 text-center">
                <div class="gradient-primary-bg flex size-16 items-center justify-center rounded-2xl shadow-lg">
                    <AppLogoIcon class="size-9 text-white" />
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-primary">Enlace de invitacion</p>
                    <h1 class="text-2xl font-bold">{{ club.name }}</h1>
                    <p v-if="club.description" class="mt-1 text-sm text-muted-foreground">{{ club.description }}</p>
                </div>
                <Badge v-if="requiresApproval" variant="outline" class="gap-1.5">
                    <AlertCircle class="size-3" />
                    Requiere aprobacion del admin
                </Badge>
            </div>

            <!-- Register form -->
            <form v-if="mode === 'register'" class="space-y-4" @submit.prevent="submitRegister">
                <div class="grid gap-1.5">
                    <Label for="name">Nombre</Label>
                    <Input
                        id="name"
                        v-model="registerForm.name"
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Tu nombre"
                    />
                    <InputError :message="registerForm.errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="email">Correo electronico</Label>
                    <Input
                        id="email"
                        v-model="registerForm.email"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="tu@email.com"
                    />
                    <InputError :message="registerForm.errors.email" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="password">Contrasena</Label>
                    <Input
                        id="password"
                        v-model="registerForm.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="Minimo 8 caracteres"
                    />
                    <InputError :message="registerForm.errors.password" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="password_confirmation">Confirmar contrasena</Label>
                    <Input
                        id="password_confirmation"
                        v-model="registerForm.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="Repite tu contrasena"
                    />
                </div>

                <Button type="submit" class="gradient-primary-bg w-full border-0 text-white hover:opacity-90" size="lg" :disabled="registerForm.processing">
                    <Spinner v-if="registerForm.processing" />
                    <UserPlus v-else class="mr-2 size-4" />
                    Crear cuenta y unirme
                </Button>

                <p class="text-center text-sm text-muted-foreground">
                    Ya tienes cuenta?
                    <button type="button" class="font-medium text-primary underline underline-offset-4" @click="mode = 'login'">
                        Iniciar sesion
                    </button>
                </p>
            </form>

            <!-- Login form -->
            <form v-else class="space-y-4" @submit.prevent="submitLogin">
                <div class="grid gap-1.5">
                    <Label for="login-email">Correo electronico</Label>
                    <Input
                        id="login-email"
                        v-model="loginForm.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="tu@email.com"
                    />
                    <InputError :message="loginForm.errors.email" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="login-password">Contrasena</Label>
                    <Input
                        id="login-password"
                        v-model="loginForm.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        placeholder="Tu contrasena"
                    />
                    <InputError :message="loginForm.errors.password" />
                </div>

                <Button type="submit" class="gradient-primary-bg w-full border-0 text-white hover:opacity-90" size="lg" :disabled="loginForm.processing">
                    <Spinner v-if="loginForm.processing" />
                    Iniciar sesion y unirme
                </Button>

                <p class="text-center text-sm text-muted-foreground">
                    No tienes cuenta?
                    <button type="button" class="font-medium text-primary underline underline-offset-4" @click="mode = 'register'">
                        Crear cuenta
                    </button>
                </p>
            </form>
        </div>
    </div>
</template>
