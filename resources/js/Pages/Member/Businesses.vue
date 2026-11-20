<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { naira } from '@/money';
const props = defineProps({ businesses: Array, categories: Array, states: Array, featuredMonthKobo: Number });
const page = usePage();
const blank = { name: '', category: '', description: '', state: '', location: '', phone: '', whatsapp: '', website: '', logo: null };
const form = useForm({ ...blank });
const editing = ref(null);
const edit = (b) => { editing.value = b; Object.assign(form, { ...blank, ...Object.fromEntries(Object.keys(blank).filter((k) => k !== 'logo').map((k) => [k, b[k] || ''])) }); };
const reset = () => { editing.value = null; form.reset(); Object.assign(form, blank); };
const save = () => form.post(editing.value ? route('businesses.update', editing.value.slug) : route('businesses.store'), { forceFormData: true, preserveScroll: true, onSuccess: reset });
const remove = (b) => confirm(`Remove ${b.name}?`) && router.delete(route('businesses.destroy', b.slug), { preserveScroll: true });
const months = ref({});
const feature = (b) => router.post(route('businesses.feature', b.slug), { months: months.value[b.id] || 1 });
const input = 'mt-1 w-full rounded-lg border-brown-soft/60 focus:border-star focus:ring-star';
const badge = { pending: 'bg-pink-soft', approved: 'bg-green-100 text-green-800', rejected: 'bg-gray-100 text-gray-500' };
</script>
<template>
    <Head title="My business listing" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="font-display text-4xl font-extrabold">My business listing</h1>
            <p class="mt-1 text-sm text-ink/60">Basic listings are free. Editors approve each listing (and every edit) before it appears in the <Link :href="route('directory')" class="font-semibold text-star">directory</Link>.</p>
        </template>
        <div class="space-y-3">
            <div v-for="b in businesses" :key="b.id" class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span><b>{{ b.name }}</b> <span class="text-xs text-ink/60">· {{ b.category }} · {{ b.state }}</span></span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="badge[b.status]">{{ b.status }}</span>
                </div>
                <p v-if="b.review_note" class="mt-1 text-xs text-star">Editor's note: {{ b.review_note }}</p>
                <p v-if="b.featured" class="mt-1 text-xs font-semibold text-star">Featured until {{ b.featured_until }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <button @click="edit(b)" class="rounded-full bg-milk px-3 py-1 font-semibold ring-1 ring-brown-soft/40">Edit</button>
                    <button @click="remove(b)" class="rounded-full px-3 py-1 font-semibold text-ink/60">Remove</button>
                    <template v-if="page.props.payments">
                        <select v-model="months[b.id]" class="rounded-full border-brown-soft/60 py-1 text-xs"><option :value="1">1 month</option><option :value="3">3 months</option><option :value="6">6 months</option><option :value="12">12 months</option></select>
                        <button @click="feature(b)" class="rounded-full bg-star px-3 py-1 font-semibold text-white">{{ b.featured ? 'Extend' : 'Feature' }} for {{ naira(featuredMonthKobo * (months[b.id] || 1)) }}</button>
                    </template>
                </div>
            </div>
        </div>
        <form @submit.prevent="save" class="mt-8 grid gap-4 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30 md:grid-cols-2">
            <h2 class="font-display text-xl font-bold md:col-span-2">{{ editing ? `Edit ${editing.name}` : 'Add a business' }}</h2>
            <div><label class="text-sm font-semibold">Business name</label><input v-model="form.name" :class="input" /><InputError :message="form.errors.name" /></div>
            <div><label class="text-sm font-semibold">Category</label><select v-model="form.category" :class="input"><option value="">Choose…</option><option v-for="c in categories" :key="c">{{ c }}</option></select><InputError :message="form.errors.category" /></div>
            <div class="md:col-span-2"><label class="text-sm font-semibold">Description</label><textarea v-model="form.description" rows="3" maxlength="2000" :class="input"></textarea><InputError :message="form.errors.description" /></div>
            <div><label class="text-sm font-semibold">State</label><select v-model="form.state" :class="input"><option value="">Choose…</option><option v-for="s in states" :key="s">{{ s }}</option></select><InputError :message="form.errors.state" /></div>
            <div><label class="text-sm font-semibold">Town / address</label><input v-model="form.location" :class="input" /></div>
            <div><label class="text-sm font-semibold">Phone</label><input v-model="form.phone" :class="input" /></div>
            <div><label class="text-sm font-semibold">WhatsApp</label><input v-model="form.whatsapp" placeholder="+234…" :class="input" /></div>
            <div><label class="text-sm font-semibold">Website</label><input v-model="form.website" placeholder="https://" :class="input" /><InputError :message="form.errors.website" /></div>
            <div><label class="text-sm font-semibold">Logo</label><input type="file" accept="image/*" @input="form.logo = $event.target.files[0]" class="mt-1 block text-sm" /><InputError :message="form.errors.logo" /></div>
            <div class="flex gap-2 md:col-span-2">
                <button :disabled="form.processing" class="rounded-full bg-star px-6 py-2.5 text-sm font-bold text-white">{{ editing ? 'Save & resubmit' : 'Submit for approval' }}</button>
                <button v-if="editing" type="button" @click="reset" class="rounded-full px-4 py-2.5 text-sm font-semibold text-ink/60">Cancel</button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
