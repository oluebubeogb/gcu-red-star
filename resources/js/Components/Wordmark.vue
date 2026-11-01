<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ light: Boolean, compact: Boolean });
</script>
<template>
    <Link :href="route('home')" class="group flex items-center gap-3">
        <img src="/gcu-badge.png" alt="GCU badge" :class="compact ? 'h-9 w-9' : 'h-11 w-11'" class="shrink-0 object-contain drop-shadow-sm" />
        <span class="leading-none">
            <span :class="[light ? 'text-white' : 'text-ink', compact ? 'text-xl' : 'text-2xl']" class="block font-display font-bold tracking-tight">
                The Red <span :class="light ? 'text-pink' : 'text-star'">Star</span>
            </span>
            <span v-if="!compact" :class="light ? 'text-milk/70' : 'text-brown'" class="mt-1 block text-[10px] font-semibold uppercase tracking-[0.22em]">Government College Umuahia</span>
        </span>
    </Link>
</template>
