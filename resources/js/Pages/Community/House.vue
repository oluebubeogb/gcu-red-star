<script setup>
import { Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import MemberChip from '@/Components/MemberChip.vue';
import TagList from '@/Components/TagList.vue';
import NewsList from '@/Components/NewsList.vue';
defineProps({ house: String, members: Array, tags: Array, news: Array });
</script>
<template>
    <SiteLayout :title="house">
        <section class="mx-auto max-w-5xl px-5 py-14">
            <Link :href="route('houses.index')" class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">← All houses</Link>
            <h1 class="mt-2 font-display text-4xl font-extrabold">{{ house }}</h1>
            <div class="mt-10 grid gap-10 md:grid-cols-[1.3fr_1fr]">
                <div>
                    <h2 class="font-display text-2xl font-bold">Members <span class="text-base font-normal text-ink/50">({{ members.length }})</span></h2>
                    <p v-if="!members.length" class="mt-3 text-sm text-ink/60">No public members yet. Set your house in your profile.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2"><MemberChip v-for="m in members" :key="m.id" :member="m" /></div>
                    <h2 class="mt-10 font-display text-2xl font-bold">In the archive</h2>
                    <div class="mt-4"><TagList :tags="tags" /></div>
                </div>
                <div><h2 class="font-display text-2xl font-bold">House news</h2><div class="mt-4"><NewsList :news="news" /></div></div>
            </div>
        </section>
    </SiteLayout>
</template>
