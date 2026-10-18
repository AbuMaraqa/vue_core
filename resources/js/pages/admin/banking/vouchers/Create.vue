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
import PageHeader from '@/components/common/PageHeader.vue';
import { useContactSearch } from '@/composables/useContactSearch';
import { useExchangeRate } from '@/composables/useExchangeRate';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { rowKey } from '@/lib/rowKey';
import { index, store } from '@/routes/admin/banking/vouchers';
import type {
    BankAccountOption,
    ComboboxOption,
    TaxOption,
    VoucherDirection,
} from '@/types';

const props = defineProps<{
    direction: VoucherDirection;
    directionLabel: string;
    bankAccounts: BankAccountOption[];
    accounts: ComboboxOption[];
    costCenters: ComboboxOption[];
    taxes: TaxOption[];
    baseCurrencyId: number;
    defaults: { date: string };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const searchContacts = useContactSearch();

type LineInput = {
    account_id: number | string | null;
    description: string;
    cost_center_id: number | string | null;
    tax_id: number | string | null;
    amount: string;
};

function emptyLine(): LineInput {
    return {
        account_id: null,
        description: '',
        cost_center_id: null,
        tax_id: null,
        amount: '',
    };
}

const form = useForm({
    direction: props.direction,
    date: props.defaults.date,
    bank_account_id: null as number | string | null,
    contact_id: null as number | string | null,
    payee: '',
    exchange_rate: '1',
    reference: '',
    description: '',
    lines: [emptyLine()] as LineInput[],
});

const bankAccount = computed(() =>
    props.bankAccounts.find((option) => option.value === form.bank_account_id),
);

const decimals = computed(() => bankAccount.value?.decimal_places ?? 2);

const isForeign = computed(
    () =>
        bankAccount.value !== undefined &&
        bankAccount.value.currency_id !== props.baseCurrencyId,
);

const { error: rateError } = useExchangeRate(
    computed(() => bankAccount.value?.currency_id ?? null),
    toRef(form, 'date'),
    toRef(form, 'exchange_rate'),
);

/**
 * The tax of a line: its rate of the amount, rounded to the currency.
 */
function lineTax(line: LineInput): number {
    const tax = props.taxes.find((option) => option.value === line.tax_id);
    const amount = Number(line.amount || 0);

    if (!tax || !(amount > 0)) {
        return 0;
    }

    const factor = 10 ** decimals.value;

    return Math.round(((amount * Number(tax.rate)) / 100) * factor) / factor;
}

const totals = computed(() => {
    const subtotal = form.lines.reduce(
        (sum, line) => sum + Number(line.amount || 0),
        0,
    );
    const tax = form.lines.reduce((sum, line) => sum + lineTax(line), 0);

    return { subtotal, tax, total: subtotal + tax };
});

const money = (value: number): string =>
    formatDecimal(value, locale.value, decimals.value);

function lineError(position: number, field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `lines.${position}.${field}`
    ];
}

const title = computed(() =>
    props.direction === 'out'
        ? t('New Payment Voucher')
        : t('New Receipt Voucher'),
);

function submit(): void {
    form.transform((data) => ({
        ...data,
        exchange_rate: isForeign.value ? data.exchange_rate : null,
    })).submit(store(), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                { title: t('Vouchers'), href: index() },
                { title: directionLabel },
            ]"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <BaseCard :title="t('Voucher details')">
                <div
                    class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
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
                        :label="
                            direction === 'out'
                                ? t('Paid from')
                                : t('Received into')
                        "
                        required
                        :error="form.errors.bank_account_id"
                    >
                        <BaseCombobox
                            v-model="form.bank_account_id"
                            :options="bankAccounts"
                            :placeholder="t('Choose an account')"
                            :invalid="Boolean(form.errors.bank_account_id)"
                        />
                    </BaseField>
                    <BaseField
                        v-if="isForeign"
                        :label="t('Exchange rate')"
                        for="exchange_rate"
                        required
                        :hint="
                            bankAccount
                                ? t('Base currency value of 1 :currency.', {
                                      currency: bankAccount.currency,
                                  })
                                : undefined
                        "
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
                            class="font-mono"
                            :invalid="Boolean(form.errors.exchange_rate)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Contact')"
                        :hint="t('Optional: the customer or vendor involved.')"
                        :error="form.errors.contact_id"
                    >
                        <BaseCombobox
                            v-model="form.contact_id"
                            :search="searchContacts"
                            clearable
                            :placeholder="t('Choose a contact')"
                            :invalid="Boolean(form.errors.contact_id)"
                        />
                    </BaseField>
                    <BaseField
                        :label="
                            direction === 'out'
                                ? t('Paid to')
                                : t('Received from')
                        "
                        for="payee"
                        :error="form.errors.payee"
                    >
                        <BaseInput id="payee" v-model="form.payee" />
                    </BaseField>
                    <BaseField
                        :label="t('Reference')"
                        for="reference"
                        :error="form.errors.reference"
                    >
                        <BaseInput id="reference" v-model="form.reference" />
                    </BaseField>
                    <BaseField
                        class="sm:col-span-2 lg:col-span-3"
                        :label="t('Description')"
                        for="description"
                        :error="form.errors.description"
                    >
                        <BaseInput
                            id="description"
                            v-model="form.description"
                        />
                    </BaseField>
                </div>
            </BaseCard>

            <BaseCard
                :title="t('Lines')"
                :description="
                    bankAccount
                        ? t('Amounts in :currency', {
                              currency: bankAccount.currency,
                          })
                        : undefined
                "
            >
                <div class="-mx-2 custom-scrollbar overflow-x-auto px-2">
                    <table class="w-full min-w-[960px]">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="w-[26%] th-cell">
                                    {{ t('Account') }}
                                </th>
                                <th class="th-cell">{{ t('Description') }}</th>
                                <th class="w-[16%] th-cell">
                                    {{ t('Cost center') }}
                                </th>
                                <th class="w-[14%] th-cell">{{ t('Tax') }}</th>
                                <th class="w-36 th-cell text-end">
                                    {{ t('Amount') }}
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
                                                lineError(
                                                    position,
                                                    'account_id',
                                                ),
                                            )
                                        "
                                    />
                                    <BaseInputError
                                        :message="
                                            lineError(position, 'account_id')
                                        "
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
                                    <BaseCombobox
                                        v-model="line.tax_id"
                                        :options="taxes"
                                        clearable
                                        :placeholder="t('No tax')"
                                    />
                                    <p
                                        v-if="lineTax(line) > 0"
                                        class="mt-1 font-mono text-theme-xs text-gray-500 dark:text-gray-400"
                                        dir="ltr"
                                    >
                                        + {{ money(lineTax(line)) }}
                                    </p>
                                </td>
                                <td class="px-1 py-2">
                                    <BaseInput
                                        v-model="line.amount"
                                        type="number"
                                        step="any"
                                        min="0"
                                        dir="ltr"
                                        class="text-end font-mono"
                                        :invalid="
                                            Boolean(
                                                lineError(position, 'amount'),
                                            )
                                        "
                                    />
                                    <BaseInputError
                                        :message="lineError(position, 'amount')"
                                    />
                                </td>
                                <td class="px-1 py-2">
                                    <BaseIconButton
                                        v-if="form.lines.length > 1"
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
                                <td
                                    class="px-3 py-3 text-theme-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ t('Subtotal') }}
                                </td>
                                <td
                                    class="px-3 py-3 text-end font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ money(totals.subtotal) }}
                                </td>
                                <td />
                            </tr>
                            <tr>
                                <td colspan="3" />
                                <td
                                    class="px-3 py-1 text-theme-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ t('Tax') }}
                                </td>
                                <td
                                    class="px-3 py-1 text-end font-mono text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{ money(totals.tax) }}
                                </td>
                                <td />
                            </tr>
                            <tr
                                class="font-semibold text-gray-800 dark:text-white/90"
                            >
                                <td colspan="3" />
                                <td class="px-3 py-3">{{ t('Total') }}</td>
                                <td
                                    class="px-3 py-3 text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ money(totals.total) }}
                                    <span
                                        class="text-theme-xs font-normal text-gray-400"
                                    >
                                        {{ bankAccount?.currency }}
                                    </span>
                                </td>
                                <td />
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
                    {{ t('Post voucher') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
