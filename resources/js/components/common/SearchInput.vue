<script setup lang="ts">
import Search from '~icons/solar/magnifier-linear';
import BaseInput from '@/components/base/BaseInput.vue';

defineProps<{
    placeholder?: string;
}>();

const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="relative w-full lg:max-w-sm">
        <Search
            class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-gray-400"
        />
        <BaseInput
            :model-value="model"
            type="search"
            class="ps-12"
            :placeholder="placeholder"
            @update:model-value="(value) => (model = String(value ?? ''))"
        />
    </div>
</template>
