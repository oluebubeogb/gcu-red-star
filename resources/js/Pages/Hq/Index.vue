<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
defineProps({ counts: Object, byCategory: Object, submissions: Array });
const user = usePage().props.auth.user;
const setStatus = (s, status) => router.patch(route('hq.submissions.update', s.id), { status }, { preserveScroll: true });
</script>
<template>
    <Head title="Editorial HQ" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Editorial HQ</p><h1 class="mt-2 font-display text-4xl font-extrabold">Newsroom</h1></div>
                <Link v-if="user.is_admin" :href="route('hq.members')" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk">Manage members</Link>
            </div>
        </template>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div v-for="(v, k) in counts" :key="k" class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                <p class="text-xs font-semibold uppercase tracking-wider text-brown">{{ k }}</p><p class="mt-1 font-display text-3xl font-bold">{{ v }}</p>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <span v-for="(c, k) in byCategory" :key="k" class="rounded-full bg-milk px-3 py-1 text-xs font-semibold ring-1 ring-brown-soft/40">{{ k.replace('_', ' ') }} · {{ c }}</span>
        </div>
        <div class="mt-8 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
            <h2 class="font-display text-xl font-bold">Editions</h2>
            <p class="mt-1 text-sm text-ink/70">PDF upload, processing and publishing arrive in Phase 2. The data model and storage layer are ready.</p>
        </div>
        <h2 class="mt-10 font-display text-2xl font-bold">Submissions</h2>
        <p v-if="!submissions.length" class="mt-3 text-sm text-ink/60">No submissions yet.</p>
        <div class="mt-4 space-y-3">
            <details v-for="s in submissions" :key="s.id" class="rounded-xl bg-white p-4 ring-1 ring-brown-soft/30">
                <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3">
                    <span><b>{{ s.title }}</b> <span class="text-xs text-ink/60">· {{ s.kind }} · {{ s.user?.name }}</span></span>
                    <select :value="s.status" @click.stop @change="setStatus(s, $event.target.value)" class="rounded-full border-brown-soft/60 py-1 text-xs">
                        <option value="submitted">Submitted</option><option value="in_review">In review</option><option value="accepted">Accepted</option><option value="declined">Declined</option>
                    </select>
                </summary>
                <p class="mt-3 whitespace-pre-line font-serif text-ink/80">{{ s.body }}</p>
            </details>
        </div>
    </AuthenticatedLayout>
</template>
