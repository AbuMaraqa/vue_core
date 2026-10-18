import type { DashboardTone } from '@/types';

/**
 * The classes of the colored icon box of a dashboard shortcut or figure.
 */
export const toneClasses: Record<DashboardTone, string> = {
    brand: 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
    success:
        'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
    error: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
    warning:
        'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-500',
    info: 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-500',
};
