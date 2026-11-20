<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const props = defineProps({ members: Object, categories: Object, roles: Array, q: String, filters: Object, houses: Array });
const search = ref(props.q || '');
const category = ref(props.filters?.category || '');
const demo = ref(props.filters?.demo || '');
const house = ref(props.filters?.house || '');
const go = () => router.get(route('hq.members'), { q: search.value, category: category.value, demo: demo.value, house: house.value }, { preserveState: true });
const save = (m, field, value) => router.patch(route('hq.members.update', m.id), { category: m.category, role: m.role, [field]: value }, { preserveScroll: true });
</script>
<template>
    <Head title="Members" />
    <AuthenticatedLayout>
        <template #header><h1 class="font-display text-4xl font-extrabold">Members</h1></template>
        <form @submit.prevent="go" class="mb-6 flex max-w-3xl flex-wrap gap-2">
            <input v-model="search" placeholder="Search name, email, username" class="min-w-0 flex-1 rounded-full border-brown-soft/60 focus:border-star focus:ring-star" />
            <select v-model="category" @change="go" class="rounded-full border-brown-soft/60 text-sm"><option value="">All categories</option><option v-for="(l, k) in categories" :key="k" :value="k">{{ l }}</option></select>
            <select v-model="house" @change="go" class="rounded-full border-brown-soft/60 text-sm"><option value="">All houses</option><option v-for="h in houses" :key="h" :value="h">{{ h }}</option></select>
            <select v-model="demo" @change="go" class="rounded-full border-brown-soft/60 text-sm"><option value="">Real + demo</option><option value="hide">Real only</option><option value="only">Demo only</option></select>
            <button class="rounded-full bg-star px-4 text-sm font-semibold text-white">Search</button>
        </form>
        <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-brown-soft/30">
            <table class="min-w-full text-sm">
                <thead class="bg-milk text-left text-xs uppercase tracking-wider text-brown"><tr><th class="p-3">Member</th><th class="p-3">Category</th><th class="p-3">Role</th><th class="p-3">Verified</th></tr></thead>
                <tbody>
                    <tr v-for="m in members.data" :key="m.id" class="border-t border-milk">
                        <td class="p-3"><b>{{ m.name }}</b> <span class="text-ink/50">@{{ m.username }}</span> <span v-if="m.is_demo" class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase text-gray-600">demo</span><br><span class="text-xs text-ink/60">{{ m.email }}<span v-if="m.admission_year"> · set {{ m.admission_year }}</span><span v-if="m.house"> · {{ m.house }}</span></span></td>
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
