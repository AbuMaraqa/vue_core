<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import BasePasswordInput from '@/components/base/BasePasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Reset password',
        description: 'Please enter your new password below',
    },
});

defineProps<{
    token: string;
    email: string;
    passwordRules?: string;
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('Reset password')" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="space-y-5">
            <div>
                <BaseLabel for="email">{{ t('Email') }}</BaseLabel>
                <BaseInput
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    :model-value="email"
                    readonly
                />
                <BaseInputError :message="errors.email" />
            </div>

            <div>
                <BaseLabel for="password" required>{{
                    t('Password')
                }}</BaseLabel>
                <BasePasswordInput
                    id="password"
                    v-focus
                    name="password"
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
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <BaseInputError :message="errors.password_confirmation" />
            </div>

            <BaseButton
                type="submit"
                class="w-full"
                :loading="processing"
                data-test="reset-password-button"
            >
                {{ t('Reset password') }}
            </BaseButton>
        </div>
    </Form>
</template>
