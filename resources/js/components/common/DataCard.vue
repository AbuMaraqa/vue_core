<script setup lang="ts">
/**
 * The card around a list: a toolbar for the search and filters, the content
 * (a table or a grid) and the pagination when the list is paginated.
 *
 * On phones the filters fold behind a "Filters" button so the list stays in
 * view; pass `activeFilters` (e.g. `activeCount` from `useIndexFilters`) to
 * show how many are set.
 */
import Tuning from '~icons/solar/tuning-2-linear';
import { ref } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import TablePagination from '@/components/common/TablePagination.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatNumber } from '@/lib/format';
import type { Paginated } from '@/types';

withDefaults(
    defineProps<{
        pagination?: Paginated<unknown>;
        activeFilters?: number;
    }>(),
    { pagination: undefined, activeFilters: 0 },
);

const { t } = useTranslations();
const { locale } = useLocale();

const areFiltersOpen = ref(false);
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div
            v-if="$slots.search || $slots.filters"
            class="flex flex-col gap-3 px-5 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3 sm:contents">
                <slot name="search" />
                <BaseButton
                    v-if="$slots.filters"
                    variant="outline"
                    class="shrink-0 sm:hidden"
                    :aria-expanded="areFiltersOpen"
                    @click="areFiltersOpen = !areFiltersOpen"
                >
                    <Tuning class="size-5" />
                    {{ t('Filters') }}
                    <span
                        v-if="activeFilters > 0"
                        class="flex size-5 items-center justify-center rounded-full bg-brand-500 text-theme-xs text-white"
                    >
                        {{ formatNumber(activeFilters, locale) }}
                    </span>
                </BaseButton>
            </div>
            <div
                v-if="$slots.filters"
                :class="[
                    'w-full flex-col gap-3 sm:flex sm:flex-row lg:w-auto',
                    areFiltersOpen ? 'flex' : 'hidden',
                ]"
            >
                <slot name="filters" />
            </div>
        </div>

        <slot />

        <TablePagination
            v-if="pagination"
            :meta="pagination.meta"
            :links="pagination.links"
        />
    </div>
</template>
