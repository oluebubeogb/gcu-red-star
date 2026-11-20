<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
defineProps({ editions: Object });
const tone = { pending: 'bg-milk text-brown', processing: 'bg-pink-soft text-star-800', ready: 'bg-green-100 text-green-800', failed: 'bg-red-100 text-red-800' };
</script>
<template>
    <Head title="Editions · HQ" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Editorial HQ</p><h1 class="mt-2 font-display text-4xl font-extrabold">Editions</h1></div>
                <Link :href="route('hq.bulk')" class="rounded-full bg-white px-4 py-2 text-sm font-semibold ring-1 ring-brown-soft/40">Bulk upload</Link>
                <Link :href="route('hq.editions.create')" class="rounded-full bg-star px-4 py-2 text-sm font-semibold text-white">New edition</Link>
            </div>
        </template>
        <p v-if="!editions.data.length" class="rounded-2xl border border-dashed border-brown-soft/60 p-6 text-sm text-ink/60">No editions yet. Create one and upload its PDF.</p>
        <div class="space-y-3">
            <Link v-for="e in editions.data" :key="e.id" :href="route('hq.editions.edit', e.id)"
                  class="flex items-center gap-4 rounded-xl bg-white p-4 ring-1 ring-brown-soft/30 hover:ring-star">
                <div class="h-16 w-12 shrink-0 overflow-hidden rounded bg-star/10"><img v-if="e.cover_url" :src="e.cover_url" class="h-full w-full object-cover" /></div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ e.title }}</p>
                    <p class="text-xs text-ink/60">{{ e.year }}<span v-if="e.issue"> · Issue {{ e.issue }}</span> · {{ e.access }}<span v-if="e.page_count"> · {{ e.page_count }} pages</span><span v-if="!e.pdf_path"> · no PDF</span></p>
                </div>
                <span :class="tone[e.processing_status]" class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize">{{ e.pdf_path ? e.processing_status : 'awaiting PDF' }}</span>
                <span :class="e.status === 'published' ? 'bg-star text-white' : 'bg-gray-100 text-gray-600'" class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize">{{ e.status }}</span>
            </Link>
        </div>
        <div v-if="editions.links.length > 3" class="mt-6 flex flex-wrap gap-1">
            <Link v-for="l in editions.links" :key="l.label" :href="l.url || '#'" v-html="l.label"
                  :class="l.active ? 'bg-star text-white' : 'bg-white'" class="rounded-full px-3 py-1 text-sm ring-1 ring-brown-soft/30" />
        </div>
    </AuthenticatedLayout>
</template>
