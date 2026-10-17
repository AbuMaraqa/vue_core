<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import TablePagination from '@/components/common/TablePagination.vue';
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

const search = ref(props.filters.search ?? '');
const event = ref(props.filters.event ?? '');
const logName = ref(props.filters.log_name ?? '');

function applyFilters(): void {
    router.get(
        index.url(),
        {
            filter: {
                search: search.value || undefined,
                event: event.value || undefined,
                log_name: logName.value || undefined,
            },
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, applyFilters, { debounce: 350 });

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

        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 px-5 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="relative w-full lg:max-w-sm">
                    <Search
                        class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-gray-400"
                    />
                    <BaseInput
                        v-model="search"
                        type="search"
                        class="ps-12"
                        :placeholder="t('Search...')"
                    />
                </div>
                <div class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto">
                    <BaseSelect
                        v-model="logName"
                        class="sm:w-44"
                        @change="applyFilters"
                    >
                        <option value="">{{ t('All logs') }}</option>
                        <option
                            v-for="name in logNames"
                            :key="name"
                            :value="name"
                        >
                            {{ t(name) }}
                        </option>
                    </BaseSelect>
                    <BaseSelect
                        v-model="event"
                        class="sm:w-44"
                        @change="applyFilters"
                    >
                        <option value="">{{ t('All events') }}</option>
                        <option
                            v-for="item in events"
                            :key="item"
                            :value="item"
                        >
                            {{ t(item) }}
                        </option>
                    </BaseSelect>
                </div>
            </div>

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

            <TablePagination
                :meta="activities.meta"
                :links="activities.links"
            />
        </div>
    </div>
</template>
