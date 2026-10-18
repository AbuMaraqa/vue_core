import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

/**
 * Whether the panel is in simple mode, set on the settings page for people
 * who do not know accounting. Simple mode hides accountant-only fields and
 * uses plain wording; the full forms stay available in advanced mode.
 */
export function useSimpleMode(): ComputedRef<boolean> {
    const page = usePage();

    return computed(() => page.props.theme.simpleMode);
}
