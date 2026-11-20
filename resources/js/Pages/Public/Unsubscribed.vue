<script setup>
import { Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
</script>
<template>
    <SiteLayout title="Unsubscribed">
        <section class="mx-auto max-w-xl px-5 py-20 text-center">
            <h1 class="font-display text-3xl font-extrabold">You're unsubscribed</h1>
            <p class="mt-3 font-serif text-ink/75">You won't receive the monthly digest any more. You can turn it back on any time in your profile.</p>
            <Link :href="route('home')" class="mt-6 inline-block rounded-full bg-star px-5 py-2.5 text-sm font-bold text-white">Back to The Red Star</Link>
        </section>
    </SiteLayout>
</template>
