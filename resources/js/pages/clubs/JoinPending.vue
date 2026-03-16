<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, Home } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';

type Props = {
    club: { name: string; description: string | null };
    isNewRequest: boolean;
};

defineProps<Props>();
</script>

<template>
    <Head :title="`Solicitud enviada — ${club.name}`" />

    <div class="flex min-h-svh flex-col items-center justify-center bg-background p-4">
        <div class="w-full max-w-sm text-center">
            <div class="mb-6 flex flex-col items-center gap-4">
                <div class="gradient-primary-bg flex size-16 items-center justify-center rounded-2xl shadow-lg">
                    <AppLogoIcon class="size-9 text-white" />
                </div>
                <h1 class="text-2xl font-bold">{{ club.name }}</h1>
            </div>

            <div class="rounded-xl border border-yellow-300/50 bg-yellow-50/50 p-6 dark:border-yellow-700/50 dark:bg-yellow-950/20">
                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-yellow-500/10">
                    <Clock class="size-6 text-yellow-600 dark:text-yellow-400" />
                </div>

                <h2 class="mb-2 text-lg font-semibold">
                    {{ isNewRequest ? 'Solicitud enviada' : 'Solicitud pendiente' }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ isNewRequest
                        ? 'Tu solicitud para unirte al club ha sido enviada. El administrador debe aprobarla para que puedas acceder.'
                        : 'Ya tienes una solicitud pendiente para este club. El administrador debe aprobarla para que puedas acceder.'
                    }}
                </p>
            </div>

            <Link href="/clubs" class="mt-6 block">
                <Button variant="outline" class="w-full gap-2">
                    <Home class="size-4" />
                    Ir a Mis Clubes
                </Button>
            </Link>
        </div>
    </div>
</template>
