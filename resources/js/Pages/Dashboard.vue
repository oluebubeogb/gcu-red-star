<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const user = usePage().props.auth.user;
const labels = { student: 'Student', old_boy: 'Old Boy', teacher: 'Teacher', editorial: 'Editorial', admin: 'Admin' };
</script>
<template>
    <Head title="My Red Star" />
    <AuthenticatedLayout>
        <template #header>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">{{ labels[user.category] || 'Member' }}<span v-if="user.graduation_year"> · Class of {{ user.graduation_year }}</span><span v-if="user.current_class"> · {{ user.current_class }}</span></p>
            <h1 class="mt-2 font-display text-4xl font-extrabold">Welcome, {{ user.name.split(' ')[0] }}</h1>
        </template>
        <div class="grid gap-5 md:grid-cols-3">
            <Link :href="route('editions.index')" class="rounded-2xl bg-star p-6 text-white">
                <h3 class="font-display text-xl font-bold">Read editions</h3><p class="mt-1 text-sm text-milk/80">Browse every published issue.</p>
            </Link>
            <Link :href="route('submissions.index')" class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                <h3 class="font-display text-xl font-bold">Contribute</h3><p class="mt-1 text-sm text-ink/70">Send an article, poem or photo.</p>
            </Link>
            <div class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                <h3 class="font-display text-xl font-bold">Bookmarks</h3><p class="mt-1 text-sm text-ink/70">Saved articles arrive with the reader (Phase 2).</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
