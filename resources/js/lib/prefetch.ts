import { router } from '@inertiajs/vue3';

/**
 * Sidebar links prefetch their page on hover. A page prefetched before a save
 * would show the data as it was, so every finished change (a post, put, patch
 * or delete) drops the prefetched pages and the next visit loads them fresh.
 */
export function initializePrefetchInvalidation(): void {
    router.on('finish', (event) => {
        if (event.detail.visit.method !== 'get') {
            router.flushAll();
        }
    });
}
