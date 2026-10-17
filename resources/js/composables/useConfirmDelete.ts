import { router } from '@inertiajs/vue3';
import { shallowReactive } from 'vue';
import type { RouteDefinition } from '@/wayfinder';

export type UseConfirmDeleteOptions = {
    /**
     * Called after the server deleted the item, e.g. to close a details panel.
     */
    onSuccess?: () => void;
};

export type ConfirmDelete<T> = {
    /**
     * The item waiting for confirmation, or null while no dialog is open.
     */
    target: T | null;
    processing: boolean;
    ask: (item: T) => void;
    cancel: () => void;
    confirm: () => void;
};

/**
 * The state behind a "delete this item?" confirmation dialog.
 *
 * ```ts
 * const deletion = useConfirmDelete((user: ManagedUser) => destroy({ user: user.id }));
 * ```
 *
 * Bind it to a ConfirmDialog with `:open="deletion.target !== null"`,
 * `:processing="deletion.processing"`, `@confirm="deletion.confirm"` and
 * `@update:open="(open) => !open && deletion.cancel()"`.
 */
export function useConfirmDelete<T>(
    route: (item: T) => RouteDefinition<'delete'>,
    options: UseConfirmDeleteOptions = {},
): ConfirmDelete<T> {
    const state: ConfirmDelete<T> = shallowReactive({
        target: null,
        processing: false,
        ask: (item: T) => {
            state.target = item;
        },
        cancel: () => {
            state.target = null;
        },
        confirm: () => {
            if (state.target === null) {
                return;
            }

            router.visit(route(state.target), {
                preserveScroll: true,
                onStart: () => {
                    state.processing = true;
                },
                onSuccess: () => options.onSuccess?.(),
                onFinish: () => {
                    state.processing = false;
                    state.target = null;
                },
            });
        },
    });

    return state;
}
