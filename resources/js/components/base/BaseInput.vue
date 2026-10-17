<script setup lang="ts">
defineProps<{
    invalid?: boolean;
}>();

const model = defineModel<string | number | null>();
</script>

<template>
    <input
        v-model="model"
        :class="[
            'h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:ring-3 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30',
            invalid
                ? 'border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700'
                : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800',
        ]"
    />
</template>
