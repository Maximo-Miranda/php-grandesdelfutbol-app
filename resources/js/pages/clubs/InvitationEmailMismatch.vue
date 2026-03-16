<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Home, LogOut } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';

type Props = {
    clubName: string;
};

defineProps<Props>();

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head :title="`Invitacion — ${clubName}`" />

    <div class="flex min-h-svh flex-col items-center justify-center bg-background p-4">
        <div class="w-full max-w-sm text-center">
            <div class="mb-6 flex flex-col items-center gap-4">
                <div class="gradient-primary-bg flex size-16 items-center justify-center rounded-2xl shadow-lg">
                    <AppLogoIcon class="size-9 text-white" />
                </div>
                <h1 class="text-2xl font-bold">{{ clubName }}</h1>
            </div>

            <div class="rounded-xl border border-red-300/50 bg-red-50/50 p-6 dark:border-red-700/50 dark:bg-red-950/20">
                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-red-500/10">
                    <AlertTriangle class="size-6 text-red-600 dark:text-red-400" />
                </div>

                <h2 class="mb-2 text-lg font-semibold">Cuenta incorrecta</h2>
                <p class="text-sm text-muted-foreground">
                    La cuenta con la que iniciaste sesion no coincide con la invitacion. Cierra sesion e intenta de nuevo con la cuenta correcta.
                </p>
            </div>

            <div class="mt-6 flex flex-col gap-3">
                <Button class="w-full gap-2" variant="default" @click="logout">
                    <LogOut class="size-4" />
                    Cerrar sesion
                </Button>
                <Link href="/clubs">
                    <Button variant="outline" class="w-full gap-2">
                        <Home class="size-4" />
                        Ir a Mis Clubes
                    </Button>
                </Link>
            </div>
        </div>
    </div>
</template>
