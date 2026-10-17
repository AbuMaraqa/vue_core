<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ArrowLeft from '~icons/solar/arrow-left-linear';
import { computed } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';
import { dashboard } from '@/routes/admin';

const props = defineProps<{
    status: number;
    /**
     * Shown so a user can quote it to support; it matches the server logs.
     */
    requestId: string | null;
}>();

const { t } = useTranslations();
const { can } = usePermissions();

const messages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Access denied',
        description: 'You do not have permission to open this page.',
    },
    404: {
        title: 'Page not found',
        description:
            'The page you are looking for does not exist or has been moved.',
    },
    429: {
        title: 'Too many requests',
        description:
            'You are going too fast. Please wait a moment and try again.',
    },
    500: {
        title: 'Something went wrong',
        description:
            'An unexpected error occurred. Please try again in a moment.',
    },
    503: {
        title: 'Under maintenance',
        description:
            'We are performing scheduled maintenance. Please check back soon.',
    },
};

const message = computed(() => messages[props.status] ?? messages[500]);

const homeUrl = computed(() => (can('Dashboard View') ? dashboard() : home()));

function goBack(): void {
    window.history.back();
}
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-white p-6 text-center dark:bg-gray-900"
    >
        <Head :title="t(message.title)" />

        <p
            class="text-[7rem] leading-none font-bold text-brand-500 sm:text-[9rem]"
        >
            {{ status }}
        </p>
        <h1
            class="mt-6 text-title-sm font-semibold text-gray-800 dark:text-white/90"
        >
            {{ t(message.title) }}
        </h1>
        <p class="mt-3 max-w-md text-gray-500 dark:text-gray-400">
            {{ t(message.description) }}
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <BaseButton variant="outline" @click="goBack">
                <ArrowLeft class="size-5 rtl:rotate-180" />
                {{ t('Go back') }}
            </BaseButton>
            <BaseButton :href="homeUrl">
                {{ t('Back to home') }}
            </BaseButton>
        </div>

        <p
            v-if="requestId && status >= 500"
            class="mt-10 text-theme-xs text-gray-400 dark:text-gray-500"
        >
            {{ t('Reference') }}:
            <span class="font-mono select-all" dir="ltr">{{ requestId }}</span>
        </p>
    </div>
</template>
