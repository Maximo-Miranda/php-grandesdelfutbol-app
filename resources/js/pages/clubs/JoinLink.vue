<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { AlertCircle, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';
import { redirect as googleRedirect } from '@/actions/App/Http/Controllers/GoogleAuthController';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { privacy, terms } from '@/routes';
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
    terms: false as boolean | string,
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

function handleGoogleRegister() {
    if (!registerForm.terms) {
        registerForm.setError('terms', 'Debes aceptar los terminos y condiciones para continuar.');
        return;
    }
    registerForm.clearErrors('terms');
    window.location.href = googleRedirect({ query: { join_token: props.token, terms_accepted: '1' } }).url;
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
                <label for="terms" class="flex items-center justify-center gap-2 text-sm">
                    <Checkbox id="terms" v-model="registerForm.terms" />
                    <span class="text-muted-foreground">
                        Acepto los
                        <a :href="terms.url()" target="_blank" class="text-primary hover:underline">Terminos</a>
                        y la
                        <a :href="privacy.url()" target="_blank" class="text-primary hover:underline">Politica de Privacidad</a>
                    </span>
                </label>
                <InputError :message="registerForm.errors.terms" />

                <template v-if="$page.props.googleAuthEnabled">
                    <a
                        href="#"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2.5 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                        @click.prevent="handleGoogleRegister"
                    >
                        <svg class="size-5" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4" />
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
                        </svg>
                        Continuar con Google
                    </a>

                    <div class="relative my-1">
                        <div class="absolute inset-0 flex items-center">
                            <span class="w-full border-t" />
                        </div>
                        <div class="relative flex justify-center text-xs uppercase">
                            <span class="bg-background px-2 text-muted-foreground">o</span>
                        </div>
                    </div>
                </template>

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
                <template v-if="$page.props.googleAuthEnabled">
                    <a
                        :href="googleRedirect({ query: { join_token: token } }).url"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2.5 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                    >
                        <svg class="size-5" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4" />
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
                        </svg>
                        Continuar con Google
                    </a>

                    <div class="relative my-1">
                        <div class="absolute inset-0 flex items-center">
                            <span class="w-full border-t" />
                        </div>
                        <div class="relative flex justify-center text-xs uppercase">
                            <span class="bg-background px-2 text-muted-foreground">o</span>
                        </div>
                    </div>
                </template>

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
