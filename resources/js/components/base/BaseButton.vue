<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import LoaderCircle from '~icons/solar/loader-linear';
import { computed } from 'vue';

type Props = {
    variant?: 'primary' | 'outline' | 'danger' | 'ghost';
    size?: 'sm' | 'md';
    type?: 'button' | 'submit' | 'reset';
    href?: InertiaLinkProps['href'];
    loading?: boolean;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    size: 'md',
    type: 'button',
    href: undefined,
    loading: false,
    disabled: false,
});

const variants = {
    primary:
        'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300',
    outline:
        'bg-white text-gray-700 ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300',
    danger: 'bg-error-500 text-white shadow-theme-xs hover:bg-error-600 disabled:bg-error-300',
    ghost: 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5',
};

const sizes = {
    sm: 'px-4 py-2.5 text-sm',
    md: 'px-5 py-3 text-sm',
};

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition disabled:cursor-not-allowed disabled:opacity-60',
    variants[props.variant],
    sizes[props.size],
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes">
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :class="classes"
        :disabled="disabled || loading"
    >
        <LoaderCircle v-if="loading" class="size-4 animate-spin" />
        <slot />
    </button>
</template>
