<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Club, Player } from '@/types';

type PositionOption = { value: string; label: string };
type AvailableUser = { id: number; name: string; email: string; has_player: boolean };
type Props = { club: Club; player: Player; positions: PositionOption[]; isAdmin: boolean; availableUsers: AvailableUser[] };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clubs', href: '/clubs' },
    { title: props.club.name, href: `/clubs/${props.club.ulid}` },
    { title: 'Jugadores', href: `/clubs/${props.club.ulid}/players` },
    { title: 'Editar', href: `/clubs/${props.club.ulid}/players/${props.player.ulid}/edit` },
];

const form = useForm({
    name: props.player.name,
    position: props.player.position ?? 'none',
    jersey_number: props.player.jersey_number ?? '',
    is_active: props.player.is_active,
    user_id: props.player.user_id as number | null,
});

// User search dropdown
const userSearch = ref('');
const showUserDropdown = ref(false);

const selectedUser = computed(() => {
    if (!form.user_id) return null;
    return props.availableUsers.find(u => u.id === form.user_id) ?? null;
});

const selectedUserName = computed(() => selectedUser.value?.name ?? props.player.user?.name ?? '');

const selectedUserHasPlayer = computed(() => selectedUser.value?.has_player ?? false);

const filteredUsers = computed(() => {
    const q = userSearch.value.toLowerCase().trim();
    if (!q) return props.availableUsers;
    return props.availableUsers.filter(u =>
        u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q),
    );
});

function selectUser(user: AvailableUser) {
    form.user_id = user.id;
    userSearch.value = '';
    showUserDropdown.value = false;
}

function clearUser() {
    form.user_id = null;
    userSearch.value = '';
}

// Delete
const confirmingDelete = ref(false);

function deletePlayer() {
    router.delete(`/clubs/${props.club.ulid}/players/${props.player.ulid}`);
}

function submit() {
    form.transform((data) => {
        const transformed: Record<string, unknown> = {
            name: data.name,
            position: data.position === 'none' ? null : data.position,
            jersey_number: data.jersey_number === '' ? null : Number(data.jersey_number),
        };
        if (props.isAdmin) {
            transformed.is_active = !!data.is_active;
            transformed.user_id = data.user_id;
        }

        return transformed;
    }).put(`/clubs/${props.club.ulid}/players/${props.player.ulid}`);
}
</script>

<template>
    <Head :title="`Editar ${player.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl p-4">
            <Heading title="Editar jugador" />
            <form class="mt-6 space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Nombre</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="position">Posicion</Label>
                    <Select v-model="form.position">
                        <SelectTrigger id="position">
                            <SelectValue placeholder="Seleccionar posicion" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Sin posicion</SelectItem>
                            <SelectItem v-for="pos in positions" :key="pos.value" :value="pos.value">
                                {{ pos.label }} ({{ pos.value }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.position" />
                </div>
                <div class="grid gap-2">
                    <Label for="jersey_number">Numero de camiseta</Label>
                    <Input id="jersey_number" v-model="form.jersey_number" type="number" min="1" max="99" />
                    <InputError :message="form.errors.jersey_number" />
                </div>

                <!-- User association (admin only) -->
                <div v-if="isAdmin" class="grid gap-2">
                    <Label>Usuario asociado</Label>
                    <div v-if="form.user_id" class="flex items-center gap-2 rounded-md border border-border bg-muted/50 px-3 py-2">
                        <span class="flex-1 text-sm">{{ selectedUserName }}</span>
                        <button type="button" class="text-muted-foreground hover:text-foreground" @click="clearUser">
                            <X class="size-4" />
                        </button>
                    </div>
                    <div v-else class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="userSearch"
                            placeholder="Buscar usuario por nombre o email..."
                            class="pl-9"
                            @focus="showUserDropdown = true"
                            @blur="setTimeout(() => showUserDropdown = false, 200)"
                        />
                        <div
                            v-if="showUserDropdown && filteredUsers.length > 0"
                            class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border border-border bg-popover shadow-md"
                        >
                            <button
                                v-for="user in filteredUsers"
                                :key="user.id"
                                type="button"
                                class="flex w-full flex-col px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                                @mousedown.prevent="selectUser(user)"
                            >
                                <span class="font-medium">{{ user.name }}</span>
                                <span class="text-xs text-muted-foreground">{{ user.email }}</span>
                                <span v-if="user.has_player" class="text-xs text-amber-500">(ya tiene jugador)</span>
                            </button>
                        </div>
                        <div
                            v-if="showUserDropdown && userSearch && filteredUsers.length === 0"
                            class="absolute z-10 mt-1 w-full rounded-md border border-border bg-popover p-3 text-center text-sm text-muted-foreground shadow-md"
                        >
                            No se encontraron usuarios
                        </div>
                    </div>
                    <div
                        v-if="form.user_id && selectedUserHasPlayer"
                        class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3"
                    >
                        <p class="text-sm text-amber-400">
                            Este usuario ya tiene un jugador en el club. Al guardar, las estadisticas de
                            <strong>{{ player.display_name }}</strong> se fusionaran con el jugador existente
                            y este registro sera eliminado.
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Asocia este jugador a un usuario registrado del club.
                    </p>
                    <InputError :message="form.errors.user_id" />
                </div>

                <div v-if="isAdmin" class="flex items-center gap-2">
                    <Checkbox id="is_active" v-model="form.is_active" />
                    <Label for="is_active">Activo</Label>
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">Guardar cambios</Button>
                    <Button
                        v-if="isAdmin"
                        type="button"
                        variant="destructive"
                        @click="confirmingDelete = !confirmingDelete"
                    >
                        Eliminar
                    </Button>
                </div>
            </form>

            <!-- Delete confirmation -->
            <div v-if="confirmingDelete" class="mt-4 rounded-lg border border-red-500/30 bg-red-500/5 p-4">
                <p class="mb-3 text-sm">
                    Se eliminara a <strong>{{ player.display_name }}</strong> y todas sus estadisticas. Esta accion no se puede deshacer.
                </p>
                <div class="flex gap-2">
                    <Button variant="destructive" size="sm" @click="deletePlayer">
                        Confirmar eliminacion
                    </Button>
                    <Button variant="outline" size="sm" @click="confirmingDelete = false">
                        Cancelar
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
