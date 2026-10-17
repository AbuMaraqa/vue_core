<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Sign In',
        description: 'Enter your email and password to sign in!',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('Sign In')" />

    <BaseAlert v-if="status" variant="success" class="mb-5">
        {{ status }}
    </BaseAlert>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
    >
        <div class="space-y-5">
            <div>
                <BaseLabel for="email" required>{{ t('Email') }}</BaseLabel>
                <BaseInput
                    id="email"
                    v-focus
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
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    :placeholder="t('Enter your password')"
                    :invalid="Boolean(errors.password)"
                />
                <BaseInputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <BaseCheckbox id="remember" name="remember">
                    {{ t('Keep me logged in') }}
                </BaseCheckbox>
                <Link
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-sm text-brand-500 hover:text-brand-600 dark:text-brand-400"
                >
                    {{ t('Forgot password?') }}
                </Link>
            </div>

            <BaseButton
                type="submit"
                class="w-full"
                :loading="processing"
                data-test="login-button"
            >
                {{ t('Sign In') }}
            </BaseButton>
        </div>
    </Form>

    <p
        class="mt-5 text-center text-sm font-normal text-gray-700 sm:text-start dark:text-gray-400"
    >
        {{ t("Don't have an account?") }}
        <Link
            :href="register()"
            class="text-brand-500 hover:text-brand-600 dark:text-brand-400"
            data-test="register-link"
        >
            {{ t('Sign Up') }}
        </Link>
    </p>
</template>
