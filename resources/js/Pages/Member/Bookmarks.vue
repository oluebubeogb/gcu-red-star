<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
defineProps({ bookmarks: Array });
const remove = (b) => router.delete(route('bookmarks.destroy', b.id), { preserveScroll: true });
</script>
<template>
    <Head title="Bookmarks" />
    <AuthenticatedLayout>
        <template #header><h1 class="font-display text-4xl font-extrabold">Bookmarks</h1></template>
        <p v-if="!bookmarks.length" class="rounded-2xl border border-dashed border-brown-soft/60 p-6 text-sm text-ink/60">No bookmarks yet. Use “Bookmark page” while reading an edition.</p>
        <ul class="space-y-3">
            <li v-for="b in bookmarks" :key="b.id" class="flex items-center justify-between gap-3 rounded-xl bg-white p-4 ring-1 ring-brown-soft/30">
                <Link :href="route('editions.show', { edition: b.edition.slug, page: b.page_number })" class="min-w-0">
                    <p class="truncate font-semibold hover:text-star">{{ b.edition.title }}</p>
                    <p class="text-xs text-ink/60">Page {{ b.page_number }}<span v-if="b.edition.year"> · {{ b.edition.year }}</span><span v-if="b.note"> · {{ b.note }}</span></p>
                </Link>
                <button @click="remove(b)" class="shrink-0 text-sm font-semibold text-ink/60 hover:text-star">Remove</button>
            </li>
        </ul>
    </AuthenticatedLayout>
</template>
