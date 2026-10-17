<script setup lang="ts">
import Check from '~icons/solar/check-linear';
import Languages from '~icons/solar/translation-linear';
import { onClickOutside } from '@vueuse/core';
import { ref, useTemplateRef } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';

const { locale, locales, currentLocale, switchLocale } = useLocale();
const { t } = useTranslations();

const isOpen = ref(false);
const dropdown = useTemplateRef('dropdown');

onClickOutside(dropdown, () => {
    isOpen.value = false;
});

function select(code: string): void {
    isOpen.value = false;
    switchLocale(code);
}
</script>

<template>
    <div ref="dropdown" class="relative">
        <button
            type="button"
            :title="t('Language')"
            class="flex h-11 items-center gap-2 rounded-full border border-gray-200 bg-white px-3.5 text-sm font-medium text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
            @click="isOpen = !isOpen"
        >
            <Languages class="size-5" />
            <span class="hidden sm:inline">{{ currentLocale?.native }}</span>
        </button>

        <div
            v-if="isOpen"
            class="animate-fade-in absolute end-0 z-50 mt-3 w-48 rounded-2xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
        >
            <ul class="flex flex-col gap-1">
                <li v-for="item in locales" :key="item.code">
                    <button
                        type="button"
                        :class="[
                            'flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-start text-theme-sm font-medium transition-colors',
                            item.code === locale
                                ? 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400'
                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5',
                        ]"
                        @click="select(item.code)"
                    >
                        <span>{{ item.native }}</span>
                        <Check v-if="item.code === locale" class="size-4" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
