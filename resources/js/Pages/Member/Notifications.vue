<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
defineProps({ notifications: Array });
const readAll = () => router.post(route('notifications.read'), {}, { preserveScroll: true });
</script>
<template>
    <Head title="Notifications" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-end justify-between gap-3">
                <h1 class="font-display text-4xl font-extrabold">Notifications</h1>
                <button v-if="notifications.some((n) => !n.read)" @click="readAll" class="text-sm font-semibold text-star">Mark all read</button>
            </div>
        </template>
        <p v-if="!notifications.length" class="rounded-2xl border border-dashed border-brown-soft/60 p-6 text-sm text-ink/60">Nothing yet. You'll be notified here when someone tags you in an edition.</p>
        <ul class="space-y-2">
            <li v-for="n in notifications" :key="n.id" :class="n.read ? 'bg-white' : 'bg-pink-soft'" class="rounded-xl p-4 ring-1 ring-brown-soft/30">
                <a :href="n.data.url" class="font-semibold hover:text-star">{{ n.data.message }}</a>
                <p class="text-xs text-ink/50">{{ new Date(n.created_at).toLocaleString() }}</p>
            </li>
        </ul>
    </AuthenticatedLayout>
</template>
