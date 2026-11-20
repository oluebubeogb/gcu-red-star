<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const props = defineProps({ configured: Boolean, flags: Object, switches: Object, env: Object, autoPublish: Boolean, models: Object, pgvector: Boolean, stats: Object, topQuestions: Array });
const sw = reactive({ ...props.switches });
const auto = reactive({ on: props.autoPublish });
const labels = { search: 'Semantic search', ask: 'Ask the archive', summaries: 'Summaries & auto-tags', ocr: 'OCR cleanup', audio: 'Audio editions', assistant: "Editor's assistant" };
const save = () => router.post(route('hq.ai.settings'), { switches: sw, auto_publish: auto.on }, { preserveScroll: true });
const rebuild = () => confirm('Re-index every processed edition? Unchanged text reuses existing embeddings.') && router.post(route('hq.ai.rebuild'), {}, { preserveScroll: true });
</script>
<template>
    <Head title="HQ · AI" />
    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('hq.index')" class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">← HQ</Link>
            <h1 class="mt-2 font-display text-4xl font-extrabold">AI (Replicate)</h1>
        </template>
        <div v-if="!configured" class="mb-6 rounded-2xl bg-pink-soft p-5 text-sm">
            <b>REPLICATE_API_TOKEN is not set</b> (or REDSTAR_AI_ENABLED=false). All AI features are hidden and the site works without them. Add the token in Coolify → Environment and redeploy.
        </div>
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-2xl bg-white p-4 ring-1 ring-brown-soft/30"><p class="text-xs text-ink/60">Chunks embedded</p><p class="font-display text-2xl font-bold">{{ stats.embedded }} / {{ stats.chunks }}</p></div>
            <div class="rounded-2xl bg-white p-4 ring-1 ring-brown-soft/30"><p class="text-xs text-ink/60">Replicate calls today</p><p class="font-display text-2xl font-bold">{{ stats.callsToday }} <span class="text-sm text-ink/50">/ {{ stats.dailyCap }}</span></p></div>
            <div class="rounded-2xl bg-white p-4 ring-1 ring-brown-soft/30"><p class="text-xs text-ink/60">Waiting for review</p><p class="font-display text-2xl font-bold">{{ stats.pendingSummaries }} <span class="text-sm">summaries</span> · {{ stats.pendingOcr }} <span class="text-sm">pages</span></p></div>
            <div class="rounded-2xl bg-white p-4 ring-1 ring-brown-soft/30"><p class="text-xs text-ink/60">Vector store</p><p class="font-display text-lg font-bold">{{ pgvector ? 'pgvector' : 'JSON fallback' }}</p></div>
        </div>
        <div class="mt-8 grid gap-8 lg:grid-cols-2">
            <section class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                <h2 class="font-display text-xl font-bold">Features</h2>
                <label v-for="(label, f) in labels" :key="f" class="mt-3 flex items-center justify-between gap-3 text-sm">
                    <span>{{ label }} <span v-if="!env[f]" class="text-xs text-star">(off in env)</span>
                        <span class="ml-1 text-xs" :class="flags[f] ? 'text-green-700' : 'text-ink/40'">{{ flags[f] ? '● live' : '○ off' }}</span></span>
                    <input type="checkbox" v-model="sw[f]" class="rounded text-star" />
                </label>
                <label class="mt-5 flex items-center justify-between gap-3 border-t border-milk pt-4 text-sm">
                    <span>Auto-publish AI summaries <span class="block text-xs text-ink/50">Off = editors approve each summary first.</span></span>
                    <input type="checkbox" v-model="auto.on" class="rounded text-star" />
                </label>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button @click="save" class="rounded-full bg-star px-5 py-2 text-sm font-semibold text-white">Save</button>
                    <button @click="rebuild" :disabled="!configured" class="rounded-full bg-ink px-5 py-2 text-sm font-semibold text-milk disabled:opacity-50">Rebuild AI index</button>
                </div>
                <dl class="mt-6 space-y-1 text-xs text-ink/60">
                    <div v-for="(m, k) in models" :key="k"><dt class="inline font-semibold">{{ k }}:</dt> <dd class="inline font-mono">{{ m }}</dd></div>
                </dl>
            </section>
            <section class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                <h2 class="font-display text-xl font-bold">Top questions <span class="text-sm font-normal text-ink/50">(last 90 days)</span></h2>
                <p v-if="!topQuestions.length" class="mt-3 text-sm text-ink/60">No questions yet.</p>
                <ol class="mt-3 space-y-2 text-sm">
                    <li v-for="q in topQuestions" :key="q.normalized" class="flex justify-between gap-3">
                        <span>{{ q.question }} <span v-if="+q.answered === 0" class="text-xs text-star">· never answered</span></span>
                        <span class="shrink-0 text-xs font-semibold text-ink/60">×{{ q.c }}</span>
                    </li>
                </ol>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
