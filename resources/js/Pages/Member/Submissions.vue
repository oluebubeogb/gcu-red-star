<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
defineProps({ submissions: Array });
const form = useForm({ title: '', kind: 'article', body: '' });
const submit = () => form.post(route('submissions.store'), { preserveScroll: true, onSuccess: () => form.reset() });
const tone = { submitted: 'bg-milk text-brown', in_review: 'bg-pink-soft text-star-800', accepted: 'bg-green-100 text-green-800', declined: 'bg-gray-100 text-gray-600' };
</script>
<template>
    <Head title="My submissions" />
    <AuthenticatedLayout>
        <template #header><h1 class="font-display text-4xl font-extrabold">My submissions</h1></template>
        <div class="grid gap-8 lg:grid-cols-[1.2fr_1fr]">
            <form @submit.prevent="submit" class="space-y-4 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                <div>
                    <label class="text-sm font-semibold">Title</label>
                    <input v-model="form.title" class="mt-1 w-full rounded-lg border-brown-soft/60 focus:border-star focus:ring-star" />
                    <InputError :message="form.errors.title" />
                </div>
                <div>
                    <label class="text-sm font-semibold">Type</label>
                    <select v-model="form.kind" class="mt-1 w-full rounded-lg border-brown-soft/60 focus:border-star focus:ring-star">
                        <option value="article">Article</option><option value="poem">Poem / story</option>
                        <option value="photo">Photo essay</option><option value="letter">Old boy's letter</option><option value="news">House / school news</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold">Your piece</label>
                    <textarea v-model="form.body" rows="10" class="mt-1 w-full rounded-lg border-brown-soft/60 font-serif focus:border-star focus:ring-star"></textarea>
                    <InputError :message="form.errors.body" />
                </div>
                <button :disabled="form.processing" class="rounded-full bg-star px-6 py-2.5 text-sm font-bold text-white disabled:opacity-50">Send to the editors</button>
            </form>
            <div>
                <p v-if="!submissions.length" class="rounded-2xl border border-dashed border-brown-soft/60 p-6 text-sm text-ink/60">Nothing submitted yet.</p>
                <ul class="space-y-3">
                    <li v-for="s in submissions" :key="s.id" class="flex items-center justify-between gap-3 rounded-xl bg-white p-4 ring-1 ring-brown-soft/30">
                        <div class="min-w-0"><p class="truncate font-semibold">{{ s.title }}</p><p class="text-xs capitalize text-ink/60">{{ s.kind }} · {{ new Date(s.created_at).toLocaleDateString() }}</p></div>
                        <span :class="tone[s.status]" class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize">{{ s.status.replace('_', ' ') }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
