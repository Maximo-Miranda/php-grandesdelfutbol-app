<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Plus, Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Club } from '@/types';

const page = usePage<{ userClubs: Pick<Club, 'id' | 'ulid' | 'name'>[]; currentClub: Club | null }>();
const clubs = computed(() => page.props.userClubs ?? []);
const currentClub = computed(() => page.props.currentClub);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium hover:bg-accent">
                <Shield class="size-4 text-primary" />
                <span class="truncate">{{ currentClub?.name ?? 'Select Club' }}</span>
                <ChevronDown class="size-3.5 text-muted-foreground" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-56" align="start">
            <DropdownMenuLabel>Switch Club</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem v-for="club in clubs" :key="club.id" as-child>
                <Link :href="`/clubs/${club.ulid}`" class="w-full cursor-pointer">
                    {{ club.name }}
                </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator v-if="clubs.length" />
            <DropdownMenuItem as-child>
                <Link href="/clubs/create" class="w-full cursor-pointer">
                    <Plus class="mr-2 size-4" />
                    Create Club
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
