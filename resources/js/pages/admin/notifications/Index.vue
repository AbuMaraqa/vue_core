<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import CheckRead from '~icons/solar/check-read-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useTranslations } from '@/composables/useTranslations';
import { destroy, index, readAll } from '@/routes/notifications';
import type { AppNotification, Paginated } from '@/types';

const props = defineProps<{
    notifications: Paginated<AppNotification>;
    filters: {
        status: string | null;
    };
}>();

const page = usePage();
const { t } = useTranslations();

const { filters } = useIndexFilters(index, { status: props.filters.status });

function markAllAsRead(): void {
    router.visit(readAll(), { preserveScroll: true });
}

function remove(notification: AppNotification): void {
    router.visit(destroy({ notification: notification.id }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Notifications')"
            :breadcrumbs="[{ title: t('Notifications') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="page.props.unreadNotifications > 0"
                    variant="outline"
                    @click="markAllAsRead"
                >
                    <CheckRead class="size-5" />
                    {{ t('Mark all as read') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="notifications">
            <template #filters>
                <BaseSelect v-model="filters.status" class="sm:w-44">
                    <option value="">{{ t('All notifications') }}</option>
                    <option value="unread">{{ t('Unread') }}</option>
                    <option value="read">{{ t('Read') }}</option>
                </BaseSelect>
            </template>

            <ul
                v-if="notifications.data.length"
                class="divide-y divide-gray-100 border-t border-gray-100 px-3 py-2 dark:divide-gray-800 dark:border-gray-800"
            >
                <li
                    v-for="notification in notifications.data"
                    :key="notification.id"
                    class="flex items-start gap-2 py-1"
                >
                    <NotificationItem
                        class="flex-1"
                        :notification="notification"
                    />
                    <BaseIconButton
                        class="mt-2"
                        color="danger"
                        :label="t('Delete')"
                        @click="remove(notification)"
                    >
                        <Trash2 />
                    </BaseIconButton>
                </li>
            </ul>
            <EmptyState v-else :message="t('You have no notifications.')" />
        </DataCard>
    </div>
</template>
