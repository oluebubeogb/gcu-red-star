<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import SiteLayout from '@/Layouts/SiteLayout.vue';
const props = defineProps({ q: String, available: Boolean, scope: String });
const user = usePage().props.auth.user;
const question = ref(props.q || '');
const loading = ref(false);
const result = ref(null);
const error = ref('');
const examples = ['Who won the inter-house sports?', 'What happened on Founders\u2019 Day?', 'Which old boys wrote about the Civil War years?'];

const ask = async () => {
    if (question.value.trim().length < 5 || loading.value) return;
    loading.value = true; error.value = ''; result.value = null;
    try {
        result.value = (await axios.post(route('ask.store'), { question: question.value })).data;
    } catch (e) {
        error.value = e.response?.status === 429 ? 'You\u2019ve asked a lot of questions — please wait a while and try again.'
            : e.response?.data?.message || 'Something went wrong. Please try again.';
    } finally { loading.value = false; }
};
if (props.q && props.available) ask();

// Render [n] citations as links to the cited page; text is escaped first.
const esc = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const withCitations = (r) => esc(r.answer).replace(/\[(\d{1,2})\]/g, (m, n) => {
    const c = r.citations.find((x) => x.n === +n);
    return c ? `<a href="${c.url}" class="cite">[${n}]</a>` : '';
}).replace(/\n/g, '<br>');
</script>
<template>
    <SiteLayout title="Ask the archive">
        <section class="mx-auto max-w-3xl px-5 py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">AI · Ask the archive</p>
            <h1 class="mt-2 font-display text-4xl font-extrabold">Ask The Red Star</h1>
            <p class="mt-3 font-serif text-ink/75">Answers come only from published editions, with links to the exact pages.
                <template v-if="scope === 'public'"> Guests search public editions — <Link :href="route(user ? 'verification.notice' : 'register')" class="font-semibold text-star">{{ user ? 'verify your email' : 'join free' }}</Link> to include members-only editions.</template></p>
            <div v-if="!available" class="mt-8 rounded-2xl bg-white p-6 text-sm ring-1 ring-brown-soft/30">
                Ask the archive isn't available right now. <Link :href="route('search')" class="font-semibold text-star">Use the search instead →</Link>
            </div>
            <template v-else>
                <form @submit.prevent="ask" class="mt-8 flex flex-col gap-2 sm:flex-row">
                    <input v-model="question" maxlength="500" placeholder="e.g. Who was principal in 1975?" class="flex-1 rounded-full border-brown-soft/60 px-5 focus:border-star focus:ring-star" />
                    <button :disabled="loading" class="rounded-full bg-star px-6 py-2.5 text-sm font-bold text-white disabled:opacity-60">{{ loading ? 'Searching the archive…' : 'Ask' }}</button>
                </form>
                <div v-if="!result && !loading" class="mt-4 flex flex-wrap gap-2">
                    <button v-for="e in examples" :key="e" @click="question = e; ask()" class="rounded-full bg-white px-3 py-1 text-xs ring-1 ring-brown-soft/40">{{ e }}</button>
                </div>
                <div v-if="loading" class="mt-8 animate-pulse space-y-3 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                    <div class="h-3 w-3/4 rounded bg-milk"></div><div class="h-3 w-full rounded bg-milk"></div><div class="h-3 w-2/3 rounded bg-milk"></div>
                    <p class="text-xs text-ink/50">Reading the archive…</p>
                </div>
                <p v-if="error" class="mt-6 rounded-xl bg-pink-soft p-4 text-sm">{{ error }}</p>
                <div v-if="result" class="mt-8 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                    <p class="answer font-serif text-lg leading-relaxed" v-html="withCitations(result)"></p>
                    <div v-if="result.citations.length" class="mt-6 border-t border-milk pt-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Sources</p>
                        <ol class="mt-2 space-y-2 text-sm">
                            <li v-for="c in result.citations" :key="c.n"><a :href="c.url" class="font-semibold hover:text-star">[{{ c.n }}] {{ c.title }}<span v-if="c.year"> ({{ c.year }})</span>, page {{ c.page }}</a>
                                <p class="text-xs text-ink/60">“{{ c.quote }}…”</p></li>
                        </ol>
                    </div>
                    <p v-else class="mt-3 text-sm text-ink/60">Try different words, or <Link :href="route('search', { q: question })" class="font-semibold text-star">search the archive</Link>.</p>
                    <p class="mt-4 text-[11px] text-ink/40">AI-generated from archive pages — check the sources.</p>
                </div>
            </template>
        </section>
    </SiteLayout>
</template>
<style scoped>
.answer :deep(.cite) { color: #a52a2a; font-weight: 700; font-size: 0.8em; vertical-align: super; }
</style>
