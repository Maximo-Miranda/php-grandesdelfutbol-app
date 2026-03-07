<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Moon, Sun, Trophy, UserPlus, Users, Video } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, login, register } from '@/routes';
import heroImage from '../../images/hero-football.jpg';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const { updateAppearance, resolvedAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

function toggleDarkMode() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <Head title="Bienvenido" />

    <div class="min-h-screen bg-background text-foreground">
        <!-- Header -->
        <header class="fixed top-0 right-0 left-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-2">
                    <AppLogo />
                </Link>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        @click="toggleDarkMode"
                    >
                        <Sun v-if="isDark" class="size-5" />
                        <Moon v-else class="size-5" />
                    </button>

                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="dashboard()"
                            class="gradient-primary-bg rounded-lg px-5 py-2 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                        >
                            Ir al panel
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-accent"
                        >
                            Iniciar sesion
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="gradient-primary-bg rounded-lg px-5 py-2 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                        >
                            Registrarse
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative flex min-h-screen items-center justify-center overflow-hidden pt-16">
            <div class="absolute inset-0">
                <img
                    :src="heroImage"
                    alt="Campo de futbol"
                    class="h-full w-full object-cover"
                />
                <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/50 to-background" />
            </div>

            <div class="relative z-10 mx-auto max-w-4xl px-4 text-center">
                <h1 class="mb-6 text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">
                    Gestiona tus partidos y
                    <span class="gradient-primary-text">estadisticas</span>
                    como un profesional
                </h1>
                <p class="mx-auto mb-10 max-w-2xl text-lg text-gray-300 sm:text-xl">
                    La plataforma definitiva para organizar partidos de futbol, registrar goles, asistencias y seguir el rendimiento de cada jugador.
                </p>
                <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="register()"
                        class="gradient-primary-bg inline-flex items-center rounded-xl px-8 py-3.5 text-lg font-semibold text-white shadow-lg transition-opacity hover:opacity-90"
                    >
                        Comenzar gratis
                    </Link>
                    <Link
                        v-else
                        :href="dashboard()"
                        class="gradient-primary-bg inline-flex items-center rounded-xl px-8 py-3.5 text-lg font-semibold text-white shadow-lg transition-opacity hover:opacity-90"
                    >
                        Ir al panel
                    </Link>
                    <a
                        href="#como-funciona"
                        class="inline-flex items-center rounded-xl border border-white/20 px-8 py-3.5 text-lg font-semibold text-white transition-colors hover:bg-white/10"
                    >
                        Como funciona
                    </a>
                </div>
            </div>
        </section>

        <!-- Como Funciona -->
        <section id="como-funciona" class="py-20 sm:py-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-16 text-center">
                    <h2 class="mb-4 text-3xl font-bold sm:text-4xl">
                        Como funciona
                    </h2>
                    <p class="mx-auto max-w-2xl text-lg text-muted-foreground">
                        En tres simples pasos, tendras todo listo para gestionar tus partidos de futbol.
                    </p>
                </div>

                <div class="grid gap-8 md:grid-cols-3">
                    <div class="rounded-2xl border border-border bg-card p-8 text-center transition-shadow hover:shadow-lg">
                        <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                            <UserPlus class="size-8 text-white" />
                        </div>
                        <h3 class="mb-3 text-xl font-semibold">1. Crea tu club</h3>
                        <p class="text-muted-foreground">
                            Registrate y crea tu club de futbol. Invita a tus amigos y comienza a organizar.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-8 text-center transition-shadow hover:shadow-lg">
                        <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                            <Users class="size-8 text-white" />
                        </div>
                        <h3 class="mb-3 text-xl font-semibold">2. Organiza partidos</h3>
                        <p class="text-muted-foreground">
                            Programa partidos, arma los equipos automaticamente y lleva el control de asistencia.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-8 text-center transition-shadow hover:shadow-lg">
                        <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                            <Trophy class="size-8 text-white" />
                        </div>
                        <h3 class="mb-3 text-xl font-semibold">3. Registra estadisticas</h3>
                        <p class="text-muted-foreground">
                            Goles, asistencias, tarjetas y mas. Lleva un registro detallado de cada partido en tiempo real.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Grabacion -->
        <section class="py-20 sm:py-28">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-3xl border border-border bg-card p-10 text-center sm:p-16">
                    <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                        <Video class="size-8 text-white" />
                    </div>
                    <h2 class="mb-4 text-3xl font-bold sm:text-4xl">
                        Proximamente: Grabacion de partidos
                    </h2>
                    <p class="mx-auto mb-8 max-w-2xl text-lg text-muted-foreground">
                        Graba tus partidos y revive las mejores jugadas. Comparte los highlights con tu equipo y revisa cada momento clave.
                    </p>
                    <Link
                        v-if="!$page.props.auth.user"
                        :href="register()"
                        class="gradient-primary-bg inline-flex items-center rounded-xl px-8 py-3.5 text-lg font-semibold text-white shadow-lg transition-opacity hover:opacity-90"
                    >
                        Crear cuenta gratis
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <div class="flex items-center gap-2">
                        <AppLogo />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} Grandes del Futbol. Todos los derechos reservados.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
