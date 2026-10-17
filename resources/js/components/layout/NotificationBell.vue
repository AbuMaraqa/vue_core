<script setup lang="ts">
/**
 * The bell in the header: the unread count comes with every page, and the
 * newest notifications are fetched when the dropdown opens.
 */
import { Link, router, useHttp, usePage } from '@inertiajs/vue3';
import Bell from '~icons/solar/bell-linear';
import { onClickOutside } from '@vueuse/core';
import { ref, useTemplateRef } from 'vue';
import EmptyState from '@/components/common/EmptyState.vue';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index, latest, readAll } from '@/routes/notifications';
import type { AppNotification } from '@/types';

const page = usePage();
const { t } = useTranslations();

const isOpen = ref(false);
const notifications = ref<AppNotification[]>([]);
const container = useTemplateRef('container');

const request = useHttp<
    Record<string, never>,
    { unread_count: number; notifications: AppNotification[] }
>({});

onClickOutside(container, () => (isOpen.value = false));

async function toggle(): Promise<void> {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        const response = await request.get(latest.url());
        notifications.value = response.notifications;
    }
}

function markAllAsRead(): void {
    router.visit(readAll(), {
        preserveScroll: true,
        onSuccess: () => {
            notifications.value = notifications.value.map((notification) => ({
                ...notification,
                read_at: notification.read_at ?? new Date().toISOString(),
            }));
        },
    });
}
</script>

<template>
    <div ref="container" class="relative">
        <button
            type="button"
            :title="t('Notifications')"
            :aria-label="t('Notifications')"
            class="relative flex size-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
            @click="toggle"
        >
            <Bell class="size-5" />
            <span
                v-if="page.props.unreadNotifications > 0"
                class="absolute -end-1 -top-1 flex min-w-5 items-center justify-center rounded-full bg-error-500 px-1 text-[10px] leading-5 font-semibold text-white"
            >
                {{
                    page.props.unreadNotifications > 99
                        ? '99+'
                        : page.props.unreadNotifications
                }}
            </span>
        </button>

        <div
            v-if="isOpen"
            class="absolute end-0 mt-3 flex w-[min(360px,calc(100vw-2rem))] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="mb-2 flex items-center justify-between border-b border-gray-100 px-2 pb-3 dark:border-gray-800"
            >
                <h5 class="font-semibold text-gray-800 dark:text-white/90">
                    {{ t('Notifications') }}
                </h5>
                <button
                    v-if="page.props.unreadNotifications > 0"
                    type="button"
                    class="text-theme-xs font-medium text-brand-500 hover:text-brand-600"
                    @click="markAllAsRead"
                >
                    {{ t('Mark all as read') }}
                </button>
            </div>

            <div
                v-if="request.processing && !notifications.length"
                class="space-y-2 p-2"
            >
                <div
                    v-for="row in 3"
                    :key="row"
                    class="h-14 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-800"
                />
            </div>
            <ul
                v-else-if="notifications.length"
                class="custom-scrollbar max-h-96 space-y-1 overflow-y-auto"
            >
                <li
                    v-for="notification in notifications"
                    :key="notification.id"
                >
                    <NotificationItem
                        :notification="notification"
                        @opened="isOpen = false"
                    />
                </li>
            </ul>
            <EmptyState v-else :message="t('You have no notifications.')" />

            <Link
                :href="index()"
                class="mt-2 block rounded-lg border border-gray-200 py-2.5 text-center text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-white/5"
                @click="isOpen = false"
            >
                {{ t('View all notifications') }}
            </Link>
        </div>
    </div>
</template>
