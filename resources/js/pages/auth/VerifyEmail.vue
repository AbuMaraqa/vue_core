<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import { useTranslations } from '@/composables/useTranslations';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Email verification',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
});

defineProps<{
    status?: string;
}>();

const { t } = useTranslations();
</script>

<template>
    <Head :title="t('Email verification')" />

    <BaseAlert
        v-if="status === 'verification-link-sent'"
        variant="success"
        class="mb-5"
    >
        {{
            t(
                'A new verification link has been sent to the email address you provided during registration.',
            )
        }}
    </BaseAlert>

    <Form v-bind="send.form()" class="space-y-5" v-slot="{ processing }">
        <BaseButton type="submit" class="w-full" :loading="processing">
            {{ t('Resend verification email') }}
        </BaseButton>

        <Link
            :href="logout()"
            as="button"
            class="mx-auto block text-sm text-brand-500 hover:text-brand-600 dark:text-brand-400"
        >
            {{ t('Sign out') }}
        </Link>
    </Form>
</template>
