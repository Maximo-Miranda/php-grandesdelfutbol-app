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
import { ref } from 'vue';
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
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Notificaciones push',
        href: edit(),
    },
];

const { appStoreUrl, platformLabel } = useNtfySetup();

const testSent = ref(false);
const copied = ref(false);

function copyTopic(): void {
    navigator.clipboard.writeText(`${props.ntfyUrl}/${props.ntfyTopic}`);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
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
                            Abre la app de ntfy y suscríbete a tu canal personal.
                        </p>

                        <div class="flex flex-wrap gap-2">
                            <a
                                :href="`${ntfyUrl}/${ntfyTopic}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            >
                                <ExternalLink class="size-4" />
                                Suscribirse al canal
                            </a>
                        </div>

                        <div class="space-y-2 rounded-md border border-border bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">
                                Si prefieres hacerlo manualmente, copia esta dirección y pégala en ntfy:
                            </p>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 break-all rounded bg-background px-2 py-1 font-mono text-sm">
                                    {{ ntfyUrl }}/{{ ntfyTopic }}
                                </code>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="copyTopic"
                                >
                                    <Clipboard
                                        v-if="!copied"
                                        class="size-4"
                                    />
                                    <Check v-else class="size-4" />
                                </Button>
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
