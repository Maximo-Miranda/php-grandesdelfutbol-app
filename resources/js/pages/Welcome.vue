<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Moon, Sun, Trophy, UserPlus, Users, Video } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, login, privacy, register, terms } from '@/routes';
import heroImage from '../../images/hero-football.jpg';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        appUrl: string;
    }>(),
    {
        canRegister: true,
        appUrl: '',
    },
);

const { updateAppearance, resolvedAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

function toggleDarkMode() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <Head title="Organiza partidos de futbol con tu grupo de amigos">
        <meta head-key="description" name="description" content="Deja de coordinar por WhatsApp. Controla asistencia, arma equipos y sigue las estadisticas de cada jugador de tu grupo en cancha sintetica." />
        <meta head-key="og:title" property="og:title" content="Grandes del Futbol — Organiza el futbol con tu grupo de amigos" />
        <meta head-key="og:description" property="og:description" content="Controla asistencia, arma equipos y sigue las estadisticas de cada jugador. Servicio de grabacion y estadisticas desde $60.000 por partido." />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:image" property="og:image" :content="`${props.appUrl}/pwa-512x512.png`" />
        <meta head-key="og:locale" property="og:locale" content="es_CO" />
        <meta head-key="twitter:card" name="twitter:card" content="summary" />
    </Head>

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
                    Organiza el futbol con tu
                    <span class="gradient-primary-text">grupo de amigos</span>
                </h1>
                <p class="mx-auto mb-10 max-w-2xl text-lg text-gray-300 sm:text-xl">
                    Deja de coordinar por WhatsApp. Controla asistencia, arma equipos y lleva las estadisticas de cada jugador en un solo lugar.
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
                        En tres simples pasos, tu grupo de amigos tiene todo listo para jugar organizado.
                    </p>
                </div>

                <div class="grid gap-8 md:grid-cols-3">
                    <div class="rounded-2xl border border-border bg-card p-8 text-center transition-shadow hover:shadow-lg">
                        <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                            <UserPlus class="size-8 text-white" />
                        </div>
                        <h3 class="mb-3 text-xl font-semibold">1. Crea tu club</h3>
                        <p class="text-muted-foreground">
                            Registrate gratis y crea tu club. Invita a tu grupo de amigos con un simple link.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-8 text-center transition-shadow hover:shadow-lg">
                        <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                            <Users class="size-8 text-white" />
                        </div>
                        <h3 class="mb-3 text-xl font-semibold">2. Organiza partidos</h3>
                        <p class="text-muted-foreground">
                            Confirma asistencia, arma equipos y programa tus partidos de cancha sintetica sin depender de un chat.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-8 text-center transition-shadow hover:shadow-lg">
                        <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                            <Trophy class="size-8 text-white" />
                        </div>
                        <h3 class="mb-3 text-xl font-semibold">3. Sigue el rendimiento</h3>
                        <p class="text-muted-foreground">
                            Goles, atajadas, tarjetas y mas. Cada jugador tiene su perfil con estadisticas detalladas.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Servicio de grabacion -->
        <section class="py-20 sm:py-28">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-3xl border border-border bg-card p-10 text-center sm:p-16">
                    <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                        <Video class="size-8 text-white" />
                    </div>
                    <h2 class="mb-4 text-3xl font-bold sm:text-4xl">
                        Grabacion de partidos + estadisticas
                    </h2>
                    <p class="mx-auto mb-4 max-w-2xl text-lg text-muted-foreground">
                        Vamos a tu cancha, grabamos el partido completo y cargamos las estadisticas de cada jugador en la app. Goles, atajadas, tarjetas — todo registrado.
                    </p>
                    <p class="mb-8 text-2xl font-bold">
                        $60.000 por partido
                        <span class="block text-base font-normal text-muted-foreground">
                            Entre 12 jugadores son solo $5.000 cada uno
                        </span>
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
                <div class="flex flex-col items-center gap-6">
                    <div class="flex items-center gap-2">
                        <AppLogo />
                    </div>
                    <div class="flex flex-col items-center gap-3 sm:flex-row sm:gap-6">
                        <a :href="terms.url()" class="text-sm text-muted-foreground transition-colors hover:text-foreground">
                            Terminos y Condiciones de Uso
                        </a>
                        <span class="hidden text-muted-foreground/50 sm:inline">&middot;</span>
                        <a :href="privacy.url()" class="text-sm text-muted-foreground transition-colors hover:text-foreground">
                            Politica de Tratamiento de Datos Personales
                        </a>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} Grandes del Futbol. Todos los derechos reservados.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
