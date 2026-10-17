<script setup lang="ts">
import { useSidebar } from '@/composables/useSidebar';

const { isMobileOpen, closeMobileSidebar } = useSidebar();
</script>

<template>
    <div
        v-if="isMobileOpen"
        class="fixed inset-0 z-9999 bg-gray-900/50 xl:hidden"
        @click="closeMobileSidebar"
    />
</template>
