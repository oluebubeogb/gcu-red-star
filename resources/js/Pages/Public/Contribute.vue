<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
const user = usePage().props.auth.user;
const kinds = [
    ['Articles', 'Features, opinion, school life, sport and house news.'],
    ['Poems & stories', 'Creative writing from every class.'],
    ['Photographs', 'Events, people and moments around campus.'],
    ['Old boys’ letters', 'Memories, reunions and news from alumni.'],
];
</script>
<template>
    <SiteLayout title="Contribute">
        <section class="mx-auto max-w-5xl px-5 py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Contribute</p>
            <h1 class="mt-2 font-display text-4xl font-extrabold md:text-5xl">Write for The Red Star</h1>
            <p class="mt-3 max-w-2xl font-serif text-lg text-ink/70">Every edition is built from the voices of the GCU community. Submit your work — the editorial team reviews it for the next issue.</p>
            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <div v-for="k in kinds" :key="k[0]" class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                    <h3 class="font-display text-xl font-bold text-star">{{ k[0] }}</h3>
                    <p class="mt-1 font-serif text-ink/75">{{ k[1] }}</p>
                </div>
            </div>
            <div class="mt-10 rounded-3xl bg-pink-soft p-8">
                <h2 class="font-display text-2xl font-bold">How it works</h2>
                <ol class="mt-4 list-decimal space-y-2 pl-5 font-serif text-ink/80">
                    <li>Join free and verify your email.</li>
                    <li>Send your piece from <em>My submissions</em>.</li>
                    <li>An editor reviews it and you'll see its status update.</li>
                </ol>
                <Link :href="user ? route('submissions.index') : route('register')" class="mt-6 inline-block rounded-full bg-star px-6 py-3 text-sm font-bold text-white">
                    {{ user ? 'Submit a piece' : 'Join free to submit' }}
                </Link>
            </div>
        </section>
    </SiteLayout>
</template>
