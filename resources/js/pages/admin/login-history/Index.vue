<script setup lang="ts">
import Smartphone from '~icons/solar/smartphone-linear';
import Monitor from '~icons/solar/monitor-linear';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import ExportMenu from '@/components/common/ExportMenu.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { describeDevice } from '@/lib/device';
import { formatDate, formatRelative } from '@/lib/format';
import { exportMethod, index } from '@/routes/admin/login-history';
import type { AuthenticationLog, Paginated, SelectOption } from '@/types';

const props = defineProps<{
    logs: Paginated<AuthenticationLog>;
    events: SelectOption[];
    filters: {
        search: string | null;
        event: string | null;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters } = useIndexFilters(index, {
    search: props.filters.search,
    event: props.filters.event,
});
</script>

<template>
    <div>
        <PageHeader
            :title="t('Login History')"
            :description="
                t(
                    'Every sign-in, sign-out and failed attempt, to spot suspicious access.',
                )
            "
            :breadcrumbs="[{ title: t('Login History') }]"
        >
            <template #actions>
                <ExportMenu
                    v-if="can('Login History Export')"
                    :route="exportMethod"
                />
            </template>
        </PageHeader>

        <DataCard :pagination="logs">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search by email or IP address...')"
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.event" class="sm:w-44">
                    <option value="">{{ t('All events') }}</option>
                    <option
                        v-for="option in events"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </BaseSelect>
            </template>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="logs.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Event') }}</th>
                            <th class="th-cell">{{ t('Account') }}</th>
                            <th class="th-cell">{{ t('Device') }}</th>
                            <th class="th-cell">{{ t('IP address') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="log in logs.data" :key="log.id">
                            <td class="td-cell">
                                <BaseBadge :color="log.event_color">
                                    {{ log.event_label }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">
                                <p
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ log.user?.name ?? t('Unknown account') }}
                                </p>
                                <span class="text-theme-xs">
                                    {{ log.email ?? '—' }}
                                </span>
                            </td>
                            <td class="td-cell">
                                <span class="inline-flex items-center gap-2">
                                    <Smartphone
                                        v-if="log.device.is_mobile"
                                        class="size-4"
                                    />
                                    <Monitor v-else class="size-4" />
                                    {{ describeDevice(log.device, t) }}
                                </span>
                            </td>
                            <td class="td-cell font-mono" dir="ltr">
                                {{ log.ip_address ?? '—' }}
                            </td>
                            <td
                                class="td-cell"
                                :title="
                                    formatDate(log.created_at, locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })
                                "
                            >
                                {{ formatRelative(log.created_at, locale) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No sign-ins recorded yet.')" />
            </div>
        </DataCard>
    </div>
</template>
