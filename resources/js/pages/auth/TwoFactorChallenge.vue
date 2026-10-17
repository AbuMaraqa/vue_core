<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const { t } = useTranslations();

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery code',
            description:
                'Please confirm access to your account by entering one of your emergency recovery codes.',
            buttonText: 'Sign in using an authentication code',
        };
    }

    return {
        title: 'Authentication code',
        description:
            'Enter the authentication code provided by your authenticator application.',
        buttonText: 'Sign in using a recovery code',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head :title="t('Two-factor authentication')" />

    <Form
        v-if="!showRecoveryInput"
        v-bind="store.form()"
        class="space-y-5"
        reset-on-error
        @error="code = ''"
        #default="{ errors, processing, clearErrors }"
    >
        <input type="hidden" name="code" :value="code" />
        <div class="flex flex-col items-center gap-3" dir="ltr">
            <InputOTP
                id="otp"
                v-model="code"
                :maxlength="6"
                :disabled="processing"
                autofocus
            >
                <InputOTPGroup>
                    <InputOTPSlot
                        v-for="index in 6"
                        :key="index"
                        :index="index - 1"
                    />
                </InputOTPGroup>
            </InputOTP>
            <BaseInputError :message="errors.code" />
        </div>

        <BaseButton type="submit" class="w-full" :loading="processing">
            {{ t('Continue') }}
        </BaseButton>

        <button
            type="button"
            class="mx-auto block text-sm text-brand-500 hover:text-brand-600 dark:text-brand-400"
            @click="() => toggleRecoveryMode(clearErrors)"
        >
            {{ t(authConfigContent.buttonText) }}
        </button>
    </Form>

    <Form
        v-else
        v-bind="store.form()"
        class="space-y-5"
        reset-on-error
        #default="{ errors, processing, clearErrors }"
    >
        <div>
            <BaseInput
                v-focus
                name="recovery_code"
                type="text"
                :placeholder="t('Enter recovery code')"
                required
                :invalid="Boolean(errors.recovery_code)"
            />
            <BaseInputError :message="errors.recovery_code" />
        </div>

        <BaseButton type="submit" class="w-full" :loading="processing">
            {{ t('Continue') }}
        </BaseButton>

        <button
            type="button"
            class="mx-auto block text-sm text-brand-500 hover:text-brand-600 dark:text-brand-400"
            @click="() => toggleRecoveryMode(clearErrors)"
        >
            {{ t(authConfigContent.buttonText) }}
        </button>
    </Form>
</template>
