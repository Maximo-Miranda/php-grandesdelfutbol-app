<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        canonicalUrl: string;
        ogImage?: string | null;
        ogType?: 'website' | 'article';
        ogLocale?: string;
        twitterCard?: 'summary' | 'summary_large_image';
        publishedAt?: string | null;
        publisher?: string | null;
        noindex?: boolean;
    }>(),
    {
        ogImage: null,
        ogType: 'website',
        ogLocale: 'es_CO',
        twitterCard: 'summary_large_image',
        publishedAt: null,
        publisher: null,
        noindex: false,
    },
);
</script>

<template>
    <Head :title="title">
        <meta head-key="description" name="description" :content="description" />
        <link head-key="canonical" rel="canonical" :href="canonicalUrl" />
        <meta v-if="noindex" head-key="robots" name="robots" content="noindex, follow" />
        <meta head-key="og:title" property="og:title" :content="title" />
        <meta head-key="og:description" property="og:description" :content="description" />
        <meta head-key="og:url" property="og:url" :content="canonicalUrl" />
        <meta head-key="og:type" property="og:type" :content="ogType" />
        <meta head-key="og:locale" property="og:locale" :content="ogLocale" />
        <meta v-if="ogImage" head-key="og:image" property="og:image" :content="ogImage" />
        <meta v-if="publishedAt" head-key="article:published_time" property="article:published_time" :content="publishedAt" />
        <meta v-if="publisher" head-key="article:publisher" property="article:publisher" :content="publisher" />
        <meta head-key="twitter:card" name="twitter:card" :content="twitterCard" />
        <meta head-key="twitter:title" name="twitter:title" :content="title" />
        <meta head-key="twitter:description" name="twitter:description" :content="description" />
        <meta v-if="ogImage" head-key="twitter:image" name="twitter:image" :content="ogImage" />
    </Head>
</template>
