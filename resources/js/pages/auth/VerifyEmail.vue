<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { MailCheck, Shield } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';

type Props = {
    status?: string;
    pendingClub?: { name: string; description: string | null } | null;
};

const props = defineProps<Props>();

const code = ref('');

const verifyForm = useForm({ code: '' });
const resendForm = useForm({});

function submitCode() {
    verifyForm.code = code.value;
    verifyForm.post('/email/verify-code', {
        onError: () => { code.value = ''; },
    });
}

function resend() {
    resendForm.post('/email/resend-code');
}

watch(code, (val) => {
    if (val.length === 6) {
        submitCode();
    }
});
</script>

<template>
    <Head title="Verificar correo" />

    <div class="flex min-h-svh flex-col items-center justify-center bg-background p-4">
        <div class="w-full max-w-sm">
            <!-- Header -->
            <div class="mb-8 flex flex-col items-center gap-4 text-center">
                <div class="gradient-primary-bg flex size-16 items-center justify-center rounded-2xl shadow-lg">
                    <AppLogoIcon class="size-9 text-white" />
                </div>

                <div>
                    <h1 class="text-2xl font-bold">Verifica tu correo</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Ingresa el codigo de 6 digitos que enviamos a tu correo.
                    </p>
                </div>

                <!-- Pending club context -->
                <div v-if="pendingClub" class="flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2">
                    <Shield class="size-4 text-primary" />
                    <span class="text-sm">
                        Uniendote a <span class="font-medium">{{ pendingClub.name }}</span>
                    </span>
                </div>
            </div>

            <!-- Success message -->
            <div
                v-if="status === 'Se ha enviado un nuevo codigo a tu correo.'"
                class="mb-4 flex items-center gap-2 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-700 dark:border-green-700 dark:bg-green-950/30 dark:text-green-400"
            >
                <MailCheck class="size-4 shrink-0" />
                Nuevo codigo enviado!
            </div>

            <!-- OTP Input -->
            <div class="space-y-6">
                <div class="flex flex-col items-center gap-3">
                    <InputOTP
                        v-model="code"
                        :maxlength="6"
                        :disabled="verifyForm.processing"
                        autofocus
                        input-mode="numeric"
                    >
                        <InputOTPGroup>
                            <InputOTPSlot v-for="index in 6" :key="index" :index="index - 1" />
                        </InputOTPGroup>
                    </InputOTP>
                    <InputError :message="verifyForm.errors.code" />
                </div>

                <Button
                    class="gradient-primary-bg w-full border-0 text-white hover:opacity-90"
                    size="lg"
                    :disabled="verifyForm.processing || code.length < 6"
                    @click="submitCode"
                >
                    <Spinner v-if="verifyForm.processing" />
                    Verificar
                </Button>

                <div class="flex flex-col items-center gap-2 text-center text-sm text-muted-foreground">
                    <button
                        type="button"
                        class="font-medium text-primary underline underline-offset-4"
                        :disabled="resendForm.processing"
                        @click="resend"
                    >
                        <Spinner v-if="resendForm.processing" class="mr-1 inline size-3" />
                        Reenviar codigo
                    </button>
                    <TextLink
                        :href="logout()"
                        as="button"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        Cerrar sesion
                    </TextLink>
                </div>
            </div>
        </div>
    </div>
</template>
