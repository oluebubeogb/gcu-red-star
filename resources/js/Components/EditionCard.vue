<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ edition: Object });
</script>
<template>
    <Link :href="route('editions.show', edition.slug)" class="group block">
        <div class="aspect-[3/4] overflow-hidden rounded-lg bg-star/10 shadow-[0_12px_30px_-18px_rgba(26,18,16,0.6)] ring-1 ring-brown-soft/30 transition group-hover:-translate-y-1">
            <img v-if="edition.cover_url" :src="edition.cover_url" :alt="edition.title" class="h-full w-full object-cover" loading="lazy" />
            <div v-else class="flex h-full flex-col justify-between bg-star p-4 text-white">
                <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-pink">The Red Star</span>
                <span class="font-display text-2xl font-bold leading-tight">{{ edition.title }}</span>
            </div>
        </div>
        <div class="mt-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-brown">{{ edition.year }}<span v-if="edition.issue"> · Issue {{ edition.issue }}</span></p>
            <h3 class="mt-1 font-display text-lg font-bold group-hover:text-star">{{ edition.title }}</h3>
        </div>
    </Link>
</template>
