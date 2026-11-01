<script setup>
import Wordmark from '@/Components/Wordmark.vue';
</script>
<template>
    <div class="flex min-h-screen flex-col items-center bg-milk px-5 pt-10 sm:justify-center sm:pt-0">
        <Wordmark />
        <div class="mt-8 w-full overflow-hidden rounded-2xl border border-brown-soft/40 bg-white px-6 py-6 shadow-[0_10px_40px_-20px_rgba(107,68,35,0.35)] sm:max-w-md">
            <slot />
        </div>
        <p class="mt-6 pb-8 text-xs text-brown">Free membership for students, old boys and teachers of GCU.</p>
    </div>
</template>
