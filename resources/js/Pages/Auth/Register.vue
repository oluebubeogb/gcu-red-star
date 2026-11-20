<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ categories: Array, houses: Array });
const thisYear = new Date().getFullYear();
const form = useForm({
    username: '',
    category: 'student',
    admission_year: '',
    house: '',
    digest_opt_in: true,
    graduation_year: '',
    current_class: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Join The Red Star" />

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-4">
                <InputLabel for="username" value="Username" />
                <TextInput id="username" type="text" class="mt-1 block w-full" v-model="form.username" required autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.username" />
            </div>

            <div class="mt-4">
                <InputLabel value="I am a…" />
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <button v-for="c in categories" :key="c.value" type="button" @click="form.category = c.value"
                        :class="form.category === c.value ? 'bg-star text-white ring-star' : 'bg-milk text-ink ring-brown-soft/50'"
                        class="rounded-xl px-2 py-2.5 text-xs font-semibold ring-1 transition">{{ c.label.replace(' (alumnus)', '').replace(' / Staff', '') }}</button>
                </div>
                <p v-if="form.category === 'friend'" class="mt-2 text-xs text-ink/60">Not a student, teacher or old boy? Join as a friend of the college — parents, neighbours and well-wishers welcome.</p>
                <InputError class="mt-2" :message="form.errors.category" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3" v-if="form.category !== 'friend'">
                <div>
                    <InputLabel for="admission_year" :value="form.category === 'teacher' ? 'Year of admission (if you are an old boy)' : 'Year of admission (your set)'" />
                    <TextInput id="admission_year" type="number" min="1929" :max="thisYear" placeholder="e.g. 1975" class="mt-1 block w-full" v-model="form.admission_year" />
                    <InputError class="mt-2" :message="form.errors.admission_year" />
                </div>
                <div v-if="form.category !== 'teacher'">
                    <InputLabel for="house" value="House" />
                    <select id="house" v-model="form.house" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-star focus:ring-star">
                        <option value="">Select your house</option>
                        <option v-for="h in houses" :key="h" :value="h">{{ h }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.house" />
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3" v-if="['student', 'old_boy'].includes(form.category)">
                <div>
                    <InputLabel for="graduation_year" :value="form.category === 'student' ? 'Expected graduation year' : 'Year you left (graduation)'" />
                    <TextInput id="graduation_year" type="number" min="1929" :max="thisYear + 7" class="mt-1 block w-full" v-model="form.graduation_year" />
                    <InputError class="mt-2" :message="form.errors.graduation_year" />
                </div>
                <div v-if="form.category === 'student'">
                    <InputLabel for="current_class" value="Current class (optional)" />
                    <TextInput id="current_class" type="text" placeholder="e.g. SS2" class="mt-1 block w-full" v-model="form.current_class" />
                    <InputError class="mt-2" :message="form.errors.current_class" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirm Password"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <label class="mt-4 flex items-center gap-2 text-sm text-ink/80">
                <input type="checkbox" v-model="form.digest_opt_in" class="rounded border-gray-300 text-star focus:ring-star" />
                Email me the monthly Red Star digest
            </label>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-star focus:ring-offset-2"
                >
                    Already registered?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Register
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
