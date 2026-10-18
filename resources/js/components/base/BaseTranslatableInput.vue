<script setup lang="ts">
/**
 * One input per interface language for a translatable attribute, bound to
 * `{ ar: '...', en: '...' }`. At least one language is required by the server.
 */
import { usePage } from '@inertiajs/vue3';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import type { Translations } from '@/types';

const props = defineProps<{
    label: string;
    /**
     * The attribute name, used for the ids and to find its errors.
     */
    field: string;
    errors: Partial<Record<string, string>>;
    required?: boolean;
}>();

const model = defineModel<Translations>({ default: () => ({}) });

const page = usePage();

function update(code: string, value: string | number | null | undefined): void {
    model.value = { ...model.value, [code]: String(value ?? '') };
}

function error(code: string): string | undefined {
    return props.errors[`${props.field}.${code}`] ?? props.errors[props.field];
}
</script>

<template>
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <BaseField
            v-for="locale in page.props.locales"
            :key="locale.code"
            :label="`${label} (${locale.native})`"
            :for="`${field}-${locale.code}`"
            :required="required"
            :error="error(locale.code)"
        >
            <BaseInput
                :id="`${field}-${locale.code}`"
                :model-value="model[locale.code] ?? ''"
                :dir="locale.direction"
                :invalid="Boolean(error(locale.code))"
                @update:model-value="(value) => update(locale.code, value)"
            />
        </BaseField>
    </div>
</template>
