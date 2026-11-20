<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const props = defineProps({ counts: Object, byCategory: Object, submissions: Array, editionStatus: Object, demo: Object });
const demoCount = ref(25);
const seedDemo = () => router.post(route('hq.demo.seed'), { count: demoCount.value }, { preserveScroll: true });
const purgeDemo = () => confirm('Remove all demo members, submissions and bookmarks?') && router.delete(route('hq.demo.purge'), { preserveScroll: true });
const user = usePage().props.auth.user;
const ai = usePage().props.ai || {};
const runAssistant = (s) => router.post(route('hq.submissions.assistant', s.id), {}, { preserveScroll: true });
const setStatus = (s, status) => router.patch(route('hq.submissions.update', s.id), { status }, { preserveScroll: true });
</script>
<template>
    <Head title="Editorial HQ" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Editorial HQ</p><h1 class="mt-2 font-display text-4xl font-extrabold">Newsroom</h1></div>
                <div class="flex gap-2"><Link :href="route('hq.community')" class="rounded-full bg-star px-4 py-2 text-sm font-semibold text-white">Community</Link>
                    <Link :href="route('hq.ai')" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk">AI</Link>
                    <Link :href="route('hq.commerce')" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk">Commerce</Link>
                    <Link :href="route('hq.revenue')" class="rounded-full bg-white px-4 py-2 text-sm font-semibold ring-1 ring-brown-soft/40">Revenue</Link>
                <Link v-if="user.is_admin" :href="route('hq.members')" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk">Manage members</Link></div>
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
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl font-bold">Editions</h2>
                    <p class="mt-1 text-sm text-ink/70">Upload PDFs, track processing and publish issues.</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('hq.editions.index')" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk">Manage editions</Link>
                    <Link :href="route('hq.editions.create')" class="rounded-full bg-star px-4 py-2 text-sm font-semibold text-white">New edition</Link>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <span v-for="(c, k) in editionStatus" :key="k" class="rounded-full bg-milk px-3 py-1 text-xs font-semibold ring-1 ring-brown-soft/40">{{ k }} · {{ c }}</span>
            </div>
        </div>
        <div v-if="user.is_admin" class="mt-4 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
            <h2 class="font-display text-xl font-bold">Demo data</h2>
            <p class="mt-1 text-sm text-ink/70">Creates dummy members (random passwords, <code>.invalid</code> emails) with sample submissions and bookmarks. Removal deletes only records flagged as demo. Currently {{ demo.count }} demo members.</p>
            <p v-if="!demo.allowed" class="mt-3 text-sm font-semibold text-brown">Disabled in production. Set REDSTAR_ALLOW_DEMO=true to enable.</p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <label class="text-sm font-semibold">Members</label>
                <input v-model.number="demoCount" type="number" min="1" max="25" :disabled="!demo.allowed" class="w-20 rounded-lg border-brown-soft/60 py-1 text-sm" />
                <button @click="seedDemo" :disabled="!demo.allowed" class="rounded-full bg-star px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Seed demo data</button>
                <button @click="purgeDemo" :disabled="!demo.allowed || !demo.count" class="rounded-full px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-red-200 disabled:opacity-40">Remove demo data</button>
            </div>
        </div>
        <h2 class="mt-10 font-display text-2xl font-bold">Submissions</h2>
        <p v-if="!submissions.length" class="mt-3 text-sm text-ink/60">No submissions yet.</p>
        <div class="mt-4 space-y-3">
            <details v-for="s in submissions" :key="s.id" class="rounded-xl bg-white p-4 ring-1 ring-brown-soft/30">
                <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3">
                    <span><b>{{ s.title }}</b> <span class="text-xs text-ink/60">· {{ s.kind }} · {{ s.user?.name }}</span>
                        <span v-if="s.user?.category === 'friend'" class="ml-1 rounded-full bg-pink-soft px-2 py-0.5 text-[10px] font-bold uppercase text-star-800" title="Submitted by a Friend of GCU (not a student, teacher or old boy)">Friend of GCU</span>
                        <span v-if="s.ai_review?.spam_score >= 0.6" class="ml-1 rounded-full bg-star px-2 py-0.5 text-[10px] font-bold uppercase text-white" title="AI assistant thinks this may be spam or abusive">likely spam</span>
                        <span v-if="s.is_demo" class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase text-gray-600">demo</span></span>
                    <select :value="s.status" @click.stop @change="setStatus(s, $event.target.value)" class="rounded-full border-brown-soft/60 py-1 text-xs">
                        <option value="submitted">Submitted</option><option value="in_review">In review</option><option value="accepted">Accepted</option><option value="declined">Declined</option>
                    </select>
                </summary>
                <a v-if="s.photo_url" :href="s.photo_url" target="_blank" class="mt-3 block"><img :src="s.photo_url" :alt="s.title" class="max-h-80 rounded-lg ring-1 ring-brown-soft/30" /></a>
                <p v-if="s.body" class="mt-3 whitespace-pre-line font-serif text-ink/80">{{ s.body }}</p>
                <div v-if="ai.assistant || s.ai_review" class="mt-4 rounded-xl bg-milk p-4 text-sm ring-1 ring-brown-soft/30">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Editor's assistant <span class="normal-case tracking-normal text-ink/50">· suggestions only, nothing is changed</span></p>
                        <button v-if="ai.assistant" @click="runAssistant(s)" class="rounded-full bg-white px-3 py-1 text-xs font-semibold ring-1 ring-brown-soft/40">{{ s.ai_review ? 'Re-run' : 'Run assistant' }}</button>
                    </div>
                    <template v-if="s.ai_review">
                        <p class="mt-2">Spam/abuse score: <b :class="s.ai_review.spam_score >= 0.6 ? 'text-star' : ''">{{ Math.round(s.ai_review.spam_score * 100) }}%</b>
                            <span v-for="f in s.ai_review.flags" :key="f" class="ml-1 rounded-full bg-pink-soft px-2 py-0.5 text-[10px] font-semibold">{{ f }}</span></p>
                        <p v-if="s.ai_review.headline" class="mt-1">Suggested headline: <b>{{ s.ai_review.headline }}</b></p>
                        <ul v-if="s.ai_review.suggestions?.length" class="mt-2 list-disc space-y-1 pl-5 text-ink/80">
                            <li v-for="(g, i) in s.ai_review.suggestions" :key="i">{{ g }}</li>
                        </ul>
                    </template>
                    <p v-else class="mt-1 text-xs text-ink/60">Not reviewed yet.</p>
                </div>
            </details>
        </div>
    </AuthenticatedLayout>
</template>
