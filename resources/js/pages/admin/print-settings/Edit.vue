<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import PrintButton from '@/components/common/PrintButton.vue';
import { useTranslations } from '@/composables/useTranslations';
import { preview, update } from '@/routes/admin/print-settings';

const props = defineProps<{
    settings: {
        paper_size: string;
        show_logo: boolean;
        show_printed_by: boolean;
        footer_note: string | null;
    };
    paperSizes: { value: string; label: string }[];
    canUpdate: boolean;
}>();

const { t } = useTranslations();

const form = useForm({ ...props.settings });

function submit(): void {
    form.submit(update(), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Print Settings')"
            :description="
                t(
                    'How invoices, vouchers, reports and every other printed document look.',
                )
            "
            :breadcrumbs="[{ title: t('Print Settings') }]"
        >
            <template #actions>
                <PrintButton :url="preview.url()" />
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <fieldset :disabled="!canUpdate" class="space-y-6">
                <BaseCard
                    :title="t('Paper')"
                    :description="
                        t(
                            'The name, logo, contacts, colour and font come from the general settings.',
                        )
                    "
                >
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <BaseField
                            :label="t('Paper size')"
                            for="paper_size"
                            required
                            :error="form.errors.paper_size"
                        >
                            <BaseSelect
                                id="paper_size"
                                v-model="form.paper_size"
                                :invalid="Boolean(form.errors.paper_size)"
                            >
                                <option
                                    v-for="size in paperSizes"
                                    :key="size.value"
                                    :value="size.value"
                                >
                                    {{ size.label }}
                                </option>
                            </BaseSelect>
                        </BaseField>
                    </div>
                </BaseCard>

                <BaseCard
                    :title="t('Header and footer')"
                    :description="
                        t('Repeated at the top and bottom of every page.')
                    "
                >
                    <div class="space-y-5">
                        <BaseCheckbox v-model="form.show_logo">
                            {{ t('Show the logo in the header') }}
                        </BaseCheckbox>
                        <BaseCheckbox v-model="form.show_printed_by">
                            {{
                                t(
                                    'Show who printed the document and when in the footer',
                                )
                            }}
                        </BaseCheckbox>
                        <BaseField
                            :label="t('Footer note')"
                            for="footer_note"
                            :hint="
                                t(
                                    'For example bank details, terms of sale or a thank-you line.',
                                )
                            "
                            :error="form.errors.footer_note"
                        >
                            <BaseTextarea
                                id="footer_note"
                                v-model="form.footer_note"
                                rows="3"
                                maxlength="300"
                                :invalid="Boolean(form.errors.footer_note)"
                            />
                        </BaseField>
                    </div>
                </BaseCard>
            </fieldset>

            <div v-if="canUpdate" class="flex justify-end">
                <BaseButton type="submit" :loading="form.processing">
                    {{ t('Save changes') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
