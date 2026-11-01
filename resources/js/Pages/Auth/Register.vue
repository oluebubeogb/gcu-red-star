<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ categories: Array });
const thisYear = new Date().getFullYear();
const form = useForm({
    username: '',
    category: 'student',
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
                <div class="mt-2 grid grid-cols-3 gap-2">
                    <button v-for="c in categories" :key="c.value" type="button" @click="form.category = c.value"
                        :class="form.category === c.value ? 'bg-star text-white ring-star' : 'bg-milk text-ink ring-brown-soft/50'"
                        class="rounded-xl px-2 py-2.5 text-xs font-semibold ring-1 transition">{{ c.label.replace(' (alumnus)', '').replace(' / Staff', '') }}</button>
                </div>
                <InputError class="mt-2" :message="form.errors.category" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3" v-if="form.category !== 'teacher'">
                <div>
                    <InputLabel for="graduation_year" :value="form.category === 'student' ? 'Graduating year' : 'Class of'" />
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
