<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed, toRef } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import { useExchangeRate } from '@/composables/useExchangeRate';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { rowKey } from '@/lib/rowKey';
import {
    index,
    store,
    update,
} from '@/routes/admin/accounting/journal-entries';
import type { ComboboxOption, CurrencyOption, JournalEntry } from '@/types';

const props = defineProps<{
    entry?: JournalEntry;
    accounts: ComboboxOption[];
    costCenters: ComboboxOption[];
    currencies: CurrencyOption[];
    defaults?: { date: string; currency_id: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

type LineInput = {
    account_id: number | null;
    cost_center_id: number | null;
    description: string;
    debit: string;
    credit: string;
};

function emptyLine(): LineInput {
    return {
        account_id: null,
        cost_center_id: null,
        description: '',
        debit: '',
        credit: '',
    };
}

const form = useForm({
    date: props.entry?.date ?? props.defaults?.date ?? '',
    reference: props.entry?.reference ?? '',
    description: props.entry?.description ?? '',
    currency_id:
        props.entry?.currency_id ?? props.defaults?.currency_id ?? null,
    exchange_rate: props.entry?.exchange_rate ?? '1',
    lines: props.entry?.lines?.map((line): LineInput => ({
        account_id: line.account_id,
        cost_center_id: line.cost_center_id,
        description: line.description ?? '',
        debit: Number(line.debit) > 0 ? line.debit : '',
        credit: Number(line.credit) > 0 ? line.credit : '',
    })) ?? [emptyLine(), emptyLine()],
});

const { isBase, error: rateError } = useExchangeRate(
    toRef(form, 'currency_id'),
    toRef(form, 'date'),
    toRef(form, 'exchange_rate'),
);

const currency = computed(() =>
    props.currencies.find((option) => option.value === form.currency_id),
);
const decimals = computed(() => currency.value?.decimal_places ?? 2);

const totals = computed(() => {
    const debit = form.lines.reduce(
        (sum, line) => sum + Number(line.debit || 0),
        0,
    );
    const credit = form.lines.reduce(
        (sum, line) => sum + Number(line.credit || 0),
        0,
    );

    return {
        debit,
        credit,
        difference: Math.round((debit - credit) * 10000) / 10000,
    };
});

/**
 * Entering an amount on one side clears the other side of the line.
 */
function setAmount(
    line: LineInput,
    side: 'debit' | 'credit',
    value: string | number | null | undefined,
): void {
    line[side] = String(value ?? '');

    if (line[side] !== '') {
        line[side === 'debit' ? 'credit' : 'debit'] = '';
    }
}

function lineError(position: number, field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `lines.${position}.${field}`
    ];
}

function submit(): void {
    form.submit(
        props.entry ? update({ journalEntry: props.entry.id }) : store(),
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <BaseCard :title="t('Entry details')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <BaseField
                    :label="t('Date')"
                    for="date"
                    required
                    :error="form.errors.date"
                >
                    <BaseInput
                        id="date"
                        v-model="form.date"
                        type="date"
                        :invalid="Boolean(form.errors.date)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Reference')"
                    for="reference"
                    :error="form.errors.reference"
                >
                    <BaseInput id="reference" v-model="form.reference" />
                </BaseField>
                <BaseField
                    :label="t('Currency')"
                    required
                    :error="form.errors.currency_id"
                >
                    <BaseCombobox
                        v-model="form.currency_id"
                        :options="currencies"
                        :invalid="Boolean(form.errors.currency_id)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Exchange rate')"
                    for="exchange_rate"
                    required
                    :error="
                        form.errors.exchange_rate ??
                        (rateError ? t(rateError) : undefined)
                    "
                >
                    <BaseInput
                        id="exchange_rate"
                        v-model="form.exchange_rate"
                        type="number"
                        step="any"
                        min="0"
                        dir="ltr"
                        :disabled="isBase"
                        :invalid="Boolean(form.errors.exchange_rate)"
                    />
                </BaseField>
                <BaseField
                    class="sm:col-span-2 lg:col-span-4"
                    :label="t('Description')"
                    for="description"
                    :error="form.errors.description"
                >
                    <BaseInput id="description" v-model="form.description" />
                </BaseField>
            </div>
        </BaseCard>

        <BaseCard :title="t('Lines')">
            <div class="-mx-2 custom-scrollbar overflow-x-auto px-2">
                <table class="w-full min-w-[880px]">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="w-[30%] th-cell">{{ t('Account') }}</th>
                            <th class="th-cell">{{ t('Description') }}</th>
                            <th class="w-[18%] th-cell">
                                {{ t('Cost center') }}
                            </th>
                            <th class="w-32 th-cell text-end">
                                {{ t('Debit') }}
                            </th>
                            <th class="w-32 th-cell text-end">
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
                                    :invalid="
                                        Boolean(
                                            lineError(position, 'account_id'),
                                        )
                                    "
                                />
                                <BaseInputError
                                    :message="lineError(position, 'account_id')"
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseInput v-model="line.description" />
                            </td>
                            <td class="px-1 py-2">
                                <BaseCombobox
                                    v-model="line.cost_center_id"
                                    :options="costCenters"
                                    clearable
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseInput
                                    :model-value="line.debit"
                                    type="number"
                                    step="any"
                                    min="0"
                                    dir="ltr"
                                    class="text-end"
                                    :invalid="
                                        Boolean(lineError(position, 'debit'))
                                    "
                                    @update:model-value="
                                        (value) =>
                                            setAmount(line, 'debit', value)
                                    "
                                />
                                <BaseInputError
                                    :message="lineError(position, 'debit')"
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseInput
                                    :model-value="line.credit"
                                    type="number"
                                    step="any"
                                    min="0"
                                    dir="ltr"
                                    class="text-end"
                                    :invalid="
                                        Boolean(lineError(position, 'credit'))
                                    "
                                    @update:model-value="
                                        (value) =>
                                            setAmount(line, 'credit', value)
                                    "
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseIconButton
                                    v-if="form.lines.length > 2"
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
                            class="border-t border-gray-100 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90"
                        >
                            <td class="px-1 py-3" colspan="3">
                                <BaseButton
                                    variant="ghost"
                                    size="sm"
                                    @click="form.lines.push(emptyLine())"
                                >
                                    <Plus class="size-4" />
                                    {{ t('Add line') }}
                                </BaseButton>
                            </td>
                            <td class="px-3 py-3 text-end font-mono" dir="ltr">
                                {{
                                    formatDecimal(
                                        totals.debit,
                                        locale,
                                        decimals,
                                    )
                                }}
                            </td>
                            <td class="px-3 py-3 text-end font-mono" dir="ltr">
                                {{
                                    formatDecimal(
                                        totals.credit,
                                        locale,
                                        decimals,
                                    )
                                }}
                            </td>
                            <td />
                        </tr>
                        <tr v-if="totals.difference !== 0">
                            <td
                                colspan="6"
                                class="px-3 pb-2 text-end text-theme-sm text-error-500"
                            >
                                {{ t('Difference') }}:
                                <span class="font-mono" dir="ltr">{{
                                    formatDecimal(
                                        totals.difference,
                                        locale,
                                        decimals,
                                    )
                                }}</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <BaseInputError :message="form.errors.lines" />
        </BaseCard>

        <div class="flex justify-end gap-3">
            <BaseButton variant="outline" :href="index()">
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton type="submit" :loading="form.processing">
                {{ t('Save as draft') }}
            </BaseButton>
        </div>
    </form>
</template>
