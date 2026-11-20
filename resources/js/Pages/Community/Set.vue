<script setup>
import { Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import MemberChip from '@/Components/MemberChip.vue';
import TagList from '@/Components/TagList.vue';
import NewsList from '@/Components/NewsList.vue';
defineProps({ year: Number, members: Array, tags: Array, news: Array, isJubilee: Boolean, jubileeYear: Number });
</script>
<template>
    <SiteLayout :title="`Class of ${year}`">
        <section class="mx-auto max-w-5xl px-5 py-14">
            <Link :href="route('sets.index')" class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">← All sets</Link>
            <h1 class="mt-2 font-display text-4xl font-extrabold">Class of {{ year }}</h1>
            <p class="mt-2 text-sm text-ink/60">Admitted {{ year }} · Golden jubilee at Homecoming {{ jubileeYear }}</p>
            <Link v-if="isJubilee" :href="route('homecoming')" class="mt-4 inline-block rounded-full bg-star px-5 py-2 text-sm font-bold text-white">This set's jubilee is next — Homecoming {{ jubileeYear }} →</Link>
            <div class="mt-10 grid gap-10 md:grid-cols-[1.3fr_1fr]">
                <div>
                    <h2 class="font-display text-2xl font-bold">Members <span class="text-base font-normal text-ink/50">({{ members.length }})</span></h2>
                    <p v-if="!members.length" class="mt-3 text-sm text-ink/60">No public members yet. Admitted in {{ year }}? Add “Year of admission” in your profile.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2"><MemberChip v-for="m in members" :key="m.id" :member="m" /></div>
                    <h2 class="mt-10 font-display text-2xl font-bold">In the archive</h2>
                    <div class="mt-4"><TagList :tags="tags" /></div>
                </div>
                <div><h2 class="font-display text-2xl font-bold">Set news</h2><div class="mt-4"><NewsList :news="news" /></div></div>
            </div>
        </section>
    </SiteLayout>
</template>
