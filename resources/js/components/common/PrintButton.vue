<script setup lang="ts">
/**
 * Open the printable PDF of a document in a new tab, or save it. Every
 * document shares the same print layout (letterhead, footer, page numbers),
 * built on the server.
 */
import Download from '~icons/solar/download-minimalistic-linear';
import Printer from '~icons/solar/printer-linear';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    /**
     * The URL of the document's `pdf` route, e.g. `pdf.url({ invoice: invoice.id })`.
     */
    url: string;
}>();

const { t } = useTranslations();

const downloadUrl = computed(
    () => `${props.url}${props.url.includes('?') ? '&' : '?'}download=1`,
);
</script>

<template>
    <div
        class="inline-flex overflow-hidden rounded-lg bg-white ring-1 ring-gray-300 ring-inset dark:bg-gray-800 dark:ring-gray-700"
    >
        <a
            :href="url"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center justify-center gap-2 px-5 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300"
        >
            <Printer class="size-5" />
            {{ t('Print') }}
        </a>
        <a
            :href="downloadUrl"
            :title="t('Download PDF')"
            :aria-label="t('Download PDF')"
            class="inline-flex items-center justify-center border-s border-gray-300 px-3 text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300"
        >
            <Download class="size-5" />
        </a>
    </div>
</template>
