<script setup lang="ts">
/**
 * A form field: the label, the control passed in the slot, an optional hint
 * and the validation error.
 */
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';

defineProps<{
    label?: string;
    /**
     * The id of the control the label points to.
     */
    for?: string;
    required?: boolean;
    hint?: string;
    error?: string;
}>();
</script>

<template>
    <div>
        <BaseLabel v-if="label" :for="$props.for" :required="required">
            {{ label }}
        </BaseLabel>
        <slot />
        <p
            v-if="hint"
            class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400"
        >
            {{ hint }}
        </p>
        <BaseInputError :message="error" />
    </div>
</template>
