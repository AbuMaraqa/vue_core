import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import type { ComputedRef, Ref } from 'vue';
import { computed, reactive, ref, watch } from 'vue';

export type IndexFiltersOptions = {
    /**
     * The current sort, e.g. `-created_at`, for lists that can be sorted.
     */
    sort?: string;

    /**
     * Filters typed into a text field. They are applied after a short pause
     * instead of on every key stroke.
     */
    debounced?: string[];
};

export type UseIndexFiltersReturn<TKey extends string> = {
    filters: Record<TKey, string>;
    sort: Ref<string | undefined>;
    /**
     * How many filters other than the text fields are set, for the badge of
     * the collapsed filters button on phones.
     */
    activeCount: ComputedRef<number>;
    apply: () => void;
    sortBy: (column: string) => void;
};

/**
 * Keep the filters of an index page in the query string, in the shape
 * spatie/laravel-query-builder reads: `?filter[role]=Admin&sort=-created_at`.
 *
 * Every filter is applied as soon as it changes, so selects need no
 * `@change` handler; the text filters wait until the user stops typing.
 */
export function useIndexFilters<TKey extends string>(
    route: { url: () => string },
    initial: Record<TKey, string | null>,
    options: IndexFiltersOptions = {},
): UseIndexFiltersReturn<TKey> {
    const keys = Object.keys(initial) as TKey[];
    const debounced = options.debounced ?? ['search'];

    const filters = reactive(
        Object.fromEntries(keys.map((key) => [key, initial[key] ?? ''])),
    ) as Record<TKey, string>;

    const sort = ref(options.sort);

    function apply(): void {
        router.get(
            route.url(),
            {
                filter: Object.fromEntries(
                    keys.map((key) => [key, filters[key] || undefined]),
                ),
                sort: sort.value,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function sortBy(column: string): void {
        sort.value = sort.value === column ? `-${column}` : column;
        apply();
    }

    watchDebounced(
        () =>
            keys
                .filter((key) => debounced.includes(key))
                .map((key) => filters[key]),
        apply,
        { debounce: 350 },
    );

    watch(
        () =>
            keys
                .filter((key) => !debounced.includes(key))
                .map((key) => filters[key]),
        apply,
    );

    const activeCount = computed(
        () =>
            keys.filter((key) => !debounced.includes(key) && filters[key])
                .length,
    );

    return { filters, sort, activeCount, apply, sortBy };
}
