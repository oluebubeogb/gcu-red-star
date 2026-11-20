<script setup>
import { Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
const props = defineProps({ sets: Object, jubileeSet: Number });
const decades = Object.entries(props.sets).reduce((acc, [y, c]) => {
    const d = Math.floor(y / 10) * 10; (acc[d] ||= []).push({ y: +y, c }); return acc;
}, {});
const order = Object.keys(decades).sort((a, b) => b - a);
</script>
<template>
    <SiteLayout title="Sets">
        <section class="mx-auto max-w-5xl px-5 py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">Sets · by year of admission</p>
            <h1 class="mt-2 font-display text-4xl font-extrabold">Find your set</h1>
            <p class="mt-3 max-w-2xl font-serif text-ink/75">At GCU a set is named by the year its boys were admitted — the “Class of 1975” entered in 1975. Each set celebrates its golden jubilee at Homecoming 50 years later.</p>
            <Link :href="route('sets.show', jubileeSet)" class="mt-6 inline-block rounded-full bg-star px-5 py-2.5 text-sm font-bold text-white">Jubilee set: Class of {{ jubileeSet }} →</Link>
            <p v-if="!order.length" class="mt-10 text-sm text-ink/60">No members have added their year of admission yet.</p>
            <div v-for="d in order" :key="d" class="mt-10">
                <h2 class="font-display text-2xl font-bold">{{ d }}s</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Link v-for="s in decades[d].sort((a, b) => b.y - a.y)" :key="s.y" :href="route('sets.show', s.y)"
                          :class="s.y === jubileeSet ? 'bg-star text-white' : 'bg-white'" class="rounded-full px-4 py-1.5 text-sm font-semibold ring-1 ring-brown-soft/40">
                        {{ s.y }} <span class="font-normal opacity-70">· {{ s.c }}</span>
                    </Link>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
