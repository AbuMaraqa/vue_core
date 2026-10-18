import { useHttp } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import type { Ref } from 'vue';
import { ref } from 'vue';
import { exchangeRate } from '@/routes/admin/accounting';

/**
 * Keep a document's exchange rate in step with its currency and date: when
 * either changes, the rate of that day is fetched and written to `rate`. The
 * base currency always has a rate of 1. The user can still type another rate.
 */
export function useExchangeRate(
    currencyId: Ref<number | null>,
    date: Ref<string>,
    rate: Ref<string>,
): { isBase: Ref<boolean>; error: Ref<string | null> } {
    const isBase = ref(true);
    const error = ref<string | null>(null);

    const request = useHttp<
        Record<string, never>,
        { rate: string; is_base: boolean }
    >({});

    async function refresh(): Promise<void> {
        if (!currencyId.value || !date.value) {
            return;
        }

        try {
            const response = await request.get(
                exchangeRate.url(undefined, {
                    query: { currency_id: currencyId.value, date: date.value },
                }),
            );

            isBase.value = response.is_base;
            rate.value = response.is_base ? '1' : response.rate;
            error.value = null;
        } catch {
            isBase.value = false;
            error.value =
                'There is no exchange rate for this currency on this date.';
        }
    }

    watchDebounced([currencyId, date], refresh, { debounce: 300 });

    return { isBase, error };
}
