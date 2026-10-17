<script setup lang="ts">
/**
 * A square icon-only action, e.g. the edit and delete buttons of a table row.
 * It renders an Inertia link when `href` is given, otherwise a button.
 */
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type Props = {
    /**
     * Describes the action for screen readers and the hover tooltip.
     */
    label: string;
    href?: InertiaLinkProps['href'];
    color?: 'neutral' | 'danger';
};

const props = withDefaults(defineProps<Props>(), {
    href: undefined,
    color: 'neutral',
});

const classes = computed(() => [
    'flex size-9 items-center justify-center rounded-lg text-gray-500 transition dark:text-gray-400 [&>svg]:size-4',
    props.color === 'danger'
        ? 'hover:bg-error-50 hover:text-error-500 dark:hover:bg-error-500/15'
        : 'hover:bg-gray-100 hover:text-brand-500 dark:hover:bg-white/5',
]);
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :title="label"
        :aria-label="label"
        :class="classes"
    >
        <slot />
    </Link>
    <button
        v-else
        type="button"
        :title="label"
        :aria-label="label"
        :class="classes"
    >
        <slot />
    </button>
</template>
