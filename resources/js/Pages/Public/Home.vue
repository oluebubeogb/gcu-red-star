<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import EditionCard from '@/Components/EditionCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StarMark from '@/Components/StarMark.vue';
defineProps({ latest: Array, stats: Object });
const user = usePage().props.auth.user;
const pillars = [
    { t: 'Comfort Reading', d: 'On your phone, every article flows in one clean column — no pinch-zooming, no sideways scrolling.' },
    { t: 'The original magazine', d: 'On desktop, every page exactly as it was printed: photographs, typography and adverts preserved.' },
    { t: 'A living archive', d: 'Decades of editions, searchable down to the page — for the class of today and the old boys of yesterday.' },
];
</script>
<template>
    <SiteLayout title="The Voice of Government College Umuahia">
        <section class="relative overflow-hidden bg-star text-white">
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-pink/25 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 left-10 h-80 w-80 rounded-full bg-brown/40 blur-3xl"></div>
            <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-5 py-16 md:grid-cols-[1.3fr_1fr] md:py-24">
                <div>
                    <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-pink">
                        <StarMark class="h-3.5 w-3.5" /> GCU · Founded 1929
                    </p>
                    <h1 class="mt-6 font-display text-5xl font-extrabold leading-[1.02] tracking-tight md:text-7xl">The Red Star</h1>
                    <p class="mt-4 font-display text-xl italic text-milk/90 md:text-2xl">The Voice of Government College Umuahia</p>
                    <p class="mt-6 max-w-xl font-serif text-lg text-milk/85">A digital home for every edition — read on any device, search the archive, and contribute to the next issue.</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link :href="route('editions.index')" class="rounded-full bg-milk px-6 py-3 text-sm font-bold text-star shadow hover:bg-white">Browse editions</Link>
                        <Link v-if="!user" :href="route('register')" class="rounded-full border border-white/40 px-6 py-3 text-sm font-bold text-white hover:bg-white/10">Join free</Link>
                        <Link v-else :href="route('contribute')" class="rounded-full border border-white/40 px-6 py-3 text-sm font-bold text-white hover:bg-white/10">Contribute</Link>
                    </div>
                </div>
                <div class="hidden justify-center md:flex">
                    <div class="relative">
                        <div class="absolute inset-0 -rotate-6 rounded-2xl bg-brown"></div>
                        <div class="absolute inset-0 rotate-3 rounded-2xl bg-pink"></div>
                        <div class="relative flex h-80 w-60 flex-col items-center justify-center rounded-2xl bg-milk p-6 text-ink shadow-2xl">
                            <img src="/gcu-badge.png" alt="GCU badge" class="h-28 w-28 object-contain" />
                            <p class="mt-5 font-display text-2xl font-bold">The Red <span class="text-star">Star</span></p>
                            <p class="mt-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-brown">Next edition · coming soon</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-5 py-16">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Latest</p>
                    <h2 class="mt-2 font-display text-3xl font-bold md:text-4xl">Recent editions</h2>
                </div>
                <Link :href="route('editions.index')" class="text-sm font-semibold text-star hover:underline">All editions →</Link>
            </div>
            <div v-if="latest.length" class="mt-8 grid grid-cols-2 gap-6 md:grid-cols-4">
                <EditionCard v-for="e in latest" :key="e.id" :edition="e" />
            </div>
            <EmptyState v-else class="mt-8" title="The first digital edition is on its way"
                body="The editorial team is preparing The Red Star's first online issue and digitising past editions. Join free to be notified when it lands.">
                <Link :href="route('register')" class="rounded-full bg-star px-5 py-2.5 text-sm font-semibold text-white">Join free</Link>
            </EmptyState>
        </section>

        <section class="bg-white">
            <div class="mx-auto grid max-w-6xl gap-6 px-5 py-16 md:grid-cols-3">
                <div v-for="(p, i) in pillars" :key="p.t" class="rounded-2xl bg-milk p-6 ring-1 ring-brown-soft/30">
                    <span class="font-display text-4xl font-extrabold" :class="['text-star','text-brown','text-pink'][i]">0{{ i + 1 }}</span>
                    <h3 class="mt-3 font-display text-xl font-bold">{{ p.t }}</h3>
                    <p class="mt-2 font-serif text-ink/75">{{ p.d }}</p>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-5 pt-16">
            <div class="grid items-center gap-8 rounded-3xl bg-brown px-8 py-12 text-milk md:grid-cols-[1.5fr_1fr]">
                <div>
                    <h2 class="font-display text-3xl font-bold">Have a story for the next issue?</h2>
                    <p class="mt-3 font-serif text-milk/80">Articles, poems, photographs, house news, letters from old boys — the editorial team reads everything.</p>
                </div>
                <div class="md:text-right">
                    <Link :href="route('contribute')" class="inline-block rounded-full bg-pink px-6 py-3 text-sm font-bold text-ink hover:bg-pink-soft">How to contribute</Link>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
