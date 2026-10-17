<script setup lang="ts">
import { Check, Minus } from '@lucide/vue';

defineOptions({ inheritAttrs: false });

defineProps<{
    indeterminate?: boolean;
}>();

const model = defineModel<boolean | string[]>();
</script>

<template>
    <label
        class="flex cursor-pointer items-center gap-3 text-sm font-normal text-gray-700 select-none dark:text-gray-400"
    >
        <span class="relative flex">
            <input
                v-model="model"
                v-bind="$attrs"
                type="checkbox"
                class="peer sr-only"
            />
            <span
                :class="[
                    'flex size-5 items-center justify-center rounded-md border-[1.25px] text-white transition peer-focus-visible:ring-3 peer-focus-visible:ring-brand-500/20 [&>svg]:opacity-0 peer-checked:[&>svg]:opacity-100',
                    indeterminate
                        ? 'border-brand-500 bg-brand-500 [&>svg]:opacity-100'
                        : 'border-gray-300 bg-transparent peer-checked:border-brand-500 peer-checked:bg-brand-500 dark:border-gray-700',
                ]"
            >
                <Minus v-if="indeterminate" class="size-3.5" />
                <Check v-else class="size-3.5" />
            </span>
        </span>
        <slot />
    </label>
</template>
