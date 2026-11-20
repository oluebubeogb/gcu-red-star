<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
const props = defineProps({ q: String, results: Object, didYouMean: String, fuzzy: Boolean, editions: Array, semantic: Boolean });
const term = ref(props.q || '');
const ai = usePage().props.ai || {};
const go = () => router.get(route('search'), { q: term.value }, { preserveState: true });
</script>
<template>
    <SiteLayout title="Search the archive">
        <section class="mx-auto max-w-4xl px-5 py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Archive search</p>
            <h1 class="mt-2 font-display text-4xl font-extrabold">Search every edition</h1>
            <form @submit.prevent="go" class="mt-6 flex gap-2">
                <input v-model="term" type="search" placeholder="Names, houses, events, years…" class="flex-1 rounded-full border-brown-soft/60 px-5 focus:border-star focus:ring-star" />
                <button class="rounded-full bg-star px-6 py-2.5 text-sm font-bold text-white">Search</button>
            </form>
            <Link v-if="ai.ask && q" :href="route('ask', { q })" class="mt-4 flex items-center justify-between gap-3 rounded-2xl bg-ink px-5 py-3 text-sm text-milk">
                <span><b>Ask the archive:</b> “{{ q }}” — get an answer with page citations</span><span>→</span>
            </Link>
            <div v-if="editions?.length" class="mt-8">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Editions</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Link v-for="e in editions" :key="e.slug" :href="route('editions.show', e.slug)" class="rounded-full bg-white px-4 py-1.5 text-sm font-semibold ring-1 ring-brown-soft/40 hover:text-star">{{ e.title }}<span v-if="e.year" class="font-normal text-ink/60"> · {{ e.year }}</span></Link>
                </div>
            </div>
            <template v-if="results">
                <p v-if="didYouMean" class="mt-8 rounded-xl bg-pink-soft px-4 py-3 text-sm">No exact matches for “{{ q }}”. Showing results for <b>“{{ didYouMean }}”</b>.</p>
                <p v-else-if="fuzzy" class="mt-8 rounded-xl bg-pink-soft px-4 py-3 text-sm">No exact matches for “{{ q }}”. Showing close matches.</p>
                <p class="mt-8 text-sm text-ink/60">{{ results.total }} matching page{{ results.total === 1 ? '' : 's' }}<span v-if="semantic"> · ranked with AI meaning-match</span></p>
                <ul class="mt-4 space-y-4">
                    <li v-for="(r, i) in results.data" :key="i" class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                        <Link :href="route('editions.show', { edition: r.edition.slug, page: r.page })" class="font-display text-lg font-bold hover:text-star">
                            {{ r.edition.title }} <span class="text-sm font-normal text-ink/60">· page {{ r.page }}<span v-if="r.edition.year"> · {{ r.edition.year }}</span><span v-if="r.edition.access === 'members'"> · members</span></span>
                        </Link>
                        <p class="snippet mt-2 font-serif text-ink/80" v-html="r.snippet"></p>
                    </li>
                </ul>
                <div v-if="results.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                    <Link v-for="l in results.links" :key="l.label" :href="l.url || '#'" v-html="l.label"
                          :class="l.active ? 'bg-star text-white' : 'bg-white'" class="rounded-full px-3 py-1 text-sm ring-1 ring-brown-soft/30" />
                </div>
            </template>
        </section>
    </SiteLayout>
</template>
<style scoped>
.snippet :deep(mark) { background: #fde2e4; color: inherit; padding: 0 2px; border-radius: 2px; }
</style>
