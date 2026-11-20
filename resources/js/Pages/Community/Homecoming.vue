<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import Countdown from '@/Components/Countdown.vue';
import MemberChip from '@/Components/MemberChip.vue';
import SponsorCredits from '@/Components/SponsorCredits.vue';
defineProps({ set: Number, year: Number, event: Object, members: Array, past: Array, sponsors: { type: Array, default: () => [] } });
const user = usePage().props.auth.user;
const fmt = (d) => new Date(d).toLocaleDateString('en-NG', { day: 'numeric', month: 'long', year: 'numeric' });
</script>
<template>
    <SiteLayout :title="`Homecoming ${year}`">
        <section class="bg-star text-white">
            <div class="mx-auto max-w-5xl px-5 py-16">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-pink">GCUOBA Homecoming {{ year }} · Golden Jubilee</p>
                <h1 class="mt-3 font-display text-5xl font-extrabold">Class of {{ set }}</h1>
                <p class="mt-3 max-w-2xl font-serif text-lg text-milk/85">Every year the Old Boys Association welcomes home the set admitted fifty years earlier. In {{ year }} it is the turn of the boys admitted in {{ set }}.</p>
                <template v-if="event">
                    <p class="mt-6 font-semibold">{{ event.title }}</p>
                    <p v-if="event.starts_on" class="text-sm text-milk/80">{{ fmt(event.starts_on) }}<span v-if="event.ends_on && event.ends_on !== event.starts_on"> – {{ fmt(event.ends_on) }}</span><span v-if="event.venue"> · {{ event.venue }}</span></p>
                    <div v-if="event.starts_on" class="mt-5"><Countdown :date="event.starts_on" /></div>
                    <p v-if="event.description" class="mt-5 max-w-2xl whitespace-pre-line font-serif text-milk/85">{{ event.description }}</p>
                </template>
                <p v-else class="mt-6 text-sm text-milk/80">Dates and venue will be announced by the GCUOBA.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link v-if="!user" :href="route('register')" class="rounded-full bg-milk px-6 py-3 text-sm font-bold text-star">Class of {{ set }}? Join free</Link>
                    <Link v-else :href="route('profile.edit')" class="rounded-full bg-milk px-6 py-3 text-sm font-bold text-star">Add your year of admission</Link>
                    <Link :href="route('sets.show', set)" class="rounded-full border border-white/40 px-6 py-3 text-sm font-bold">Class of {{ set }} page</Link>
                </div>
            </div>
        </section>
        <section class="mx-auto max-w-5xl px-5 py-14">
            <h2 class="font-display text-2xl font-bold">The jubilee set on The Red Star <span class="text-base font-normal text-ink/50">({{ members.length }})</span></h2>
            <p v-if="!members.length" class="mt-3 text-sm text-ink/60">No members of the Class of {{ set }} have joined yet — share this page with your classmates.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-3"><MemberChip v-for="m in members" :key="m.id" :member="m" /></div>

            <h2 class="mt-14 font-display text-2xl font-bold">Past homecomings</h2>
            <p v-if="!past.length" class="mt-3 text-sm text-ink/60">No past homecomings recorded yet.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <article v-for="e in past" :key="e.id" class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                    <img v-if="e.cover_url" :src="e.cover_url" :alt="e.title" loading="lazy" class="mb-4 aspect-video w-full rounded-lg object-cover" />
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Class of {{ e.set_year }}<span v-if="e.starts_on"> · {{ fmt(e.starts_on) }}</span></p>
                    <h3 class="mt-1 font-display text-lg font-bold">{{ e.title }}</h3>
                    <p v-if="e.venue" class="text-sm text-ink/60">{{ e.venue }}</p>
                    <p v-if="e.description" class="mt-2 font-serif text-sm text-ink/75">{{ e.description }}</p>
                </article>
            </div>
        </section>
        <section class="mx-auto max-w-5xl px-5 pb-10">
            <SponsorCredits :sponsors="sponsors" :title="`Homecoming ${year} sponsors`" />
            <Link :href="route('sponsor.index')" class="mt-3 inline-block text-sm font-semibold text-star">Sponsor the Homecoming →</Link>
        </section>
    </SiteLayout>
</template>
