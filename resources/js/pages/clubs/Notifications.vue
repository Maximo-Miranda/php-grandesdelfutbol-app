<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    Bell,
    BellOff,
    Check,
    Clipboard,
    Download,
    ExternalLink,
    Send,
    Smartphone,
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
    ntfyTopic: string;
    ntfyUrl: string;
    ntfyHost: string;
    vapidPublicKey: string;
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
    isIos,
    needsInstall,
    error: pushError,
    subscribe,
    unsubscribe,
} = useWebPush();

// Platform detection for ntfy
const isAndroid = computed(() => {
    if (typeof navigator === 'undefined') return false;
    return /Android/i.test(navigator.userAgent);
});

const isMobile = computed(() => isAndroid.value || isIos.value);

const ntfyAppUrl = computed(() => {
    if (isAndroid.value) return 'https://play.google.com/store/apps/details?id=io.heckel.ntfy';
    if (isIos.value) return 'https://apps.apple.com/app/ntfy/id1625396347';
    return 'https://ntfy.sh/app';
});

const ntfyAppLabel = computed(() => {
    if (isAndroid.value) return 'Google Play';
    if (isIos.value) return 'App Store';
    return 'ntfy Web App';
});

const testSent = ref(false);
const copiedTopic = ref(false);

function copyToClipboard(text: string): void {
    navigator.clipboard.writeText(text);
    copiedTopic.value = true;
    setTimeout(() => { copiedTopic.value = false; }, 2000);
}
</script>

<template>
    <Head title="Notificaciones" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-8 px-4 py-6">
            <Heading
                title="Notificaciones"
                :description="`Configura cómo recibir notificaciones de ${club.name}`"
            />

            <!-- Web Push section -->
            <div class="space-y-4 rounded-lg border border-border p-4">
                <div class="flex items-center gap-2">
                    <Bell class="size-5 text-primary" />
                    <h2 class="text-lg font-semibold">Notificaciones push</h2>
                    <Badge v-if="isSubscribed" variant="default" class="ml-auto">
                        <Check class="mr-1 size-3" />
                        Activa
                    </Badge>
                </div>

                <p class="text-sm text-muted-foreground">
                    Recibe notificaciones directamente en tu dispositivo cuando se abra una convocatoria, se suba un video o se publiquen estadísticas.
                </p>

                <!-- iOS not installed as PWA -->
                <div v-if="needsInstall" class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3">
                    <p class="text-sm font-medium text-amber-700 dark:text-amber-400">
                        Para recibir notificaciones en iPhone, primero añade esta app a tu pantalla de inicio:
                    </p>
                    <ol class="mt-2 list-inside list-decimal space-y-1 text-sm text-amber-700 dark:text-amber-400">
                        <li>Toca el botón <strong>Compartir</strong> en Safari</li>
                        <li>Selecciona <strong>"Añadir a pantalla de inicio"</strong></li>
                        <li>Abre la app desde tu pantalla de inicio</li>
                    </ol>
                </div>

                <!-- Permission denied -->
                <div v-else-if="permission === 'denied'" class="rounded-md border border-destructive/30 bg-destructive/10 p-3">
                    <p class="text-sm text-destructive">
                        Los permisos de notificaciones están bloqueados. Actívalos desde los ajustes de tu navegador.
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
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="!isSubscribed"
                            :disabled="isLoading"
                            @click="subscribe"
                        >
                            <Bell class="size-4" />
                            Activar notificaciones
                        </Button>
                        <Button
                            v-else
                            variant="destructive"
                            :disabled="isLoading"
                            @click="unsubscribe"
                        >
                            <BellOff class="size-4" />
                            Desactivar
                        </Button>
                    </div>
                    <p v-if="pushError" class="mt-2 text-sm text-destructive">
                        {{ pushError }}
                    </p>
                </div>
            </div>

            <!-- ntfy section (secondary) -->
            <div class="space-y-5 rounded-lg border border-border p-4">
                <div class="flex items-center gap-2">
                    <Smartphone class="size-5 text-muted-foreground" />
                    <h2 class="text-lg font-semibold">ntfy (alternativa)</h2>
                </div>

                <p class="text-sm text-muted-foreground">
                    Si prefieres usar una app externa, puedes recibir las mismas notificaciones a través de ntfy. Es gratis y funciona en cualquier dispositivo.
                </p>

                <!-- Step 1: Install -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                            1
                        </span>
                        <h3 class="text-sm font-medium">Instala la app de ntfy</h3>
                    </div>
                    <p class="pl-8 text-xs text-muted-foreground">
                        Descárgala gratis desde la tienda de tu dispositivo.
                    </p>
                    <div class="pl-8">
                        <a
                            :href="ntfyAppUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            <Download class="size-4" />
                            {{ ntfyAppLabel }}
                        </a>
                    </div>
                </div>

                <!-- Step 2: Subscribe -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                            2
                        </span>
                        <h3 class="text-sm font-medium">Suscríbete al canal del club</h3>
                    </div>
                    <div class="space-y-2 pl-8">
                        <p class="text-xs text-muted-foreground">
                            Abre ntfy, toca <strong>+</strong> para agregar un canal y pega este nombre:
                        </p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 break-all rounded-md border border-border bg-background px-3 py-2 font-mono text-sm">
                                {{ ntfyTopic }}
                            </code>
                            <Button
                                variant="outline"
                                size="sm"
                                class="shrink-0 gap-1.5"
                                @click="copyToClipboard(ntfyTopic)"
                            >
                                <Check v-if="copiedTopic" class="size-3.5" />
                                <Clipboard v-else class="size-3.5" />
                                {{ copiedTopic ? 'Copiado' : 'Copiar' }}
                            </Button>
                        </div>
                        <a
                            v-if="isAndroid"
                            :href="`ntfy://${ntfyHost}/${ntfyTopic}?display=${encodeURIComponent(club.name)}`"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                        >
                            <ExternalLink class="size-3.5" />
                            O toca aquí para abrirlo directo en ntfy
                        </a>
                    </div>
                </div>

                <!-- Step 3: Verify permissions -->
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                            3
                        </span>
                        <h3 class="text-sm font-medium">Verifica los permisos</h3>
                    </div>
                    <div class="space-y-2 pl-8">
                        <p class="text-xs text-muted-foreground">
                            Asegúrate de que ntfy pueda enviar notificaciones en tu dispositivo:
                        </p>
                        <ul class="space-y-1 text-xs text-muted-foreground">
                            <li v-if="isAndroid || !isMobile" class="flex items-start gap-1.5">
                                <span class="mt-0.5 font-bold text-foreground">Android:</span>
                                Ajustes &gt; Aplicaciones &gt; ntfy &gt; Notificaciones &gt; Activar
                            </li>
                            <li v-if="isIos || !isMobile" class="flex items-start gap-1.5">
                                <span class="mt-0.5 font-bold text-foreground">iPhone:</span>
                                Ajustes &gt; Notificaciones &gt; ntfy &gt; Permitir notificaciones
                            </li>
                            <li v-if="!isMobile" class="flex items-start gap-1.5">
                                <span class="mt-0.5 font-bold text-foreground">Mac/Windows:</span>
                                Activa las notificaciones del navegador en los ajustes del sistema
                            </li>
                        </ul>

                        <!-- Test button -->
                        <div class="pt-1">
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
                            <p
                                v-if="testSent"
                                class="mt-1.5 text-xs text-green-600 dark:text-green-400"
                            >
                                Notificación enviada. Si no la ves, revisa los permisos del paso anterior.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
