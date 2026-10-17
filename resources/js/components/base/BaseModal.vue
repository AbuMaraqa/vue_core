<script setup lang="ts">
import X from '~icons/solar/close-linear';
import { onKeyStroke } from '@vueuse/core';

withDefaults(
    defineProps<{
        title?: string;
        maxWidth?: 'sm' | 'md' | 'lg';
    }>(),
    { title: undefined, maxWidth: 'md' },
);

const open = defineModel<boolean>('open', { default: false });

const widths = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
};

onKeyStroke('Escape', () => {
    open.value = false;
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-4"
                role="dialog"
                aria-modal="true"
            >
                <div
                    class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px]"
                    aria-hidden="true"
                    @click="open = false"
                />
                <div
                    :class="[
                        'relative w-full rounded-3xl bg-white p-6 shadow-theme-xl lg:p-8 dark:bg-gray-900',
                        widths[maxWidth],
                    ]"
                >
                    <button
                        type="button"
                        class="absolute end-4 top-4 flex size-10 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                        @click="open = false"
                    >
                        <X class="size-5" />
                    </button>
                    <h4
                        v-if="title"
                        class="mb-2 pe-10 text-xl font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ title }}
                    </h4>
                    <slot />
                    <div
                        v-if="$slots.footer"
                        class="mt-6 flex items-center justify-end gap-3"
                    >
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
