<script setup>
defineProps({ sponsors: Array, title: { type: String, default: 'Thank you to our sponsors' } });
</script>
<template>
    <div v-if="sponsors?.length" class="rounded-2xl bg-white p-5 ring-1 ring-brown-soft/30">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brown">{{ title }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-5">
            <component :is="s.website ? 'a' : 'div'" v-for="s in sponsors" :key="s.id" :href="s.website" target="_blank" rel="noopener sponsored"
                       class="flex items-center gap-2 text-sm font-semibold">
                <img v-if="s.logo" :src="s.logo" :alt="s.name" class="h-10 max-w-[140px] object-contain" />
                <span>{{ s.name }}<span v-if="s.package" class="block text-[10px] font-normal uppercase text-ink/50">{{ s.package }}</span></span>
            </component>
        </div>
    </div>
</template>
