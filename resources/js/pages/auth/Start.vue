<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { redirect as googleRedirect } from '@/actions/App/Http/Controllers/GoogleAuthController';
import InputError from '@/components/InputError.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input, PasswordInput } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { privacy, terms } from '@/routes';
import { request } from '@/routes/password';

const props = defineProps<{
    canRegister: boolean;
    mode: 'register' | 'login';
    googleAuthEnabled: boolean;
    status?: string;
}>();

const activeMode = ref<'register' | 'login'>(props.mode);

const registerForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

const termsAccepted = ref(false);
const termsError = ref('');

watch(termsAccepted, (value) => {
    registerForm.terms = value;
});

watch(() => registerForm.password, (value) => {
    registerForm.password_confirmation = value;
});

function handleGoogleAuth() {
    if (activeMode.value === 'register') {
        if (!termsAccepted.value) {
            termsError.value = 'Debes aceptar los terminos y condiciones para continuar.';
            return;
        }
        termsError.value = '';
        window.location.href = googleRedirect({ query: { terms_accepted: '1' } }).url;
    } else {
        window.location.href = googleRedirect().url;
    }
}

function submitRegister() {
    if (!termsAccepted.value) {
        termsError.value = 'Debes aceptar los terminos y condiciones para continuar.';
        return;
    }
    termsError.value = '';
    registerForm.post('/register', {
        onFinish: () => {
            registerForm.reset('password', 'password_confirmation');
        },
    });
}

function submitLogin() {
    loginForm.post('/login', {
        onFinish: () => {
            loginForm.reset('password');
        },
    });
}
</script>

<template>
    <div class="flex min-h-[100dvh] flex-col items-center justify-center bg-background p-6">
        <Head :title="activeMode === 'register' ? 'Crear cuenta' : 'Iniciar sesión'" />

        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-6">
                <!-- Logo -->
                <div class="flex justify-center">
                    <Link href="/">
                        <AppLogoIcon class="size-10 fill-current text-[var(--foreground)] dark:text-white" />
                    </Link>
                </div>

                <!-- Title -->
                <div class="text-center">
                    <h1 class="text-lg font-semibold">
                        {{ activeMode === 'register' ? 'Organiza tus partidos' : 'Bienvenido de vuelta' }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ activeMode === 'register' ? 'Regístrate con Google o con tu email' : 'Inicia sesión con Google o con tu email' }}
                    </p>
                </div>

                <!-- Tab toggle -->
                <div class="flex border-b border-border">
                    <button
                        type="button"
                        class="flex-1 pb-2 text-center text-sm font-medium transition-colors"
                        :class="activeMode === 'register' ? 'border-b-2 border-primary text-foreground' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeMode = 'register'"
                    >
                        Crear cuenta
                    </button>
                    <button
                        type="button"
                        class="flex-1 pb-2 text-center text-sm font-medium transition-colors"
                        :class="activeMode === 'login' ? 'border-b-2 border-primary text-foreground' : 'text-muted-foreground hover:text-foreground'"
                        @click="activeMode = 'login'"
                    >
                        Iniciar sesión
                    </button>
                </div>

                <!-- Status message -->
                <div v-if="status" class="text-center text-sm font-medium text-green-600">
                    {{ status }}
                </div>

                <!-- Register form -->
                <form v-if="activeMode === 'register'" class="flex flex-col gap-5" @submit.prevent="submitRegister">
                    <!-- Google OAuth -->
                    <template v-if="googleAuthEnabled">
                        <a
                            href="#"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2.5 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                            @click.prevent="handleGoogleAuth"
                        >
                            <svg class="size-5" viewBox="0 0 24 24">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4" />
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
                            </svg>
                            Continuar con Google
                        </a>

                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <span class="w-full border-t" />
                            </div>
                            <div class="relative flex justify-center text-xs uppercase">
                                <span class="bg-background px-2 text-muted-foreground">o con tu email</span>
                            </div>
                        </div>
                    </template>

                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="register-name">Nombre</Label>
                            <Input
                                id="register-name"
                                v-model="registerForm.name"
                                type="text"
                                required
                                autocomplete="name"
                                placeholder="Nombre completo"
                            />
                            <InputError :message="registerForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="register-email">Email</Label>
                            <Input
                                id="register-email"
                                v-model="registerForm.email"
                                type="email"
                                required
                                autocomplete="email"
                                placeholder="email@example.com"
                            />
                            <InputError :message="registerForm.errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="register-password">Contraseña</Label>
                            <PasswordInput
                                id="register-password"
                                v-model="registerForm.password"
                                required
                                autocomplete="new-password"
                                placeholder="Mínimo 8 caracteres"
                                minlength="8"
                            />
                            <InputError :message="registerForm.errors.password" />
                        </div>
                    </div>

                    <label for="register-terms" class="flex items-start gap-2 text-sm">
                        <Checkbox id="register-terms" v-model="termsAccepted" class="mt-0.5" />
                        <span class="text-muted-foreground">
                            Acepto los
                            <a :href="terms.url()" target="_blank" class="text-primary hover:underline">términos</a>
                            y la
                            <a :href="privacy.url()" target="_blank" class="text-primary hover:underline">política de privacidad</a>
                        </span>
                    </label>
                    <InputError :message="registerForm.errors.terms || termsError" />

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="registerForm.processing"
                    >
                        <Spinner v-if="registerForm.processing" />
                        Empieza gratis
                    </Button>
                </form>

                <!-- Login form -->
                <form v-else class="flex flex-col gap-5" @submit.prevent="submitLogin">
                    <!-- Google OAuth -->
                    <template v-if="googleAuthEnabled">
                        <a
                            href="#"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2.5 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                            @click.prevent="handleGoogleAuth"
                        >
                            <svg class="size-5" viewBox="0 0 24 24">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4" />
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
                            </svg>
                            Continuar con Google
                        </a>

                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <span class="w-full border-t" />
                            </div>
                            <div class="relative flex justify-center text-xs uppercase">
                                <span class="bg-background px-2 text-muted-foreground">o con tu email</span>
                            </div>
                        </div>
                    </template>

                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="login-email">Email</Label>
                            <Input
                                id="login-email"
                                v-model="loginForm.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                                placeholder="email@example.com"
                            />
                            <InputError :message="loginForm.errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="login-password">Contraseña</Label>
                            <PasswordInput
                                id="login-password"
                                v-model="loginForm.password"
                                required
                                autocomplete="current-password"
                                placeholder="Contraseña"
                            />
                            <InputError :message="loginForm.errors.password" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <a :href="request.url()" class="text-sm text-muted-foreground hover:text-foreground">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="loginForm.processing"
                    >
                        <Spinner v-if="loginForm.processing" />
                        Iniciar sesión
                    </Button>
                </form>
            </div>
        </div>
    </div>
</template>
