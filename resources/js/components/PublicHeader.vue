<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Compass, Newspaper } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';

const page = usePage();
const isLoggedIn = computed(() => !!page.props.auth?.user);
</script>

<template>
    <header class="fixed top-0 right-0 left-0 z-50 border-b border-white/10 bg-slate-950/80 backdrop-blur-md">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
            <Link href="/" class="flex items-center gap-2">
                <AppLogo />
            </Link>

            <nav class="flex items-center gap-1 sm:gap-2">
                <Link
                    href="/explorar"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white/70 transition-colors hover:bg-white/10 hover:text-white sm:px-4"
                >
                    <Compass class="size-4" />
                    <span class="hidden sm:inline">Explorar</span>
                </Link>
                <Link
                    href="/news"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white/70 transition-colors hover:bg-white/10 hover:text-white sm:px-4"
                >
                    <Newspaper class="size-4" />
                    <span class="hidden sm:inline">Noticias</span>
                </Link>
                <Link
                    v-if="!isLoggedIn"
                    href="/start"
                    class="gradient-primary-bg ml-1 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-lg transition-opacity hover:opacity-90"
                >
                    Empieza gratis
                </Link>
                <Link
                    v-else
                    href="/"
                    class="gradient-primary-bg ml-1 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-lg transition-opacity hover:opacity-90"
                >
                    Ir al panel
                </Link>
            </nav>
        </div>
    </header>
</template>
