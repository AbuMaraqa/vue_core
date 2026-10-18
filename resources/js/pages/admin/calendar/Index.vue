<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import ArrowLeft from '~icons/solar/alt-arrow-left-linear';
import ArrowRight from '~icons/solar/alt-arrow-right-linear';
import Danger from '~icons/solar/danger-triangle-linear';
import Plus from '~icons/solar/add-linear';
import { computed, ref, watch } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import CalendarEntryItem from '@/components/calendar/CalendarEntryItem.vue';
import ReminderDialog from '@/components/calendar/ReminderDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { calendarColors, daysBetween, shiftMonth } from '@/lib/calendar';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/admin/calendar';
import type { CalendarColor, CalendarEntry, CalendarSourceInfo } from '@/types';

const props = defineProps<{
    month: string;
    today: string;
    range: { from: string; to: string };
    weekStartsOn: number;
    sources: CalendarSourceInfo[];
    entries: CalendarEntry[];
    upcoming: CalendarEntry[];
    overdue: CalendarEntry[];
    reminderColors: CalendarColor[];
}>();

const { t } = useTranslations();
const { locale } = useLocale();

/**
 * How many entries a day cell shows before "+N more".
 */
const VISIBLE_PER_DAY = 3;
const HIDDEN_STORAGE_KEY = 'calendar.hiddenSources';

const view = ref<'month' | 'list'>('month');

/**
 * The sources switched off in the legend, remembered in this browser.
 */
const hidden = ref<string[]>(readHidden());

function readHidden(): string[] {
    try {
        const stored = JSON.parse(
            window.localStorage.getItem(HIDDEN_STORAGE_KEY) ?? '[]',
        );

        return Array.isArray(stored) ? stored : [];
    } catch {
        return [];
    }
}

watch(hidden, (value) => {
    try {
        window.localStorage.setItem(HIDDEN_STORAGE_KEY, JSON.stringify(value));
    } catch {
        // Storage may be unavailable (private mode); the filter still works.
    }
});

function toggleSource(key: string, visible: boolean): void {
    hidden.value = visible
        ? hidden.value.filter((hiddenKey) => hiddenKey !== key)
        : [...hidden.value, key];
}

const sourceColors = computed<Record<string, CalendarColor>>(() =>
    Object.fromEntries(
        props.sources.map((source) => [source.key, source.color]),
    ),
);

const colorOf = (entry: CalendarEntry): CalendarColor =>
    entry.color ?? sourceColors.value[entry.type] ?? 'gray';

const isVisible = (entry: CalendarEntry): boolean =>
    !hidden.value.includes(entry.type);

const visibleEntries = computed(() => props.entries.filter(isVisible));

const entriesByDay = computed(() => {
    const days: Record<string, CalendarEntry[]> = {};

    for (const entry of visibleEntries.value) {
        (days[entry.date] ??= []).push(entry);
    }

    return days;
});

const counts = computed<Record<string, number>>(() => {
    const totals: Record<string, number> = {};

    for (const entry of props.entries) {
        totals[entry.type] = (totals[entry.type] ?? 0) + 1;
    }

    return totals;
});

const days = computed(() => daysBetween(props.range.from, props.range.to));

const weekdays = computed(() =>
    days.value
        .slice(0, 7)
        .map((day) => formatDate(day, locale.value, { weekday: 'short' })),
);

const monthTitle = computed(() =>
    formatDate(`${props.month}-01`, locale.value, {
        month: 'long',
        year: 'numeric',
    }),
);

const isInMonth = (day: string): boolean => day.startsWith(props.month);

const monthDays = computed(() =>
    days.value.filter((day) => isInMonth(day) && entriesByDay.value[day]),
);

const visibleUpcoming = computed(() => props.upcoming.filter(isVisible));
const visibleOverdue = computed(() => props.overdue.filter(isVisible));

const upcomingByDay = computed(() => {
    const grouped: Array<{ day: string; entries: CalendarEntry[] }> = [];

    for (const entry of visibleUpcoming.value) {
        const last = grouped.at(-1);

        if (last?.day === entry.date) {
            last.entries.push(entry);
        } else {
            grouped.push({ day: entry.date, entries: [entry] });
        }
    }

    return grouped;
});

function goTo(month: string): void {
    router.visit(index(undefined, { query: { month } }), {
        preserveScroll: true,
        preserveState: true,
        only: ['month', 'range', 'entries'],
    });
}

/**
 * The day whose entries are open in the day panel.
 */
const selectedDay = ref<string | null>(null);

const selectedEntries = computed(() =>
    selectedDay.value ? (entriesByDay.value[selectedDay.value] ?? []) : [],
);

const reminderOpen = ref(false);
const editedReminder = ref<CalendarEntry | null>(null);
const reminderDate = ref(props.today);

function addReminder(day: string = props.today): void {
    editedReminder.value = null;
    reminderDate.value = day;
    selectedDay.value = null;
    reminderOpen.value = true;
}

function editReminder(entry: CalendarEntry): void {
    if (!entry.data.editable) {
        return;
    }

    editedReminder.value = entry;
    selectedDay.value = null;
    reminderOpen.value = true;
}

/**
 * A chip opens the entry's page, or the reminder for editing.
 */
function openEntry(entry: CalendarEntry): void {
    if (entry.url) {
        router.visit(entry.url);
    } else {
        editReminder(entry);
    }
}

const dayLabel = (day: string): string =>
    formatDate(day, locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
</script>

<template>
    <div>
        <PageHeader
            :title="t('Calendar')"
            :description="
                t(
                    'Every date of the system in one place: cheques, invoices, salaries, production and your reminders.',
                )
            "
            :breadcrumbs="[{ title: t('Calendar') }]"
        >
            <template #actions>
                <BaseButton @click="addReminder()">
                    <Plus class="size-5" />
                    {{ t('Add reminder') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 xl:col-span-9">
                <div
                    class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5 dark:border-gray-800"
                    >
                        <div class="flex items-center gap-2">
                            <BaseIconButton
                                :label="t('Previous month')"
                                @click="goTo(shiftMonth(month, -1))"
                            >
                                <ArrowLeft class="rtl:rotate-180" />
                            </BaseIconButton>
                            <BaseIconButton
                                :label="t('Next month')"
                                @click="goTo(shiftMonth(month, 1))"
                            >
                                <ArrowRight class="rtl:rotate-180" />
                            </BaseIconButton>
                            <h2
                                class="ms-2 text-lg font-semibold text-gray-800 dark:text-white/90"
                            >
                                {{ monthTitle }}
                            </h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <BaseButton
                                v-if="!today.startsWith(month)"
                                size="sm"
                                variant="outline"
                                @click="goTo(today.slice(0, 7))"
                            >
                                {{ t('Today') }}
                            </BaseButton>
                            <div
                                class="flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900"
                            >
                                <button
                                    v-for="option in [
                                        { value: 'month', label: t('Month') },
                                        { value: 'list', label: t('List') },
                                    ] as const"
                                    :key="option.value"
                                    type="button"
                                    class="rounded-md px-3 py-1.5 text-theme-sm font-medium transition"
                                    :class="
                                        view === option.value
                                            ? 'bg-white text-gray-800 shadow-theme-xs dark:bg-gray-800 dark:text-white'
                                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                                    "
                                    @click="view = option.value"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="view === 'month'"
                        class="custom-scrollbar overflow-x-auto"
                    >
                        <div class="min-w-[720px]">
                            <div
                                class="grid grid-cols-7 border-b border-gray-100 dark:border-gray-800"
                            >
                                <div
                                    v-for="weekday in weekdays"
                                    :key="weekday"
                                    class="px-2 py-2 text-center text-theme-xs font-medium text-gray-500 dark:text-gray-400"
                                >
                                    {{ weekday }}
                                </div>
                            </div>
                            <div class="grid grid-cols-7">
                                <div
                                    v-for="day in days"
                                    :key="day"
                                    role="button"
                                    tabindex="0"
                                    class="group min-h-28 cursor-pointer border-b border-gray-100 p-1.5 transition not-[:nth-child(7n)]:border-e hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]"
                                    :class="
                                        isInMonth(day)
                                            ? ''
                                            : 'bg-gray-50/60 dark:bg-white/[0.01]'
                                    "
                                    @click="selectedDay = day"
                                    @keydown.enter="selectedDay = day"
                                >
                                    <div
                                        class="mb-1 flex items-center justify-between"
                                    >
                                        <span
                                            class="flex size-7 items-center justify-center rounded-full text-theme-sm"
                                            :class="
                                                day === today
                                                    ? 'bg-brand-500 font-semibold text-white'
                                                    : isInMonth(day)
                                                      ? 'text-gray-700 dark:text-gray-300'
                                                      : 'text-gray-400 dark:text-gray-600'
                                            "
                                        >
                                            {{ Number(day.slice(8)) }}
                                        </span>
                                        <button
                                            type="button"
                                            class="rounded p-0.5 text-gray-400 opacity-0 transition group-hover:opacity-100 hover:text-brand-500"
                                            :aria-label="t('Add reminder')"
                                            @click.stop="addReminder(day)"
                                        >
                                            <Plus class="size-4" />
                                        </button>
                                    </div>
                                    <div class="space-y-1">
                                        <button
                                            v-for="entry in (
                                                entriesByDay[day] ?? []
                                            ).slice(0, VISIBLE_PER_DAY)"
                                            :key="entry.id"
                                            type="button"
                                            class="block w-full truncate rounded px-1.5 py-0.5 text-start text-theme-xs font-medium transition"
                                            :class="
                                                calendarColors[colorOf(entry)]
                                                    .chip
                                            "
                                            :title="
                                                [entry.title, entry.subtitle]
                                                    .filter(Boolean)
                                                    .join(' · ')
                                            "
                                            @click.stop="openEntry(entry)"
                                        >
                                            <span
                                                v-if="entry.time"
                                                class="font-mono"
                                                dir="ltr"
                                                >{{ entry.time }}</span
                                            >
                                            {{ entry.title }}
                                        </button>
                                        <button
                                            v-if="
                                                (entriesByDay[day]?.length ??
                                                    0) > VISIBLE_PER_DAY
                                            "
                                            type="button"
                                            class="w-full rounded px-1.5 text-start text-theme-xs font-medium text-gray-500 hover:text-brand-500 dark:text-gray-400"
                                            @click.stop="selectedDay = day"
                                        >
                                            {{
                                                t('+:count more', {
                                                    count:
                                                        (entriesByDay[day]
                                                            ?.length ?? 0) -
                                                        VISIBLE_PER_DAY,
                                                })
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <section
                            v-for="day in monthDays"
                            :key="day"
                            class="px-2 py-3 sm:px-3"
                        >
                            <h3
                                class="mb-1 px-3 text-theme-sm font-semibold"
                                :class="
                                    day === today
                                        ? 'text-brand-500'
                                        : 'text-gray-700 dark:text-gray-300'
                                "
                            >
                                {{ dayLabel(day) }}
                            </h3>
                            <CalendarEntryItem
                                v-for="entry in entriesByDay[day]"
                                :key="entry.id"
                                :entry="entry"
                                :source-color="colorOf(entry)"
                                @edit="editReminder"
                            />
                        </section>
                        <EmptyState
                            v-if="!monthDays.length"
                            :message="t('Nothing on the calendar this month.')"
                        />
                    </div>
                </div>
            </div>

            <div class="col-span-12 space-y-6 xl:col-span-3">
                <BaseCard :title="t('Show on the calendar')">
                    <div class="space-y-2.5">
                        <div
                            v-for="source in sources"
                            :key="source.key"
                            class="flex items-center justify-between gap-2"
                        >
                            <BaseCheckbox
                                :model-value="!hidden.includes(source.key)"
                                @update:model-value="
                                    (visible) =>
                                        toggleSource(
                                            source.key,
                                            visible === true,
                                        )
                                "
                            >
                                <span class="inline-flex items-center gap-2">
                                    <span
                                        class="size-2.5 rounded-full"
                                        :class="
                                            calendarColors[source.color].dot
                                        "
                                    />
                                    {{ source.label }}
                                </span>
                            </BaseCheckbox>
                            <span
                                class="text-theme-xs text-gray-400 dark:text-gray-500"
                            >
                                {{ counts[source.key] ?? 0 }}
                            </span>
                        </div>
                    </div>
                </BaseCard>

                <BaseCard v-if="visibleOverdue.length">
                    <div class="-mx-3 -my-2">
                        <h3
                            class="mb-1 flex items-center gap-2 px-3 font-semibold text-error-600 dark:text-error-500"
                        >
                            <Danger class="size-5" />
                            {{ t('Overdue') }}
                            <BaseBadge color="error" size="sm">
                                {{ visibleOverdue.length }}
                            </BaseBadge>
                        </h3>
                        <div class="custom-scrollbar max-h-80 overflow-y-auto">
                            <CalendarEntryItem
                                v-for="entry in visibleOverdue"
                                :key="entry.id"
                                :entry="entry"
                                :source-color="colorOf(entry)"
                                show-date
                                @edit="editReminder"
                            />
                        </div>
                    </div>
                </BaseCard>

                <BaseCard :title="t('Next 14 days')">
                    <div class="-mx-3 -my-2">
                        <section
                            v-for="group in upcomingByDay"
                            :key="group.day"
                            class="py-1"
                        >
                            <h4
                                class="px-3 text-theme-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
                            >
                                {{
                                    group.day === today
                                        ? t('Today')
                                        : dayLabel(group.day)
                                }}
                            </h4>
                            <CalendarEntryItem
                                v-for="entry in group.entries"
                                :key="entry.id"
                                :entry="entry"
                                :source-color="colorOf(entry)"
                                @edit="editReminder"
                            />
                        </section>
                        <p
                            v-if="!upcomingByDay.length"
                            class="px-3 py-2 text-theme-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ t('Nothing in the next 14 days.') }}
                        </p>
                    </div>
                </BaseCard>
            </div>
        </div>

        <BaseModal
            :open="selectedDay !== null"
            :title="selectedDay ? dayLabel(selectedDay) : ''"
            max-width="lg"
            @update:open="(isOpen: boolean) => !isOpen && (selectedDay = null)"
        >
            <div class="-mx-3 custom-scrollbar max-h-[60vh] overflow-y-auto">
                <CalendarEntryItem
                    v-for="entry in selectedEntries"
                    :key="entry.id"
                    :entry="entry"
                    :source-color="colorOf(entry)"
                    @edit="editReminder"
                />
                <p
                    v-if="!selectedEntries.length"
                    class="px-3 py-4 text-theme-sm text-gray-500 dark:text-gray-400"
                >
                    {{ t('Nothing on this day.') }}
                </p>
            </div>
            <template #footer>
                <BaseButton
                    variant="outline"
                    @click="selectedDay && addReminder(selectedDay)"
                >
                    <Plus class="size-4" />
                    {{ t('Add reminder') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ReminderDialog
            v-model:open="reminderOpen"
            :reminder="editedReminder"
            :date="reminderDate"
            :colors="reminderColors"
        />
    </div>
</template>
