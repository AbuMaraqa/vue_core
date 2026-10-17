<script setup lang="ts">
import { computed } from 'vue';
import { getInitials } from '@/composables/useInitials';

const props = withDefaults(
    defineProps<{
        name: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { size: 'md' },
);

const sizes = {
    sm: 'size-8 text-theme-xs',
    md: 'size-10 text-sm',
    lg: 'size-11 text-sm',
};

const initials = computed(() => getInitials(props.name));
</script>

<template>
    <span
        :class="[
            'inline-flex shrink-0 items-center justify-center rounded-full bg-brand-100 font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
            sizes[size],
        ]"
    >
        {{ initials }}
    </span>
</template>
