<script setup>
import { reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import Reveal from '@/Components/Reveal.vue';
const props = defineProps({ businesses: Object, filters: Object, categories: Array, states: Array });
const f = reactive({ q: props.filters.q || '', category: props.filters.category || '', state: props.filters.state || '' });
const apply = () => router.get(route('directory'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true });
const wa = (n) => 'https://wa.me/' + String(n).replace(/[^0-9]/g, '');
const user = usePage().props.auth.user;
</script>
<template>
    <SiteLayout title="Alumni business directory">
        <section class="mx-auto max-w-6xl px-5 py-14">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Umuahians in business</p>
                    <h1 class="mt-2 font-display text-4xl font-extrabold">Alumni business directory</h1>
                </div>
                <Link :href="user ? route('businesses.mine') : route('register')" class="rounded-full bg-star px-5 py-2 text-sm font-bold text-white">List your business — free</Link>
            </div>
            <form @submit.prevent="apply" class="mt-6 grid gap-2 sm:grid-cols-[1fr_auto_auto_auto]">
                <input v-model="f.q" placeholder="Search businesses" class="rounded-full border-brown-soft/60 px-4" />
                <select v-model="f.category" @change="apply" class="rounded-full border-brown-soft/60"><option value="">All categories</option><option v-for="c in categories" :key="c">{{ c }}</option></select>
                <select v-model="f.state" @change="apply" class="rounded-full border-brown-soft/60"><option value="">All states</option><option v-for="s in states" :key="s">{{ s }}</option></select>
                <button class="rounded-full bg-ink px-5 py-2 text-sm font-semibold text-milk">Search</button>
            </form>
            <p v-if="!businesses.data.length" class="mt-10 text-sm text-ink/60">No businesses match yet.</p>
            <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Reveal v-for="b in businesses.data" :key="b.id"><article :class="b.featured ? 'ring-2 ring-star' : 'ring-1 ring-brown-soft/30'" class="card-lift rounded-2xl bg-white p-5">
                    <div class="flex items-start gap-3">
                        <img v-if="b.logo" :src="b.logo" :alt="b.name" class="h-14 w-14 rounded-lg object-contain ring-1 ring-brown-soft/30" />
                        <div class="min-w-0">
                            <p v-if="b.featured" class="text-[10px] font-bold uppercase tracking-[0.2em] text-star">Featured</p>
                            <h2 class="font-display text-lg font-bold">{{ b.name }}</h2>
                            <p class="text-xs text-ink/60">{{ b.category }} · {{ b.location ? b.location + ', ' : '' }}{{ b.state }}</p>
                        </div>
                    </div>
                    <p class="mt-3 line-clamp-4 text-sm text-ink/75">{{ b.description }}</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                        <a v-if="b.whatsapp" :href="wa(b.whatsapp)" target="_blank" rel="noopener" class="rounded-full bg-green-100 px-3 py-1 text-green-800">WhatsApp</a>
                        <a v-if="b.phone" :href="`tel:${b.phone}`" class="rounded-full bg-milk px-3 py-1 ring-1 ring-brown-soft/40">Call</a>
                        <a v-if="b.website" :href="b.website" target="_blank" rel="noopener" class="rounded-full bg-milk px-3 py-1 ring-1 ring-brown-soft/40">Website</a>
                    </div>
                    <p v-if="b.owner" class="mt-3 border-t border-milk pt-2 text-xs text-ink/60">Owner:
                        <Link v-if="b.owner.username" :href="route('members.show', b.owner.username)" class="font-semibold text-star">{{ b.owner.name }}</Link><span v-else>{{ b.owner.name }}</span>
                        <Link v-if="b.owner.admission_year" :href="route('sets.show', b.owner.admission_year)" class="ml-1 font-semibold hover:text-star">· Class of {{ b.owner.admission_year }}</Link></p>
                </article></Reveal>
            </div>
            <div v-if="businesses.last_page > 1" class="mt-8 flex justify-center gap-2">
                <Link v-for="l in businesses.links" :key="l.label" :href="l.url || '#'" v-html="l.label" :class="l.active ? 'bg-star text-white' : 'bg-white'" class="rounded-full px-3 py-1 text-sm ring-1 ring-brown-soft/30" />
            </div>
        </section>
    </SiteLayout>
</template>
