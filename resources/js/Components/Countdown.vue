<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
const props = defineProps({ date: String });
const now = ref(Date.now());
let t;
onMounted(() => { t = setInterval(() => (now.value = Date.now()), 60000); });
onBeforeUnmount(() => clearInterval(t));
const left = computed(() => {
    const ms = new Date(`${props.date}T00:00:00+01:00`).getTime() - now.value; // Africa/Lagos
    if (ms <= 0) return null;
    return { d: Math.floor(ms / 864e5), h: Math.floor((ms % 864e5) / 36e5), m: Math.floor((ms % 36e5) / 6e4) };
});
</script>
<template>
    <div v-if="left" class="flex gap-3" aria-label="Countdown">
        <div v-for="(v, k) in { days: left.d, hours: left.h, mins: left.m }" :key="k" class="min-w-[64px] rounded-xl bg-white/10 px-3 py-2 text-center">
            <p class="font-display text-3xl font-extrabold">{{ v }}</p><p class="text-[10px] font-semibold uppercase tracking-widest opacity-80">{{ k }}</p>
        </div>
    </div>
    <p v-else class="text-sm font-semibold">Happening now!</p>
</template>
