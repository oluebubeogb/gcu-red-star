<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ member: Object });
const label = { student: 'Student', old_boy: 'Old Boy', teacher: 'Teacher', friend: 'Friend of GCU', editorial: 'Editorial', admin: 'Admin' };
</script>
<template>
    <Link :href="route('members.show', member.username)" class="card-lift flex items-center gap-3 rounded-xl bg-white p-3 ring-1 ring-brown-soft/30 hover:ring-star">
        <img v-if="member.avatar" :src="member.avatar" :alt="member.name" class="h-10 w-10 shrink-0 rounded-full object-cover" />
        <span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-star font-display font-bold text-white">{{ member.name.charAt(0) }}</span>
        <span class="min-w-0">
            <span class="block truncate font-semibold">{{ member.name }}</span>
            <span class="block truncate text-xs text-ink/60">{{ label[member.category] || 'Member' }}<span v-if="member.admission_year"> · Set of {{ member.admission_year }}</span><span v-if="member.house"> · {{ member.house }}</span></span>
        </span>
    </Link>
</template>
