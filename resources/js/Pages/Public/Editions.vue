<script setup>
import { Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import EditionCard from '@/Components/EditionCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
defineProps({ editions: Object });
</script>
<template>
    <SiteLayout title="Editions">
        <section class="mx-auto max-w-6xl px-5 py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">The Red Star</p>
            <h1 class="mt-2 font-display text-4xl font-extrabold md:text-5xl">Editions</h1>
            <p class="mt-3 max-w-2xl font-serif text-lg text-ink/70">Every issue, readable on your phone in Comfort Reading or as the original printed magazine.</p>
            <div v-if="editions.data.length" class="mt-10 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                <EditionCard v-for="e in editions.data" :key="e.id" :edition="e" />
            </div>
            <EmptyState v-else class="mt-10" title="No editions published yet"
                body="Editions will appear here as soon as the editorial team publishes them. Members get notified first." />
            <div v-if="editions.last_page > 1" class="mt-10 flex justify-center gap-2">
                <template v-for="l in editions.links" :key="l.label">
                    <Link v-if="l.url" :href="l.url" v-html="l.label" :class="l.active ? 'bg-star text-white' : 'bg-white'" class="rounded-full px-3 py-1.5 text-sm ring-1 ring-brown-soft/40" />
                </template>
            </div>
        </section>
    </SiteLayout>
</template>
