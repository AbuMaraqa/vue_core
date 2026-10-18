<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, toRef } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useExchangeRate } from '@/composables/useExchangeRate';
import { useTranslations } from '@/composables/useTranslations';
import dashboard from '@/actions/App/Modules/Core/Http/Controllers/DashboardController';
import { create, store } from '@/routes/admin/banking/vouchers';
import type { BankAccountOption, ComboboxOption } from '@/types';

/**
 * One everyday expense (rent, electricity, transport...) paid from a cash
 * box or a bank account. It is saved as a payment voucher with one line.
 */
const props = defineProps<{
    bankAccounts: BankAccountOption[];
    expenseAccounts: ComboboxOption[];
    baseCurrencyId: number;
    defaults: { date: string };
}>();

const { t } = useTranslations();
const page = usePage();

const form = useForm({
    date: props.defaults.date,
    account_id: null as number | string | null,
    amount: '',
    bank_account_id: (props.bankAccounts.length === 1
        ? props.bankAccounts[0].value
        : null) as number | string | null,
    payee: '',
    exchange_rate: '1',
    description: '',
});

const bankAccount = computed(() =>
    props.bankAccounts.find((option) => option.value === form.bank_account_id),
);

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

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function submit(): void {
    form.transform((data) => ({
        direction: 'out',
        date: data.date,
        bank_account_id: data.bank_account_id,
        payee: data.payee,
        exchange_rate: isForeign.value ? data.exchange_rate : null,
        description: data.description,
        lines: [
            {
                account_id: data.account_id,
                amount: data.amount,
                description: data.description,
                tax_id: null,
                cost_center_id: null,
            },
        ],
    })).submit(store(), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Record an expense')"
            :description="
                t('Rent, electricity, transport and other running costs.')
            "
            :breadcrumbs="[{ title: t('Record an expense') }]"
        />

        <form class="mx-auto max-w-2xl space-y-6" @submit.prevent="submit">
            <BaseCard>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <BaseField
                        class="sm:col-span-2"
                        :label="t('What did you pay for?')"
                        required
                        :hint="t('Choose the kind of expense.')"
                        :error="errors['lines.0.account_id']"
                    >
                        <BaseCombobox
                            v-model="form.account_id"
                            :options="expenseAccounts"
                            :placeholder="t('Choose the kind of expense')"
                            :invalid="Boolean(errors['lines.0.account_id'])"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Amount')"
                        for="amount"
                        required
                        :error="errors['lines.0.amount']"
                    >
                        <BaseInput
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="any"
                            min="0"
                            dir="ltr"
                            class="text-end font-mono text-lg"
                            :invalid="Boolean(errors['lines.0.amount'])"
                        />
                    </BaseField>
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
                        :label="t('Paid from')"
                        required
                        :hint="
                            t('The cash box or bank account the money left.')
                        "
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
                        :label="t('Paid to')"
                        for="payee"
                        :hint="t('Optional.')"
                        :error="form.errors.payee"
                    >
                        <BaseInput id="payee" v-model="form.payee" />
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
                        class="sm:col-span-2"
                        :label="t('Note')"
                        for="description"
                        :hint="
                            t('Optional, e.g. the electricity bill of March.')
                        "
                        :error="form.errors.description"
                    >
                        <BaseInput
                            id="description"
                            v-model="form.description"
                        />
                    </BaseField>
                </div>
            </BaseCard>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <BaseButton
                    v-if="!page.props.theme.simpleMode"
                    variant="ghost"
                    size="sm"
                    :href="create(undefined, { query: { direction: 'out' } })"
                >
                    {{ t('Several lines or tax? Use a payment voucher') }}
                </BaseButton>
                <div class="ms-auto flex gap-3">
                    <BaseButton variant="outline" :href="dashboard()">
                        {{ t('Cancel') }}
                    </BaseButton>
                    <BaseButton type="submit" :loading="form.processing">
                        {{ t('Save the expense') }}
                    </BaseButton>
                </div>
            </div>
        </form>
    </div>
</template>
