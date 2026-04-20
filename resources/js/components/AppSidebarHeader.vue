<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Moon, Sun, User } from 'lucide-vue-next';
import { computed } from 'vue';
import ClubSwitcher from '@/components/ClubSwitcher.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAppearance } from '@/composables/useAppearance';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { appearance, updateAppearance } = useAppearance();

function toggleTheme() {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <header class="shrink-0 border-b border-border pt-[env(safe-area-inset-top,0px)]">
        <div class="flex h-14 items-center justify-between px-4">
            <div class="flex items-center gap-2">
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
        </div>
    </header>
</template>
