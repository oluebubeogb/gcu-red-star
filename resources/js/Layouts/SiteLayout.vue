<script setup>
import { ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Wordmark from '@/Components/Wordmark.vue';
import StarMark from '@/Components/StarMark.vue';

defineProps({ title: String });
const page = usePage();
const open = ref(false);
const nav = [
    { label: 'Editions', route: 'editions.index' },
    { label: 'Archive', route: 'archive' },
    { label: 'Contribute', route: 'contribute' },
    { label: 'About', route: 'about' },
];
const year = new Date().getFullYear();
</script>
<template>
    <Head :title="title" />
    <div class="min-h-screen bg-milk text-ink">
        <div class="h-1.5 bg-gradient-to-r from-star via-star to-pink"></div>
        <header class="sticky top-0 z-40 border-b border-brown-soft/30 bg-milk/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-3">
                <Wordmark compact />
                <nav class="hidden items-center gap-7 md:flex">
                    <Link v-for="n in nav" :key="n.route" :href="route(n.route)"
                          :class="route().current(n.route) ? 'text-star' : 'text-ink/75 hover:text-star'"
                          class="text-sm font-semibold transition">{{ n.label }}</Link>
                </nav>
                <div class="hidden items-center gap-3 md:flex">
                    <template v-if="page.props.auth.user">
                        <Link v-if="page.props.auth.user.is_editor" :href="route('hq.index')" class="text-sm font-semibold text-brown hover:text-star">HQ</Link>
                        <Link :href="route('dashboard')" class="rounded-full bg-ink px-4 py-2 text-sm font-semibold text-milk hover:bg-star">My Red Star</Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="text-sm font-semibold text-ink/75 hover:text-star">Sign in</Link>
                        <Link :href="route('register')" class="rounded-full bg-star px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-star-700">Join free</Link>
                    </template>
                </div>
                <button class="rounded-lg p-2 md:hidden" @click="open = !open" aria-label="Menu">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path v-if="!open" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                        <path v-else stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>
            <div v-if="open" class="border-t border-brown-soft/30 bg-milk px-5 pb-5 md:hidden">
                <Link v-for="n in nav" :key="n.route" :href="route(n.route)" class="block py-3 text-base font-semibold">{{ n.label }}</Link>
                <div class="mt-3 flex gap-3">
                    <template v-if="page.props.auth.user">
                        <Link :href="route('dashboard')" class="flex-1 rounded-full bg-ink py-2.5 text-center text-sm font-semibold text-milk">My Red Star</Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="flex-1 rounded-full border border-ink/20 py-2.5 text-center text-sm font-semibold">Sign in</Link>
                        <Link :href="route('register')" class="flex-1 rounded-full bg-star py-2.5 text-center text-sm font-semibold text-white">Join free</Link>
                    </template>
                </div>
            </div>
        </header>

        <main><slot /></main>

        <footer class="mt-24 bg-ink text-milk">
            <div class="mx-auto grid max-w-6xl gap-10 px-5 py-14 md:grid-cols-[1.4fr_1fr_1fr]">
                <div>
                    <Wordmark light />
                    <p class="mt-5 max-w-sm font-serif text-milk/70">The voice of Government College Umuahia — a digital publication, searchable archive and community for students, teachers and old boys.</p>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-[0.2em] text-pink">Read</h4>
                    <ul class="mt-4 space-y-2 text-sm text-milk/80">
                        <li><Link :href="route('editions.index')" class="hover:text-white">Editions</Link></li>
                        <li><Link :href="route('archive')" class="hover:text-white">Archive</Link></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-[0.2em] text-pink">Community</h4>
                    <ul class="mt-4 space-y-2 text-sm text-milk/80">
                        <li><Link :href="route('contribute')" class="hover:text-white">Contribute</Link></li>
                        <li><Link :href="route('about')" class="hover:text-white">About</Link></li>
                        <li><Link :href="route('register')" class="hover:text-white">Join free</Link></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/10">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 text-xs text-milk/50">
                    <span>© {{ year }} The Red Star · Government College Umuahia</span>
                    <StarMark class="h-4 w-4 text-star" />
                </div>
            </div>
        </footer>
    </div>
</template>
