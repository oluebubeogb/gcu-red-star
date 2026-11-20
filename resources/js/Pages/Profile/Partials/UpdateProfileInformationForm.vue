<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    houses: { type: Array, default: () => [] },
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    graduation_year: user.graduation_year ?? '',
    current_class: user.current_class ?? '',
    house: user.house ?? '',
    admission_year: user.admission_year ?? '',
    show_in_directory: user.show_in_directory ?? true,
    digest_opt_in: user.digest_opt_in ?? false,
    notify_email: user.notify_email ?? true,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Profile Information
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Update your account's profile information and email address.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
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

            <div>
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

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-star focus:ring-offset-2"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div v-if="user.category !== 'friend'"><InputLabel for="admission_year" value="Year of admission (your set)" /><TextInput id="admission_year" type="number" placeholder="e.g. 1975" class="mt-1 block w-full" v-model="form.admission_year" /><p class="mt-1 text-xs text-ink/60">GCU sets are named by the year you were admitted — “Class of 1975” = admitted 1975.</p><InputError class="mt-2" :message="form.errors.admission_year" /></div>
                <div><InputLabel for="graduation_year" value="Graduation year" /><TextInput id="graduation_year" type="number" class="mt-1 block w-full" v-model="form.graduation_year" /><InputError class="mt-2" :message="form.errors.graduation_year" /></div>
                <div><InputLabel for="current_class" value="Current class" /><TextInput id="current_class" type="text" class="mt-1 block w-full" v-model="form.current_class" /><InputError class="mt-2" :message="form.errors.current_class" /></div>
                <div><InputLabel for="house" value="House" />
                    <select id="house" v-model="form.house" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-star focus:ring-star">
                        <option value="">— None —</option><option v-for="h in houses" :key="h" :value="h">{{ h }}</option>
                    </select><InputError class="mt-2" :message="form.errors.house" /></div>
            </div>

            <div class="space-y-2 text-sm text-ink/80">
                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.show_in_directory" class="rounded border-gray-300 text-star focus:ring-star" /> Show me on my public profile, set and house pages</label>
                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.digest_opt_in" class="rounded border-gray-300 text-star focus:ring-star" /> Email me the monthly digest</label>
                <label class="flex items-center gap-2"><input type="checkbox" v-model="form.notify_email" class="rounded border-gray-300 text-star focus:ring-star" /> Email me when someone tags me in an edition</label>
                <p v-if="user.username"><Link :href="route('members.show', user.username)" class="font-semibold text-star">View my public profile →</Link></p>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
