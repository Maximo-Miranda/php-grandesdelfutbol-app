<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

type Props = {
    club: { id: number; name: string; description: string | null };
    token: string;
};

const props = defineProps<Props>();

const form = useForm({});

function submit() {
    form.post(`/join/${props.token}`);
}
</script>

<template>
    <Head :title="`Join ${club.name}`" />

    <AppLayout :breadcrumbs="[]">
        <div class="flex min-h-[60vh] items-center justify-center p-4">
            <Card class="w-full max-w-md">
                <CardHeader>
                    <CardTitle>Join {{ club.name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p v-if="club.description" class="mb-4 text-muted-foreground">{{ club.description }}</p>
                    <Heading title="" description="You've been invited to join this club." />
                    <form class="mt-4" @submit.prevent="submit">
                        <Button type="submit" class="w-full" :disabled="form.processing">Join Club</Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
