<script setup lang="ts">
import Check from '~icons/solar/check-linear';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps<{
    error?: string;
}>();

const simpleMode = defineModel<boolean>({ required: true });

const { t } = useTranslations();

const modes = [
    {
        value: true,
        title: 'Simple',
        description:
            'For people who do not know accounting: quick buttons and plain words, and the advanced pages are hidden.',
    },
    {
        value: false,
        title: 'Full',
        description:
            'For accountants: every page, including journal entries, the chart of accounts and all the reports.',
    },
];
</script>

<template>
    <BaseCard
        :title="t('Interface mode')"
        :description="
            t(
                'Choose how much of the system is shown. You can change it at any time.',
            )
        "
    >
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label
                v-for="mode in modes"
                :key="mode.title"
                :class="[
                    'relative flex cursor-pointer flex-col gap-1 rounded-xl border p-4 transition',
                    simpleMode === mode.value
                        ? 'border-brand-500 bg-brand-50 ring-3 ring-brand-500/10 dark:bg-brand-500/10'
                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700',
                ]"
            >
                <input
                    v-model="simpleMode"
                    type="radio"
                    name="simple_mode"
                    :value="mode.value"
                    class="sr-only"
                />
                <span class="font-medium text-gray-800 dark:text-white/90">
                    {{ t(mode.title) }}
                </span>
                <span class="text-theme-sm text-gray-500 dark:text-gray-400">
                    {{ t(mode.description) }}
                </span>
                <Check
                    v-if="simpleMode === mode.value"
                    class="absolute end-3 top-3 size-4 text-brand-500"
                />
            </label>
        </div>
        <BaseInputError :message="error" />
    </BaseCard>
</template>
