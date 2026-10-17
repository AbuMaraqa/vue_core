<script setup lang="ts">
/**
 * Upload an Excel or CSV file to an Importer. Valid rows are saved; rows that
 * fail validation are listed with their row number so they can be fixed.
 */
import { useForm } from '@inertiajs/vue3';
import Import from '~icons/solar/import-linear';
import { ref } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { ImportResult } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

const props = defineProps<{
    title: string;
    /**
     * The Wayfinder routes of the import and of its template file.
     */
    importRoute: () => RouteDefinition<'post'>;
    templateRoute: { url: () => string };
    /**
     * The expected columns, shown as a reminder.
     */
    columns: string[];
}>();

const { t } = useTranslations();

const isOpen = ref(false);
const result = ref<ImportResult | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{ file: File | null }>({ file: null });

function open(): void {
    result.value = null;
    form.reset();
    form.clearErrors();
    isOpen.value = true;
}

function selectFile(event: Event): void {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit(): void {
    form.submit(props.importRoute(), {
        forceFormData: true,
        preserveScroll: true,
        onFlash: (flash) => {
            result.value = flash.importResult ?? null;
        },
        onSuccess: () => {
            form.reset();

            if (fileInput.value) {
                fileInput.value.value = '';
            }

            if (!result.value?.failures.length) {
                isOpen.value = false;
            }
        },
    });
}
</script>

<template>
    <div>
        <BaseButton variant="outline" @click="open">
            <Import class="size-5" />
            {{ t('Import') }}
        </BaseButton>

        <BaseModal v-model:open="isOpen" :title="title" max-width="lg">
            <form id="import-form" class="space-y-5" @submit.prevent="submit">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{
                        t(
                            'Upload an Excel or CSV file whose first row contains these columns:',
                        )
                    }}
                    <span
                        class="font-mono text-gray-700 dark:text-gray-300"
                        dir="ltr"
                        >{{ columns.join(', ') }}</span
                    >
                </p>

                <a
                    :href="templateRoute.url()"
                    class="inline-block text-sm font-medium text-brand-500 hover:text-brand-600"
                >
                    {{ t('Download the template file') }}
                </a>

                <BaseField
                    :label="t('File')"
                    for="import-file"
                    required
                    :error="form.errors.file"
                >
                    <input
                        id="import-file"
                        ref="fileInput"
                        type="file"
                        accept=".xlsx,.csv"
                        class="block w-full rounded-lg border border-gray-300 text-sm text-gray-700 file:me-4 file:border-0 file:bg-gray-100 file:px-4 file:py-2.5 file:text-gray-700 dark:border-gray-700 dark:text-gray-300 dark:file:bg-gray-800 dark:file:text-gray-300"
                        @change="selectFile"
                    />
                </BaseField>

                <BaseAlert
                    v-if="result && result.failures.length"
                    variant="warning"
                    :title="
                        t(
                            ':imported rows were imported. Fix these rows and import them again:',
                            { imported: result.imported },
                        )
                    "
                >
                    <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto text-sm">
                        <li
                            v-for="(failure, position) in result.failures"
                            :key="position"
                        >
                            {{ t('Row :row', { row: failure.row }) }}
                            (<span dir="ltr">{{ failure.column }}</span
                            >):
                            {{ failure.errors.join(' ') }}
                        </li>
                    </ul>
                </BaseAlert>
            </form>

            <template #footer>
                <BaseButton variant="outline" size="sm" @click="isOpen = false">
                    {{ t('Close') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="import-form"
                    size="sm"
                    :loading="form.processing"
                    :disabled="!form.file"
                >
                    {{ t('Import') }}
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>
