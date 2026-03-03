<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as registerStore } from '@/routes/register';
import { store as loginStore } from '@/routes/login';

type Props = {
    invitation: {
        token: string;
        email: string;
        club: { id: number; name: string; description: string | null };
        inviter: { name: string } | null;
    };
};

const props = defineProps<Props>();

const mode = ref<'register' | 'login'>('register');

const registerForm = useForm({
    name: '',
    email: props.invitation.email,
    password: '',
    password_confirmation: '',
    invite_token: props.invitation.token,
});

const loginForm = useForm({
    email: props.invitation.email,
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
    <Head :title="`Unirse a ${invitation.club.name}`" />

    <div class="flex min-h-svh flex-col items-center justify-center bg-background p-6">
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center gap-6">
                <!-- Logo + Club info -->
                <AppLogoIcon class="size-10 fill-current text-foreground" />
                <div class="space-y-1 text-center">
                    <h1 class="text-xl font-bold">Unirse a {{ invitation.club.name }}</h1>
                    <p v-if="invitation.inviter" class="text-sm text-muted-foreground">
                        <span class="font-medium text-foreground">{{ invitation.inviter.name }}</span>
                        te ha invitado a jugar
                    </p>
                </div>

                <!-- Register form -->
                <form v-if="mode === 'register'" class="w-full space-y-4" @submit.prevent="submitRegister">
                    <div class="grid gap-2">
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

                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            v-model="registerForm.email"
                            type="email"
                            required
                            autocomplete="email"
                            :disabled="true"
                            class="bg-muted"
                        />
                        <InputError :message="registerForm.errors.email" />
                    </div>

                    <div class="grid gap-2">
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

                    <div class="grid gap-2">
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

                    <Button type="submit" class="w-full" size="lg" :disabled="registerForm.processing">
                        <Spinner v-if="registerForm.processing" />
                        Crear cuenta y unirme
                    </Button>

                    <p class="text-center text-sm text-muted-foreground">
                        Ya tienes cuenta?
                        <button type="button" class="font-medium text-foreground underline underline-offset-4" @click="mode = 'login'">
                            Iniciar sesion
                        </button>
                    </p>
                </form>

                <!-- Login form -->
                <form v-else class="w-full space-y-4" @submit.prevent="submitLogin">
                    <div class="grid gap-2">
                        <Label for="login-email">Email</Label>
                        <Input
                            id="login-email"
                            v-model="loginForm.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="email@ejemplo.com"
                        />
                        <InputError :message="loginForm.errors.email" />
                    </div>

                    <div class="grid gap-2">
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

                    <Button type="submit" class="w-full" size="lg" :disabled="loginForm.processing">
                        <Spinner v-if="loginForm.processing" />
                        Iniciar sesion y unirme
                    </Button>

                    <p class="text-center text-sm text-muted-foreground">
                        No tienes cuenta?
                        <button type="button" class="font-medium text-foreground underline underline-offset-4" @click="mode = 'register'">
                            Crear cuenta
                        </button>
                    </p>
                </form>
            </div>
        </div>
    </div>
</template>
