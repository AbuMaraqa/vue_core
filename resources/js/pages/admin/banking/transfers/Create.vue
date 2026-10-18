<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, toRef } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useExchangeRate } from '@/composables/useExchangeRate';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { index, store } from '@/routes/admin/banking/transfers';
import type { BankAccountOption } from '@/types';

const props = defineProps<{
    bankAccounts: BankAccountOption[];
    baseCurrencyId: number;
    defaults: { date: string };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const form = useForm({
    date: props.defaults.date,
    from_bank_account_id: null as number | string | null,
    to_bank_account_id: null as number | string | null,
    amount: '',
    to_amount: '',
    exchange_rate: '',
    fees: '',
    reference: '',
    description: '',
});

const fromAccount = computed(() =>
    props.bankAccounts.find(
        (bankAccount) => bankAccount.value === form.from_bank_account_id,
    ),
);
const toAccount = computed(() =>
    props.bankAccounts.find(
        (bankAccount) => bankAccount.value === form.to_bank_account_id,
    ),
);

const toAccountOptions = computed(() =>
    props.bankAccounts.map((bankAccount) => ({
        ...bankAccount,
        disabled: bankAccount.value === form.from_bank_account_id,
    })),
);

/**
 * Between two currencies the receiving account gets its own amount.
 */
const differentCurrencies = computed(
    () =>
        fromAccount.value !== undefined &&
        toAccount.value !== undefined &&
        fromAccount.value.currency_id !== toAccount.value.currency_id,
);

/**
 * Both accounts share one foreign currency: the base value of the entry
 * uses an exchange rate.
 */
const sameForeignCurrency = computed(
    () =>
        fromAccount.value !== undefined &&
        toAccount.value !== undefined &&
        !differentCurrencies.value &&
        fromAccount.value.currency_id !== props.baseCurrencyId,
);

const rateCurrencyId = computed<number | null>(() =>
    sameForeignCurrency.value ? (fromAccount.value?.currency_id ?? null) : null,
);
const { error: rateError } = useExchangeRate(
    rateCurrencyId,
    toRef(form, 'date'),
    toRef(form, 'exchange_rate'),
);

/**
 * The rate the two amounts imply, shown as a check.
 */
const impliedRate = computed((): string | null => {
    const sent = Number(form.amount);
    const received = Number(form.to_amount);

    if (!differentCurrencies.value || !(sent > 0) || !(received > 0)) {
        return null;
    }

    return formatDecimal(received / sent, locale.value, 6);
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        to_amount: differentCurrencies.value ? data.to_amount : null,
        exchange_rate: sameForeignCurrency.value
            ? data.exchange_rate || null
            : null,
        fees: data.fees || null,
    })).submit(store(), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('New Transfer')"
            :breadcrumbs="[
                { title: t('Bank Transfers'), href: index() },
                { title: t('New transfer') },
            ]"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <BaseCard :title="t('Transfer details')">
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
                        :label="t('From account')"
                        required
                        :error="form.errors.from_bank_account_id"
                    >
                        <BaseCombobox
                            v-model="form.from_bank_account_id"
                            :options="bankAccounts"
                            :placeholder="t('Choose an account')"
                            :invalid="Boolean(form.errors.from_bank_account_id)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('To account')"
                        required
                        :error="form.errors.to_bank_account_id"
                    >
                        <BaseCombobox
                            v-model="form.to_bank_account_id"
                            :options="toAccountOptions"
                            :placeholder="t('Choose an account')"
                            :invalid="Boolean(form.errors.to_bank_account_id)"
                        />
                    </BaseField>
                    <BaseField
                        :label="
                            differentCurrencies ? t('Amount sent') : t('Amount')
                        "
                        for="amount"
                        required
                        :hint="fromAccount?.currency"
                        :error="form.errors.amount"
                    >
                        <BaseInput
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="any"
                            min="0"
                            dir="ltr"
                            class="font-mono"
                            :invalid="Boolean(form.errors.amount)"
                        />
                    </BaseField>
                    <BaseField
                        v-if="differentCurrencies"
                        :label="t('Amount received')"
                        for="to_amount"
                        required
                        :hint="
                            impliedRate
                                ? `${toAccount?.currency} · ${t('Rate')}: ${impliedRate}`
                                : toAccount?.currency
                        "
                        :error="form.errors.to_amount"
                    >
                        <BaseInput
                            id="to_amount"
                            v-model="form.to_amount"
                            type="number"
                            step="any"
                            min="0"
                            dir="ltr"
                            class="font-mono"
                            :invalid="Boolean(form.errors.to_amount)"
                        />
                    </BaseField>
                    <BaseField
                        v-if="sameForeignCurrency"
                        :label="t('Exchange rate')"
                        for="exchange_rate"
                        :hint="t('Values the transfer in the base currency.')"
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
                        :label="t('Bank fees')"
                        for="fees"
                        :hint="
                            fromAccount
                                ? t(
                                      'Charged by the sending bank, in :currency.',
                                      {
                                          currency: fromAccount.currency,
                                      },
                                  )
                                : t('Charged by the sending bank.')
                        "
                        :error="form.errors.fees"
                    >
                        <BaseInput
                            id="fees"
                            v-model="form.fees"
                            type="number"
                            step="any"
                            min="0"
                            dir="ltr"
                            class="font-mono"
                            :invalid="Boolean(form.errors.fees)"
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
                        class="sm:col-span-2"
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

            <div class="flex justify-end gap-3">
                <BaseButton variant="outline" :href="index()">
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton type="submit" :loading="form.processing">
                    {{ t('Post transfer') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
