<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { naira } from '@/money';
const props = defineProps({ totals: Object, months: Array, recent: Array });
const labels = { sponsorship: 'Sponsorships', listing: 'Featured listings', ad: 'Ads & notices', print: 'Print orders' };
const colors = { sponsorship: 'bg-star', listing: 'bg-brown', ad: 'bg-pink', print: 'bg-ink' };
const grand = computed(() => Object.values(props.totals).reduce((a, b) => a + b, 0));
const sum = (m) => Object.keys(labels).reduce((a, k) => a + (m[k] || 0), 0);
const max = computed(() => Math.max(1, ...props.months.map(sum)));
</script>
<template>
    <Head title="HQ · Revenue" />
    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('hq.commerce')" class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">← Commerce</Link>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <h1 class="font-display text-4xl font-extrabold">Revenue</h1>
                <a :href="route('hq.revenue.export')" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk">Export CSV</a>
            </div>
        </template>
        <div class="grid gap-4 sm:grid-cols-5">
            <div class="rounded-2xl bg-star p-4 text-white"><p class="text-xs">All time</p><p class="font-display text-2xl font-bold">{{ naira(grand) }}</p></div>
            <div v-for="(l, k) in labels" :key="k" class="rounded-2xl bg-white p-4 ring-1 ring-brown-soft/30"><p class="text-xs text-ink/60">{{ l }}</p><p class="font-display text-xl font-bold">{{ naira(totals[k]) }}</p></div>
        </div>
        <div class="mt-8 rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
            <h2 class="font-display text-xl font-bold">Last 12 months</h2>
            <div class="mt-4 flex h-48 items-end gap-2">
                <div v-for="m in months" :key="m.month" class="flex flex-1 flex-col items-center gap-1" :title="`${m.month}: ${naira(sum(m))}`">
                    <div class="flex w-full flex-col-reverse overflow-hidden rounded-t" :style="{ height: (sum(m) / max * 160) + 'px' }">
                        <div v-for="(l, k) in labels" :key="k" :class="colors[k]" :style="{ height: (sum(m) ? (m[k] / sum(m) * 100) : 0) + '%' }"></div>
                    </div>
                    <span class="text-[10px] text-ink/50">{{ m.month.slice(2) }}</span>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-3 text-xs"><span v-for="(l, k) in labels" :key="k" class="flex items-center gap-1"><i :class="colors[k]" class="inline-block h-3 w-3 rounded"></i>{{ l }}</span></div>
            <table class="mt-4 w-full text-sm">
                <thead><tr class="text-left text-xs text-ink/60"><th>Month</th><th v-for="(l, k) in labels" :key="k" class="text-right">{{ l }}</th><th class="text-right">Total</th></tr></thead>
                <tbody><tr v-for="m in [...months].reverse()" :key="m.month" class="border-t border-milk"><td>{{ m.month }}</td><td v-for="(l, k) in labels" :key="k" class="text-right">{{ naira(m[k]) }}</td><td class="text-right font-semibold">{{ naira(sum(m)) }}</td></tr></tbody>
            </table>
        </div>
        <div class="mt-8 rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
            <h2 class="font-display text-xl font-bold">Recent payments</h2>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-xs text-ink/60"><th>Reference</th><th>Gateway</th><th>Purpose</th><th>Payer</th><th class="text-right">Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody><tr v-for="p in recent" :key="p.id" class="border-t border-milk">
                    <td class="font-mono text-xs">{{ p.reference }}</td><td class="capitalize">{{ p.gateway || "paystack" }}</td><td>{{ labels[p.purpose] || p.purpose }}</td><td>{{ p.user || p.email }}</td>
                    <td class="text-right">{{ naira(p.amount_kobo) }}</td><td :class="p.status === 'success' ? 'text-green-700' : 'text-ink/50'">{{ p.status }}</td><td class="text-xs">{{ p.paid_at || p.created_at }}</td></tr></tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
