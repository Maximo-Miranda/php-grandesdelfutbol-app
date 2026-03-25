<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    Bell,
    BellOff,
    Check,
    Mail,
    Send,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useWebPush } from '@/composables/useWebPush';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type Props = {
    club: Club;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Notificaciones', href: `/clubs/${props.club.ulid}/notifications` },
];

const {
    isSupported,
    isSubscribed,
    isLoading,
    permission,
    needsInstall,
    error: pushError,
    subscribe,
    unsubscribe,
} = useWebPush();

const testSent = ref(false);

const permissionDeniedInstructions = computed(() => {
    if (typeof navigator === 'undefined') return '';
    const ua = navigator.userAgent;
    if (/Android/i.test(ua)) {
        return 'Abre los ajustes del navegador > Configuración del sitio > Notificaciones y permite este sitio.';
    }
    if (/iPhone|iPad|iPod/i.test(ua) || (ua.includes('Macintosh') && navigator.maxTouchPoints > 1)) {
        return 'Ve a Ajustes del iPhone > la app del navegador > Notificaciones y actívalas. Luego recarga esta página.';
    }
    if (/Chrome/i.test(ua)) {
        return 'Haz clic en el candado junto a la URL > Permisos del sitio > Notificaciones > Permitir.';
    }
    if (/Firefox/i.test(ua)) {
        return 'Haz clic en el candado junto a la URL > Permisos > Notificaciones > Permitir.';
    }
    return 'Abre los ajustes de tu navegador y permite las notificaciones para este sitio.';
});
</script>

<template>
    <Head title="Notificaciones" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 px-4 py-6">
            <Heading
                title="Notificaciones"
                :description="`Configura cómo recibir notificaciones de ${club.name}`"
            />

            <!-- Email section -->
            <div class="space-y-3 rounded-lg border border-border p-4">
                <div class="flex items-center gap-2">
                    <Mail class="size-5 shrink-0 text-primary" style="margin-top: 1px" />
                    <h2 class="text-base font-semibold">Correo electrónico</h2>
                    <Badge variant="default" class="ml-auto">
                        <Check class="mr-1 size-3" />
                        Siempre activo
                    </Badge>
                </div>

                <p class="text-sm text-muted-foreground">
                    Te enviamos por correo las convocatorias, videos y estadísticas de tu club. Si no ves nuestros correos, revisa tu carpeta de spam y marca los mensajes de <strong class="text-foreground">Grandes del Futbol</strong> como "no es spam" para recibirlos siempre en tu bandeja principal.
                </p>
            </div>

            <!-- Web Push section -->
            <div class="space-y-4 rounded-lg border border-border p-4">
                <div class="flex items-center gap-2">
                    <Bell class="size-5 shrink-0 text-primary" style="margin-top: 1px" />
                    <h2 class="text-base font-semibold">Notificaciones push</h2>
                    <Badge v-if="isSubscribed" variant="default" class="ml-auto">
                        <Check class="mr-1 size-3" />
                        Activa
                    </Badge>
                    <Badge v-else variant="secondary" class="ml-auto">
                        Opcional
                    </Badge>
                </div>

                <p class="text-sm text-muted-foreground">
                    Recibe alertas instantáneas en tu dispositivo además del correo. Ideal para no perderte convocatorias de último momento.
                </p>

                <!-- iOS not installed as PWA -->
                <div v-if="needsInstall" class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3">
                    <p class="text-sm font-medium text-amber-700 dark:text-amber-400">
                        Para recibir notificaciones push en iPhone, primero añade esta app a tu pantalla de inicio:
                    </p>
                    <ol class="mt-2 list-inside list-decimal space-y-1 text-sm text-amber-700 dark:text-amber-400">
                        <li>Toca el botón <strong>Compartir</strong> en Safari</li>
                        <li>Selecciona <strong>"Añadir a pantalla de inicio"</strong></li>
                        <li>Abre la app desde tu pantalla de inicio</li>
                    </ol>
                </div>

                <!-- Permission denied -->
                <div v-else-if="permission === 'denied'" class="rounded-md border border-destructive/30 bg-destructive/10 p-3">
                    <p class="text-sm font-medium text-destructive">
                        Las notificaciones están bloqueadas en tu navegador.
                    </p>
                    <p class="mt-1 text-sm text-destructive/80">
                        {{ permissionDeniedInstructions }}
                    </p>
                </div>

                <!-- Not supported -->
                <div v-else-if="!isSupported" class="rounded-md border border-border bg-muted/50 p-3">
                    <p class="text-sm text-muted-foreground">
                        Tu navegador no soporta notificaciones push. Prueba con Chrome, Firefox o Edge.
                    </p>
                </div>

                <!-- Active/Inactive toggle -->
                <div v-else>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="!isSubscribed"
                            :disabled="isLoading"
                            @click="subscribe"
                        >
                            <Bell class="size-4" />
                            Activar notificaciones push
                        </Button>
                        <template v-else>
                            <Button
                                variant="destructive"
                                :disabled="isLoading"
                                @click="unsubscribe"
                            >
                                <BellOff class="size-4" />
                                Desactivar
                            </Button>

                            <!-- Test push -->
                            <Form
                                :action="`/clubs/${club.ulid}/notifications/test`"
                                method="post"
                                @success="testSent = true"
                                #default="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                >
                                    <Send class="size-3.5" />
                                    Enviar prueba
                                </Button>
                            </Form>
                        </template>
                    </div>
                    <p v-if="pushError" class="mt-2 text-sm text-destructive">
                        {{ pushError }}
                    </p>
                    <p
                        v-if="testSent"
                        class="mt-2 text-xs text-green-600 dark:text-green-400"
                    >
                        Notificación enviada. Si no la ves, revisa los permisos de notificaciones de tu navegador.
                    </p>
                </div>
            </div>

            <!-- What you receive -->
            <div class="space-y-3 rounded-lg border border-border p-4">
                <h2 class="text-base font-semibold">Notificaciones que recibes</h2>
                <ul class="space-y-2 text-sm text-muted-foreground">
                    <li class="flex items-start gap-2">
                        <span class="mt-[7px] size-1.5 shrink-0 rounded-full bg-primary" />
                        <span><strong class="text-foreground">Convocatoria abierta</strong> — cuando se habilita la confirmación de un partido</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-[7px] size-1.5 shrink-0 rounded-full bg-primary" />
                        <span><strong class="text-foreground">Video disponible</strong> — cuando el video del partido está listo</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-[7px] size-1.5 shrink-0 rounded-full bg-primary" />
                        <span><strong class="text-foreground">Estadísticas registradas</strong> — cuando el admin registra las estadísticas del partido</span>
                    </li>
                </ul>
                <p class="text-xs text-muted-foreground">
                    Todas se envían por correo. Si tienes push activas, también las recibes como alerta instantánea.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
