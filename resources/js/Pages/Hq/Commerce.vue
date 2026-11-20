<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { naira } from '@/money';
const props = defineProps({ gateway: Object, packages: Array, sponsorships: Array, businesses: Array, ads: Array, slots: Array,
    featuredMonthKobo: Number, printEditions: Array, orders: Array, statuses: Array });
const tab = ref('sponsors');
const tabs = { sponsors: 'Sponsorships', directory: 'Directory', ads: 'Ads & notices', print: 'Print' };
const opts = { preserveScroll: true };
const k2n = (k) => (k || 0) / 100;

const pkg = reactive({ name: '', target: 'any', price_naira: '', perks: '', active: true, sort: 0 });
const addPackage = () => router.post(route('hq.packages.store'), pkg, { ...opts, onSuccess: () => Object.assign(pkg, { name: '', price_naira: '', perks: '' }) });
const pkgEdits = reactive(Object.fromEntries(props.packages.map((p) => [p.id, { name: p.name, target: p.target, price_naira: k2n(p.price_kobo), perks: p.perks || '', active: p.active, sort: p.sort }])));
const savePackage = (p) => router.put(route('hq.packages.update', p.id), pkgEdits[p.id], opts);
const names = reactive(Object.fromEntries(props.sponsorships.map((s) => [s.id, s.sponsor_name])));
const reviewSponsor = (s, status, remove_logo = false) => router.put(route('hq.sponsorships.review', s.id), { status, sponsor_name: names[s.id], remove_logo }, opts);

const notes = reactive({});
const reviewBusiness = (b, status) => router.put(route('hq.businesses.review', b.id), { status, review_note: notes['b' + b.id] || null }, opts);
const reviewAd = (a, status) => router.put(route('hq.ads.review', a.id), { status, review_note: notes['a' + a.id] || null }, opts);
const slotEdits = reactive(Object.fromEntries(props.slots.map((s) => [s.id, { price_naira: k2n(s.price_kobo), period_days: s.period_days, active: s.active }])));
const saveSlot = (s) => router.put(route('hq.slots.update', s.id), slotEdits[s.id], opts);
const featured = ref(k2n(props.featuredMonthKobo));
const saveFeatured = () => router.put(route('hq.featured.price'), { price_naira: featured.value }, opts);

const printEdits = reactive(Object.fromEntries(props.printEditions.map((e) => [e.id, { print_available: e.print_available, price_naira: e.print_price_kobo ? k2n(e.print_price_kobo) : '', print_stock: e.print_stock }])));
const savePrint = (e) => router.put(route('hq.print.update', e.id), printEdits[e.id], opts);
const orderEdits = reactive(Object.fromEntries(props.orders.map((o) => [o.id, { status: o.status, tracking_note: o.tracking_note || '' }])));
const saveOrder = (o) => router.put(route('hq.orders.update', o.id), orderEdits[o.id], opts);
const badge = (s) => ({ paid: 'bg-pink-soft', pending: 'bg-pink-soft', approved: 'bg-green-100 text-green-800', rejected: 'bg-gray-100 text-gray-500' }[s] || 'bg-milk');
const input = 'rounded-lg border-brown-soft/60 text-sm';
</script>
<template>
    <Head title="HQ · Commerce" />
    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('hq.index')" class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">← HQ</Link>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <h1 class="font-display text-4xl font-extrabold">Commerce</h1>
                <div class="flex gap-2"><Link :href="route('hq.revenue')" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk">Revenue</Link>
                    <Link :href="route('hq.bulk')" class="rounded-full bg-white px-4 py-2 text-sm font-semibold ring-1 ring-brown-soft/40">Bulk upload</Link></div>
            </div>
        </template>
        <div :class="gateway.configured ? 'bg-white' : 'bg-pink-soft'" class="mb-6 rounded-2xl p-4 text-sm ring-1 ring-brown-soft/30">
            <template v-if="gateway.configured">{{ gateway.label }} connected <b>({{ gateway.test ? 'TEST / sandbox' : 'LIVE' }})</b> via PAYMENT_METHOD={{ gateway.method }}. Webhook URL: <code class="select-all">{{ gateway.webhook }}</code></template>
            <template v-else><b>{{ gateway.label }} keys are not set</b> (PAYMENT_METHOD={{ gateway.method }}) — paid features are hidden.
                <span v-if="gateway.method === 'monnify'">Add MONNIFY_API_KEY, MONNIFY_SECRET_KEY and MONNIFY_CONTRACT_CODE in Coolify.</span>
                <span v-else>Add PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY in Coolify.</span>
                Webhook URL to register: <code class="select-all">{{ gateway.webhook }}</code></template>
        </div>
        <div class="flex flex-wrap gap-2">
            <button v-for="(l, k) in tabs" :key="k" @click="tab = k" :class="tab === k ? 'bg-star text-white' : 'bg-white ring-1 ring-brown-soft/40'" class="rounded-full px-4 py-1.5 text-sm font-semibold">{{ l }}</button>
        </div>

        <section v-if="tab === 'sponsors'" class="mt-6 space-y-6">
            <div class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                <h2 class="font-display text-xl font-bold">Packages</h2>
                <div v-for="p in packages" :key="p.id" class="mt-3 grid gap-2 border-t border-milk pt-3 md:grid-cols-[1fr_120px_110px_1.5fr_auto]">
                    <input v-model="pkgEdits[p.id].name" :class="input" />
                    <select v-model="pkgEdits[p.id].target" :class="input"><option value="any">Any</option><option value="edition">Edition</option><option value="homecoming">Homecoming</option></select>
                    <input v-model="pkgEdits[p.id].price_naira" type="number" :class="input" title="Price (₦)" />
                    <input v-model="pkgEdits[p.id].perks" :class="input" placeholder="Perks" />
                    <div class="flex items-center gap-2 text-xs"><label><input type="checkbox" v-model="pkgEdits[p.id].active" class="rounded text-star" /> active</label>
                        <button @click="savePackage(p)" class="rounded-full bg-ink px-3 py-1 font-semibold text-milk">Save</button>
                        <button @click="router.delete(route('hq.packages.destroy', p.id), opts)" class="text-ink/50">Remove</button></div>
                </div>
                <form @submit.prevent="addPackage" class="mt-4 grid gap-2 border-t border-milk pt-4 md:grid-cols-[1fr_120px_110px_1.5fr_auto]">
                    <input v-model="pkg.name" placeholder="New package name" :class="input" />
                    <select v-model="pkg.target" :class="input"><option value="any">Any</option><option value="edition">Edition</option><option value="homecoming">Homecoming</option></select>
                    <input v-model="pkg.price_naira" type="number" placeholder="₦" :class="input" />
                    <input v-model="pkg.perks" placeholder="Perks (e.g. logo on edition page)" :class="input" />
                    <button class="rounded-full bg-star px-4 py-1.5 text-sm font-semibold text-white">Add</button>
                </form>
            </div>
            <div class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                <h2 class="font-display text-xl font-bold">Paid sponsors — approve name & logo</h2>
                <p v-if="!sponsorships.length" class="mt-2 text-sm text-ink/60">No paid sponsorships yet.</p>
                <div v-for="s in sponsorships" :key="s.id" class="mt-3 flex flex-wrap items-center gap-3 border-t border-milk pt-3 text-sm">
                    <img v-if="s.logo" :src="s.logo" class="h-10 max-w-[120px] object-contain" />
                    <input v-model="names[s.id]" :class="input" class="w-56" />
                    <span class="text-xs text-ink/60">{{ s.sponsor_type }}<span v-if="s.set_year"> {{ s.set_year }}</span><span v-if="s.house"> · {{ s.house }}</span> · {{ s.package }} · {{ naira(s.amount_kobo) }} · {{ s.target === 'edition' ? s.edition : `Homecoming ${s.homecoming_year}` }} · {{ s.email }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="badge(s.status)">{{ s.status }}</span>
                    <button @click="reviewSponsor(s, 'approved')" class="rounded-full bg-star px-3 py-1 text-xs font-semibold text-white">Approve</button>
                    <button @click="reviewSponsor(s, 'rejected')" class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-brown-soft/40">Hide</button>
                    <button v-if="s.logo" @click="reviewSponsor(s, s.status, true)" class="text-xs text-ink/50">Remove logo</button>
                    <p v-if="s.message" class="w-full text-xs text-ink/60">“{{ s.message }}”</p>
                </div>
            </div>
        </section>

        <section v-if="tab === 'directory'" class="mt-6 space-y-3">
            <form @submit.prevent="saveFeatured" class="flex items-center gap-2 text-sm">Featured listing price per month ₦ <input v-model="featured" type="number" :class="input" class="w-32" /><button class="rounded-full bg-ink px-3 py-1 text-xs font-semibold text-milk">Save</button></form>
            <p v-if="!businesses.length" class="text-sm text-ink/60">No listings yet.</p>
            <div v-for="b in businesses" :key="b.id" class="rounded-2xl bg-white p-4 text-sm ring-1 ring-brown-soft/30">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span><b>{{ b.name }}</b> · {{ b.category }} · {{ b.state }} <span class="text-xs text-ink/60">by {{ b.owner?.name }}</span>
                        <span v-if="b.featured" class="ml-1 text-xs font-semibold text-star">featured → {{ b.featured_until }}</span></span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="badge(b.status)">{{ b.status }}</span>
                </div>
                <p class="mt-1 text-ink/70">{{ b.description }}</p>
                <p class="text-xs text-ink/50">{{ [b.phone, b.whatsapp, b.website].filter(Boolean).join(' · ') }}</p>
                <div class="mt-2 flex flex-wrap gap-2"><input v-model="notes['b' + b.id]" placeholder="Note to owner (optional)" :class="input" class="flex-1" />
                    <button @click="reviewBusiness(b, 'approved')" class="rounded-full bg-star px-3 py-1 text-xs font-semibold text-white">Approve</button>
                    <button @click="reviewBusiness(b, 'rejected')" class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-brown-soft/40">Reject</button></div>
            </div>
        </section>

        <section v-if="tab === 'ads'" class="mt-6 space-y-4">
            <div class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                <h2 class="font-display text-xl font-bold">Slots & prices</h2>
                <div v-for="s in slots" :key="s.id" class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <span class="w-56 font-semibold">{{ s.name }}</span> ₦<input v-model="slotEdits[s.id].price_naira" type="number" :class="input" class="w-28" />
                    per <input v-model="slotEdits[s.id].period_days" type="number" :class="input" class="w-20" /> days
                    <label class="text-xs"><input type="checkbox" v-model="slotEdits[s.id].active" class="rounded text-star" /> active</label>
                    <button @click="saveSlot(s)" class="rounded-full bg-ink px-3 py-1 text-xs font-semibold text-milk">Save</button>
                </div>
            </div>
            <p v-if="!ads.length" class="text-sm text-ink/60">No paid ads yet.</p>
            <div v-for="a in ads" :key="a.id" class="grid gap-3 rounded-2xl bg-white p-4 text-sm ring-1 ring-brown-soft/30 md:grid-cols-[160px_1fr]">
                <img v-if="a.image" :src="a.image" class="max-h-32 w-full rounded-lg object-cover" /><div v-else></div>
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2"><b>{{ a.title }}</b><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="badge(a.status)">{{ a.status }}</span></div>
                    <p class="text-xs text-ink/60">{{ a.kind }} · {{ a.slot_key }} · {{ a.advertiser }} ({{ a.email }}) · {{ a.audience }} · {{ a.starts_on }} → {{ a.ends_on }} · {{ naira(a.amount_kobo) }}</p>
                    <p v-if="a.body" class="mt-1 text-ink/75">{{ a.body }}</p>
                    <p v-if="a.link_url" class="text-xs text-ink/50">{{ a.link_url }}</p>
                    <p class="mt-1 text-xs">{{ a.impressions }} impressions · {{ a.clicks }} clicks · CTR {{ a.ctr }}%</p>
                    <div class="mt-2 flex flex-wrap gap-2"><input v-model="notes['a' + a.id]" placeholder="Note to advertiser (optional)" :class="input" class="flex-1" />
                        <button @click="reviewAd(a, 'approved')" class="rounded-full bg-star px-3 py-1 text-xs font-semibold text-white">Approve</button>
                        <button @click="reviewAd(a, 'rejected')" class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-brown-soft/40">Reject</button></div>
                </div>
            </div>
        </section>

        <section v-if="tab === 'print'" class="mt-6 space-y-6">
            <div class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                <h2 class="font-display text-xl font-bold">Orders</h2>
                <p v-if="!orders.length" class="mt-2 text-sm text-ink/60">No paid orders yet.</p>
                <div v-for="o in orders" :key="o.id" class="mt-3 border-t border-milk pt-3 text-sm">
                    <p><b>#{{ o.id }}</b> {{ o.edition?.title }} × {{ o.quantity }} · {{ naira(o.amount_kobo) }} · {{ o.user?.name }} ({{ o.user?.email }}) · {{ o.created }}</p>
                    <p class="text-xs text-ink/60">{{ o.fulfilment === 'pickup' ? 'Pickup at school' : `${o.address}, ${o.city}, ${o.state}` }} · {{ o.name }} {{ o.phone }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <select v-model="orderEdits[o.id].status" :class="input"><option v-for="s in statuses.filter((x) => x !== 'pending_payment')" :key="s" :value="s">{{ s }}</option></select>
                        <input v-model="orderEdits[o.id].tracking_note" placeholder="Tracking note (emailed to the member)" :class="input" class="flex-1" />
                        <button @click="saveOrder(o)" class="rounded-full bg-ink px-3 py-1 text-xs font-semibold text-milk">Update</button>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                <h2 class="font-display text-xl font-bold">Editions in print</h2>
                <div v-for="e in printEditions" :key="e.id" class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <span class="w-64 truncate">{{ e.title }}{{ e.year ? ` (${e.year})` : '' }}</span>
                    <label class="text-xs"><input type="checkbox" v-model="printEdits[e.id].print_available" class="rounded text-star" /> in print</label>
                    ₦<input v-model="printEdits[e.id].price_naira" type="number" :class="input" class="w-24" />
                    stock <input v-model="printEdits[e.id].print_stock" type="number" min="0" :class="input" class="w-20" />
                    <button @click="savePrint(e)" class="rounded-full bg-ink px-3 py-1 text-xs font-semibold text-milk">Save</button>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
