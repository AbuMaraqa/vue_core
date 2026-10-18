import { useHttp } from '@inertiajs/vue3';
import { search as searchRoute } from '@/routes/admin/inventory/items';
import type { ItemOption } from '@/types';

/**
 * The `search` of an item picker (BaseCombobox): active items whose code,
 * barcode or name match the query, fetched from the server. Pass `type` to
 * list only stock items or only services.
 */
export function useItemSearch(
    type?: 'stock' | 'service',
): (query: string) => Promise<ItemOption[]> {
    const request = useHttp<Record<string, never>, ItemOption[]>({});

    return async (query: string): Promise<ItemOption[]> => {
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
