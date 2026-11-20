<script setup>
defineProps({ ad: Object, banner: Boolean });
</script>
<template>
    <component :is="ad.url ? 'a' : 'div'" v-if="ad" :href="ad.url" target="_blank" rel="noopener sponsored"
               class="block overflow-hidden rounded-2xl bg-white ring-1 ring-brown-soft/30">
        <img v-if="ad.image" :src="ad.image" :alt="ad.title" :class="banner ? 'max-h-60' : 'max-h-72'" class="w-full object-cover" />
        <div class="p-4">
            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-ink/40">Sponsored · {{ ad.advertiser }}</p>
            <p class="mt-1 font-display text-lg font-bold">{{ ad.title }}</p>
            <p v-if="ad.body" class="mt-1 text-sm text-ink/70">{{ ad.body }}</p>
        </div>
    </component>
</template>
