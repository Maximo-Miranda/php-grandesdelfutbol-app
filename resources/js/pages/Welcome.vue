<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronRight, Send, Trophy, UserPlus, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import InputError from '@/components/InputError.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { getCsrfToken } from '@/lib/utils';
import { dashboard, privacy, terms } from '@/routes';
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

const page = usePage();
const isLoggedIn = computed(() => !!page.props.auth?.user);

// Video service request dialog
const showRequestDialog = ref(false);
const requestForm = ref({
    name: '',
    email: '',
    phone: '',
    club_name: '',
    venue_address: '',
    preferred_date: '',
    preferred_time: '',
    message: '',
    selected_plan: '',
});
const requestErrors = ref<Record<string, string>>({});
const requestSubmitting = ref(false);
const requestSuccess = ref(false);

function openRequest(plan: string) {
    requestForm.value.selected_plan = plan;
    requestErrors.value = {};
    requestSuccess.value = false;
    showRequestDialog.value = true;
}

async function submitRequest() {
    requestSubmitting.value = true;
    requestErrors.value = {};

    try {
        const res = await fetch('/video-service-request', {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': getCsrfToken(),
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(requestForm.value),
        });

        if (res.ok) {
            requestSuccess.value = true;
            requestForm.value = { name: '', email: '', phone: '', club_name: '', venue_address: '', preferred_date: '', preferred_time: '', message: '', selected_plan: '' };
        } else if (res.status === 422) {
            const data = await res.json();
            requestErrors.value = data.errors ?? {};
        }
    } finally {
        requestSubmitting.value = false;
    }
}

const pricingPlans = [
    {
        name: 'Gratis',
        price: '$0',
        period: 'para siempre',
        description: 'Todo lo que necesitas para organizar partidos con tu grupo.',
        features: ['Crea tu club e invita amigos', 'Programa partidos', 'Confirma asistencia en 1 tap', 'Arma equipos equilibrados', 'Estadísticas de cada jugador', 'Comparte reels y tarjetas'],
        cta: 'Empieza gratis',
        ctaLink: '/start',
        highlight: false,
    },
    {
        name: 'Recocha',
        price: '$60.000',
        period: 'por partido',
        description: 'Grabamos tu partido y subimos el video completo.',
        features: ['Video completo en la app', 'Subida a YouTube', 'Stats globales del equipo', 'Reels automáticos de goles'],
        cta: 'Solicitar',
        plan: 'recocha',
        highlight: false,
    },
    {
        name: 'Profesional',
        price: '$130.000',
        period: 'por partido',
        description: 'La experiencia completa: video + estadísticas individuales.',
        features: ['Todo lo de Recocha', 'Stats individuales verificadas', 'Tarjetas de jugador actualizadas', 'Highlights personalizados'],
        cta: 'Solicitar',
        plan: 'profesional',
        highlight: true,
        badge: 'Popular',
    },
    {
        name: 'Mensual',
        price: 'Desde $200.000',
        period: 'por mes',
        description: 'Suscripción mensual — 4 partidos incluidos.',
        features: ['4 partidos grabados al mes', 'Stats individuales de cada partido', 'Tarjetas de jugador', 'Prioridad de agenda'],
        cta: 'Solicitar',
        plan: 'mensual',
        highlight: false,
    },
];
</script>

<template>
    <Head title="Grandes del Fútbol — La app para organizar partidos de fútbol con tus amigos">
        <meta head-key="description" name="description" content="App gratis para organizar partidos de fútbol con amigos. Confirma asistencia, arma equipos, lleva estadísticas de goles y asistencias, y comparte reels de tus mejores jugadas." />
        <meta head-key="og:title" property="og:title" content="Grandes del Fútbol — Organiza partidos de fútbol con tu grupo de amigos" />
        <meta head-key="og:description" property="og:description" content="Confirma asistencia, arma equipos equilibrados y lleva estadísticas de cada jugador. Gratis. Servicio de grabación desde $60.000 por partido." />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:image" property="og:image" :content="`${props.appUrl}/pwa-512x512.png`" />
        <meta head-key="og:locale" property="og:locale" content="es_CO" />
        <meta head-key="twitter:card" name="twitter:card" content="summary" />
    </Head>

    <div class="min-h-screen bg-background text-foreground">
        <!-- Header -->
        <header class="fixed top-0 right-0 left-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
                <Link href="/" class="flex items-center gap-2">
                    <AppLogo />
                </Link>

                <div class="flex items-center gap-3">
                    <template v-if="isLoggedIn">
                        <Link
                            :href="dashboard()"
                            class="gradient-primary-bg rounded-lg px-5 py-2 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                        >
                            Ir al panel
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            href="/start?mode=login"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors hover:bg-accent"
                        >
                            Iniciar sesión
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative flex min-h-screen items-center justify-center overflow-hidden pt-16">
            <div class="absolute inset-0">
                <img :src="heroImage" alt="Partido de fútbol amateur" class="h-full w-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/50 to-background" />
            </div>

            <div class="relative z-10 mx-auto max-w-4xl px-4 text-center">
                <h1 class="mb-6 text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">
                    Organizar partidos de fútbol
                    <span class="gradient-primary-text">no debería ser un caos</span>
                </h1>
                <p class="mx-auto mb-10 max-w-2xl text-lg text-gray-300 sm:text-xl">
                    Confirma asistencia, arma equipos y lleva estadísticas — sin perderte en 50 mensajes. Comparte reels de goles y las mejores jugadas en tu grupo.
                </p>
                <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link
                        v-if="!isLoggedIn"
                        href="/start"
                        class="gradient-primary-bg inline-flex items-center gap-2 rounded-xl px-8 py-3.5 text-lg font-semibold text-white shadow-lg transition-opacity hover:opacity-90"
                    >
                        Empieza gratis
                        <ChevronRight class="size-5" />
                    </Link>
                    <Link
                        v-else
                        :href="dashboard()"
                        class="gradient-primary-bg inline-flex items-center gap-2 rounded-xl px-8 py-3.5 text-lg font-semibold text-white shadow-lg transition-opacity hover:opacity-90"
                    >
                        Ir al panel
                    </Link>
                    <a
                        href="#como-funciona"
                        class="inline-flex items-center rounded-xl border border-white/20 px-8 py-3.5 text-lg font-semibold text-white transition-colors hover:bg-white/10"
                    >
                        Cómo funciona
                    </a>
                </div>
            </div>
        </section>

        <!-- WhatsApp + App -->
        <section class="py-16 sm:py-24">
            <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
                <h2 class="mb-4 text-3xl font-bold sm:text-4xl">
                    Tu grupo de WhatsApp es para pasarla bien.
                    <span class="gradient-primary-text">La app es para que todo funcione.</span>
                </h2>
                <p class="mx-auto mb-12 max-w-2xl text-lg text-muted-foreground">
                    No reemplazamos tu grupo — lo complementamos. La app organiza lo que los mensajes no pueden, y genera contenido que tus amigos van a querer compartir.
                </p>

                <div class="grid gap-6 text-left sm:grid-cols-3">
                    <div class="rounded-2xl border border-border bg-card p-6">
                        <p class="mb-2 text-sm font-medium text-muted-foreground">En WhatsApp</p>
                        <p class="mb-3 text-base font-semibold">"¿Quién va el jueves?"</p>
                        <p class="text-sm text-muted-foreground">50 mensajes después... sigues sin saber.</p>
                        <div class="mt-4 border-t border-border pt-4">
                            <p class="text-sm font-medium text-emerald-500">En la app →</p>
                            <p class="text-sm text-muted-foreground">Confirmación en 1 tap. Todos ven quién va.</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-6">
                        <p class="mb-2 text-sm font-medium text-muted-foreground">En WhatsApp</p>
                        <p class="mb-3 text-base font-semibold">"¿Cómo armamos los equipos?"</p>
                        <p class="text-sm text-muted-foreground">Siempre los mismos se juntan y ganan.</p>
                        <div class="mt-4 border-t border-border pt-4">
                            <p class="text-sm font-medium text-emerald-500">En la app →</p>
                            <p class="text-sm text-muted-foreground">Equipos aleatorios o equilibrados — tú decides.</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-6">
                        <p class="mb-2 text-sm font-medium text-muted-foreground">En WhatsApp</p>
                        <p class="mb-3 text-base font-semibold">"¿Cuántos goles llevo?"</p>
                        <p class="text-sm text-muted-foreground">Nadie lleva la cuenta.</p>
                        <div class="mt-4 border-t border-border pt-4">
                            <p class="text-sm font-medium text-emerald-500">En la app →</p>
                            <p class="text-sm text-muted-foreground">Stats de cada jugador. Comparte tu tarjeta.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cómo funciona -->
        <section id="como-funciona" class="border-t border-border py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-16 text-center">
                    <h2 class="mb-4 text-3xl font-bold sm:text-4xl">Cómo funciona</h2>
                    <p class="mx-auto max-w-2xl text-lg text-muted-foreground">
                        En tres pasos tu grupo de amigos tiene todo listo para jugar organizado.
                    </p>
                </div>

                <div class="grid gap-8 md:grid-cols-3">
                    <div class="rounded-2xl border border-border bg-card p-8 text-center transition-shadow hover:shadow-lg">
                        <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                            <UserPlus class="size-8 text-white" />
                        </div>
                        <h3 class="mb-3 text-xl font-semibold">1. Crea tu club</h3>
                        <p class="text-muted-foreground">
                            Regístrate gratis y crea tu club. Invita a tu grupo de amigos con un simple link.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-8 text-center transition-shadow hover:shadow-lg">
                        <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                            <Users class="size-8 text-white" />
                        </div>
                        <h3 class="mb-3 text-xl font-semibold">2. Organiza partidos</h3>
                        <p class="text-muted-foreground">
                            Programa partidos. Cada jugador confirma asistencia desde la app — sin depender del chat.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-8 text-center transition-shadow hover:shadow-lg">
                        <div class="gradient-primary-bg mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl">
                            <Trophy class="size-8 text-white" />
                        </div>
                        <h3 class="mb-3 text-xl font-semibold">3. Graba, sube y comparte</h3>
                        <p class="text-muted-foreground">
                            Graba tu partido, súbelo a la plataforma, carga estadísticas y genera reels de los mejores momentos. Todo gratis.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Precios -->
        <section id="precios" class="border-t border-border py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-16 text-center">
                    <h2 class="mb-4 text-3xl font-bold sm:text-4xl">
                        ¿No tienes tiempo de grabar y cargar stats?
                        <span class="gradient-primary-text">Nosotros lo hacemos por ti.</span>
                    </h2>
                    <p class="mx-auto max-w-2xl text-lg text-muted-foreground">
                        Vamos a tu cancha, grabamos el partido completo, subimos el video a tu club y cargamos las estadísticas de cada jugador. Tú solo juegas.
                    </p>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="plan in pricingPlans"
                        :key="plan.name"
                        class="relative flex flex-col rounded-2xl border bg-card p-6 transition-shadow hover:shadow-lg"
                        :class="plan.highlight ? 'border-emerald-500 shadow-emerald-500/10 shadow-lg' : 'border-border'"
                    >
                        <div v-if="plan.badge" class="absolute -top-3 right-4 rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-white">
                            {{ plan.badge }}
                        </div>
                        <h3 class="mb-1 text-lg font-bold">{{ plan.name }}</h3>
                        <div class="mb-3">
                            <span class="text-2xl font-bold">{{ plan.price }}</span>
                            <span class="ml-1 text-sm text-muted-foreground">{{ plan.period }}</span>
                        </div>
                        <p class="mb-4 text-sm text-muted-foreground">{{ plan.description }}</p>
                        <ul class="mb-6 flex-1 space-y-2">
                            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm">
                                <Check class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                {{ feature }}
                            </li>
                        </ul>
                        <Link
                            v-if="plan.ctaLink"
                            :href="plan.ctaLink"
                            class="mt-auto w-full rounded-lg py-2.5 text-center text-sm font-semibold transition-colors"
                            :class="plan.highlight ? 'gradient-primary-bg text-white hover:opacity-90' : 'border border-border hover:bg-accent'"
                        >
                            {{ plan.cta }}
                        </Link>
                        <button
                            v-else
                            type="button"
                            class="mt-auto w-full rounded-lg py-2.5 text-center text-sm font-semibold transition-colors"
                            :class="plan.highlight ? 'gradient-primary-bg text-white hover:opacity-90' : 'border border-border hover:bg-accent'"
                            @click="openRequest(plan.plan!)"
                        >
                            {{ plan.cta }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Final -->
        <section class="border-t border-border py-16 sm:py-24">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
                <h2 class="mb-4 text-3xl font-bold sm:text-4xl">
                    ¿Listo para organizar tus partidos?
                </h2>
                <p class="mb-8 text-lg text-muted-foreground">
                    Crea tu club gratis en menos de 2 minutos.
                </p>
                <Link
                    v-if="!isLoggedIn"
                    href="/start"
                    class="gradient-primary-bg inline-flex items-center gap-2 rounded-xl px-8 py-3.5 text-lg font-semibold text-white shadow-lg transition-opacity hover:opacity-90"
                >
                    Empieza gratis
                    <ChevronRight class="size-5" />
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center gap-6">
                    <AppLogo />
                    <div class="flex flex-col items-center gap-3 sm:flex-row sm:gap-6">
                        <a :href="terms.url()" class="text-sm text-muted-foreground transition-colors hover:text-foreground">
                            Términos y Condiciones
                        </a>
                        <span class="hidden text-muted-foreground/50 sm:inline">&middot;</span>
                        <a :href="privacy.url()" class="text-sm text-muted-foreground transition-colors hover:text-foreground">
                            Política de Privacidad
                        </a>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} Grandes del Fútbol. Todos los derechos reservados.
                    </p>
                </div>
            </div>
        </footer>

        <!-- Dialog solicitud de video -->
        <Dialog v-model:open="showRequestDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Solicitar servicio de video</DialogTitle>
                    <DialogDescription>
                        Déjanos tus datos y te contactamos para coordinar la grabación de tu partido.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="requestSuccess" class="py-6 text-center">
                    <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-emerald-500/10">
                        <Check class="size-6 text-emerald-500" />
                    </div>
                    <p class="font-semibold">Solicitud enviada</p>
                    <p class="mt-1 text-sm text-muted-foreground">Te contactaremos pronto para coordinar.</p>
                    <Button class="mt-4" variant="outline" @click="showRequestDialog = false">Cerrar</Button>
                </div>

                <form v-else class="space-y-4" @submit.prevent="submitRequest">
                    <!-- Auto-filled when authenticated -->
                    <template v-if="!isLoggedIn">
                        <div class="grid gap-1.5">
                            <Label for="req-name">Nombre <span class="text-destructive">*</span></Label>
                            <Input id="req-name" v-model="requestForm.name" placeholder="Tu nombre" />
                            <InputError :message="requestErrors.name?.[0]" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="req-email">Email <span class="text-destructive">*</span></Label>
                            <Input id="req-email" v-model="requestForm.email" placeholder="tu@email.com" />
                            <InputError :message="requestErrors.email?.[0]" />
                        </div>
                    </template>
                    <div class="grid gap-1.5">
                        <Label for="req-phone">WhatsApp <span class="text-destructive">*</span></Label>
                        <PhoneInput id="req-phone" v-model="requestForm.phone" />
                        <InputError :message="requestErrors.phone?.[0]" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="req-plan">Tipo de servicio <span class="text-destructive">*</span></Label>
                        <select
                            id="req-plan"
                            v-model="requestForm.selected_plan"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="recocha">Recocha — $60.000/partido</option>
                            <option value="profesional">Profesional — $130.000/partido</option>
                            <option value="mensual">Mensual — Desde $200.000/mes</option>
                        </select>
                        <InputError :message="requestErrors.selected_plan?.[0]" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="req-date">Fecha <span class="text-destructive">*</span></Label>
                            <Input id="req-date" v-model="requestForm.preferred_date" type="date" />
                            <InputError :message="requestErrors.preferred_date?.[0]" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="req-time">Hora <span class="text-destructive">*</span></Label>
                            <Input id="req-time" v-model="requestForm.preferred_time" type="time" />
                            <InputError :message="requestErrors.preferred_time?.[0]" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="req-venue">Dirección de la cancha <span class="text-destructive">*</span></Label>
                        <Input id="req-venue" v-model="requestForm.venue_address" placeholder="Ej: Cancha Sintética Los Pinos, Cra 7 #45" />
                        <InputError :message="requestErrors.venue_address?.[0]" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="req-message">Mensaje</Label>
                        <Textarea id="req-message" v-model="requestForm.message" rows="2" placeholder="Algo que debamos saber..." />
                    </div>

                    <DialogFooter>
                        <Button type="submit" :disabled="requestSubmitting" class="w-full gap-2">
                            <Send class="size-4" />
                            {{ requestSubmitting ? 'Enviando...' : 'Enviar solicitud' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
