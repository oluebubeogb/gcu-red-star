<script setup>
import SiteLayout from '@/Layouts/SiteLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
defineProps({ decades: Object });
</script>
<template>
    <SiteLayout title="Archive">
        <section class="mx-auto max-w-6xl px-5 py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Digital archive</p>
            <h1 class="mt-2 font-display text-4xl font-extrabold md:text-5xl">The Red Star, through the decades</h1>
            <p class="mt-3 max-w-2xl font-serif text-lg text-ink/70">A growing, searchable collection of historical editions — preserved exactly as published and indexed page by page.</p>
            <div class="mt-8 flex max-w-xl items-center gap-2 rounded-full bg-white px-4 py-2 ring-1 ring-brown-soft/40">
                <svg class="h-5 w-5 text-brown" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
                <input disabled placeholder="Search the archive — arriving with the first digitised editions" class="w-full border-0 bg-transparent text-sm focus:ring-0" />
            </div>
            <div v-if="Object.keys(decades).length" class="mt-10 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div v-for="(count, decade) in decades" :key="decade" class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                    <p class="font-display text-3xl font-bold text-star">{{ decade }}s</p>
                    <p class="mt-1 text-sm text-ink/70">{{ count }} edition{{ count === 1 ? '' : 's' }}</p>
                </div>
            </div>
            <EmptyState v-else class="mt-10" title="The archive is being digitised"
                body="Have an old copy of The Red Star? The editorial team would love to scan it. Get in touch through Contribute." />
        </section>
    </SiteLayout>
</template>
