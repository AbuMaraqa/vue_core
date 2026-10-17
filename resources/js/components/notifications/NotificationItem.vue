<script setup lang="ts">
/**
 * One notification, shared by the notification bell and the notifications
 * page. Clicking it marks it as read and opens the page it is about.
 */
import { router } from '@inertiajs/vue3';
import { useLocale } from '@/composables/useLocale';
import { formatRelative } from '@/lib/format';
import { open } from '@/routes/notifications';
import type { AppNotification } from '@/types';

const props = defineProps<{
    notification: AppNotification;
}>();

const emit = defineEmits<{
    opened: [];
}>();

const { locale } = useLocale();

const dots: Record<AppNotification['level'], string> = {
    info: 'bg-blue-light-500',
    success: 'bg-success-500',
    warning: 'bg-warning-500',
    error: 'bg-error-500',
};

function openNotification(): void {
    emit('opened');
    router.visit(open({ notification: props.notification.id }));
}
</script>

<template>
    <button
        type="button"
        :class="[
            'flex w-full gap-3 rounded-lg px-3 py-3 text-start transition hover:bg-gray-100 dark:hover:bg-white/5',
            { 'bg-brand-25 dark:bg-brand-500/5': !notification.read_at },
        ]"
        @click="openNotification"
    >
        <span
            :class="[
                'mt-1.5 size-2.5 shrink-0 rounded-full',
                notification.read_at
                    ? 'bg-gray-300 dark:bg-gray-700'
                    : dots[notification.level],
            ]"
        />
        <span class="min-w-0 flex-1">
            <span
                class="block text-theme-sm font-medium text-gray-800 dark:text-white/90"
            >
                {{ notification.title }}
            </span>
            <span
                class="mt-0.5 block text-theme-sm text-gray-500 dark:text-gray-400"
            >
                {{ notification.body }}
            </span>
            <span
                class="mt-1 block text-theme-xs text-gray-400 dark:text-gray-500"
            >
                {{ formatRelative(notification.created_at, locale) }}
            </span>
        </span>
    </button>
</template>
