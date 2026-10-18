<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/common/AppIcon.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { toneClasses } from '@/lib/dashboard';
import { formatDecimal } from '@/lib/format';
import type { DashboardStat } from '@/types';

defineProps<{
    stats: DashboardStat[];
}>();

const { t } = useTranslations();
const { locale } = useLocale();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
        <component
            :is="stat.url ? Link : 'div'"
            v-for="stat in stats"
            :key="stat.key"
            :href="stat.url ?? undefined"
            :class="[
                'block rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]',
                stat.url
                    ? 'transition hover:border-brand-300 hover:shadow-theme-md dark:hover:border-brand-500/40'
                    : '',
            ]"
        >
            <div class="flex items-center gap-3">
                <span
                    :class="[
                        'flex size-10 shrink-0 items-center justify-center rounded-xl',
                        toneClasses[stat.tone],
                    ]"
                >
                    <AppIcon :name="stat.icon" class="size-5" />
                </span>
                <span
                    class="text-theme-sm font-medium text-gray-500 dark:text-gray-400"
                >
                    {{ t(stat.label) }}
                </span>
            </div>
            <p
                class="mt-4 text-title-sm font-bold text-gray-800 dark:text-white/90"
            >
                <span dir="ltr" class="inline-flex items-baseline gap-1.5">
                    <span class="font-mono">
                        {{ formatDecimal(stat.value, locale, stat.decimals) }}
                    </span>
                    <span
                        v-if="stat.currency"
                        class="text-theme-sm font-medium text-gray-400"
                    >
                        {{ stat.currency }}
                    </span>
                </span>
            </p>
            <p
                v-if="stat.hint"
                class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400"
            >
                {{ t(stat.hint) }}
            </p>
        </component>
    </div>
</template>
