import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

/**
 * The filters of a report page. They are applied on demand (reports can be
 * heavy), and kept in the address bar so the export downloads the same view.
 */
export function useReportFilters<T extends Record<string, unknown>>(
    route: { url: () => string },
    initial: T,
): { filters: T; apply: () => void } {
    const filters = reactive({ ...initial }) as T;

    function apply(): void {
        const query = Object.fromEntries(
            Object.entries(filters).filter(
                ([, value]) =>
                    value !== null && value !== '' && value !== false,
            ),
        );

        router.get(route.url(), query as Record<string, string>, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    return { filters, apply };
}
