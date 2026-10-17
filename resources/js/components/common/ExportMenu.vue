<script setup lang="ts">
/**
 * Download the current list as Excel, CSV or PDF. The filters and sorting in
 * the address bar are kept, so the file matches what is on screen.
 */
import Export from '~icons/solar/export-linear';
import { onClickOutside } from '@vueuse/core';
import { ref, useTemplateRef } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { RouteQueryOptions } from '@/wayfinder';

const props = defineProps<{
    /**
     * The Wayfinder export route, e.g. `exportMethod` from `@/routes/admin/users`.
     */
    route: { url: (args?: undefined, options?: RouteQueryOptions) => string };
}>();

const { t } = useTranslations();

const isOpen = ref(false);
const container = useTemplateRef('container');

onClickOutside(container, () => (isOpen.value = false));

const formats = [
    { value: 'xlsx', label: 'Excel (.xlsx)' },
    { value: 'csv', label: 'CSV (.csv)' },
    { value: 'pdf', label: 'PDF (.pdf)' },
];

function downloadUrl(format: string): string {
    return props.route.url(undefined, { mergeQuery: { format } });
}
</script>

<template>
    <div ref="container" class="relative">
        <BaseButton variant="outline" @click="isOpen = !isOpen">
            <Export class="size-5" />
            {{ t('Export') }}
        </BaseButton>

        <div
            v-if="isOpen"
            class="absolute end-0 z-10 mt-2 w-48 rounded-xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
        >
            <a
                v-for="format in formats"
                :key="format.value"
                :href="downloadUrl(format.value)"
                class="block rounded-lg px-3 py-2 text-theme-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                @click="isOpen = false"
            >
                {{ format.label }}
            </a>
        </div>
    </div>
</template>
