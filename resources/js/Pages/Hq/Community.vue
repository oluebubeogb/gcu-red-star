<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
const props = defineProps({ events: Array, announcements: Array, features: Array, houses: Array, jubileeSet: Number, digestRecipients: Number });
const input = 'mt-1 w-full rounded-lg border-brown-soft/60 text-sm focus:border-star focus:ring-star';

const blankEvent = () => ({ id: null, title: `GCUOBA Homecoming ${props.jubileeSet + 50} — Class of ${props.jubileeSet} Golden Jubilee`, set_year: props.jubileeSet, starts_on: '', ends_on: '', venue: 'Government College Umuahia, Abia State', description: '', published: true, cover: null });
const ev = useForm(blankEvent());
const editEvent = (e) => { Object.assign(ev, { ...e, cover: null }); window.scrollTo({ top: 0, behavior: 'smooth' }); };
const saveEvent = () => ev.id
    ? ev.transform((d) => ({ ...d, _method: 'put' })).post(route('hq.events.update', ev.id), { forceFormData: true, preserveScroll: true, onSuccess: () => Object.assign(ev, blankEvent()) })
    : ev.post(route('hq.events.store'), { forceFormData: true, preserveScroll: true, onSuccess: () => Object.assign(ev, blankEvent()) });
const delEvent = (e) => confirm(`Delete “${e.title}”?`) && router.delete(route('hq.events.destroy', e.id), { preserveScroll: true });

const blankAnn = () => ({ id: null, title: '', body: '', set_year: '', house: '', published: true });
const an = useForm(blankAnn());
const editAnn = (a) => Object.assign(an, { id: a.id, title: a.title, body: a.body, set_year: a.set_year ?? '', house: a.house ?? '', published: a.published });
const saveAnn = () => (an.id ? an.put(route('hq.announcements.update', an.id), { preserveScroll: true, onSuccess: () => Object.assign(an, blankAnn()) })
    : an.post(route('hq.announcements.store'), { preserveScroll: true, onSuccess: () => Object.assign(an, blankAnn()) }));
const delAnn = (a) => confirm('Delete announcement?') && router.delete(route('hq.announcements.destroy', a.id), { preserveScroll: true });

const ft = useForm({ username: '', month: new Date().toISOString().slice(0, 7), blurb: '' });
const saveFeature = () => ft.post(route('hq.features.store'), { preserveScroll: true, onSuccess: () => ft.reset('username', 'blurb') });
const delFeature = (f) => router.delete(route('hq.features.destroy', f.id), { preserveScroll: true });
const testDigest = () => router.post(route('hq.digest.test'), {}, { preserveScroll: true });
</script>
<template>
    <Head title="Community · HQ" />
    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('hq.index')" class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">← Editorial HQ</Link>
            <h1 class="mt-2 font-display text-4xl font-extrabold">Community</h1>
        </template>
        <div class="grid gap-8 lg:grid-cols-2">
            <section class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                <h2 class="font-display text-xl font-bold">Homecoming events</h2>
                <p class="mt-1 text-sm text-ink/60">Next jubilee set: Class of {{ jubileeSet }} (admitted {{ jubileeSet }}, homecoming {{ jubileeSet + 50 }}).</p>
                <form @submit.prevent="saveEvent" class="mt-4 space-y-3">
                    <div><label class="text-sm font-semibold">Title</label><input v-model="ev.title" :class="input" /><InputError :message="ev.errors.title" /></div>
                    <div class="grid grid-cols-3 gap-2">
                        <div><label class="text-sm font-semibold">Set (admission yr)</label><input v-model="ev.set_year" type="number" :class="input" /></div>
                        <div><label class="text-sm font-semibold">Starts</label><input v-model="ev.starts_on" type="date" :class="input" /></div>
                        <div><label class="text-sm font-semibold">Ends</label><input v-model="ev.ends_on" type="date" :class="input" /><InputError :message="ev.errors.ends_on" /></div>
                    </div>
                    <div><label class="text-sm font-semibold">Venue</label><input v-model="ev.venue" :class="input" /></div>
                    <div><label class="text-sm font-semibold">Description</label><textarea v-model="ev.description" rows="3" :class="input"></textarea></div>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <input type="file" accept="image/*" @input="ev.cover = $event.target.files[0]" />
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="ev.published" class="rounded text-star" /> Published</label>
                    </div>
                    <InputError :message="ev.errors.cover" />
                    <div class="flex gap-2">
                        <button :disabled="ev.processing" class="rounded-full bg-star px-5 py-2 text-sm font-bold text-white">{{ ev.id ? 'Save event' : 'Add event' }}</button>
                        <button v-if="ev.id" type="button" @click="Object.assign(ev, blankEvent())" class="text-sm font-semibold text-ink/60">Cancel</button>
                    </div>
                </form>
                <ul class="mt-5 divide-y divide-milk text-sm">
                    <li v-for="e in events" :key="e.id" class="flex items-center justify-between gap-3 py-2">
                        <span><b>{{ e.title }}</b><br><span class="text-xs text-ink/60">Set {{ e.set_year }} · {{ e.starts_on || 'no date' }} · {{ e.is_past ? 'past' : 'upcoming' }} · {{ e.published ? 'published' : 'draft' }}</span></span>
                        <span class="flex shrink-0 gap-3"><button @click="editEvent(e)" class="font-semibold text-star">Edit</button><button @click="delEvent(e)" class="text-red-700">Delete</button></span>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30">
                <h2 class="font-display text-xl font-bold">Old Boy of the month</h2>
                <form @submit.prevent="saveFeature" class="mt-4 space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="text-sm font-semibold">Member username</label><input v-model="ft.username" placeholder="e.g. chinedu" :class="input" /><InputError :message="ft.errors.username" /></div>
                        <div><label class="text-sm font-semibold">Month</label><input v-model="ft.month" type="month" :class="input" /><InputError :message="ft.errors.month" /></div>
                    </div>
                    <div><label class="text-sm font-semibold">Blurb</label><textarea v-model="ft.blurb" rows="4" :class="input"></textarea><InputError :message="ft.errors.blurb" /></div>
                    <button :disabled="ft.processing" class="rounded-full bg-star px-5 py-2 text-sm font-bold text-white">Save feature</button>
                </form>
                <ul class="mt-5 divide-y divide-milk text-sm">
                    <li v-for="f in features" :key="f.id" class="flex items-center justify-between gap-3 py-2">
                        <span><b>{{ f.month }}</b> · {{ f.user?.name }} <span class="text-ink/50">@{{ f.user?.username }}</span></span>
                        <button @click="delFeature(f)" class="text-red-700">Remove</button>
                    </li>
                </ul>
                <div class="mt-8 border-t border-milk pt-5">
                    <h2 class="font-display text-xl font-bold">Monthly digest</h2>
                    <p class="mt-1 text-sm text-ink/60">Sent automatically on the 1st of each month at 09:00 to {{ digestRecipients }} opted-in verified members.</p>
                    <button @click="testDigest" class="mt-3 rounded-full px-5 py-2 text-sm font-semibold ring-1 ring-brown-soft/60">Send test digest to me</button>
                </div>
            </section>

            <section class="rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30 lg:col-span-2">
                <h2 class="font-display text-xl font-bold">Announcements</h2>
                <p class="mt-1 text-sm text-ink/60">General announcements show on the home page and digest. Pick a set or house to post it as set/house news instead.</p>
                <form @submit.prevent="saveAnn" class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr]">
                    <div class="space-y-3">
                        <div><label class="text-sm font-semibold">Title</label><input v-model="an.title" :class="input" /><InputError :message="an.errors.title" /></div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-sm font-semibold">Set (optional)</label><input v-model="an.set_year" type="number" placeholder="e.g. 1976" :class="input" /></div>
                            <div><label class="text-sm font-semibold">House (optional)</label><select v-model="an.house" :class="input"><option value="">—</option><option v-for="h in houses" :key="h">{{ h }}</option></select></div>
                        </div>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="an.published" class="rounded text-star" /> Published</label>
                    </div>
                    <div><label class="text-sm font-semibold">Body</label><textarea v-model="an.body" rows="6" :class="input"></textarea><InputError :message="an.errors.body" /></div>
                    <div class="flex gap-2"><button :disabled="an.processing" class="rounded-full bg-star px-5 py-2 text-sm font-bold text-white">{{ an.id ? 'Save' : 'Post announcement' }}</button>
                        <button v-if="an.id" type="button" @click="Object.assign(an, blankAnn())" class="text-sm font-semibold text-ink/60">Cancel</button></div>
                </form>
                <ul class="mt-5 divide-y divide-milk text-sm">
                    <li v-for="a in announcements" :key="a.id" class="flex items-center justify-between gap-3 py-2">
                        <span><b>{{ a.title }}</b> <span class="text-xs text-ink/60">{{ a.set_year ? `· Class of ${a.set_year}` : '' }}{{ a.house ? ` · ${a.house}` : '' }} · {{ a.published ? 'published' : 'draft' }}</span></span>
                        <span class="flex shrink-0 gap-3"><button @click="editAnn(a)" class="font-semibold text-star">Edit</button><button @click="delAnn(a)" class="text-red-700">Delete</button></span>
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
