<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { naira } from '@/money';
defineProps({ orders: Array });
const label = { pending_payment: 'Awaiting payment', paid: 'Paid', printing: 'Printing', shipped: 'Shipped', ready: 'Ready for pickup', delivered: 'Delivered', cancelled: 'Cancelled' };
</script>
<template>
    <Head title="My print orders" />
    <AuthenticatedLayout>
        <template #header><h1 class="font-display text-4xl font-extrabold">Print orders</h1></template>
        <p v-if="!orders.length" class="text-sm text-ink/60">No orders yet. Editions available in print show an “Order a printed copy” button.</p>
        <ul class="space-y-3">
            <li v-for="o in orders" :key="o.id" class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
                <div class="flex flex-wrap justify-between gap-2">
                    <span><b>#{{ o.id }}</b> · <Link v-if="o.edition" :href="route('editions.show', o.edition.slug)" class="font-semibold hover:text-star">{{ o.edition.title }}</Link> × {{ o.quantity }}</span>
                    <span class="rounded-full bg-milk px-3 py-0.5 text-xs font-semibold ring-1 ring-brown-soft/40">{{ label[o.status] || o.status }}</span>
                </div>
                <p class="mt-1 text-sm text-ink/60">{{ naira(o.amount_kobo) }} · {{ o.fulfilment === 'pickup' ? 'Pickup at school' : `Delivery to ${o.state}` }} · {{ o.created_at }}</p>
                <p v-if="o.tracking_note" class="mt-1 text-sm">Note: {{ o.tracking_note }}</p>
            </li>
        </ul>
    </AuthenticatedLayout>
</template>
