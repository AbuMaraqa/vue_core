<script setup lang="ts">
import { computed } from 'vue';

type Props = {
    color?: 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light';
    size?: 'sm' | 'md';
};

const props = withDefaults(defineProps<Props>(), {
    color: 'primary',
    size: 'sm',
});

const colors = {
    primary:
        'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
    success:
        'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
    error: 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
    warning:
        'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
    info: 'bg-blue-light-50 text-blue-light-500 dark:bg-blue-light-500/15 dark:text-blue-light-500',
    light: 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-white/80',
};

const classes = computed(() => [
    'inline-flex items-center justify-center gap-1 rounded-full px-2.5 py-0.5 font-medium',
    props.size === 'sm' ? 'text-theme-xs' : 'text-sm',
    colors[props.color],
]);
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
