<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const props = defineProps({ edition: Object, flags: Object, summaries: Array, ocrPages: Array, needsCleanup: Number, audio: Array, chunks: Object });
const e = props.edition;
const opts = { preserveScroll: true };
const kinds = ['people', 'places', 'events', 'years'];
const drafts = reactive(Object.fromEntries(props.summaries.map((s) => [s.id, {
    summary: s.summary, entities: Object.fromEntries(kinds.map((k) => [k, (s.entities?.[k] || []).join(', ')])) }])));
const ocr = reactive(Object.fromEntries(props.ocrPages.map((p) => [p.id, p.cleaned_text || ''])));
const audioPage = ref('');
const post = (name, data = {}) => router.post(route(name, e.id), data, opts);
const saveSummary = (s, status) => router.put(route('hq.ai.summaries.update', s.id), {
    summary: drafts[s.id].summary, status,
    entities: Object.fromEntries(kinds.map((k) => [k, drafts[s.id].entities[k].split(',').map((x) => x.trim()).filter(Boolean)])),
}, opts);
const saveOcr = (p, status) => router.put(route('hq.ai.pages.update', p.id), { cleaned_text: ocr[p.id], status }, opts);
const tagMember = (s, t) => router.post(route('tags.store', e.slug), { page_number: s.page_from || 1, username: t.username }, opts);
const badge = { draft: 'bg-pink-soft', approved: 'bg-green-100 text-green-800', rejected: 'bg-gray-100 text-gray-500', pending: 'bg-pink-soft', ready: 'bg-green-100 text-green-800', failed: 'bg-star text-white', processing: 'bg-milk' };
</script>
<template>
    <Head :title="`AI · ${e.title}`" />
    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('hq.editions.edit', e.id)" class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">← {{ e.title }}</Link>
            <h1 class="mt-2 font-display text-4xl font-extrabold">AI review</h1>
            <p class="mt-1 text-sm text-ink/60">Indexed {{ e.ai_indexed_at || 'never' }} · {{ chunks.embedded }}/{{ chunks.total }} chunks embedded</p>
        </template>
        <div class="flex flex-wrap gap-2">
            <button @click="post('hq.editions.ai.reindex')" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk">Re-index</button>
            <button :disabled="!flags.summaries" @click="post('hq.editions.ai.summarize')" class="rounded-full bg-star px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">{{ summaries.length ? 'Regenerate' : 'Generate' }} summaries</button>
            <button :disabled="!flags.ocr" @click="post('hq.editions.ai.ocr')" class="rounded-full bg-star px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Clean OCR ({{ needsCleanup }} pages flagged)</button>
        </div>
        <p v-if="!flags.summaries && !flags.ocr && !flags.audio" class="mt-3 text-sm text-ink/60">AI features are off — see <Link :href="route('hq.ai')" class="font-semibold text-star">HQ → AI</Link>.</p>

        <h2 class="mt-10 font-display text-2xl font-bold">Summaries & tags</h2>
        <p class="text-sm text-ink/60">Drafts are hidden from readers until approved.</p>
        <p v-if="!summaries.length" class="mt-3 text-sm text-ink/60">No summaries yet.</p>
        <div v-for="s in summaries" :key="s.id" class="mt-4 rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
            <div class="flex items-center justify-between">
                <b>{{ s.page_from ? `Pages ${s.page_from}–${s.page_to}` : 'Whole edition' }}</b>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="badge[s.status]">{{ s.status }}</span>
            </div>
            <textarea v-model="drafts[s.id].summary" rows="3" class="mt-2 w-full rounded-lg border-brown-soft/60 text-sm"></textarea>
            <div class="grid gap-2 sm:grid-cols-2">
                <label v-for="k in kinds" :key="k" class="text-xs font-semibold capitalize">{{ k }}
                    <input v-model="drafts[s.id].entities[k]" class="mt-1 w-full rounded-lg border-brown-soft/60 text-sm font-normal" placeholder="comma separated" /></label>
            </div>
            <div v-if="s.suggestedTags.length" class="mt-3 text-xs">Members mentioned — tag them?
                <button v-for="t in s.suggestedTags" :key="t.username" @click="tagMember(s, t)" class="ml-1 rounded-full bg-milk px-2 py-0.5 font-semibold ring-1 ring-brown-soft/40">+ {{ t.name }} (@{{ t.username }})</button>
            </div>
            <div class="mt-3 flex gap-2">
                <button @click="saveSummary(s, 'approved')" class="rounded-full bg-star px-4 py-1.5 text-xs font-semibold text-white">Approve & publish</button>
                <button @click="saveSummary(s, 'draft')" class="rounded-full bg-white px-4 py-1.5 text-xs font-semibold ring-1 ring-brown-soft/40">Save draft</button>
                <button @click="saveSummary(s, 'rejected')" class="rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-ink/60 ring-1 ring-brown-soft/40">Reject</button>
            </div>
        </div>

        <h2 class="mt-10 font-display text-2xl font-bold">OCR cleanup</h2>
        <p class="text-sm text-ink/60">The original text is always kept. Approved cleaned text is used by Comfort Reading and search.</p>
        <p v-if="!ocrPages.length" class="mt-3 text-sm text-ink/60">No cleaned pages yet.</p>
        <div v-for="p in ocrPages" :key="p.id" class="mt-4 rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
            <div class="flex items-center justify-between"><b>Page {{ p.page_number }}</b><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="badge[p.cleaned_status]">{{ p.cleaned_status }}</span></div>
            <div class="mt-2 grid gap-3 md:grid-cols-2">
                <div><p class="text-xs font-semibold text-ink/60">Original</p><pre class="mt-1 max-h-80 overflow-auto whitespace-pre-wrap rounded-lg bg-milk p-3 text-xs">{{ p.text }}</pre></div>
                <div><p class="text-xs font-semibold text-ink/60">Cleaned (editable)</p><textarea v-model="ocr[p.id]" class="mt-1 h-80 w-full rounded-lg border-brown-soft/60 text-xs"></textarea></div>
            </div>
            <div class="mt-3 flex gap-2">
                <button @click="saveOcr(p, 'approved')" class="rounded-full bg-star px-4 py-1.5 text-xs font-semibold text-white">Approve</button>
                <button @click="saveOcr(p, 'rejected')" class="rounded-full bg-white px-4 py-1.5 text-xs font-semibold ring-1 ring-brown-soft/40">Reject (keep original)</button>
            </div>
        </div>

        <h2 class="mt-10 font-display text-2xl font-bold">Audio edition</h2>
        <p class="text-sm text-ink/60">Text-to-speech is paid per character — generate only what you need. Pages that already have audio are skipped when generating all.</p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button :disabled="!flags.audio" @click="post('hq.editions.ai.audio')" class="rounded-full bg-star px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Generate all pages</button>
            <input v-model="audioPage" type="number" min="1" :max="e.page_count" placeholder="Page" class="w-24 rounded-full border-brown-soft/60 text-sm" />
            <button :disabled="!flags.audio || !audioPage" @click="post('hq.editions.ai.audio', { page: +audioPage })" class="rounded-full bg-white px-4 py-2 text-sm font-semibold ring-1 ring-brown-soft/40 disabled:opacity-40">Generate this page</button>
        </div>
        <ul class="mt-4 space-y-2 text-sm">
            <li v-for="a in audio" :key="a.page_number" class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-3 ring-1 ring-brown-soft/30">
                <b>Page {{ a.page_number }}</b><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="badge[a.status]">{{ a.status }}</span>
                <audio v-if="a.url && a.status === 'ready'" :src="a.url" controls preload="none" class="h-8"></audio>
                <span v-if="a.error" class="text-xs text-star">{{ a.error }}</span>
            </li>
        </ul>
    </AuthenticatedLayout>
</template>
