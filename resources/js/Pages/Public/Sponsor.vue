<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import InputError from '@/Components/InputError.vue';
import SponsorCredits from '@/Components/SponsorCredits.vue';
import { naira } from '@/money';
const props = defineProps({ packages: Array, editions: Array, houses: Array, homecomingYear: Number, sponsors: Array });
const page = usePage();
const user = page.props.auth.user;
const form = useForm({ package_id: props.packages[0]?.id, sponsor_type: 'individual', sponsor_name: user?.name || '', set_year: user?.admission_year || '',
    house: '', target: 'homecoming', edition_id: '', email: user?.email || '', website: '', message: '', logo: null });
const pkg = computed(() => props.packages.find((p) => p.id === form.package_id));
const submit = () => form.post(route('sponsor.store'), { forceFormData: true });
const input = 'mt-1 w-full rounded-lg border-brown-soft/60 focus:border-star focus:ring-star';
</script>
<template>
    <SiteLayout title="Sponsor The Red Star">
        <section class="mx-auto max-w-5xl px-5 py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Support the magazine</p>
            <h1 class="mt-2 font-display text-4xl font-extrabold">Sponsor an edition or the Homecoming</h1>
            <p class="mt-3 max-w-2xl font-serif text-ink/75">Sets, houses, old boys and companies keep The Red Star in print and online. Sponsors are credited by name and logo once the editors approve them.</p>
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                <button v-for="p in packages" :key="p.id" @click="form.package_id = p.id" type="button"
                        :class="form.package_id === p.id ? 'ring-2 ring-star' : 'ring-1 ring-brown-soft/30'" class="rounded-2xl bg-white p-5 text-left">
                    <p class="font-display text-xl font-bold">{{ p.name }}</p>
                    <p class="mt-1 text-2xl font-extrabold text-star">{{ naira(p.price_kobo) }}</p>
                    <p class="text-xs uppercase text-ink/50">{{ p.target === 'any' ? 'Edition or homecoming' : p.target }}</p>
                    <p v-if="p.perks" class="mt-3 whitespace-pre-line text-sm text-ink/75">{{ p.perks }}</p>
                </button>
                <p v-if="!packages.length" class="text-sm text-ink/60">Sponsorship packages will be announced soon.</p>
            </div>
            <div v-if="!page.props.payments && packages.length" class="mt-8 rounded-2xl bg-pink-soft p-5 text-sm">Online payment isn't available right now — please contact the editors to sponsor.</div>
            <form v-else-if="packages.length" @submit.prevent="submit" class="mt-8 grid gap-4 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30 md:grid-cols-2">
                <div><label class="text-sm font-semibold">Sponsor as</label>
                    <select v-model="form.sponsor_type" :class="input"><option value="individual">Individual</option><option value="set">A set (year of admission)</option><option value="house">A house</option><option value="company">A company</option></select></div>
                <div><label class="text-sm font-semibold">Name to credit</label><input v-model="form.sponsor_name" :class="input" /><InputError :message="form.errors.sponsor_name" /></div>
                <div v-if="form.sponsor_type === 'set'"><label class="text-sm font-semibold">Year of admission</label><input v-model="form.set_year" type="number" :class="input" /><InputError :message="form.errors.set_year" /></div>
                <div v-if="form.sponsor_type === 'house'"><label class="text-sm font-semibold">House</label>
                    <select v-model="form.house" :class="input"><option value="">Choose…</option><option v-for="h in houses" :key="h">{{ h }}</option></select><InputError :message="form.errors.house" /></div>
                <div><label class="text-sm font-semibold">Sponsor</label>
                    <select v-model="form.target" :class="input"><option value="homecoming">Homecoming {{ homecomingYear }}</option><option value="edition">An edition</option></select><InputError :message="form.errors.package_id" /></div>
                <div v-if="form.target === 'edition'"><label class="text-sm font-semibold">Edition</label>
                    <select v-model="form.edition_id" :class="input"><option value="">Choose…</option><option v-for="e in editions" :key="e.id" :value="e.id">{{ e.title }}{{ e.year ? ` (${e.year})` : '' }}</option></select><InputError :message="form.errors.edition_id" /></div>
                <div><label class="text-sm font-semibold">Email for receipt</label><input v-model="form.email" type="email" :class="input" /><InputError :message="form.errors.email" /></div>
                <div><label class="text-sm font-semibold">Website (optional)</label><input v-model="form.website" :class="input" placeholder="https://" /><InputError :message="form.errors.website" /></div>
                <div><label class="text-sm font-semibold">Logo (optional)</label><input type="file" accept="image/*" @input="form.logo = $event.target.files[0]" class="mt-1 block text-sm" /><InputError :message="form.errors.logo" /></div>
                <div class="md:col-span-2"><label class="text-sm font-semibold">Message (optional)</label><input v-model="form.message" maxlength="300" :class="input" /></div>
                <div class="md:col-span-2 flex items-center justify-between gap-3">
                    <p class="text-sm text-ink/60">You'll pay securely with Paystack (card, transfer, USSD).</p>
                    <button :disabled="form.processing || !pkg" class="rounded-full bg-star px-6 py-2.5 text-sm font-bold text-white disabled:opacity-50">Pay {{ pkg ? naira(pkg.price_kobo) : '' }}</button>
                </div>
            </form>
            <div class="mt-10"><SponsorCredits :sponsors="sponsors" title="Recent sponsors" /></div>
        </section>
    </SiteLayout>
</template>
