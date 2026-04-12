<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Copy, Link2, MessageCircle, Share2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    articleSlug: string;
    articleTitle: string;
    shareUrl: string;
}>();

const open = ref(false);
const justCopied = ref(false);

function trackShare(): void {
    router.post(
        `/news/${props.articleSlug}/share`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: [],
        },
    );
}

function shareToWhatsApp(): void {
    const text = `${props.articleTitle}\n${props.shareUrl}`;
    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank', 'noopener,noreferrer');
    trackShare();
    open.value = false;
}

async function copyLink(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.shareUrl);
        justCopied.value = true;
        setTimeout(() => {
            justCopied.value = false;
        }, 1500);
        trackShare();
    } catch {
        // Silent — user can still use the other share options.
    }
}

async function shareNative(): Promise<void> {
    if (!navigator.share) {
        return;
    }

    try {
        await navigator.share({
            title: props.articleTitle,
            url: props.shareUrl,
        });
        trackShare();
        open.value = false;
    } catch {
        // User cancelled, ignore.
    }
}

function toggleMenu(): void {
    if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
        shareNative();

        return;
    }

    open.value = !open.value;
}

function closeMenu(): void {
    open.value = false;
}
</script>

<template>
    <div class="relative inline-flex h-5 items-center" @focusout.capture="closeMenu">
        <button
            type="button"
            aria-label="Compartir noticia"
            class="inline-flex h-5 items-center text-foreground transition-colors hover:text-primary"
            @click="toggleMenu"
        >
            <Share2 class="size-5" />
        </button>

        <div
            v-if="open"
            class="absolute right-0 top-full z-20 mt-2 w-48 overflow-hidden rounded-xl border border-border bg-background shadow-lg"
        >
            <button
                type="button"
                class="flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm text-foreground transition-colors hover:bg-accent"
                @click="shareToWhatsApp"
            >
                <MessageCircle class="size-4 text-emerald-500" />
                WhatsApp
            </button>
            <button
                type="button"
                class="flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm text-foreground transition-colors hover:bg-accent"
                @click="copyLink"
            >
                <Check v-if="justCopied" class="size-4 text-emerald-500" />
                <Copy v-else class="size-4 text-muted-foreground" />
                {{ justCopied ? 'Copiado' : 'Copiar link' }}
            </button>
            <a
                :href="shareUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm text-foreground transition-colors hover:bg-accent"
                @click="trackShare"
            >
                <Link2 class="size-4 text-muted-foreground" />
                Abrir en nueva pestaña
            </a>
        </div>
    </div>
</template>
