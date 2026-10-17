<script setup lang="ts">
/**
 * The card around a list: a toolbar for the search and filters, the content
 * (a table or a grid) and the pagination when the list is paginated.
 */
import TablePagination from '@/components/common/TablePagination.vue';
import type { Paginated } from '@/types';

defineProps<{
    pagination?: Paginated<unknown>;
}>();
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div
            v-if="$slots.search || $slots.filters"
            class="flex flex-col gap-3 px-5 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
        >
            <slot name="search" />
            <div
                v-if="$slots.filters"
                class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto"
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
