<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const props = defineProps({ members: Object, categories: Object, roles: Array, q: String });
const search = ref(props.q || '');
const go = () => router.get(route('hq.members'), { q: search.value }, { preserveState: true });
const save = (m, field, value) => router.patch(route('hq.members.update', m.id), { category: m.category, role: m.role, [field]: value }, { preserveScroll: true });
</script>
<template>
    <Head title="Members" />
    <AuthenticatedLayout>
        <template #header><h1 class="font-display text-4xl font-extrabold">Members</h1></template>
        <form @submit.prevent="go" class="mb-6 flex max-w-md gap-2">
            <input v-model="search" placeholder="Search name, email, username" class="w-full rounded-full border-brown-soft/60 focus:border-star focus:ring-star" />
            <button class="rounded-full bg-star px-4 text-sm font-semibold text-white">Search</button>
        </form>
        <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-brown-soft/30">
            <table class="min-w-full text-sm">
                <thead class="bg-milk text-left text-xs uppercase tracking-wider text-brown"><tr><th class="p-3">Member</th><th class="p-3">Category</th><th class="p-3">Role</th><th class="p-3">Verified</th></tr></thead>
                <tbody>
                    <tr v-for="m in members.data" :key="m.id" class="border-t border-milk">
                        <td class="p-3"><b>{{ m.name }}</b> <span class="text-ink/50">@{{ m.username }}</span><br><span class="text-xs text-ink/60">{{ m.email }}</span></td>
                        <td class="p-3"><select :value="m.category" @change="save(m, 'category', $event.target.value)" class="rounded-lg border-brown-soft/60 py-1 text-xs"><option v-for="(l, k) in categories" :key="k" :value="k">{{ l }}</option></select></td>
                        <td class="p-3"><select :value="m.role" @change="save(m, 'role', $event.target.value)" class="rounded-lg border-brown-soft/60 py-1 text-xs"><option v-for="r in roles" :key="r" :value="r">{{ r }}</option></select>
                            <span v-if="m.is_admin && m.role !== 'admin'" class="ml-1 text-[10px] font-semibold text-star">env admin</span></td>
                        <td class="p-3">{{ m.email_verified_at ? '✓' : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="members.last_page > 1" class="mt-6 flex gap-2">
            <template v-for="l in members.links" :key="l.label"><Link v-if="l.url" :href="l.url" v-html="l.label" :class="l.active ? 'bg-star text-white' : 'bg-white'" class="rounded-full px-3 py-1 text-sm ring-1 ring-brown-soft/40" /></template>
        </div>
    </AuthenticatedLayout>
</template>
