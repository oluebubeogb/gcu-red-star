<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import WhatsAppShare from '@/Components/WhatsAppShare.vue';
import SponsorCredits from '@/Components/SponsorCredits.vue';
import AdCard from '@/Components/AdCard.vue';
import { naira } from '@/money';
import { isSaved, offlineSupported, removeEdition, saveEdition } from '@/offline';
const props = defineProps({
    edition: Object, canRead: Boolean, canDownload: Boolean, startPage: Number,
    pages: Array, blocks: Object, bookmarks: Array, shareUrl: String, summary: Object, audio: { type: Object, default: () => ({}) }, tags: { type: Array, default: () => [] },
    sponsors: { type: Array, default: () => [] }, sidebarAd: Object, print: Object,
});
const user = usePage().props.auth.user;
const total = computed(() => props.pages.length);
const page = ref(Math.min(props.startPage || 1, Math.max(1, total.value)));
const mode = ref('pages');
const current = computed(() => props.pages.find((p) => p.n === page.value));
const currentBlocks = computed(() => props.blocks?.[page.value] || []);
const bookmark = computed(() => props.bookmarks.find((b) => b.page_number === page.value));

const go = (n) => { page.value = Math.min(Math.max(1, n), total.value); };
const pageShareUrl = computed(() => `${props.shareUrl}?page=${page.value}`);

// Slow connections: warm the next/previous page image in the background only.
const prefetch = (n) => { const p = props.pages.find((x) => x.n === n); if (p?.image_url) new Image().src = p.image_url; };

// Offline copy
const canOffline = ref(false);
const saved = ref(false);
const saving = ref(0);
const toggleOffline = async () => {
    if (saved.value) { await removeEdition(props.edition.slug); saved.value = false; return; }
    saving.value = 1;
    await saveEdition(props.edition.slug, props.shareUrl, props.pages.map((p) => p.image_url).filter(Boolean), (pct) => { saving.value = Math.max(1, pct); });
    saving.value = 0; saved.value = true;
};
watch(page, (n) => {
    const url = new URL(window.location.href); url.searchParams.set('page', n);
    window.history.replaceState(window.history.state, '', url);
    window.scrollTo({ top: document.getElementById('reader')?.offsetTop - 80 || 0, behavior: 'smooth' });
    prefetch(n + 1);
});
const onKey = (e) => {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
    if (e.key === 'ArrowRight') go(page.value + 1);
    if (e.key === 'ArrowLeft') go(page.value - 1);
};
onMounted(() => {
    if (window.innerWidth < 768) mode.value = 'comfort';
    canOffline.value = props.canRead && offlineSupported();
    saved.value = isSaved(props.edition.slug);
    if (mode.value === 'pages') prefetch(page.value + 1);
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

// People tagging
const pageAudio = computed(() => props.audio?.[page.value]);
const pageSummary = computed(() => props.summary?.sections?.find((s) => page.value >= s.from && page.value <= s.to));
const entityLabels = { people: 'People', places: 'Places', events: 'Events', years: 'Years' };
const pageTags = computed(() => props.tags.filter((t) => t.page === page.value));
const tagForm = useForm({ page_number: 1, username: '', name: '', note: '' });
const tagMode = ref('member');
const addTag = (self = false) => {
    tagForm.page_number = page.value;
    if (self) { tagForm.username = user.username; tagForm.name = ''; }
    else if (tagMode.value === 'member') tagForm.name = ''; else tagForm.username = '';
    tagForm.post(route('tags.store', props.edition.slug), { preserveScroll: true, preserveState: true, onSuccess: () => tagForm.reset('username', 'name', 'note') });
};
const claimTag = (t) => router.post(route('tags.claim', t.id), {}, { preserveScroll: true, preserveState: true });
const removeTag = (t) => router.delete(route('tags.destroy', t.id), { preserveScroll: true, preserveState: true });

const toggleBookmark = () => bookmark.value
    ? router.delete(route('bookmarks.destroy', bookmark.value.id), { preserveScroll: true, preserveState: true })
    : router.post(route('bookmarks.store'), { edition_id: props.edition.id, page_number: page.value }, { preserveScroll: true, preserveState: true });
</script>
<template>
    <SiteLayout :title="edition.title">
        <section class="mx-auto grid max-w-5xl gap-10 px-5 pt-14 md:grid-cols-[220px_1fr]">
            <div class="aspect-[3/4] overflow-hidden rounded-xl bg-star shadow-xl">
                <img v-if="edition.cover_url" :src="edition.cover_url" :alt="edition.title" fetchpriority="high" decoding="async" class="h-full w-full object-cover" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">{{ edition.year }} <span v-if="edition.volume">· Vol. {{ edition.volume }}</span> <span v-if="edition.issue">· Issue {{ edition.issue }}</span><span v-if="edition.page_count"> · {{ edition.page_count }} pages</span></p>
                <h1 class="mt-2 font-display text-4xl font-extrabold">{{ edition.title }}</h1>
                <p v-if="edition.summary" class="mt-4 font-serif text-lg text-ink/75">{{ edition.summary }}</p>
                <div v-if="!canRead" class="mt-8 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                    <p class="font-semibold">This edition is for Red Star members</p>
                    <p class="mt-1 text-sm text-ink/70">Membership is free for students, teachers, old boys and friends of GCU. Verify your email to start reading.</p>
                    <Link v-if="!user" :href="route('register')" class="mt-4 inline-block rounded-full bg-star px-5 py-2.5 text-sm font-semibold text-white">Join free to read</Link>
                    <Link v-else :href="route('verification.notice')" class="mt-4 inline-block rounded-full bg-star px-5 py-2.5 text-sm font-semibold text-white">Verify your email</Link>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <WhatsAppShare :title="edition.title" :url="shareUrl" />
                    <button v-if="canOffline" @click="toggleOffline" :disabled="saving > 0" class="rounded-full px-5 py-2 text-sm font-semibold ring-1 ring-brown-soft/60 disabled:opacity-60">
                        {{ saving ? `Saving… ${saving}%` : saved ? '✓ Saved offline · remove' : 'Save for offline' }}
                    </button>
                </div>
                <div v-if="canRead" class="mt-3 flex flex-wrap gap-2">
                    <a v-if="canDownload" :href="route('editions.download', edition.slug)" target="_blank" class="rounded-full bg-ink px-5 py-2.5 text-sm font-semibold text-milk">Download PDF</a>
                    <Link v-else-if="!user" :href="route('login')" class="rounded-full px-5 py-2.5 text-sm font-semibold ring-1 ring-brown-soft/60">Sign in to download or bookmark</Link>
                </div>
                <div v-if="print && usePage().props.payments" class="mt-3">
                    <Link :href="route('print.create', edition.slug)" class="inline-block rounded-full bg-brown px-5 py-2.5 text-sm font-semibold text-white">
                        {{ print.stock ? `Order a printed copy · ${naira(print.price_kobo)}` : 'Printed copies sold out' }}</Link>
                </div>
            </div>
        </section>

        <section v-if="sponsors.length || sidebarAd" class="mx-auto mt-10 grid max-w-5xl gap-4 px-5" :class="sponsors.length && sidebarAd ? 'md:grid-cols-[2fr_1fr]' : ''">
            <SponsorCredits v-if="sponsors.length" :sponsors="sponsors" title="This edition is sponsored by" />
            <AdCard v-if="sidebarAd" :ad="sidebarAd" />
        </section>

        <section v-if="summary" class="mx-auto mt-10 max-w-5xl px-5">
            <div class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">In this edition <span class="normal-case tracking-normal text-ink/40">· AI summary, reviewed by the editors</span></p>
                <p v-if="summary.overall" class="mt-2 font-serif text-lg text-ink/85">{{ summary.overall }}</p>
                <div v-for="(label, k) in entityLabels" :key="k">
                    <div v-if="summary.entities?.[k]?.length" class="mt-3 flex flex-wrap items-center gap-1.5">
                        <span class="mr-1 text-xs font-semibold text-ink/60">{{ label }}:</span>
                        <Link v-for="e in summary.entities[k].slice(0, 15)" :key="e" :href="route('search', { q: e })" class="rounded-full bg-milk px-2.5 py-0.5 text-xs font-semibold ring-1 ring-brown-soft/40 hover:text-star">{{ e }}</Link>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="canRead && total" id="reader" class="mx-auto mt-10 max-w-5xl px-5">
            <div class="sticky top-[57px] z-30 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white/95 p-3 ring-1 ring-brown-soft/30 backdrop-blur">
                <div class="flex rounded-full bg-milk p-1 text-sm font-semibold">
                    <button @click="mode = 'pages'" :class="mode === 'pages' ? 'bg-star text-white' : 'text-ink/70'" class="rounded-full px-4 py-1.5">Original pages</button>
                    <button @click="mode = 'comfort'" :class="mode === 'comfort' ? 'bg-star text-white' : 'text-ink/70'" class="rounded-full px-4 py-1.5">Comfort Reading</button>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <button @click="go(page - 1)" :disabled="page <= 1" class="rounded-full px-3 py-1.5 font-semibold ring-1 ring-brown-soft/60 disabled:opacity-40" aria-label="Previous page">←</button>
                    <select :value="page" @change="go(+$event.target.value)" class="rounded-full border-brown-soft/60 py-1 text-sm">
                        <option v-for="p in pages" :key="p.n" :value="p.n">Page {{ p.n }}</option>
                    </select>
                    <span class="text-ink/60">of {{ total }}</span>
                    <button @click="go(page + 1)" :disabled="page >= total" class="rounded-full px-3 py-1.5 font-semibold ring-1 ring-brown-soft/60 disabled:opacity-40" aria-label="Next page">→</button>
                    <WhatsAppShare compact :title="`${edition.title} — page ${page}`" :url="pageShareUrl" />
                    <button v-if="canDownload" @click="toggleBookmark" :class="bookmark ? 'bg-pink-soft text-star-800' : 'ring-1 ring-brown-soft/60'" class="rounded-full px-3 py-1.5 font-semibold">
                        {{ bookmark ? '★ Bookmarked' : '☆ Bookmark page' }}
                    </button>
                </div>
            </div>

            <div v-if="pageAudio || pageSummary" class="mx-auto mt-4 max-w-2xl space-y-2">
                <div v-if="pageAudio" class="flex items-center gap-3 rounded-2xl bg-white p-3 ring-1 ring-brown-soft/30">
                    <span class="text-xs font-semibold text-brown">Listen to page {{ page }}</span>
                    <audio :key="page" :src="pageAudio" controls preload="none" class="h-9 flex-1"></audio>
                </div>
                <p v-if="pageSummary" class="rounded-2xl bg-milk px-4 py-2 text-sm text-ink/75 ring-1 ring-brown-soft/30"><b>Pages {{ pageSummary.from }}–{{ pageSummary.to }}:</b> {{ pageSummary.summary }}</p>
            </div>
            <div v-if="mode === 'pages'" class="mt-6 flex justify-center">
                <img v-if="current?.image_url" :key="page" :src="current.image_url" :alt="`${edition.title}, page ${page}`"
                     :width="current.width" :height="current.height" loading="lazy" decoding="async" class="h-auto max-w-full rounded-lg bg-white shadow-xl ring-1 ring-brown-soft/30" />
                <p v-else class="rounded-2xl bg-white p-6 text-sm text-ink/60 ring-1 ring-brown-soft/30">No preview for this page.</p>
            </div>
            <article v-else class="mx-auto mt-6 max-w-2xl rounded-2xl bg-white px-6 py-8 ring-1 ring-brown-soft/30 md:px-10">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Page {{ page }}</p>
                <template v-for="(b, i) in currentBlocks" :key="i">
                    <h2 v-if="b.type === 'heading'" class="mt-6 font-display text-2xl font-bold leading-snug">{{ b.text }}</h2>
                    <p v-else class="mt-4 whitespace-pre-line font-serif text-lg leading-relaxed text-ink/85">{{ b.text }}</p>
                </template>
                <p v-if="!currentBlocks.length" class="mt-4 text-sm text-ink/60">No text on this page — switch to Original pages to see it.</p>
            </article>
            <div class="mx-auto mt-6 max-w-2xl rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                <h3 class="font-display text-lg font-bold">People on page {{ page }}</h3>
                <p v-if="!pageTags.length" class="mt-1 text-sm text-ink/60">Nobody tagged yet. Recognise someone? Tag them.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span v-for="t in pageTags" :key="t.id" class="inline-flex items-center gap-2 rounded-full bg-milk px-3 py-1 text-sm ring-1 ring-brown-soft/40" :title="t.note || ''">
                        <Link v-if="t.username" :href="route('members.show', t.username)" class="font-semibold hover:text-star">{{ t.name }}</Link>
                        <span v-else class="font-semibold">{{ t.name }}</span>
                        <button v-if="t.claimable && canDownload" @click="claimTag(t)" class="text-xs font-semibold text-star">This is me</button>
                        <button v-if="t.can_remove" @click="removeTag(t)" class="text-ink/50 hover:text-star" aria-label="Remove tag">✕</button>
                    </span>
                </div>
                <form v-if="canDownload" @submit.prevent="addTag()" class="mt-4 space-y-2 border-t border-milk pt-4 text-sm">
                    <div class="flex flex-wrap gap-2">
                        <select v-model="tagMode" class="rounded-full border-brown-soft/60 py-1 text-sm"><option value="member">Member (username)</option><option value="name">Name (no account)</option></select>
                        <input v-if="tagMode === 'member'" v-model="tagForm.username" placeholder="@username" class="min-w-0 flex-1 rounded-full border-brown-soft/60 py-1 text-sm" />
                        <input v-else v-model="tagForm.name" placeholder="Full name" class="min-w-0 flex-1 rounded-full border-brown-soft/60 py-1 text-sm" />
                    </div>
                    <input v-model="tagForm.note" placeholder="Note (optional), e.g. back row, second from left" class="w-full rounded-full border-brown-soft/60 py-1 text-sm" />
                    <p v-if="tagForm.errors.username || tagForm.errors.name || tagForm.errors.page_number" class="text-xs text-red-700">{{ tagForm.errors.username || tagForm.errors.name || tagForm.errors.page_number }}</p>
                    <div class="flex gap-2">
                        <button :disabled="tagForm.processing" class="rounded-full bg-star px-4 py-1.5 font-semibold text-white">Tag</button>
                        <button type="button" @click="addTag(true)" :disabled="tagForm.processing" class="rounded-full px-4 py-1.5 font-semibold ring-1 ring-brown-soft/60">That's me</button>
                    </div>
                </form>
            </div>
            <div class="mt-6 flex justify-between">
                <button @click="go(page - 1)" :disabled="page <= 1" class="rounded-full px-5 py-2 text-sm font-semibold ring-1 ring-brown-soft/60 disabled:opacity-40">← Previous</button>
                <button @click="go(page + 1)" :disabled="page >= total" class="rounded-full bg-star px-5 py-2 text-sm font-semibold text-white disabled:opacity-40">Next →</button>
            </div>
        </section>
        <section v-else-if="canRead" class="mx-auto mt-10 max-w-5xl px-5">
            <p class="rounded-2xl bg-white p-6 text-sm text-ink/60 ring-1 ring-brown-soft/30">Pages for this edition are still being prepared.</p>
        </section>
    </SiteLayout>
</template>
