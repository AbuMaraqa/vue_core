<script setup lang="ts">
/**
 * The frame of every financial report: title, filters with an "Apply"
 * button, and the Excel / CSV / PDF export of the report as filtered.
 */
import Filter from '~icons/solar/filter-linear';
import BaseButton from '@/components/base/BaseButton.vue';
import ExportMenu from '@/components/common/ExportMenu.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import type { RouteQueryOptions } from '@/wayfinder';

const props = defineProps<{
    title: string;
    description?: string;
    /**
     * The report route: the export downloads it with the current filters.
     */
    route?: { url: (args?: undefined, options?: RouteQueryOptions) => string };
    /**
     * The permission that allows the export, e.g. `Inventory Report Export`.
     */
    exportPermission?: string;
    /**
     * The first breadcrumb, e.g. `Inventory`.
     */
    section?: string;
}>();

const emit = defineEmits<{
    apply: [];
}>();

const { t } = useTranslations();
const { can } = usePermissions();
</script>

<template>
    <div>
        <PageHeader
            :title="title"
            :description="description"
            :breadcrumbs="[
                { title: props.section ?? t('Financial Reports') },
                { title },
            ]"
        >
            <template
                v-if="
                    route &&
                    can(props.exportPermission ?? 'Financial Report Export')
                "
                #actions
            >
                <ExportMenu :route="route" />
            </template>
        </PageHeader>

        <form
            v-if="$slots.filters"
            class="mb-6 flex flex-wrap items-end gap-4 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            @submit.prevent="emit('apply')"
        >
            <slot name="filters" />
            <BaseButton type="submit" size="sm">
                <Filter class="size-4" />
                {{ t('Apply') }}
            </BaseButton>
        </form>

        <slot />
    </div>
</template>
