<script setup lang="ts">
import Inbox from '~icons/solar/inbox-linear';

defineProps<{
    message: string;
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 px-6 py-12">
        <span
            class="flex size-12 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
        >
            <Inbox class="size-6" />
        </span>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ message }}</p>
        <slot />
    </div>
</template>
