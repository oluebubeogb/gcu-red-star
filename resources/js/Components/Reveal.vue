<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
const root = ref(null);
const shown = ref(false);
let io;
onMounted(() => {
    if (typeof window === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        shown.value = true;
        return;
    }
    io = new IntersectionObserver(([e]) => {
        if (e.isIntersecting) { shown.value = true; io.disconnect(); }
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    if (root.value) io.observe(root.value);
});
onUnmounted(() => io?.disconnect());
</script>
<template>
    <div ref="root" :class="shown ? 'is-revealed' : 'will-reveal'">
        <slot />
    </div>
</template>
