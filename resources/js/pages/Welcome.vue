<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarCheck, Check, ChevronRight, Info, Send, Shield, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import InputError from '@/components/InputError.vue';
import NewsArticleCard from '@/components/news/NewsArticleCard.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import PublicHeader from '@/components/PublicHeader.vue';
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
import type { NewsArticle } from '@/types';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        appUrl: string;
        recentNews?: NewsArticle[];
    }>(),
    {
        canRegister: true,
        appUrl: '',
        recentNews: () => [],
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

type PriceTier = { label: string; value: string };
type FeatureGroup = { title: string; items: string[] };
type PricingPlan = {
    name: string;
    price: string;
    period?: string;
    priceNote?: string;
    description: string;
    features?: string[];
    featureGroups?: FeatureGroup[];
    priceTiers?: PriceTier[];
    cta: string;
    ctaLink?: string;
    plan?: string;
    highlight: boolean;
    badge?: string;
};

const pricingPlans: PricingPlan[] = [
    {
        name: 'Gratis',
        price: '$0',
        description: 'Toda la gestión deportiva de tu club, sin pagar nada.',
        featureGroups: [
            {
                title: 'Organiza tu club',
                items: [
                    'Crea tu club, equipos y temporadas',
                    'Programa partidos (incluye recurrentes semanales)',
                    'Invita jugadores por email o con link público del club',
                    'Auto-balanceo de equipos según stats reales (goles, asistencias, experiencia, posición)',
                    'Lista de espera con promoción automática cuando se libera un cupo',
                ],
            },
            {
                title: 'Para cada jugador',
                items: [
                    'Instálala como app en tu celular (Android e iOS) sin pasar por la tienda',
                    'Notificaciones push en tu celular y email para cada partido',
                    'Confirma asistencia en 1 tap',
                    'Historial completo: goles, asistencias, tarjetas, atajadas y partidos jugados',
                    'Perfil de jugador con foto, posición y biografía',
                ],
            },
            {
                title: 'Sigue tu fútbol',
                items: [
                    'Página pública del club, equipo y cada jugador (sin login)',
                    'Tabla de posiciones por temporada con goleadores y mejores arqueros',
                    'Link compartible del partido con alineaciones y eventos',
                ],
            },
        ],
        cta: 'Empieza gratis',
        ctaLink: '/start',
        highlight: false,
    },
    {
        name: 'Partido Pro',
        price: 'Desde $30.000',
        period: 'por partido',
        priceNote: 'Pago único. Sin suscripción.',
        description: 'Nosotros vamos, grabamos y dejamos las stats y los reels listos. Tú solo juegas.',
        features: [
            'Grabación con cámara profesional deportiva',
            'Video completo del partido subido a tu club',
            'Eventos del partido cargados (goles, asistencias, tarjetas, atajadas)',
            'Reels automáticos de cada gol (clip de 25 segundos)',
            'Stats de cada jugador actualizadas en su perfil público',
            'Link compartible del partido con todo el contenido',
        ],
        priceTiers: [
            { label: 'Canchas aliadas', value: '$30.000' },
            { label: 'Grabación agendada en otras canchas', value: 'Desde $60.000' },
        ],
        cta: 'Solicitar grabación',
        plan: 'partido_pro',
        highlight: true,
    },
];

const MONTHLY_QUOTE_WHATSAPP_URL = `https://wa.me/573008316105?text=${encodeURIComponent(
    'Hola! Me interesa cotizar un paquete mensual de grabación de partidos.',
)}`;
</script>

<template>
    <Head title="Grandes del Fútbol — La app para organizar partidos de fútbol con tus amigos">
        <meta head-key="description" name="description" content="App gratis para organizar partidos de fútbol con amigos. Confirma asistencia, arma equipos, lleva estadísticas de goles y asistencias, y comparte reels de tus mejores jugadas." />
        <meta head-key="og:title" property="og:title" content="Grandes del Fútbol — Organiza partidos de fútbol con tu grupo de amigos" />
        <meta head-key="og:description" property="og:description" content="Confirma asistencia, arma equipos equilibrados y lleva estadísticas de cada jugador. Gratis. Grabación profesional desde $30.000 por partido en canchas aliadas." />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:image" property="og:image" :content="`${props.appUrl}/pwa-512x512.png`" />
        <meta head-key="og:locale" property="og:locale" content="es_CO" />
        <meta head-key="twitter:card" name="twitter:card" content="summary" />
    </Head>

    <div class="min-h-screen bg-background text-foreground">
        <PublicHeader />

        <!-- Hero: dark emerald gradient with grid texture -->
        <section class="relative flex min-h-screen items-center justify-center overflow-hidden pt-16">
            <!-- Base: dark emerald → slate gradient (stadium at night) -->
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-950 via-slate-900 to-slate-950" />

            <!-- Grid texture (consistent with /clubes) -->
            <div
                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: repeating-linear-gradient(0deg, transparent 0, transparent 40px, white 40px, white 41px), repeating-linear-gradient(90deg, transparent 0, transparent 40px, white 40px, white 41px);"
            />

            <!-- Stadium light glows for depth -->
            <div class="pointer-events-none absolute -top-32 -left-20 size-[32rem] rounded-full bg-emerald-500/25 blur-3xl" />
            <div class="pointer-events-none absolute -bottom-32 -right-20 size-[32rem] rounded-full bg-emerald-600/20 blur-3xl" />
            <div class="pointer-events-none absolute top-1/4 right-1/3 size-64 rounded-full bg-emerald-400/10 blur-3xl" />

            <!-- Soft fade to page background at bottom -->
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-32 bg-gradient-to-b from-transparent to-background" />

            <div class="relative z-10 mx-auto max-w-4xl px-4 text-center">
                <h1 class="mb-6 text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">
                    Organizar partidos de fútbol
                    <span class="gradient-primary-text">no debería ser un caos</span>
                </h1>
                <p class="mx-auto mb-10 max-w-2xl text-lg text-gray-300 sm:text-xl">
                    Confirma asistencia, arma equipos y lleva estadísticas — sin perderte en 50 mensajes. Comparte reels de goles y las mejores jugadas en tu grupo.
                </p>
                <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <a
                        href="#como-funciona"
                        class="inline-flex items-center rounded-xl border border-white/20 px-8 py-3.5 text-lg font-semibold text-white transition-colors hover:bg-white/10"
                    >
                        Cómo funciona
                    </a>
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
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="mb-12 text-center sm:mb-16">
                    <h2 class="mb-3 text-3xl font-bold sm:text-4xl">Empieza en 3 pasos</h2>
                    <p class="mx-auto max-w-xl text-lg text-muted-foreground">
                        Tu club listo en menos de 5 minutos. Tu primer partido organizado el mismo día.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-3 md:gap-6">
                    <!-- Paso 1 -->
                    <div class="rounded-2xl border border-border bg-card p-6 transition-all hover:border-emerald-500/40 hover:shadow-lg">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <span class="text-xs font-bold tracking-wider text-emerald-500 uppercase">Paso 1</span>
                            <span class="text-xs text-muted-foreground">1 minuto</span>
                        </div>
                        <div class="mb-2 flex items-center gap-2">
                            <Shield class="size-5 shrink-0 text-emerald-500" />
                            <h3 class="text-xl font-semibold">Crea tu club</h3>
                        </div>
                        <p class="mb-4 text-sm text-muted-foreground">
                            Regístrate con tu email y ponle nombre a tu club. Quedas como admin desde el primer minuto.
                        </p>
                        <ul class="space-y-1.5 text-sm">
                            <li class="flex items-start gap-2">
                                <Check class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                <span>Sin tarjeta de crédito</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <Check class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                <span>Crea equipos y temporadas</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Paso 2 -->
                    <div class="rounded-2xl border border-border bg-card p-6 transition-all hover:border-emerald-500/40 hover:shadow-lg">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <span class="text-xs font-bold tracking-wider text-emerald-500 uppercase">Paso 2</span>
                            <span class="text-xs text-muted-foreground">2 minutos</span>
                        </div>
                        <div class="mb-2 flex items-center gap-2">
                            <Users class="size-5 shrink-0 text-emerald-500" />
                            <h3 class="text-xl font-semibold">Suma a tu grupo</h3>
                        </div>
                        <p class="mb-4 text-sm text-muted-foreground">
                            Cada jugador entra y crea su perfil con foto, posición y biografía. Listo para jugar.
                        </p>
                        <ul class="space-y-1.5 text-sm">
                            <li class="flex items-start gap-2">
                                <Check class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                <span>Invitación por email</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <Check class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                <span>O comparte el link público del club</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Paso 3 -->
                    <div class="rounded-2xl border border-border bg-card p-6 transition-all hover:border-emerald-500/40 hover:shadow-lg">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <span class="text-xs font-bold tracking-wider text-emerald-500 uppercase">Paso 3</span>
                            <span class="text-xs text-muted-foreground">El mismo día</span>
                        </div>
                        <div class="mb-2 flex items-center gap-2">
                            <CalendarCheck class="size-5 shrink-0 text-emerald-500" />
                            <h3 class="text-xl font-semibold">Tu primer partido</h3>
                        </div>
                        <p class="mb-4 text-sm text-muted-foreground">
                            Programa cancha y hora. Todos confirman con 1 tap y la app arma equipos balanceados automáticamente.
                        </p>
                        <ul class="space-y-1.5 text-sm">
                            <li class="flex items-start gap-2">
                                <Check class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                <span>Asistencia y lista de espera automática</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <Check class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                <span>Stats de cada jugador después del partido</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- CTA registro al final de la sección -->
                <div class="mt-10 flex flex-col items-center gap-3 text-center sm:mt-12">
                    <Link
                        v-if="!isLoggedIn"
                        href="/start"
                        class="gradient-primary-bg inline-flex items-center gap-2 rounded-xl px-7 py-3.5 text-base font-semibold text-white shadow-lg transition-opacity hover:opacity-90 sm:text-lg"
                    >
                        Crea tu club gratis
                        <ChevronRight class="size-5" />
                    </Link>
                    <Link
                        v-else
                        :href="dashboard()"
                        class="gradient-primary-bg inline-flex items-center gap-2 rounded-xl px-7 py-3.5 text-base font-semibold text-white shadow-lg transition-opacity hover:opacity-90 sm:text-lg"
                    >
                        Ir al panel
                        <ChevronRight class="size-5" />
                    </Link>
                    <p class="text-xs text-muted-foreground">Sin tarjeta de crédito · En menos de 5 minutos</p>
                </div>
            </div>
        </section>

        <!-- Precios -->
        <section id="precios" class="border-t border-border py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-16 text-center">
                    <h2 class="mb-4 text-3xl font-bold sm:text-4xl">
                        Tu equipo juega.
                        <span class="gradient-primary-text">Nosotros grabamos, cargamos las stats y creamos el contenido.</span>
                    </h2>
                    <p class="mx-auto max-w-2xl text-lg text-muted-foreground">
                        Grabamos tu partido con cámara profesional deportiva, subimos el video a tu club, cargamos las estadísticas de cada jugador y generamos reels automáticos de los goles. Listo para compartir.
                    </p>
                    <div class="mt-5 inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-medium text-emerald-300">
                        <Info class="size-4" />
                        Servicio sujeto a disponibilidad y cobertura
                    </div>
                </div>

                <div class="mx-auto grid max-w-4xl gap-6 sm:grid-cols-2">
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
                        <div>
                            <span class="text-2xl font-bold">{{ plan.price }}</span>
                            <span v-if="plan.period" class="ml-1 text-sm text-muted-foreground">{{ plan.period }}</span>
                        </div>
                        <p v-if="plan.priceNote" class="mt-1 text-xs text-muted-foreground">{{ plan.priceNote }}</p>
                        <p class="mt-3 mb-4 text-sm text-muted-foreground">{{ plan.description }}</p>

                        <div v-if="plan.featureGroups" class="mb-6 flex-1 space-y-4">
                            <div v-for="group in plan.featureGroups" :key="group.title">
                                <p class="mb-2 text-xs font-semibold tracking-wide text-emerald-500/80 uppercase">
                                    {{ group.title }}
                                </p>
                                <ul class="space-y-1.5">
                                    <li v-for="item in group.items" :key="item" class="flex items-start gap-2 text-sm">
                                        <Check class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <ul v-else-if="plan.features" class="mb-6 flex-1 space-y-2">
                            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm">
                                <Check class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                                {{ feature }}
                            </li>
                        </ul>

                        <div v-if="plan.priceTiers" class="mb-4 rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 text-xs">
                            <p v-for="tier in plan.priceTiers" :key="tier.label" class="flex items-baseline justify-between gap-3 py-0.5">
                                <span class="text-muted-foreground">{{ tier.label }}</span>
                                <span class="shrink-0 whitespace-nowrap font-semibold text-emerald-400">{{ tier.value }}</span>
                            </p>
                        </div>

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

                <!-- Equipos frecuentes (cotización mensual vía WhatsApp) -->
                <div class="mx-auto mt-12 max-w-3xl rounded-2xl border border-border bg-card/50 p-6 text-center sm:p-8">
                    <h3 class="mb-2 text-xl font-semibold sm:text-2xl">¿Juegan todas las semanas?</h3>
                    <p class="mx-auto mb-5 max-w-xl text-sm text-muted-foreground">
                        Agenda varias grabaciones al mes y arma un paquete a tu medida. Ideal para grupos, clubes o torneos que quieren mantener video, estadísticas y contenido durante toda la temporada.
                    </p>
                    <a
                        :href="MONTHLY_QUOTE_WHATSAPP_URL"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 rounded-lg border border-border px-5 py-2.5 text-sm font-semibold transition-colors hover:bg-accent"
                    >
                        Cotizar paquete mensual
                    </a>
                </div>
            </div>
        </section>

        <!-- Noticias -->
        <section v-if="recentNews.length > 0" id="noticias" class="border-t border-border py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-12 flex flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-left">
                    <div>
                        <h2 class="mb-2 text-3xl font-bold sm:text-4xl">Últimas noticias del fútbol</h2>
                        <p class="max-w-2xl text-muted-foreground">
                            Lo que se habla hoy, resumido y organizado. Acceso libre, sin registro.
                        </p>
                    </div>
                    <Link
                        href="/news"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors hover:bg-accent"
                    >
                        Ver todas
                        <ChevronRight class="size-4" />
                    </Link>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <NewsArticleCard
                        v-for="article in recentNews"
                        :key="article.ulid"
                        :article="article"
                    />
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
                    <div class="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm">
                        <span class="text-muted-foreground">Servicio:</span>
                        <span class="ml-2 font-semibold">Partido Pro — Desde $30.000</span>
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
