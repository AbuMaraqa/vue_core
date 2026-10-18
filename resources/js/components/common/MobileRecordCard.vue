<script setup lang="ts">
/**
 * One row of a list on a phone: a tappable card that opens the record. The
 * title, party and date sit on the start side; the amount and the status
 * badges (default slot) on the end side.
 */
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import ArrowEnd from '~icons/solar/alt-arrow-right-linear';

type Props = {
    href: InertiaLinkProps['href'];
    title: string;
    /**
     * Show the title as a document number: monospaced and left-to-right.
     */
    mono?: boolean;
    subtitle?: string | null;
    meta?: string | null;
    /**
     * Highlight the meta line in red, e.g. an overdue due date.
     */
    alert?: boolean;
    amount?: string | null;
    currency?: string | null;
    amountClass?: string;
};

withDefaults(defineProps<Props>(), {
    mono: false,
    subtitle: null,
    meta: null,
    alert: false,
    amount: null,
    currency: null,
    amountClass: 'text-gray-800 dark:text-white/90',
});
</script>

<template>
    <li>
        <Link
            :href="href"
            class="flex items-center gap-3 px-5 py-4 transition hover:bg-gray-50 active:bg-gray-100 dark:hover:bg-white/[0.02] dark:active:bg-white/[0.04]"
        >
            <div class="min-w-0 flex-1">
                <p
                    :class="[
                        'truncate font-medium text-gray-800 dark:text-white/90',
                        mono ? 'font-mono' : '',
                    ]"
                >
                    <span :dir="mono ? 'ltr' : undefined">{{ title }}</span>
                </p>
                <p
                    v-if="subtitle"
                    class="mt-0.5 truncate text-theme-sm text-gray-500 dark:text-gray-400"
                >
                    {{ subtitle }}
                </p>
                <p
                    v-if="meta"
                    :class="[
                        'mt-0.5 text-theme-xs',
                        alert
                            ? 'font-medium text-error-600 dark:text-error-500'
                            : 'text-gray-400 dark:text-gray-500',
                    ]"
                >
                    {{ meta }}
                </p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1.5">
                <p
                    v-if="amount"
                    :class="[
                        'font-mono font-semibold whitespace-nowrap',
                        amountClass,
                    ]"
                >
                    <span dir="ltr">
                        {{ amount }}
                        <span
                            v-if="currency"
                            class="text-theme-xs font-normal text-gray-400"
                        >
                            {{ currency }}
                        </span>
                    </span>
                </p>
                <div
                    v-if="$slots.default"
                    class="flex flex-wrap justify-end gap-1"
                >
                    <slot />
                </div>
            </div>
            <ArrowEnd class="size-4 shrink-0 text-gray-400 rtl:rotate-180" />
        </Link>
    </li>
</template>
