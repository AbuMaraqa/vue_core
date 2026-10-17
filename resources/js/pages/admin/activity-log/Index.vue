<script setup lang="ts">
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { eventColor } from '@/lib/activity';
import { formatDate, formatRelative } from '@/lib/format';
import { index } from '@/routes/admin/activity-log';
import type { Activity, Paginated } from '@/types';

const props = defineProps<{
    activities: Paginated<Activity>;
    logNames: string[];
    filters: {
        search: string | null;
        event: string | null;
        log_name: string | null;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const events = ['created', 'updated', 'deleted'];

const { filters } = useIndexFilters(index, {
    search: props.filters.search,
    event: props.filters.event,
    log_name: props.filters.log_name,
});

function changedAttributes(activity: Activity): string[] {
    return Object.keys(activity.changes?.attributes ?? {});
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Activity Log')"
            :description="t('A record of every change made in the system.')"
            :breadcrumbs="[{ title: t('Activity Log') }]"
        />

        <DataCard :pagination="activities">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search...')"
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.log_name" class="sm:w-44">
                    <option value="">{{ t('All logs') }}</option>
                    <option v-for="name in logNames" :key="name" :value="name">
                        {{ t(name) }}
                    </option>
                </BaseSelect>
                <BaseSelect v-model="filters.event" class="sm:w-44">
                    <option value="">{{ t('All events') }}</option>
                    <option v-for="item in events" :key="item" :value="item">
                        {{ t(item) }}
                    </option>
                </BaseSelect>
            </template>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="activities.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Event') }}</th>
                            <th class="th-cell">{{ t('Subject') }}</th>
                            <th class="th-cell">{{ t('Changes') }}</th>
                            <th class="th-cell">{{ t('By') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="activity in activities.data"
                            :key="activity.id"
                        >
                            <td class="td-cell">
                                <BaseBadge :color="eventColor(activity.event)">
                                    {{
                                        t(
                                            activity.event ??
                                                activity.description,
                                        )
                                    }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">
                                <span
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{
                                        t(
                                            activity.subject_type ??
                                                activity.log_name ??
                                                '',
                                        )
                                    }}
                                </span>
                                <span v-if="activity.subject_id">
                                    #{{ activity.subject_id }}
                                </span>
                            </td>
                            <td class="td-cell">
                                <div class="flex flex-wrap gap-1">
                                    <BaseBadge
                                        v-for="attribute in changedAttributes(
                                            activity,
                                        )"
                                        :key="attribute"
                                        color="light"
                                    >
                                        {{ t(attribute) }}
                                    </BaseBadge>
                                    <span
                                        v-if="
                                            !changedAttributes(activity).length
                                        "
                                        >—</span
                                    >
                                </div>
                            </td>
                            <td class="td-cell">
                                {{ activity.causer ?? t('System') }}
                            </td>
                            <td
                                class="td-cell"
                                :title="
                                    formatDate(activity.created_at, locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })
                                "
                            >
                                {{
                                    formatRelative(activity.created_at, locale)
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No activity found.')" />
            </div>
        </DataCard>
    </div>
</template>
