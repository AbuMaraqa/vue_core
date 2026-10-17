<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onUnmounted } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import AppBackdrop from '@/components/layout/AppBackdrop.vue';
import AppHeader from '@/components/layout/AppHeader.vue';
import AppSidebar from '@/components/layout/AppSidebar.vue';
import { provideSidebar } from '@/composables/useSidebar';

const { isExpanded, closeMobileSidebar } = provideSidebar();

const stopListening = router.on('navigate', () => closeMobileSidebar());

onUnmounted(stopListening);
</script>

<template>
    <div class="min-h-screen xl:flex">
        <AppSidebar />
        <AppBackdrop />
        <div
            :class="[
                'min-w-0 flex-1 transition-all duration-300 ease-in-out',
                isExpanded ? 'xl:ms-[290px]' : 'xl:ms-[90px]',
            ]"
        >
            <AppHeader />
            <main class="mx-auto max-w-(--breakpoint-2xl) p-4 pb-20 md:p-6">
                <slot />
            </main>
        </div>
        <Toaster rich-colors />
    </div>
</template>
