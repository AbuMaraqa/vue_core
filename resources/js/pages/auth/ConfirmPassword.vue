<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirm password',
        description:
            'This is a secure area of the application. Please confirm your password before continuing.',
    },
});

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('Confirm password')" />

    <PasskeyVerify
        :routes="{ options: confirmOptions(), submit: confirmStore() }"
        :label="t('Confirm with a passkey')"
        :loading-label="t('Confirming...')"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-5">
            <div>
                <BaseLabel for="password" required>{{
                    t('Password')
                }}</BaseLabel>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    autofocus
                    :invalid="Boolean(errors.password)"
                />
                <BaseInputError :message="errors.password" />
            </div>

            <BaseButton
                type="submit"
                class="w-full"
                :loading="processing"
                data-test="confirm-password-button"
            >
                {{ t('Confirm password') }}
            </BaseButton>
        </div>
    </Form>
</template>
