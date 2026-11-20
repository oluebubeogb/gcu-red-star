<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const props = defineProps({ folders: Array, editions: Array, maxKb: Number });
const opts = reactive({ csv: 'filename,title,date,volume,issue,members_only\n', access: 'public', auto_publish: false });
const queue = ref([]); // { file, state, error }
const busy = ref(false);
const pick = (e) => { queue.value = [...e.target.files].filter((f) => f.name.toLowerCase().endsWith('.pdf')).map((file) => ({ file, state: 'waiting', error: '' })); };
// One request per file: no giant multipart bodies, per-file progress and retries.
const upload = async () => {
    busy.value = true;
    const batch = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
    for (const q of queue.value.filter((x) => x.state !== 'done')) {
        if (q.file.size > props.maxKb * 1024) { q.state = 'error'; q.error = 'File too large'; continue; }
        q.state = 'uploading';
        const fd = new FormData();
        fd.append('pdfs[]', q.file); fd.append('csv', opts.csv); fd.append('access', opts.access);
        fd.append('auto_publish', opts.auto_publish ? '1' : '0'); fd.append('batch', batch);
        try { await axios.post(route('hq.bulk.upload'), fd, { headers: { Accept: 'application/json' } }); q.state = 'done'; }
        catch (e) { q.state = 'error'; q.error = e.response?.data?.message || 'Upload failed'; }
    }
    busy.value = false;
    router.reload({ only: ['editions'] });
};
const folder = reactive({ path: props.folders[0]?.path || '' });
const importFolder = () => router.post(route('hq.bulk.folder'), { folder: folder.path, csv: opts.csv, access: opts.access, auto_publish: opts.auto_publish }, { preserveScroll: true });
const batches = computed(() => [...new Set(props.editions.map((e) => e.import_batch))]);
const setAuto = (batch, on) => router.post(route('hq.bulk.auto'), { batch, auto_publish: on }, { preserveScroll: true });
const pending = computed(() => props.editions.some((e) => ['pending', 'processing'].includes(e.processing_status)));
let timer;
onMounted(() => { timer = setInterval(() => pending.value && !busy.value && router.reload({ only: ['editions'] }), 10000); });
onBeforeUnmount(() => clearInterval(timer));
const badge = { ready: 'bg-green-100 text-green-800', failed: 'bg-star text-white', pending: 'bg-pink-soft', processing: 'bg-milk' };
</script>
<template>
    <Head title="HQ · Bulk upload" />
    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('hq.editions.index')" class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">← Editions</Link>
            <h1 class="mt-2 font-display text-4xl font-extrabold">Bulk upload (archive push)</h1>
            <p class="mt-1 text-sm text-ink/60">Each PDF becomes a <b>draft</b> edition queued for the PDF worker.</p>
        </template>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-4 rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                <h2 class="font-display text-xl font-bold">1. Details (optional CSV)</h2>
                <p class="text-xs text-ink/60">Columns: <code>filename,title,date,volume,issue,members_only</code>. Rows are matched by file name; files without a row get a title from the file name and a year if one appears in it.</p>
                <textarea v-model="opts.csv" rows="6" class="w-full rounded-lg border-brown-soft/60 font-mono text-xs"></textarea>
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <label>Default access <select v-model="opts.access" class="rounded-lg border-brown-soft/60 text-sm"><option value="public">Public</option><option value="members">Members only</option></select></label>
                    <label><input type="checkbox" v-model="opts.auto_publish" class="rounded text-star" /> Publish automatically when ready</label>
                </div>
            </div>
            <div class="space-y-4">
                <div class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                    <h2 class="font-display text-xl font-bold">2a. Upload PDFs</h2>
                    <input type="file" multiple accept="application/pdf" @change="pick" class="mt-2 block text-sm" />
                    <ul v-if="queue.length" class="mt-3 max-h-60 space-y-1 overflow-auto text-xs">
                        <li v-for="q in queue" :key="q.file.name" class="flex justify-between gap-2"><span class="truncate">{{ q.file.name }}</span>
                            <span :class="{ 'text-green-700': q.state === 'done', 'text-star': q.state === 'error' }">{{ q.state }}{{ q.error ? `: ${q.error}` : '' }}</span></li>
                    </ul>
                    <button :disabled="busy || !queue.length" @click="upload" class="mt-3 rounded-full bg-star px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ busy ? 'Uploading…' : `Upload ${queue.length} PDFs` }}</button>
                </div>
                <div class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                    <h2 class="font-display text-xl font-bold">2b. …or import a folder on the server</h2>
                    <p class="text-xs text-ink/60">Copy PDFs to <code>storage/app/magazines/imports/&lt;folder&gt;/</code> on the volume (e.g. with scp/rsync). Files are moved into place.</p>
                    <div v-if="folders.length" class="mt-2 flex gap-2">
                        <select v-model="folder.path" class="flex-1 rounded-lg border-brown-soft/60 text-sm"><option v-for="f in folders" :key="f.path" :value="f.path">{{ f.path }} ({{ f.pdfs }} PDFs)</option></select>
                        <button @click="importFolder" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk">Import</button>
                    </div>
                    <p v-else class="mt-2 text-sm text-ink/60">No folders under imports/ yet.</p>
                </div>
            </div>
        </div>
        <div class="mt-8 rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
            <h2 class="font-display text-xl font-bold">Progress <span v-if="pending" class="text-sm font-normal text-ink/50">(refreshing every 10s)</span></h2>
            <div v-for="b in batches" :key="b" class="mt-4">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm"><b>Batch {{ b }}</b>
                    <span class="text-xs"><button @click="setAuto(b, true)" class="font-semibold text-star">Auto-publish all</button> · <button @click="setAuto(b, false)" class="text-ink/60">Keep as drafts</button></span></div>
                <table class="mt-2 w-full text-sm">
                    <tbody><tr v-for="e in editions.filter((x) => x.import_batch === b)" :key="e.id" class="border-t border-milk">
                        <td class="py-1"><Link :href="route('hq.editions.edit', e.id)" class="font-semibold hover:text-star">{{ e.title }}</Link> <span class="text-xs text-ink/50">{{ e.year }} {{ e.volume ? `· vol ${e.volume}` : '' }} · {{ e.access }}</span></td>
                        <td><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="badge[e.processing_status] || 'bg-milk'">{{ e.processing_status }}</span>
                            <span v-if="e.processing_error" class="ml-1 text-xs text-star">{{ e.processing_error }}</span></td>
                        <td class="text-xs">{{ e.page_count ? `${e.page_count} pages` : '' }}</td>
                        <td class="text-right text-xs">{{ e.status }}<span v-if="e.auto_publish && e.status === 'draft'"> · auto</span></td>
                    </tr></tbody>
                </table>
            </div>
            <p v-if="!editions.length" class="mt-2 text-sm text-ink/60">Nothing imported yet.</p>
        </div>
    </AuthenticatedLayout>
</template>
