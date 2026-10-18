<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import ChevronDown from '~icons/solar/alt-arrow-down-linear';
import { computed, ref, toRef, watch } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useContactSearch } from '@/composables/useContactSearch';
import { useExchangeRate } from '@/composables/useExchangeRate';
import { useTranslations } from '@/composables/useTranslations';
import { index, store } from '@/routes/admin/banking/cheques';
import type {
    BankAccountOption,
    ChequeBookOption,
    ChequeDirection,
    ComboboxOption,
    CurrencyOption,
} from '@/types';

const props = defineProps<{
    direction: ChequeDirection;
    directionLabel: string;
    bankAccounts: BankAccountOption[];
    chequeBooks: ChequeBookOption[];
    currencies: CurrencyOption[];
    accounts: ComboboxOption[];
    defaults: { issue_date: string; due_date: string; currency_id: number };
}>();

const { t } = useTranslations();

const isOutgoing = computed(() => props.direction === 'outgoing');

const searchContacts = useContactSearch(
    props.direction === 'incoming' ? 'customers' : 'vendors',
);

const form = useForm({
    direction: props.direction,
    number: '',
    contact_id: null as number | string | null,
    amount: '',
    currency_id: props.defaults.currency_id as number | string | null,
    exchange_rate: '1',
    issue_date: props.defaults.issue_date,
    due_date: props.defaults.due_date,
    drawee_bank: '',
    // With a single account there is nothing to choose.
    bank_account_id: (props.bankAccounts.length === 1
        ? props.bankAccounts[0].value
        : null) as number | string | null,
    cheque_book_id: null as number | string | null,
    counter_account_id: null as number | string | null,
    description: '',
});

const showAdvanced = ref(false);

const bankAccount = computed(() =>
    props.bankAccounts.find((option) => option.value === form.bank_account_id),
);

const currency = computed(() =>
    props.currencies.find((option) => option.value === form.currency_id),
);

const books = computed(() =>
    props.chequeBooks.filter(
        (book) => book.bank_account_id === form.bank_account_id,
    ),
);

const book = computed(() =>
    books.value.find((option) => option.value === form.cheque_book_id),
);

/**
 * An issued cheque is in the currency of the account it is drawn on, and
 * comes from one of that account's cheque books.
 */
watch(
    () => form.bank_account_id,
    () => {
        if (isOutgoing.value && bankAccount.value) {
            form.currency_id = bankAccount.value.currency_id;
        }

        if (!book.value) {
            form.cheque_book_id = books.value[0]?.value ?? null;
        }
    },
    { immediate: true },
);

const { isBase, error: rateError } = useExchangeRate(
    computed(() =>
        form.currency_id === null ? null : Number(form.currency_id),
    ),
    toRef(form, 'issue_date'),
    toRef(form, 'exchange_rate'),
);

const isForeign = computed(() =>
    currency.value ? !currency.value.is_base : !isBase.value,
);

function submit(): void {
    form.transform((data) => ({
        ...data,
        exchange_rate: isForeign.value ? data.exchange_rate : null,
        bank_account_id: isOutgoing.value ? data.bank_account_id : null,
        cheque_book_id: isOutgoing.value ? data.cheque_book_id : null,
        drawee_bank: isOutgoing.value ? null : data.drawee_bank,
    })).submit(store(), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="isOutgoing ? t('Issue Cheque') : t('Receive Cheque')"
            :breadcrumbs="[
                { title: t('Cheques'), href: index() },
                { title: directionLabel },
            ]"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <BaseCard :title="t('Cheque details')">
                <div
                    class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <BaseField
                        class="sm:col-span-2 lg:col-span-1"
                        :label="isOutgoing ? t('Vendor') : t('Customer')"
                        required
                        :error="form.errors.contact_id"
                    >
                        <BaseCombobox
                            v-model="form.contact_id"
                            :search="searchContacts"
                            :placeholder="t('Choose a contact')"
                            :invalid="Boolean(form.errors.contact_id)"
                        />
                    </BaseField>

                    <template v-if="isOutgoing">
                        <BaseField
                            :label="t('Bank account')"
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
                            :label="t('Cheque book')"
                            :hint="
                                form.bank_account_id && !books.length
                                    ? t('This account has no cheque book.')
                                    : undefined
                            "
                            :error="form.errors.cheque_book_id"
                        >
                            <BaseCombobox
                                v-model="form.cheque_book_id"
                                :options="books"
                                clearable
                                :disabled="!books.length"
                                :placeholder="t('Enter the number by hand')"
                                :invalid="Boolean(form.errors.cheque_book_id)"
                            />
                        </BaseField>
                    </template>

                    <BaseField
                        :label="t('Cheque number')"
                        for="number"
                        :required="!(isOutgoing && book)"
                        :hint="
                            isOutgoing && book
                                ? t(
                                      'Leave empty to use the next number of the book.',
                                  )
                                : undefined
                        "
                        :error="form.errors.number"
                    >
                        <BaseInput
                            id="number"
                            v-model="form.number"
                            dir="ltr"
                            class="font-mono"
                            :placeholder="
                                isOutgoing && book
                                    ? String(book.next_number)
                                    : undefined
                            "
                            :invalid="Boolean(form.errors.number)"
                        />
                    </BaseField>

                    <BaseField
                        v-if="!isOutgoing"
                        :label="t('Drawee bank')"
                        for="drawee_bank"
                        :hint="t('The bank the cheque is drawn on.')"
                        :error="form.errors.drawee_bank"
                    >
                        <BaseInput
                            id="drawee_bank"
                            v-model="form.drawee_bank"
                        />
                    </BaseField>

                    <BaseField
                        :label="t('Amount')"
                        for="amount"
                        required
                        :hint="currency?.code"
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
                        :label="t('Currency')"
                        required
                        :hint="
                            isOutgoing
                                ? t('The currency of the bank account.')
                                : undefined
                        "
                        :error="form.errors.currency_id"
                    >
                        <BaseCombobox
                            v-model="form.currency_id"
                            :options="currencies"
                            :disabled="isOutgoing"
                            :invalid="Boolean(form.errors.currency_id)"
                        />
                    </BaseField>
                    <BaseField
                        v-if="isForeign"
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
                            class="font-mono"
                            :invalid="Boolean(form.errors.exchange_rate)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Cheque date')"
                        for="issue_date"
                        required
                        :error="form.errors.issue_date"
                    >
                        <BaseInput
                            id="issue_date"
                            v-model="form.issue_date"
                            type="date"
                            :invalid="Boolean(form.errors.issue_date)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Due date')"
                        for="due_date"
                        required
                        :error="form.errors.due_date"
                    >
                        <BaseInput
                            id="due_date"
                            v-model="form.due_date"
                            type="date"
                            :min="form.issue_date"
                            :invalid="Boolean(form.errors.due_date)"
                        />
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

                <div
                    class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-800"
                >
                    <button
                        type="button"
                        class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-brand-500 dark:text-gray-400"
                        :aria-expanded="showAdvanced"
                        @click="showAdvanced = !showAdvanced"
                    >
                        <ChevronDown
                            :class="[
                                'size-4 transition-transform',
                                { 'rotate-180': showAdvanced },
                            ]"
                        />
                        {{ t('Advanced') }}
                    </button>
                    <div
                        v-if="showAdvanced || form.errors.counter_account_id"
                        class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2"
                    >
                        <BaseField
                            :label="t('Counter account')"
                            :hint="
                                t(
                                    'Leave empty to use the contact\'s receivable/payable account',
                                )
                            "
                            :error="form.errors.counter_account_id"
                        >
                            <BaseCombobox
                                v-model="form.counter_account_id"
                                :options="accounts"
                                clearable
                                :placeholder="t('Default account')"
                                :invalid="
                                    Boolean(form.errors.counter_account_id)
                                "
                            />
                        </BaseField>
                    </div>
                </div>
            </BaseCard>

            <div class="flex justify-end gap-3">
                <BaseButton variant="outline" :href="index()">
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton type="submit" :loading="form.processing">
                    {{ isOutgoing ? t('Issue cheque') : t('Receive cheque') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
