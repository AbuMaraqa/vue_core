<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AltArrowDown from '~icons/solar/alt-arrow-down-linear';
import { computed, ref } from 'vue';
import AppIcon from '@/components/common/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';
import { toneClasses } from '@/lib/dashboard';
import type { DashboardShortcut } from '@/types';

const props = defineProps<{
    shortcuts: DashboardShortcut[];
}>();

const { t } = useTranslations();

/**
 * The everyday tasks come first (they are sorted by the server); only they
 * get a large card, so the money figures below stay in view on a phone.
 */
const MAIN_COUNT = 6;

const mainShortcuts = computed(() => props.shortcuts.slice(0, MAIN_COUNT));
const moreShortcuts = computed(() => props.shortcuts.slice(MAIN_COUNT));
const showsMore = ref(false);
</script>

<template>
    <div>
        <div
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:gap-4 lg:grid-cols-3"
        >
            <Link
                v-for="shortcut in mainShortcuts"
                :key="shortcut.key"
                :href="shortcut.url"
                class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-brand-300 hover:shadow-theme-md focus-visible:ring-3 focus-visible:ring-brand-500/20 focus-visible:outline-none md:p-5 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/40"
            >
                <span
                    :class="[
                        'flex size-12 shrink-0 items-center justify-center rounded-xl transition group-hover:scale-105',
                        toneClasses[shortcut.tone],
                    ]"
                >
                    <AppIcon :name="shortcut.icon" class="size-6" />
                </span>
                <span class="min-w-0">
                    <span
                        class="block font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ t(shortcut.title) }}
                    </span>
                    <span
                        v-if="shortcut.description"
                        class="mt-0.5 block text-theme-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ t(shortcut.description) }}
                    </span>
                </span>
            </Link>
        </div>

        <template v-if="moreShortcuts.length">
            <button
                type="button"
                class="mt-3 inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium text-brand-500 hover:bg-brand-50 dark:hover:bg-brand-500/10"
                :aria-expanded="showsMore"
                @click="showsMore = !showsMore"
            >
                {{ showsMore ? t('Fewer actions') : t('More actions') }}
                <AltArrowDown
                    :class="['size-4 transition', { 'rotate-180': showsMore }]"
                />
            </button>

            <div
                v-if="showsMore"
                class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
            >
                <Link
                    v-for="shortcut in moreShortcuts"
                    :key="shortcut.key"
                    :href="shortcut.url"
                    class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:border-brand-300 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:border-brand-500/40"
                >
                    <span
                        :class="[
                            'flex size-8 shrink-0 items-center justify-center rounded-lg',
                            toneClasses[shortcut.tone],
                        ]"
                    >
                        <AppIcon :name="shortcut.icon" class="size-4" />
                    </span>
                    {{ t(shortcut.title) }}
                </Link>
            </div>
        </template>
    </div>
</template>
