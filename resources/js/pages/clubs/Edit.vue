<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, ExternalLink, Globe, Share2, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClubPermissions } from '@/composables/useClubPermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club } from '@/types';

type Props = {
    club: Club;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubes', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Editar', href: `/clubs/${props.club.ulid}/edit` },
];

const form = useForm({
    name: props.club.name,
    description: props.club.description ?? '',
    is_public: props.club.is_public ?? true,
});

function submit() {
    form.put(`/clubs/${props.club.ulid}`);
}

const joinUrl = computed(() => `${window.location.origin}/join/${props.club.slug}`);
const publicUrl = computed(() => `${window.location.origin}/club/${props.club.slug}`);

const copied = ref(false);

async function copyLink() {
    await navigator.clipboard.writeText(joinUrl.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

const canShare = computed(() => typeof navigator.share === 'function');

async function shareLink() {
    try {
        await navigator.share({
            title: `Unirse a ${props.club.name}`,
            text: `Unite a ${props.club.name} en Grandes del Futbol!`,
            url: joinUrl.value,
        });
    } catch {
        // User cancelled share or not supported
    }
}

const { isOwner } = useClubPermissions();

const showDeleteDialog = ref(false);
const deletingClub = ref(false);

function deleteClub() {
    router.delete(`/clubs/${props.club.ulid}`, {
        onStart: () => (deletingClub.value = true),
        onFinish: () => (deletingClub.value = false),
    });
}
</script>

<template>
    <Head :title="`Editar ${club.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Editar club" :description="`Configuración de ${club.name}`" />

            <form class="mt-6 space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Nombre del club</Label>
                    <Input id="name" v-model="form.name" required placeholder="Nombre del club" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Descripcion</Label>
                    <Input id="description" v-model="form.description" placeholder="Descripcion opcional" />
                    <InputError :message="form.errors.description" />
                </div>

                <!-- Public visibility toggle -->
                <div class="rounded-lg border border-border p-4">
                    <div class="flex items-start gap-3">
                        <Checkbox id="is_public" v-model="form.is_public" class="mt-0.5" />
                        <div class="flex-1">
                            <Label for="is_public" class="flex cursor-pointer items-center gap-1.5 text-sm font-medium">
                                <Globe class="size-4 text-primary" />
                                Mostrar página pública del club y equipos
                            </Label>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Cualquier persona podrá ver la página del club y de sus equipos (próximos partidos, resultados y plantillas). No se expone información privada de miembros.
                            </p>
                            <div v-if="form.is_public" class="mt-3 flex items-center gap-2 rounded-md border border-border bg-muted/40 px-3 py-2 text-xs">
                                <Globe class="size-3.5 shrink-0 text-muted-foreground" />
                                <Link :href="`/club/${club.slug}`" target="_blank" class="truncate text-primary hover:underline">
                                    {{ publicUrl }}
                                </Link>
                                <ExternalLink class="size-3 shrink-0 text-muted-foreground" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">Guardar cambios</Button>
                </div>
            </form>

            <!-- Join link section -->
            <div class="mt-8 rounded-lg border border-border p-4">
                <h3 class="mb-2 font-medium">Link de ingreso al club</h3>
                <p class="mb-3 text-sm text-muted-foreground">Comparte este enlace para que otros jugadores soliciten unirse al club. Deberás aprobar cada solicitud.</p>
                <div class="flex items-center gap-2">
                    <Input :model-value="joinUrl" readonly class="bg-muted/50 text-sm" />
                    <Button variant="outline" size="icon" @click="copyLink">
                        <Check v-if="copied" class="size-4 text-green-500" />
                        <Copy v-else class="size-4" />
                    </Button>
                    <Button v-if="canShare" variant="outline" size="icon" @click="shareLink">
                        <Share2 class="size-4" />
                    </Button>
                </div>
                <p v-if="copied" class="mt-1.5 text-xs text-green-600">Copiado!</p>
            </div>

            <!-- Danger zone -->
            <div v-if="isOwner" class="mt-8 rounded-lg border border-destructive/50 p-4">
                <h3 class="mb-2 font-medium text-destructive">Zona de peligro</h3>
                <p class="mb-3 text-sm text-muted-foreground">
                    Eliminar el club borrará permanentemente todos los datos asociados: jugadores, partidos, estadísticas, sedes y miembros. Esta acción no se
                    puede deshacer.
                </p>
                <Button variant="destructive" @click="showDeleteDialog = true">
                    <Trash2 class="mr-2 size-4" />
                    Eliminar club
                </Button>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="showDeleteDialog"
            title="Eliminar club"
            description="Esta acción no se puede deshacer. Se eliminarán permanentemente todos los datos del club, incluyendo jugadores, partidos, estadísticas y miembros."
            confirm-label="Eliminar club"
            :destructive="true"
            :processing="deletingClub"
            @confirm="deleteClub"
        />
    </AppLayout>
</template>
