<script setup lang="ts">
import CircleAlert from '~icons/solar/danger-circle-linear';
import CircleCheck from '~icons/solar/check-circle-linear';
import Info from '~icons/solar/info-circle-linear';
import TriangleAlert from '~icons/solar/danger-triangle-linear';
import { computed } from 'vue';

type Props = {
    variant?: 'success' | 'error' | 'warning' | 'info';
    title?: string;
    messages?: string[];
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'info',
    title: undefined,
    messages: () => [],
});

const styles = {
    success: {
        box: 'border-success-500 bg-success-50 dark:border-success-500/30 dark:bg-success-500/15',
        icon: CircleCheck,
        iconClass: 'text-success-500',
    },
    error: {
        box: 'border-error-500 bg-error-50 dark:border-error-500/30 dark:bg-error-500/15',
        icon: CircleAlert,
        iconClass: 'text-error-500',
    },
    warning: {
        box: 'border-warning-500 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-500/15',
        icon: TriangleAlert,
        iconClass: 'text-warning-500',
    },
    info: {
        box: 'border-blue-light-500 bg-blue-light-50 dark:border-blue-light-500/30 dark:bg-blue-light-500/15',
        icon: Info,
        iconClass: 'text-blue-light-500',
    },
};

const style = computed(() => styles[props.variant]);
</script>

<template>
    <div :class="['rounded-xl border p-4', style.box]">
        <div class="flex items-start gap-3">
            <component
                :is="style.icon"
                :class="['mt-0.5 size-5 shrink-0', style.iconClass]"
            />
            <div class="text-sm text-gray-600 dark:text-gray-400">
                <h4
                    v-if="title"
                    class="mb-1 font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ title }}
                </h4>
                <slot>
                    <ul v-if="messages.length" class="space-y-1">
                        <li v-for="message in messages" :key="message">
                            {{ message }}
                        </li>
                    </ul>
                </slot>
            </div>
        </div>
    </div>
</template>
