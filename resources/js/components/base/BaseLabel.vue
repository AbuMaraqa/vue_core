<script setup lang="ts">
defineProps<{
    required?: boolean;
}>();
</script>

<template>
    <label
        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
    >
        <slot />
        <span v-if="required" class="text-error-500">*</span>
    </label>
</template>
