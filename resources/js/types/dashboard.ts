/**
 * The color of a dashboard icon (DashboardShortcut::tone, DashboardStat::tone).
 */
export type DashboardTone = 'brand' | 'success' | 'error' | 'warning' | 'info';

/**
 * A quick-action button of the dashboard (DashboardShortcut::toArray).
 */
export type DashboardShortcut = {
    key: string;
    title: string;
    description: string | null;
    icon: string | null;
    tone: DashboardTone;
    url: string;
};

/**
 * A key figure of the dashboard (DashboardStat::toArray).
 */
export type DashboardStat = {
    key: string;
    label: string;
    value: string;
    currency: string | null;
    decimals: number;
    hint: string | null;
    icon: string | null;
    tone: DashboardTone;
    url: string | null;
};
