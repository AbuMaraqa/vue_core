<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import { ArrowDown, ArrowUp } from '@lucide/vue';

defineProps<{
    label: string;
    value: string;
    icon: LucideIcon;
    change?: number | null;
}>();
</script>

<template>
    <div
        class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div
            class="flex size-12 items-center justify-center rounded-xl bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90"
        >
            <component :is="icon" class="size-6" />
        </div>

        <div class="mt-5 flex items-end justify-between">
            <div>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ label }}
                </span>
                <h4
                    class="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90"
                >
                    {{ value }}
                </h4>
            </div>

            <span
                v-if="change !== undefined && change !== null"
                :class="[
                    'flex items-center gap-1 rounded-full py-0.5 ps-2 pe-2.5 text-sm font-medium',
                    change >= 0
                        ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500'
                        : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
                ]"
            >
                <ArrowUp v-if="change >= 0" class="size-3" />
                <ArrowDown v-else class="size-3" />
                {{ Math.abs(change) }}%
            </span>
        </div>
    </div>
</template>
