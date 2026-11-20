<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
const props = defineProps({ edition: Object });
const errors = computed(() => usePage().props.errors);
const e = props.edition;
const form = useForm({
    title: e?.title ?? '', year: e?.year ?? '', volume: e?.volume ?? '', issue: e?.issue ?? '',
    summary: e?.summary ?? '', access: e?.access ?? 'members', pdf: null, cover: null,
});
const save = () => {
    if (!e) return form.post(route('hq.editions.store'), { forceFormData: true });
    form.transform((d) => ({ ...d, _method: 'put' })).post(route('hq.editions.update', e.id), {
        forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('pdf', 'cover'),
    });
};
const act = (name, data = {}) => router.post(route(`hq.editions.${name}`, e.id), data, { preserveScroll: true });
const destroy = () => confirm('Delete this edition and its files?') && router.delete(route('hq.editions.destroy', e.id));
const input = 'mt-1 w-full rounded-lg border-brown-soft/60 focus:border-star focus:ring-star';
</script>
<template>
    <Head :title="e ? `Edit ${e.title}` : 'New edition'" />
    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('hq.editions.index')" class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">← Editions</Link>
            <h1 class="mt-2 font-display text-4xl font-extrabold">{{ e ? e.title : 'New edition' }}</h1>
            <Link v-if="e" :href="route('hq.editions.ai', e.id)" class="mt-2 inline-block rounded-full bg-ink px-4 py-1.5 text-xs font-semibold text-milk">AI: summaries, OCR cleanup, audio →</Link>
        </template>
        <div class="grid gap-8 lg:grid-cols-[1.3fr_1fr]">
            <form @submit.prevent="save" class="space-y-4 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                <div><label class="text-sm font-semibold">Title</label><input v-model="form.title" :class="input" /><InputError :message="form.errors.title" /></div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="text-sm font-semibold">Year</label><input v-model="form.year" type="number" :class="input" /><InputError :message="form.errors.year" /></div>
                    <div><label class="text-sm font-semibold">Volume</label><input v-model="form.volume" :class="input" /></div>
                    <div><label class="text-sm font-semibold">Issue</label><input v-model="form.issue" :class="input" /></div>
                </div>
                <div><label class="text-sm font-semibold">Summary</label><textarea v-model="form.summary" rows="4" :class="input"></textarea></div>
                <div>
                    <label class="text-sm font-semibold">Access</label>
                    <select v-model="form.access" :class="input"><option value="members">Members (verified accounts)</option><option value="public">Public (anyone)</option></select>
                </div>
                <div>
                    <label class="text-sm font-semibold">Magazine PDF <span v-if="e?.pdf_path" class="font-normal text-ink/60">(uploading a new one replaces it and unpublishes until reprocessed)</span></label>
                    <input type="file" accept="application/pdf" @input="form.pdf = $event.target.files[0]" class="mt-1 block w-full text-sm" />
                    <InputError :message="form.errors.pdf" />
                </div>
                <div>
                    <label class="text-sm font-semibold">Cover image <span class="font-normal text-ink/60">(optional; otherwise page 1 is used)</span></label>
                    <input type="file" accept="image/jpeg,image/png,image/webp" @input="form.cover = $event.target.files[0]" class="mt-1 block w-full text-sm" />
                    <InputError :message="form.errors.cover" />
                </div>
                <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="w-full">{{ form.progress.percentage }}%</progress>
                <button :disabled="form.processing" class="rounded-full bg-star px-6 py-2.5 text-sm font-bold text-white disabled:opacity-50">{{ e ? 'Save changes' : 'Create edition' }}</button>
            </form>
            <div v-if="e" class="space-y-4">
                <div class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                    <h2 class="font-display text-xl font-bold">Status</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <dt class="text-ink/60">Editorial</dt><dd class="font-semibold capitalize">{{ e.status }}<span v-if="e.published_at" class="font-normal text-ink/60"> · {{ e.published_at }}</span></dd>
                        <dt class="text-ink/60">Processing</dt><dd class="font-semibold capitalize">{{ e.pdf_path ? e.processing_status : 'awaiting PDF' }}</dd>
                        <dt class="text-ink/60">Pages</dt><dd>{{ e.page_count ?? '—' }}</dd>
                        <dt class="text-ink/60">Processed</dt><dd>{{ e.processed_at ?? '—' }}</dd>
                    </dl>
                    <p v-if="e.processing_status === 'failed'" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ e.processing_error || 'Processing failed.' }}</p>
                    <p v-if="['pending', 'processing'].includes(e.processing_status) && e.pdf_path" class="mt-3 text-sm text-ink/60">The worker is rendering pages. Refresh in a minute.</p>
                    <InputError :message="errors.publish" class="mt-3" /><InputError :message="errors.delete" class="mt-3" />
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button v-if="e.status !== 'published'" @click="act('publish', { publish: 1 })" :disabled="e.processing_status !== 'ready'"
                                class="rounded-full bg-star px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">Publish</button>
                        <button v-else @click="act('publish', { publish: 0 })" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk">Unpublish</button>
                        <button v-if="e.pdf_path" @click="act('reprocess')" class="rounded-full px-4 py-2 text-sm font-semibold ring-1 ring-brown-soft/60">Reprocess PDF</button>
                        <a v-if="e.status === 'published'" :href="route('editions.show', e.slug)" class="rounded-full px-4 py-2 text-sm font-semibold ring-1 ring-brown-soft/60">View</a>
                        <button v-if="e.status !== 'published'" @click="destroy" class="rounded-full px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-red-200">Delete</button>
                    </div>
                </div>
                <img v-if="e.cover_url" :src="e.cover_url" :alt="e.title" class="w-48 rounded-lg shadow ring-1 ring-brown-soft/30" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
