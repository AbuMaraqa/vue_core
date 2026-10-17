<script setup lang="ts">
import Moon from '~icons/solar/moon-linear';
import Sun from '~icons/solar/sun-linear';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';

const { resolvedAppearance, updateAppearance } = useAppearance();
const { t } = useTranslations();

function toggleTheme(): void {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <button
        type="button"
        :title="t('Toggle theme')"
        class="relative flex size-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        @click="toggleTheme"
    >
        <Sun class="hidden size-5 dark:block" />
        <Moon class="size-5 dark:hidden" />
    </button>
</template>
