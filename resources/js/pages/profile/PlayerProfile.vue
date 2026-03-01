<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem, PlayerProfile } from '@/types';

type Props = { profile: PlayerProfile };
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Player Profile', href: '/player-profile' },
];

const form = useForm({
    nickname: props.profile.nickname ?? '',
    gender: props.profile.gender ?? '',
    date_of_birth: props.profile.date_of_birth ?? '',
    nationality: props.profile.nationality ?? '',
    bio: props.profile.bio ?? '',
    preferred_position: props.profile.preferred_position ?? '',
});

function submit() {
    form.patch('/player-profile');
}
</script>

<template>
    <Head title="Player Profile" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <Heading variant="small" title="Player Profile" description="Your personal football profile" />
            <form class="mt-6 space-y-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="nickname">Nickname</Label>
                    <Input id="nickname" v-model="form.nickname" />
                    <InputError :message="form.errors.nickname" />
                </div>
                <div class="grid gap-2">
                    <Label for="nationality">Nationality</Label>
                    <Input id="nationality" v-model="form.nationality" />
                </div>
                <div class="grid gap-2">
                    <Label for="date_of_birth">Date of Birth</Label>
                    <Input id="date_of_birth" v-model="form.date_of_birth" type="date" />
                </div>
                <div class="grid gap-2">
                    <Label for="preferred_position">Preferred Position</Label>
                    <Input id="preferred_position" v-model="form.preferred_position" />
                </div>
                <div class="grid gap-2">
                    <Label for="bio">Bio</Label>
                    <Input id="bio" v-model="form.bio" />
                </div>
                <Button type="submit" :disabled="form.processing">Save Profile</Button>
            </form>
        </SettingsLayout>
    </AppLayout>
</template>
