const RELATIVE_UNITS: Array<[Intl.RelativeTimeFormatUnit, number]> = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
    ['second', 1],
];

type Formatter =
    | Intl.NumberFormat
    | Intl.DateTimeFormat
    | Intl.RelativeTimeFormat;

/**
 * Building an Intl formatter is far slower than using one, and long reports
 * format thousands of values per render, so each formatter is built once per
 * locale and options and then reused.
 */
const formatters = new Map<string, Formatter>();

function cached<T extends Formatter>(
    kind: string,
    locale: string,
    options: object,
    build: () => T,
): T {
    const key = `${kind}|${locale}|${JSON.stringify(options)}`;
    let formatter = formatters.get(key) as T | undefined;

    if (formatter === undefined) {
        formatter = build();
        formatters.set(key, formatter);
    }

    return formatter;
}

/**
 * A cached `Intl.NumberFormat`, for callers that format numbers themselves.
 */
export function numberFormat(
    locale: string,
    options: Intl.NumberFormatOptions = {},
): Intl.NumberFormat {
    return cached(
        'number',
        locale,
        options,
        () => new Intl.NumberFormat(locale, options),
    );
}

/**
 * A cached `Intl.DateTimeFormat`, for callers that format dates themselves.
 */
export function dateFormat(
    locale: string,
    options: Intl.DateTimeFormatOptions = {},
): Intl.DateTimeFormat {
    return cached(
        'date',
        locale,
        options,
        () => new Intl.DateTimeFormat(locale, options),
    );
}

export function formatNumber(value: number, locale: string): string {
    return numberFormat(locale).format(value);
}

export function formatDate(
    value: string | null,
    locale: string,
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
): string {
    return value ? dateFormat(locale, options).format(new Date(value)) : '—';
}

/**
 * Format a date relative to now, e.g. "3 hours ago".
 */
export function formatRelative(value: string | null, locale: string): string {
    if (!value) {
        return '—';
    }

    const seconds = (new Date(value).getTime() - Date.now()) / 1000;
    const formatter = cached(
        'relative',
        locale,
        {},
        () => new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }),
    );

    for (const [unit, unitSeconds] of RELATIVE_UNITS) {
        if (Math.abs(seconds) >= unitSeconds || unit === 'second') {
            return formatter.format(Math.round(seconds / unitSeconds), unit);
        }
    }

    return formatter.format(0, 'second');
}

const BYTE_UNITS = ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'terabyte'];

/**
 * Format a file size, e.g. "4.2 MB", in the given locale.
 */
export function formatBytes(bytes: number, locale: string): string {
    const exponent = Math.min(
        Math.floor(Math.log(Math.max(bytes, 1)) / Math.log(1024)),
        BYTE_UNITS.length - 1,
    );

    return numberFormat(locale, {
        style: 'unit',
        unit: BYTE_UNITS[exponent],
        unitDisplay: 'short',
        maximumFractionDigits: exponent === 0 ? 0 : 1,
    }).format(bytes / 1024 ** exponent);
}

/**
 * Format an amount of money, e.g. "1,250.500 JOD". Amounts come from the
 * server as decimal strings to keep their precision; Latin digits are used in
 * every language, as accounting documents expect.
 */
export function formatMoney(
    amount: string | number | null,
    currency: string,
    locale: string,
    decimals = 2,
): string {
    if (amount === null || amount === '') {
        return '—';
    }

    return numberFormat(`${locale}-u-nu-latn`, {
        style: 'currency',
        currency,
        currencyDisplay: 'code',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(Number(amount));
}

/**
 * Format a plain decimal, e.g. a quantity or an amount without currency.
 */
export function formatDecimal(
    value: string | number | null,
    locale: string,
    decimals = 2,
): string {
    if (value === null || value === '') {
        return '—';
    }

    return numberFormat(`${locale}-u-nu-latn`, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(Number(value));
}
