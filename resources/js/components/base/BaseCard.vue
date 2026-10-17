<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div
            v-if="title || $slots.actions"
            class="flex flex-wrap items-center justify-between gap-3 px-6 py-5"
        >
            <div>
                <h3
                    v-if="title"
                    class="text-base font-medium text-gray-800 dark:text-white/90"
                >
                    {{ title }}
                </h3>
                <p
                    v-if="description"
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ description }}
                </p>
            </div>
            <slot name="actions" />
        </div>
        <div
            :class="[
                'p-4 sm:p-6',
                {
                    'border-t border-gray-100 dark:border-gray-800':
                        title || $slots.actions,
                },
            ]"
        >
            <slot />
        </div>
    </div>
</template>
