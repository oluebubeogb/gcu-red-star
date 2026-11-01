<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
defineProps({ edition: Object });
const user = usePage().props.auth.user;
</script>
<template>
    <SiteLayout :title="edition.title">
        <section class="mx-auto grid max-w-5xl gap-10 px-5 py-14 md:grid-cols-[280px_1fr]">
            <div class="aspect-[3/4] overflow-hidden rounded-xl bg-star shadow-xl">
                <img v-if="edition.cover_url" :src="edition.cover_url" :alt="edition.title" class="h-full w-full object-cover" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">{{ edition.year }} <span v-if="edition.volume">· Vol. {{ edition.volume }}</span> <span v-if="edition.issue">· Issue {{ edition.issue }}</span></p>
                <h1 class="mt-2 font-display text-4xl font-extrabold">{{ edition.title }}</h1>
                <p v-if="edition.summary" class="mt-4 font-serif text-lg text-ink/75">{{ edition.summary }}</p>
                <div class="mt-8 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                    <p class="font-semibold">The reader is coming in Phase 2</p>
                    <p class="mt-1 text-sm text-ink/70">Comfort Reading on mobile and the original-page viewer on desktop will open from here.</p>
                    <Link v-if="!user" :href="route('register')" class="mt-4 inline-block rounded-full bg-star px-5 py-2.5 text-sm font-semibold text-white">Join free to read</Link>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
