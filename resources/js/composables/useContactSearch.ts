import { useHttp } from '@inertiajs/vue3';
import { search as searchRoute } from '@/routes/admin/contacts';
import type { ContactOption } from '@/types';

/**
 * The `search` of a contact picker (BaseCombobox): active contacts whose
 * name, code or phone match the query, fetched from the server. Pass `type`
 * to list only customers or only vendors.
 */
export function useContactSearch(
    type?: 'customers' | 'vendors',
): (query: string) => Promise<ContactOption[]> {
    const request = useHttp<Record<string, never>, ContactOption[]>({});

    return async (query: string): Promise<ContactOption[]> => {
        try {
            return await request.get(
                searchRoute.url(undefined, {
                    query: { search: query || undefined, type },
                }),
            );
        } catch {
            return [];
        }
    };
}
