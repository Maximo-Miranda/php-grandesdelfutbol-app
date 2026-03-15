<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Moon, Sun, User } from 'lucide-vue-next';
import { computed } from 'vue';
import ClubSwitcher from '@/components/ClubSwitcher.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAppearance } from '@/composables/useAppearance';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const { appearance, updateAppearance } = useAppearance();

const showBack = computed(() => props.breadcrumbs.length >= 2);
const backFallback = computed(() => {
    if (props.breadcrumbs.length >= 2) {
        return props.breadcrumbs[props.breadcrumbs.length - 2].href;
    }
    return undefined;
});

function goBack() {
    if (backFallback.value) {
        router.visit(backFallback.value);
    }
}

function toggleTheme() {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <header class="flex h-14 shrink-0 items-center justify-between border-b border-border px-4">
        <div class="flex items-center gap-2">
            <button
                v-if="showBack"
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground lg:hidden"
                @click="goBack"
            >
                <ArrowLeft class="size-5" />
            </button>
            <ClubSwitcher />
        </div>
        <div class="flex items-center gap-1">
            <button
                class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                @click="toggleTheme"
            >
                <Sun v-if="appearance === 'dark'" class="size-5" />
                <Moon v-else class="size-5" />
            </button>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground">
                        <User class="size-5" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-56" align="end" :side-offset="8">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
