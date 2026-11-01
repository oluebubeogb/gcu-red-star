<script setup>
import StarMark from '@/Components/StarMark.vue';
defineProps({ title: String, body: String });
</script>
<template>
    <div class="rounded-2xl border border-dashed border-brown-soft/60 bg-white/60 px-6 py-16 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-star/10"><StarMark class="h-7 w-7 text-star" /></div>
        <h3 class="mt-5 font-display text-2xl font-bold">{{ title }}</h3>
        <p class="mx-auto mt-2 max-w-md font-serif text-ink/70">{{ body }}</p>
        <div class="mt-6"><slot /></div>
    </div>
</template>
