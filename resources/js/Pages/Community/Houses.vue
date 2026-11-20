<script setup>
import { Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
defineProps({ houses: Array });
</script>
<template>
    <SiteLayout title="Houses">
        <section class="mx-auto max-w-5xl px-5 py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Community</p>
            <h1 class="mt-2 font-display text-4xl font-extrabold">The Houses of GCU</h1>
            <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3">
                <Link v-for="h in houses" :key="h.slug" :href="route('houses.show', h.slug)" class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30 hover:ring-star">
                    <h2 class="font-display text-xl font-bold">{{ h.name }}</h2><p class="mt-1 text-sm text-ink/60">{{ h.members }} members</p>
                </Link>
            </div>
        </section>
    </SiteLayout>
</template>
