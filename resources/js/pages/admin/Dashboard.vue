<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import ActivityIcon from '~icons/solar/pulse-linear';
import ShieldCheck from '~icons/solar/shield-check-linear';
import UserPlus from '~icons/solar/user-plus-rounded-linear';
import Users from '~icons/solar/users-group-rounded-linear';
import { defineAsyncComponent } from 'vue';
import BaseCard from '@/components/base/BaseCard.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import RecentActivity from '@/components/dashboard/RecentActivity.vue';
import RecentUsers from '@/components/dashboard/RecentUsers.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatNumber } from '@/lib/format';
import type { Activity, ManagedUser, Resource } from '@/types';

// The charting library is large, so it is only downloaded once the chart is rendered.
const RegistrationsChart = defineAsyncComponent(
    () => import('@/components/dashboard/RegistrationsChart.vue'),
);

defineProps<{
    stats: {
        users: number;
        newUsers: number;
        newUsersChange: number | null;
        roles: number;
        activityToday: number;
    };
    registrations?: Array<{ month: string; total: number }>;
    recentUsers: Resource<ManagedUser[]>;
    recentActivity: Resource<Activity[]>;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
</script>

<template>
    <div>
        <PageHeader :title="t('Dashboard')" />

        <div class="grid grid-cols-12 gap-4 md:gap-6">
            <div
                class="col-span-12 grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4"
            >
                <StatCard
                    :label="t('Total users')"
                    :value="formatNumber(stats.users, locale)"
                    :icon="Users"
                />
                <StatCard
                    :label="t('New users this month')"
                    :value="formatNumber(stats.newUsers, locale)"
                    :icon="UserPlus"
                    :change="stats.newUsersChange"
                />
                <StatCard
                    :label="t('Roles')"
                    :value="formatNumber(stats.roles, locale)"
                    :icon="ShieldCheck"
                />
                <StatCard
                    :label="t('Activity today')"
                    :value="formatNumber(stats.activityToday, locale)"
                    :icon="ActivityIcon"
                />
            </div>

            <div class="col-span-12">
                <BaseCard
                    :title="t('User registrations')"
                    :description="t('New accounts over the last 12 months')"
                >
                    <Deferred data="registrations">
                        <template #fallback>
                            <div
                                class="flex h-[220px] animate-pulse items-end gap-4 px-2"
                            >
                                <div
                                    v-for="index in 12"
                                    :key="index"
                                    class="flex-1 rounded-t-md bg-gray-100 dark:bg-gray-800"
                                    :style="{
                                        height: `${20 + ((index * 37) % 70)}%`,
                                    }"
                                />
                            </div>
                        </template>
                        <RegistrationsChart :data="registrations ?? []" />
                    </Deferred>
                </BaseCard>
            </div>

            <div class="col-span-12 xl:col-span-7">
                <RecentUsers :users="recentUsers.data" />
            </div>

            <div class="col-span-12 xl:col-span-5">
                <RecentActivity :activities="recentActivity.data" />
            </div>
        </div>
    </div>
</template>
