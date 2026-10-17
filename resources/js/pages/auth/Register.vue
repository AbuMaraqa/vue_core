<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import BasePasswordInput from '@/components/base/BasePasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({
    layout: {
        title: 'Sign Up',
        description: 'Enter your details below to create your account',
    },
});

defineProps<{
    passwordRules?: string;
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('Sign Up')" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="space-y-5">
            <div>
                <BaseLabel for="name" required>{{ t('Name') }}</BaseLabel>
                <BaseInput
                    id="name"
                    v-focus
                    type="text"
                    name="name"
                    required
                    autocomplete="name"
                    :invalid="Boolean(errors.name)"
                />
                <BaseInputError :message="errors.name" />
            </div>

            <div>
                <BaseLabel for="email" required>{{ t('Email') }}</BaseLabel>
                <BaseInput
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="info@example.com"
                    :invalid="Boolean(errors.email)"
                />
                <BaseInputError :message="errors.email" />
            </div>

            <div>
                <BaseLabel for="password" required>{{
                    t('Password')
                }}</BaseLabel>
                <BasePasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                    :invalid="Boolean(errors.password)"
                />
                <BaseInputError :message="errors.password" />
            </div>

            <div>
                <BaseLabel for="password_confirmation" required>
                    {{ t('Confirm password') }}
                </BaseLabel>
                <BasePasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <BaseInputError :message="errors.password_confirmation" />
            </div>

            <BaseButton
                type="submit"
                class="w-full"
                :loading="processing"
                data-test="register-user-button"
            >
                {{ t('Create account') }}
            </BaseButton>
        </div>
    </Form>

    <p
        class="mt-5 text-center text-sm font-normal text-gray-700 sm:text-start dark:text-gray-400"
    >
        {{ t('Already have an account?') }}
        <Link
            :href="login()"
            class="text-brand-500 hover:text-brand-600 dark:text-brand-400"
        >
            {{ t('Sign In') }}
        </Link>
    </p>
</template>
