<script setup>
import SiteLayout from '@/Layouts/SiteLayout.vue';
</script>
<template>
    <SiteLayout title="About">
        <section class="mx-auto max-w-3xl px-5 py-14">
            <div class="flex flex-col items-center text-center">
                <img src="/gcu-badge.png" alt="Government College Umuahia badge" class="h-32 w-32 object-contain" />
                <p class="mt-6 text-xs font-semibold uppercase tracking-[0.2em] text-brown">About</p>
                <h1 class="mt-2 font-display text-4xl font-extrabold md:text-5xl">The Voice of Government College Umuahia</h1>
            </div>
            <div class="mt-10 space-y-6 font-serif text-[18px] leading-[1.75] text-ink/85">
                <p><strong class="font-display text-star">The Red Star</strong> is the magazine of Government College Umuahia — written, edited and published by its students and teachers, and read by generations of old boys across the world.</p>
                <p>This platform brings the magazine online: new editions you can read comfortably on any phone, the original printed pages preserved for posterity, and a searchable archive that gathers the school's story in one place.</p>
                <p>Membership is free. Students, old boys and teachers can register, read, bookmark and contribute. The editorial team reviews every submission before publication.</p>
            </div>
            <div class="mt-12 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div v-for="c in [['#A52A2A','School red'],['#E8A0B0','Shirt pink'],['#6B4423','Trouser brown'],['#F5F0E8','Milk'],['#1A1210','Ink']]" :key="c[0]" class="overflow-hidden rounded-xl bg-white ring-1 ring-brown-soft/30">
                    <div class="h-14" :style="{ background: c[0] }"></div>
                    <p class="px-3 py-2 text-xs font-semibold">{{ c[1] }}</p>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
