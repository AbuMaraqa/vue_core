<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { rowKey } from '@/lib/rowKey';
import { store } from '@/routes/admin/accounting/opening-balances';
import { index } from '@/routes/admin/accounting/journal-entries';
import type { ComboboxOption } from '@/types';

const props = defineProps<{
    accounts: ComboboxOption[];
    defaults: { date: string };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

type Line = { account_id: number | null; debit: string; credit: string };

const emptyLine = (): Line => ({ account_id: null, debit: '', credit: '' });

const form = useForm({
    date: props.defaults.date,
    lines: [emptyLine(), emptyLine(), emptyLine()],
});

const difference = computed(() =>
    form.lines.reduce(
        (sum, line) => sum + Number(line.debit || 0) - Number(line.credit || 0),
        0,
    ),
);

function submit(): void {
    form.transform((data) => ({
        ...data,
        lines: data.lines.filter((line) => line.account_id !== null),
    })).submit(store(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Opening balances')"
            :breadcrumbs="[
                { title: t('Journal Entries'), href: index() },
                { title: t('Opening balances') },
            ]"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <BaseAlert
                variant="info"
                :title="
                    t(
                        'Enter the balance of each account on the start date. Any difference goes to the opening balance equity account.',
                    )
                "
                :messages="[
                    t(
                        'Enter customer and vendor balances on each contact, so their statements start with the right amount.',
                    ),
                ]"
            />

            <BaseCard>
                <BaseField
                    :label="t('Date')"
                    for="date"
                    required
                    :error="form.errors.date"
                    class="max-w-xs"
                >
                    <BaseInput id="date" v-model="form.date" type="date" />
                </BaseField>
            </BaseCard>

            <BaseCard :title="t('Balances')">
                <table class="w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Account') }}</th>
                            <th class="w-44 th-cell text-end">
                                {{ t('Debit') }}
                            </th>
                            <th class="w-44 th-cell text-end">
                                {{ t('Credit') }}
                            </th>
                            <th class="w-12 th-cell" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(line, position) in form.lines"
                            :key="rowKey(line)"
                            class="align-top"
                        >
                            <td class="px-1 py-2">
                                <BaseCombobox
                                    v-model="line.account_id"
                                    :options="accounts"
                                    :placeholder="t('Choose an account')"
                                />
                                <BaseInputError
                                    :message="
                                        (form.errors as Record<string, string>)[
                                            `lines.${position}.account_id`
                                        ]
                                    "
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseInput
                                    v-model="line.debit"
                                    type="number"
                                    min="0"
                                    step="any"
                                    dir="ltr"
                                    class="text-end"
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseInput
                                    v-model="line.credit"
                                    type="number"
                                    min="0"
                                    step="any"
                                    dir="ltr"
                                    class="text-end"
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseIconButton
                                    color="danger"
                                    :label="t('Remove line')"
                                    @click="form.lines.splice(position, 1)"
                                >
                                    <Trash2 />
                                </BaseIconButton>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t border-gray-100 dark:border-gray-800"
                        >
                            <td class="px-1 py-3">
                                <BaseButton
                                    variant="ghost"
                                    size="sm"
                                    @click="form.lines.push(emptyLine())"
                                >
                                    <Plus class="size-4" />
                                    {{ t('Add line') }}
                                </BaseButton>
                            </td>
                            <td
                                class="px-3 py-3 text-end text-theme-sm text-gray-500"
                                colspan="2"
                            >
                                {{ t('To opening balance equity') }}:
                                <span
                                    class="font-mono font-semibold text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                    >{{
                                        formatDecimal(-difference, locale)
                                    }}</span
                                >
                            </td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
                <BaseInputError :message="form.errors.lines" />
            </BaseCard>

            <div class="flex justify-end gap-3">
                <BaseButton variant="outline" :href="index()">{{
                    t('Cancel')
                }}</BaseButton>
                <BaseButton type="submit" :loading="form.processing">{{
                    t('Post opening balances')
                }}</BaseButton>
            </div>
        </form>
    </div>
</template>
