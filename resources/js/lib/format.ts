const RELATIVE_UNITS: Array<[Intl.RelativeTimeFormatUnit, number]> = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
    ['second', 1],
];

export function formatNumber(value: number, locale: string): string {
    return new Intl.NumberFormat(locale).format(value);
}

export function formatDate(
    value: string | null,
    locale: string,
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
): string {
    return value
        ? new Intl.DateTimeFormat(locale, options).format(new Date(value))
        : '—';
}

/**
 * Format a date relative to now, e.g. "3 hours ago".
 */
export function formatRelative(value: string | null, locale: string): string {
    if (!value) {
        return '—';
    }

    const seconds = (new Date(value).getTime() - Date.now()) / 1000;
    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    for (const [unit, unitSeconds] of RELATIVE_UNITS) {
        if (Math.abs(seconds) >= unitSeconds || unit === 'second') {
            return formatter.format(Math.round(seconds / unitSeconds), unit);
        }
    }

    return formatter.format(0, 'second');
}
