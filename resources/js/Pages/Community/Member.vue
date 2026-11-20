<script setup>
import { Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import TagList from '@/Components/TagList.vue';
defineProps({ member: Object, contributions: Object, tags: Array, isMe: Boolean });
const label = { student: 'Student', old_boy: 'Old Boy', teacher: 'Teacher / Staff', friend: 'Friend of GCU', editorial: 'Editorial team', admin: 'Administrator' };
</script>
<template>
    <SiteLayout :title="member.name">
        <section class="mx-auto max-w-4xl px-5 py-14">
            <div class="flex flex-wrap items-center gap-5">
                <span class="flex h-20 w-20 items-center justify-center rounded-full bg-star font-display text-3xl font-bold text-white">{{ member.name.charAt(0) }}</span>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">{{ label[member.category] || 'Member' }}</p>
                    <h1 class="font-display text-4xl font-extrabold">{{ member.name }}</h1>
                    <p class="mt-1 text-sm text-ink/60">@{{ member.username }}
                        <template v-if="member.admission_year"> · <Link :href="route('sets.show', member.admission_year)" class="hover:text-star">Class of {{ member.admission_year }}</Link></template>
                        <template v-if="member.house"> · <Link :href="route('houses.show', member.house_slug)" class="hover:text-star">{{ member.house }}</Link></template>
                    </p>
                </div>
            </div>
            <p v-if="isMe && !member.public" class="mt-6 rounded-xl bg-pink-soft p-3 text-sm">Your profile is private — only you and the editors can see this page. <Link :href="route('profile.edit')" class="font-semibold underline">Change</Link></p>
            <div class="mt-8 flex flex-wrap items-center gap-2">
                <span v-for="b in contributions.badges" :key="b" class="rounded-full bg-star px-3 py-1 text-xs font-bold text-white">★ {{ b }}</span>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold ring-1 ring-brown-soft/40">{{ contributions.accepted }} accepted contribution{{ contributions.accepted === 1 ? '' : 's' }}</span>
            </div>
            <h2 class="mt-10 font-display text-2xl font-bold">Tagged in the archive</h2>
            <div class="mt-4"><TagList :tags="tags" empty="Not tagged in any edition yet." /></div>
        </section>
    </SiteLayout>
</template>
