<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import InputError from '@/Components/InputError.vue';
import { naira } from '@/money';
const props = defineProps({ slots: Array, mine: Array });
const page = usePage();
const user = page.props.auth.user;
const today = new Date().toISOString().slice(0, 10);
const form = useForm({ slot_key: props.slots[0]?.key, kind: 'ad', advertiser: user?.name || '', email: user?.email || '', title: '', body: '',
    link_url: '', audience: 'all', starts_on: today, periods: 1, image: null });
const slot = computed(() => props.slots.find((s) => s.key === form.slot_key));
const pick = (s) => { form.slot_key = s.key; form.kind = s.kind === 'notice' ? 'congratulatory' : 'ad'; };
const submit = () => form.post(route('advertise.store'), { forceFormData: true });
const input = 'mt-1 w-full rounded-lg border-brown-soft/60 focus:border-star focus:ring-star';
</script>
<template>
    <SiteLayout title="Advertise & notices">
        <section class="mx-auto max-w-5xl px-5 py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Reach the GCU family</p>
            <h1 class="mt-2 font-display text-4xl font-extrabold">Advertise, congratulate or remember</h1>
            <p class="mt-3 max-w-2xl font-serif text-ink/75">Place an ad on the site or in the monthly digest, or a congratulatory or obituary notice. The editors review every creative before it runs.</p>
            <div class="mt-8 grid gap-3 md:grid-cols-4">
                <button v-for="s in slots" :key="s.key" type="button" @click="pick(s)" :class="form.slot_key === s.key ? 'ring-2 ring-star' : 'ring-1 ring-brown-soft/30'" class="rounded-2xl bg-white p-4 text-left">
                    <p class="font-semibold">{{ s.name }}</p>
                    <p class="mt-1 text-xl font-extrabold text-star">{{ naira(s.price_kobo) }}</p>
                    <p class="text-xs text-ink/50">per {{ s.period_days }} days<span v-if="s.size_hint"> · {{ s.size_hint }}</span></p>
                </button>
            </div>
            <div v-if="!page.props.payments" class="mt-8 rounded-2xl bg-pink-soft p-5 text-sm">Online payment isn't available right now — please contact the editors.</div>
            <form v-else-if="slot" @submit.prevent="submit" class="mt-8 grid gap-4 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30 md:grid-cols-2">
                <div v-if="slot.kind === 'notice'"><label class="text-sm font-semibold">Notice type</label>
                    <select v-model="form.kind" :class="input"><option value="congratulatory">Congratulatory</option><option value="obituary">Obituary / in memoriam</option></select><InputError :message="form.errors.kind" /></div>
                <div><label class="text-sm font-semibold">From (person or company)</label><input v-model="form.advertiser" :class="input" /><InputError :message="form.errors.advertiser" /></div>
                <div><label class="text-sm font-semibold">Email for receipt</label><input v-model="form.email" type="email" :class="input" /><InputError :message="form.errors.email" /></div>
                <div class="md:col-span-2"><label class="text-sm font-semibold">Headline</label><input v-model="form.title" maxlength="160" :class="input" /><InputError :message="form.errors.title" /></div>
                <div class="md:col-span-2"><label class="text-sm font-semibold">Text</label><textarea v-model="form.body" maxlength="1000" rows="3" :class="input"></textarea></div>
                <div><label class="text-sm font-semibold">Link (optional)</label><input v-model="form.link_url" placeholder="https://" :class="input" /><InputError :message="form.errors.link_url" /></div>
                <div><label class="text-sm font-semibold">Image (optional)</label><input type="file" accept="image/*" @input="form.image = $event.target.files[0]" class="mt-1 block text-sm" /><InputError :message="form.errors.image" /></div>
                <div><label class="text-sm font-semibold">Show to</label>
                    <select v-model="form.audience" :class="input"><option value="all">Everyone</option><option value="members">Signed-in members only</option><option value="guests">Visitors (not signed in) only</option></select></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-sm font-semibold">Start</label><input v-model="form.starts_on" type="date" :min="today" :class="input" /><InputError :message="form.errors.starts_on" /></div>
                    <div><label class="text-sm font-semibold">Periods</label><input v-model="form.periods" type="number" min="1" max="12" :class="input" /></div>
                </div>
                <div class="md:col-span-2 flex items-center justify-between gap-3">
                    <p class="text-sm text-ink/60">{{ form.periods * slot.period_days }} days · runs after editor approval</p>
                    <button :disabled="form.processing" class="rounded-full bg-star px-6 py-2.5 text-sm font-bold text-white disabled:opacity-50">Pay {{ naira(slot.price_kobo * form.periods) }}</button>
                </div>
            </form>
            <div v-if="mine.length" class="mt-10">
                <h2 class="font-display text-2xl font-bold">Your ads & notices</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li v-for="a in mine" :key="a.id" class="flex flex-wrap justify-between gap-2 rounded-xl bg-white p-3 ring-1 ring-brown-soft/30">
                        <span><b>{{ a.title }}</b> · {{ a.slot_key.replace('_', ' ') }} · {{ a.starts_on }} → {{ a.ends_on }}<span v-if="a.review_note" class="block text-xs text-star">{{ a.review_note }}</span></span>
                        <span class="text-xs text-ink/60">{{ a.status.replace('_', ' ') }} · {{ a.impressions }} views · {{ a.clicks }} clicks</span>
                    </li>
                </ul>
            </div>
        </section>
    </SiteLayout>
</template>
