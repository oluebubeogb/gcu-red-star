<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { naira } from '@/money';
const props = defineProps({ edition: Object, states: Array, defaults: Object });
const page = usePage();
const form = useForm({ quantity: 1, fulfilment: 'delivery', name: props.defaults.name, phone: props.defaults.phone, address: '', city: '', state: '' });
const submit = () => form.post(route('print.store', props.edition.slug));
const input = 'mt-1 w-full rounded-lg border-brown-soft/60 focus:border-star focus:ring-star';
</script>
<template>
    <Head :title="`Order print: ${edition.title}`" />
    <AuthenticatedLayout>
        <template #header>
            <Link :href="route('editions.show', edition.slug)" class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">← {{ edition.title }}</Link>
            <h1 class="mt-2 font-display text-4xl font-extrabold">Order a printed copy</h1>
        </template>
        <div class="grid gap-8 md:grid-cols-[200px_1fr]">
            <img v-if="edition.cover_url" :src="edition.cover_url" :alt="edition.title" class="w-full rounded-xl ring-1 ring-brown-soft/30" />
            <div>
                <p class="text-2xl font-extrabold text-star">{{ naira(edition.print_price_kobo) }} <span class="text-sm font-normal text-ink/60">per copy · {{ edition.print_stock }} in stock</span></p>
                <p v-if="!edition.print_stock" class="mt-4 rounded-xl bg-pink-soft p-4 text-sm">Sold out for now.</p>
                <p v-else-if="!page.props.payments" class="mt-4 rounded-xl bg-pink-soft p-4 text-sm">Online payment isn't available right now — please contact the editors.</p>
                <form v-else @submit.prevent="submit" class="mt-6 grid gap-4 rounded-2xl bg-white p-6 ring-1 ring-brown-soft/30 sm:grid-cols-2">
                    <div><label class="text-sm font-semibold">Quantity</label><input v-model="form.quantity" type="number" min="1" :max="Math.min(20, edition.print_stock)" :class="input" /><InputError :message="form.errors.quantity" /></div>
                    <div><label class="text-sm font-semibold">How to receive</label><select v-model="form.fulfilment" :class="input"><option value="delivery">Delivery in Nigeria</option><option value="pickup">Pickup at the school</option></select></div>
                    <div><label class="text-sm font-semibold">Name</label><input v-model="form.name" :class="input" /><InputError :message="form.errors.name" /></div>
                    <div><label class="text-sm font-semibold">Phone</label><input v-model="form.phone" :class="input" /><InputError :message="form.errors.phone" /></div>
                    <template v-if="form.fulfilment === 'delivery'">
                        <div class="sm:col-span-2"><label class="text-sm font-semibold">Street address</label><input v-model="form.address" :class="input" /><InputError :message="form.errors.address" /></div>
                        <div><label class="text-sm font-semibold">City / town</label><input v-model="form.city" :class="input" /><InputError :message="form.errors.city" /></div>
                        <div><label class="text-sm font-semibold">State</label><select v-model="form.state" :class="input"><option value="">Choose…</option><option v-for="s in states" :key="s">{{ s }}</option></select><InputError :message="form.errors.state" /></div>
                    </template>
                    <div class="flex items-center justify-between gap-3 sm:col-span-2">
                        <p class="text-xs text-ink/60">Delivery costs, if any, are confirmed by the editors.</p>
                        <button :disabled="form.processing" class="rounded-full bg-star px-6 py-2.5 text-sm font-bold text-white">Pay {{ naira(edition.print_price_kobo * form.quantity) }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
