<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ChevronLeft from '~icons/solar/alt-arrow-left-linear';
import ChevronRight from '~icons/solar/alt-arrow-right-linear';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/types';

defineProps<{
    meta: Paginated<unknown>['meta'];
    links: Paginated<unknown>['links'];
}>();

const { t } = useTranslations();

const isNumber = (label: string): boolean => /^\d+$/.test(label);
</script>

<template>
    <div
        class="flex flex-col items-center justify-between gap-4 border-t border-gray-100 px-5 py-4 sm:flex-row sm:px-6 dark:border-gray-800"
    >
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{
                t('Showing :from to :to of :total results', {
                    from: meta.from ?? 0,
                    to: meta.to ?? 0,
                    total: meta.total,
                })
            }}
        </p>

        <div v-if="meta.last_page > 1" class="flex items-center gap-2">
            <component
                :is="links.prev ? Link : 'span'"
                :href="links.prev ?? undefined"
                preserve-scroll
                :class="[
                    'flex size-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 shadow-theme-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400',
                    links.prev
                        ? 'hover:bg-gray-50 dark:hover:bg-white/[0.03]'
                        : 'cursor-not-allowed opacity-50',
                ]"
            >
                <ChevronLeft class="size-4 rtl:rotate-180" />
            </component>

            <ul class="hidden items-center gap-1 sm:flex">
                <template v-for="link in meta.links" :key="link.label">
                    <li v-if="isNumber(link.label) || link.label === '...'">
                        <Link
                            v-if="link.url && isNumber(link.label)"
                            :href="link.url"
                            preserve-scroll
                            :class="[
                                'flex size-10 items-center justify-center rounded-lg text-sm font-medium',
                                link.active
                                    ? 'bg-brand-500 text-white'
                                    : 'text-gray-700 hover:bg-brand-500/[0.08] hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-500',
                            ]"
                        >
                            {{ link.label }}
                        </Link>
                        <span
                            v-else
                            class="flex size-10 items-center justify-center text-sm text-gray-500"
                        >
                            {{ link.label }}
                        </span>
                    </li>
                </template>
            </ul>

            <component
                :is="links.next ? Link : 'span'"
                :href="links.next ?? undefined"
                preserve-scroll
                :class="[
                    'flex size-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-700 shadow-theme-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400',
                    links.next
                        ? 'hover:bg-gray-50 dark:hover:bg-white/[0.03]'
                        : 'cursor-not-allowed opacity-50',
                ]"
            >
                <ChevronRight class="size-4 rtl:rotate-180" />
            </component>
        </div>
    </div>
</template>
