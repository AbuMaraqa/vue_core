<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import { useTranslations } from '@/composables/useTranslations';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
    },
});

defineProps<{
    status?: string;
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('Forgot password')" />

    <BaseAlert v-if="status" variant="success" class="mb-5">
        {{ status }}
    </BaseAlert>

    <Form v-bind="email.form()" v-slot="{ errors, processing }">
        <div class="space-y-5">
            <div>
                <BaseLabel for="email" required>{{ t('Email') }}</BaseLabel>
                <BaseInput
                    id="email"
                    v-focus
                    type="email"
                    name="email"
                    autocomplete="off"
                    placeholder="info@example.com"
                    :invalid="Boolean(errors.email)"
                />
                <BaseInputError :message="errors.email" />
            </div>

            <BaseButton
                type="submit"
                class="w-full"
                :loading="processing"
                data-test="email-password-reset-link-button"
            >
                {{ t('Email password reset link') }}
            </BaseButton>
        </div>
    </Form>

    <p
        class="mt-5 text-center text-sm font-normal text-gray-700 sm:text-start dark:text-gray-400"
    >
        {{ t('Or, return to') }}
        <Link
            :href="login()"
            class="text-brand-500 hover:text-brand-600 dark:text-brand-400"
        >
            {{ t('Sign In') }}
        </Link>
    </p>
</template>
