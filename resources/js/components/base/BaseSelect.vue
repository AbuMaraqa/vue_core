<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{
    invalid?: boolean;
}>();

const model = defineModel<string | number | null>();

// Layout classes belong to the wrapper, everything else to the select itself.
const attrs = useAttrs();
const selectAttrs = computed(() =>
    Object.fromEntries(
        Object.entries(attrs).filter(([key]) => key !== 'class'),
    ),
);
</script>

<template>
    <div :class="['relative', attrs.class]">
        <select
            v-model="model"
            v-bind="selectAttrs"
            :class="[
                'h-11 w-full appearance-none rounded-lg border bg-transparent py-2.5 ps-4 pe-11 text-sm text-gray-800 shadow-theme-xs focus:ring-3 focus:outline-hidden dark:bg-gray-900 dark:text-white/90',
                invalid
                    ? 'border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700'
                    : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800',
            ]"
        >
            <slot />
        </select>
        <ChevronDown
            class="pointer-events-none absolute end-4 top-1/2 size-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"
        />
    </div>
</template>
