<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import ChevronRight from '~icons/solar/alt-arrow-right-linear';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes/admin';

export type Breadcrumb = {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
};

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        breadcrumbs?: Breadcrumb[];
    }>(),
    { description: undefined, breadcrumbs: () => [] },
);

const { t } = useTranslations();
</script>

<template>
    <Head :title="title" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                {{ title }}
            </h2>
            <p
                v-if="description"
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                {{ description }}
            </p>
            <nav class="mt-1.5">
                <ol class="flex flex-wrap items-center gap-1.5">
                    <li>
                        <Link
                            :href="dashboard()"
                            class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand-500 dark:text-gray-400"
                        >
                            {{ t('Home') }}
                            <ChevronRight class="size-4 rtl:rotate-180" />
                        </Link>
                    </li>
                    <li
                        v-for="breadcrumb in breadcrumbs"
                        :key="breadcrumb.title"
                        class="inline-flex items-center gap-1.5 text-sm"
                    >
                        <Link
                            v-if="breadcrumb.href"
                            :href="breadcrumb.href"
                            class="inline-flex items-center gap-1.5 text-gray-500 hover:text-brand-500 dark:text-gray-400"
                        >
                            {{ breadcrumb.title }}
                            <ChevronRight class="size-4 rtl:rotate-180" />
                        </Link>
                        <span v-else class="text-gray-800 dark:text-white/90">
                            {{ breadcrumb.title }}
                        </span>
                    </li>
                </ol>
            </nav>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-3">
            <slot name="actions" />
        </div>
    </div>
</template>
