<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

withDefaults(
    defineProps<{
        collapsed?: boolean;
    }>(),
    { collapsed: false },
);

const page = usePage();
</script>

<template>
    <span class="flex items-center gap-3">
        <span
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-white"
        >
            <AppLogoIcon class="size-5 fill-current" />
        </span>
        <span
            v-if="!collapsed"
            class="truncate text-lg font-semibold text-gray-800 dark:text-white/90"
        >
            {{ page.props.name }}
        </span>
    </span>
</template>
