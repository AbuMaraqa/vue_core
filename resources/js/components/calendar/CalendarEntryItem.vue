<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ArrowEnd from '~icons/solar/alt-arrow-right-linear';
import Clock from '~icons/solar/clock-circle-linear';
import Users from '~icons/solar/users-group-rounded-linear';
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { calendarColors } from '@/lib/calendar';
import { formatDate, formatMoney } from '@/lib/format';
import type { CalendarColor, CalendarEntry } from '@/types';

/**
 * One entry of a list (the day panel, the agenda, overdue items): its color,
 * title, who it concerns, its amount, and a link to its page. Reminders
 * open for editing instead.
 */
const props = withDefaults(
    defineProps<{
        entry: CalendarEntry;
        sourceColor: CalendarColor;
        showDate?: boolean;
    }>(),
    { showDate: false },
);

const emit = defineEmits<{
    edit: [entry: CalendarEntry];
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const color = computed(
    () => calendarColors[props.entry.color ?? props.sourceColor],
);

const isReminder = computed(() => props.entry.data.reminder_id !== undefined);
</script>

<template>
    <component
        :is="entry.url ? Link : 'button'"
        :href="entry.url ?? undefined"
        :type="entry.url ? undefined : 'button'"
        class="group flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-start transition hover:bg-gray-50 dark:hover:bg-white/[0.03]"
        @click="!entry.url && isReminder && emit('edit', entry)"
    >
        <span
            class="mt-1.5 size-2.5 shrink-0 rounded-full"
            :class="color.dot"
        />
        <span class="min-w-0 flex-1">
            <span
                class="block truncate text-theme-sm font-medium text-gray-800 dark:text-white/90"
            >
                {{ entry.title }}
            </span>
            <span
                class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-theme-xs text-gray-500 dark:text-gray-400"
            >
                <span v-if="showDate">{{
                    formatDate(entry.date, locale)
                }}</span>
                <span v-if="entry.time" class="inline-flex items-center gap-1">
                    <Clock class="size-3.5" />
                    <span dir="ltr">{{ entry.time }}</span>
                </span>
                <span v-if="entry.subtitle" class="truncate">
                    {{ entry.subtitle }}
                </span>
                <span
                    v-if="entry.status"
                    :class="
                        entry.overdue
                            ? 'font-medium text-error-600 dark:text-error-500'
                            : ''
                    "
                >
                    {{ entry.overdue ? t('Overdue') : entry.status }}
                </span>
                <span
                    v-if="entry.data.is_shared"
                    class="inline-flex items-center gap-1"
                >
                    <Users class="size-3.5" />
                    {{ t('Shared') }}
                </span>
            </span>
            <span
                v-if="entry.data.notes"
                class="mt-1 line-clamp-2 block text-theme-xs text-gray-500 dark:text-gray-400"
            >
                {{ entry.data.notes }}
            </span>
        </span>
        <span
            v-if="entry.amount && entry.currency"
            class="shrink-0 font-mono text-theme-sm font-medium text-gray-800 dark:text-white/90"
            dir="ltr"
        >
            {{
                formatMoney(
                    entry.amount,
                    entry.currency,
                    locale,
                    entry.decimals,
                )
            }}
        </span>
        <ArrowEnd
            v-if="entry.url"
            class="mt-1 size-4 shrink-0 text-gray-300 transition group-hover:text-gray-500 rtl:rotate-180 dark:text-gray-600"
        />
    </component>
</template>
