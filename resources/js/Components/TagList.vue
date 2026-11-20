<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ tags: Array, empty: String });
</script>
<template>
    <p v-if="!tags.length" class="text-sm text-ink/60">{{ empty || 'No tagged pages yet.' }}</p>
    <ul v-else class="space-y-2">
        <li v-for="t in tags" :key="t.id" class="rounded-xl bg-white p-3 text-sm ring-1 ring-brown-soft/30">
            <Link :href="route('editions.show', { edition: t.edition.slug, page: t.page })" class="font-semibold hover:text-star">{{ t.edition.title }} · page {{ t.page }}</Link>
            <span class="text-ink/60"> — <Link v-if="t.username" :href="route('members.show', t.username)" class="hover:text-star">{{ t.name }}</Link><template v-else>{{ t.name }}</template></span>
            <p v-if="t.note" class="mt-1 text-xs text-ink/60">“{{ t.note }}”</p>
        </li>
    </ul>
</template>
