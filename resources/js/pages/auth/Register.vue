<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { PasswordInput } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { redirect as googleRedirect } from '@/actions/App/Http/Controllers/GoogleAuthController';
import { login, privacy, terms } from '@/routes';
import { store } from '@/routes/register';

const termsAccepted = ref(false);
const termsError = ref('');

function handleGoogleRegister() {
    if (!termsAccepted.value) {
        termsError.value = 'Debes aceptar los terminos y condiciones para continuar.';
        return;
    }
    termsError.value = '';
    window.location.href = googleRedirect({ query: { terms_accepted: '1' } }).url;
}
</script>

<template>
    <AuthBase
        title="Crear una cuenta"
        description="Ingresa tus datos para crear tu cuenta"
    >
        <Head title="Registrarse" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <label for="terms" class="flex items-center justify-center gap-2 text-sm">
                <Checkbox id="terms" name="terms" v-model="termsAccepted" :tabindex="1" />
                <span class="text-muted-foreground">
                    Acepto los
                    <a :href="terms.url()" target="_blank" class="text-primary hover:underline">Terminos</a>
                    y la
                    <a :href="privacy.url()" target="_blank" class="text-primary hover:underline">Politica de Privacidad</a>
                </span>
            </label>
            <InputError :message="errors.terms || termsError" />

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

                <div class="relative my-2">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t" />
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="bg-background px-2 text-muted-foreground">o</span>
                    </div>
                </div>
            </template>

            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Nombre</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        :tabindex="2"
                        autocomplete="name"
                        name="name"
                        placeholder="Nombre completo"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo electronico</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="3"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contrasena</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Minimo 8 caracteres"
                        minlength="8"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmar contrasena</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="5"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirmar contrasena"
                        minlength="8"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    :tabindex="6"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Crear cuenta
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Ya tienes una cuenta?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="7"
                    >Iniciar sesion</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
