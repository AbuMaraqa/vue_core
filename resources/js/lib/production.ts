import { formatDecimal } from '@/lib/format';

/**
 * Quantities show two decimals, or four when they need them.
 */
export function formatQuantity(
    value: string | number | null,
    locale: string,
): string {
    if (value === null || value === '') {
        return '—';
    }

    return formatDecimal(
        value,
        locale,
        /\.\d{2}\d*[1-9]/.test(String(value)) ? 4 : 2,
    );
}

/**
 * A quantity or amount of a recipe scaled to an order, e.g. 5 wood per
 * table for 3 tables: 15. Rounded to `decimals`, as the server stores it.
 */
export function scale(
    value: string | number,
    factor: number,
    decimals = 4,
): string {
    const scaled = Number(value) * factor;

    return String(Number(scaled.toFixed(decimals)));
}

/**
 * The sum of decimal strings, e.g. the total of the cost lines.
 */
export function sum(values: Array<string | number | null | undefined>): number {
    return values.reduce<number>(
        (total, value) => total + (Number(value) || 0),
        0,
    );
}
