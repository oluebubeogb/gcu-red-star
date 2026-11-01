<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
const page = usePage();
const tabs = [
    { label: 'Overview', route: 'dashboard' },
    { label: 'My submissions', route: 'submissions.index' },
    { label: 'Profile', route: 'profile.edit' },
];
</script>
<template>
    <SiteLayout>
        <div class="border-b border-brown-soft/30 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-4">
                <nav class="flex gap-1 overflow-x-auto">
                    <Link v-for="t in tabs" :key="t.route" :href="route(t.route)"
                          :class="route().current(t.route) ? 'bg-star text-white' : 'text-ink/70 hover:bg-milk'"
                          class="whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold">{{ t.label }}</Link>
                    <Link v-if="page.props.auth.user?.is_editor" :href="route('hq.index')"
                          :class="route().current('hq.*') ? 'bg-brown text-white' : 'text-brown hover:bg-milk'"
                          class="whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold">Editorial HQ</Link>
                </nav>
                <Link :href="route('logout')" method="post" as="button" class="text-sm font-semibold text-ink/60 hover:text-star">Sign out</Link>
            </div>
        </div>
        <div v-if="page.props.flash?.status" class="mx-auto mt-6 max-w-6xl px-5">
            <div class="rounded-xl bg-pink-soft px-4 py-3 text-sm font-semibold text-star-800">{{ page.props.flash.status }}</div>
        </div>
        <header v-if="$slots.header" class="mx-auto max-w-6xl px-5 pt-8"><slot name="header" /></header>
        <div class="mx-auto max-w-6xl px-5 py-8"><slot /></div>
    </SiteLayout>
</template>
