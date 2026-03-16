<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    BellOff,
    Check,
    Clipboard,
    Download,
    ExternalLink,
    Send,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useNtfySetup } from '@/composables/useNtfySetup';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import {
    confirm,
    disable,
    edit,
    test as sendTestRoute,
} from '@/routes/ntfy';
import type { BreadcrumbItem } from '@/types';

type Props = {
    ntfyTopic: string;
    ntfyEnabled: boolean;
    ntfyUrl: string;
    ntfyHost: string;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notificaciones push',
        href: edit(),
    },
];

const { appStoreUrl, platformLabel, isMobile, isIos } = useNtfySetup();

const subscribeUrl = computed(() =>
    isMobile.value
        ? `ntfy://${props.ntfyHost}/${props.ntfyTopic}`
        : `${props.ntfyUrl}/${props.ntfyTopic}`,
);

const testSent = ref(false);
const copiedTopic = ref(false);
const copiedUrl = ref(false);

function copyToClipboard(text: string, flag: 'topic' | 'url'): void {
    navigator.clipboard.writeText(text);
    if (flag === 'topic') {
        copiedTopic.value = true;
        setTimeout(() => { copiedTopic.value = false; }, 2000);
    } else {
        copiedUrl.value = true;
        setTimeout(() => { copiedUrl.value = false; }, 2000);
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Notificaciones push" />

        <h1 class="sr-only">Configuración de notificaciones push</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Notificaciones push"
                    description="Recibe notificaciones en tiempo real sobre partidos, invitaciones y actividad de tu club"
                />

                <!-- Enabled state -->
                <div
                    v-if="ntfyEnabled"
                    class="flex flex-col items-start space-y-4"
                >
                    <Badge variant="default">
                        <Check class="mr-1 size-3" />
                        Habilitada
                    </Badge>

                    <p class="text-muted-foreground">
                        Recibes notificaciones push a través de ntfy.
                    </p>

                    <div class="rounded-md border border-border bg-muted/50 px-3 py-2 text-sm">
                        <span class="text-muted-foreground">Canal:</span>
                        <code class="ml-1 font-mono">{{ ntfyTopic }}</code>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Form
                            v-bind="sendTestRoute.form()"
                            @success="testSent = true"
                            #default="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing"
                            >
                                <Send class="size-4" />
                                Enviar prueba
                            </Button>
                        </Form>

                        <Form
                            v-bind="disable.form()"
                            #default="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                            >
                                <BellOff class="size-4" />
                                Desactivar
                            </Button>
                        </Form>
                    </div>

                    <p
                        v-if="testSent"
                        class="text-sm text-green-600 dark:text-green-400"
                    >
                        Notificación de prueba enviada. Revisa tu app de ntfy.
                    </p>
                </div>

                <!-- Setup wizard -->
                <div v-else class="space-y-8">
                    <!-- Step 1: Download -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                1
                            </span>
                            <h3 class="font-medium">Descarga la app de ntfy</h3>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            ntfy es una app gratuita y de código abierto para recibir notificaciones push.
                        </p>

                        <a
                            :href="appStoreUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            <Download class="size-4" />
                            Descargar en {{ platformLabel }}
                        </a>

                        <div class="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2">
                            <p class="text-xs text-amber-700 dark:text-amber-400">
                                <strong>Importante:</strong> Al abrir ntfy por primera vez, acepta los permisos de notificaciones. Si los rechazaste, actívalos manualmente:
                            </p>
                            <ul class="mt-1 list-inside list-disc text-xs text-amber-700 dark:text-amber-400">
                                <li v-if="isMobile && !isIos"><strong>Android:</strong> Ajustes &gt; Aplicaciones &gt; ntfy &gt; Notificaciones</li>
                                <li v-else-if="isIos"><strong>iPhone:</strong> Ajustes &gt; Notificaciones &gt; ntfy &gt; Permitir notificaciones</li>
                                <li v-else><strong>Android:</strong> Ajustes &gt; Aplicaciones &gt; ntfy &gt; Notificaciones</li>
                                <li v-if="!isMobile"><strong>iPhone:</strong> Ajustes &gt; Notificaciones &gt; ntfy &gt; Permitir notificaciones</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Step 2: Subscribe -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                2
                            </span>
                            <h3 class="font-medium">Suscríbete a tu canal</h3>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            Toca el botón para abrir ntfy y suscribirte automáticamente a tu canal.
                        </p>

                        <a
                            :href="subscribeUrl"
                            :target="isMobile ? '_self' : '_blank'"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            <ExternalLink class="size-4" />
                            Suscribirse al canal
                        </a>

                        <div class="space-y-3 rounded-md border border-border bg-muted/50 p-3">
                            <p class="text-xs font-medium text-muted-foreground">
                                Si prefieres hacerlo manualmente:
                            </p>
                            <ol class="list-inside list-decimal space-y-1 text-xs text-muted-foreground">
                                <li>Abre la app de ntfy y toca <strong>+</strong> para agregar un canal</li>
                                <li>Activa la opción <strong>"Usar otro servidor"</strong></li>
                            </ol>

                            <div class="space-y-1">
                                <p class="text-xs text-muted-foreground">3. En <strong>URL del servidor</strong>, pega:</p>
                                <div class="flex items-center gap-2">
                                    <code class="flex-1 break-all rounded bg-background px-2 py-1 font-mono text-sm">
                                        {{ ntfyUrl }}
                                    </code>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="copyToClipboard(ntfyUrl, 'url')"
                                    >
                                        <Clipboard v-if="!copiedUrl" class="size-4" />
                                        <Check v-else class="size-4" />
                                    </Button>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <p class="text-xs text-muted-foreground">4. En <strong>Nombre del tema</strong>, pega:</p>
                                <div class="flex items-center gap-2">
                                    <code class="flex-1 break-all rounded bg-background px-2 py-1 font-mono text-sm">
                                        {{ ntfyTopic }}
                                    </code>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="copyToClipboard(ntfyTopic, 'topic')"
                                    >
                                        <Clipboard v-if="!copiedTopic" class="size-4" />
                                        <Check v-else class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Verify -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                3
                            </span>
                            <h3 class="font-medium">Verifica la configuración</h3>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            Envía una notificación de prueba para confirmar que todo funciona.
                        </p>

                        <div v-if="!testSent" class="flex flex-wrap gap-2">
                            <Form
                                v-bind="sendTestRoute.form()"
                                @success="testSent = true"
                                #default="{ processing }"
                            >
                                <Button type="submit" :disabled="processing">
                                    <Send class="size-4" />
                                    Enviar notificación de prueba
                                </Button>
                            </Form>
                        </div>

                        <div
                            v-else
                            class="space-y-3 rounded-md border border-border bg-muted/50 p-3"
                        >
                            <p class="text-sm">
                                ¿Recibiste la notificación en tu app de ntfy?
                            </p>

                            <div class="flex flex-wrap gap-2">
                                <Form
                                    v-bind="confirm.form()"
                                    #default="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                    >
                                        <Check class="size-4" />
                                        Sí, la recibí
                                    </Button>
                                </Form>

                                <Button
                                    variant="outline"
                                    @click="testSent = false"
                                >
                                    No la recibí, reintentar
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
