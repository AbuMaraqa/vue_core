import type { CalendarColor } from '@/types';

/**
 * The classes of each calendar color: a dot, and a soft chip. Written out in
 * full so Tailwind finds them.
 */
export const calendarColors: Record<
    CalendarColor,
    { dot: string; chip: string }
> = {
    brand: {
        dot: 'bg-brand-500',
        chip: 'bg-brand-50 text-brand-700 hover:bg-brand-100 dark:bg-brand-500/15 dark:text-brand-300',
    },
    info: {
        dot: 'bg-blue-light-500',
        chip: 'bg-blue-light-50 text-blue-light-700 hover:bg-blue-light-100 dark:bg-blue-light-500/15 dark:text-blue-light-300',
    },
    success: {
        dot: 'bg-success-500',
        chip: 'bg-success-50 text-success-700 hover:bg-success-100 dark:bg-success-500/15 dark:text-success-400',
    },
    warning: {
        dot: 'bg-warning-500',
        chip: 'bg-warning-50 text-warning-700 hover:bg-warning-100 dark:bg-warning-500/15 dark:text-orange-300',
    },
    error: {
        dot: 'bg-error-500',
        chip: 'bg-error-50 text-error-700 hover:bg-error-100 dark:bg-error-500/15 dark:text-error-400',
    },
    orange: {
        dot: 'bg-orange-500',
        chip: 'bg-orange-50 text-orange-700 hover:bg-orange-100 dark:bg-orange-500/15 dark:text-orange-400',
    },
    purple: {
        dot: 'bg-theme-purple-500',
        chip: 'bg-theme-purple-500/10 text-theme-purple-500 hover:bg-theme-purple-500/20 dark:bg-theme-purple-500/20',
    },
    pink: {
        dot: 'bg-theme-pink-500',
        chip: 'bg-theme-pink-500/10 text-theme-pink-500 hover:bg-theme-pink-500/20 dark:bg-theme-pink-500/20',
    },
    gray: {
        dot: 'bg-gray-400',
        chip: 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10',
    },
};

/**
 * A `YYYY-MM-DD` day moved by a number of days, without time zone shifts.
 */
export function addDays(day: string, days: number): string {
    const [year, month, date] = day.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, date + days))
        .toISOString()
        .slice(0, 10);
}

/**
 * Every day from `from` to `to`, both included.
 */
export function daysBetween(from: string, to: string): string[] {
    const days: string[] = [];

    for (let day = from; day <= to; day = addDays(day, 1)) {
        days.push(day);
    }

    return days;
}

/**
 * The `YYYY-MM` month next to another one.
 */
export function shiftMonth(month: string, months: number): string {
    const [year, value] = month.split('-').map(Number);
    const date = new Date(Date.UTC(year, value - 1 + months, 1));

    return date.toISOString().slice(0, 7);
}
