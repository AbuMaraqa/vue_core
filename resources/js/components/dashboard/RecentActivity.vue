<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BaseBadge from '@/components/base/BaseBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatRelative } from '@/lib/format';
import { eventColor } from '@/lib/activity';
import { index as activityLog } from '@/routes/admin/activity-log';
import type { Activity } from '@/types';

defineProps<{
    activities: Activity[];
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
</script>

<template>
    <div
        class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div class="mb-6 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                {{ t('Recent activity') }}
            </h3>
            <Link
                v-if="can('Activity Log View List')"
                :href="activityLog()"
                class="text-sm text-brand-500 hover:text-brand-600 dark:text-brand-400"
            >
                {{ t('See all') }}
            </Link>
        </div>

        <ul v-if="activities.length" class="space-y-5">
            <li
                v-for="activity in activities"
                :key="activity.id"
                class="flex items-start justify-between gap-3"
            >
                <div class="min-w-0">
                    <p
                        class="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90"
                    >
                        {{
                            t(activity.subject_type ?? activity.log_name ?? '')
                        }}
                        <span v-if="activity.subject_id" class="text-gray-400"
                            >#{{ activity.subject_id }}</span
                        >
                    </p>
                    <p class="text-theme-xs text-gray-500 dark:text-gray-400">
                        {{ activity.causer ?? t('System') }} ·
                        {{ formatRelative(activity.created_at, locale) }}
                    </p>
                </div>
                <BaseBadge :color="eventColor(activity.event)">
                    {{ t(activity.event ?? activity.description) }}
                </BaseBadge>
            </li>
        </ul>
        <EmptyState v-else :message="t('No activity yet.')" />
    </div>
</template>
