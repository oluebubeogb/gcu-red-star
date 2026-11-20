<script setup>
defineProps({ news: Array });
</script>
<template>
    <p v-if="!news.length" class="text-sm text-ink/60">No news yet.</p>
    <article v-for="n in news" :key="n.id" class="mb-3 rounded-xl bg-white p-4 ring-1 ring-brown-soft/30">
        <h3 class="font-display text-lg font-bold">{{ n.title }}</h3>
        <p class="text-xs text-ink/50">{{ new Date(n.published_at).toLocaleDateString() }}</p>
        <p class="mt-2 whitespace-pre-line font-serif text-ink/80">{{ n.body }}</p>
    </article>
</template>
