<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import Eraser from '~icons/solar/eraser-linear';
import MagicStick from '~icons/solar/magic-stick-3-linear';
import { computed, onMounted, reactive, ref, toRef, watch } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useContactSearch } from '@/composables/useContactSearch';
import { useExchangeRate } from '@/composables/useExchangeRate';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { paymentRoutes } from '@/lib/invoicing-routes';
import type {
    BankAccountOption,
    ChequeBookOption,
    ComboboxOption,
    ContactOption,
    CurrencyOption,
    OpenInvoice,
    PaymentTypeProps,
    SelectOption,
} from '@/types';

const props = defineProps<{
    type: PaymentTypeProps;
    methods: SelectOption[];
    bankAccounts: BankAccountOption[];
    chequeBooks: ChequeBookOption[];
    currencies: CurrencyOption[];
    contact: ContactOption | null;
    openInvoices: OpenInvoice[];
    defaults: {
        date: string;
        currency_id: number;
        invoice_id: number | null;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const routes = computed(() => paymentRoutes(props.type.route));
const isReceipt = computed(() => props.type.value === 'receipt');

const searchContacts = useContactSearch(props.type.contact_type);

const contactOption = ref<ContactOption | null>(props.contact);

const form = useForm({
    contact_id: (props.contact?.value ?? null) as number | string | null,
    date: props.defaults.date,
    method: String(props.methods[0]?.value ?? 'cash'),
    bank_account_id: null as number | string | null,
    amount: '',
    currency_id: props.defaults.currency_id as number | string | null,
    exchange_rate: '',
    reference: '',
    description: '',
    cheque_number: '',
    cheque_due_date: '',
    drawee_bank: '',
    cheque_book_id: null as number | null,
});

const currency = computed(() =>
    props.currencies.find((option) => option.value === form.currency_id),
);

const decimals = computed(() => currency.value?.decimal_places ?? 2);

const isForeign = computed(
    () => currency.value !== undefined && !currency.value.is_base,
);

const isCheque = computed(() => form.method === 'cheque');

/**
 * A received cheque stays in hand until it is deposited, so it needs no
 * bank account; everything else goes through one.
 */
const needsBankAccount = computed(() => !(isCheque.value && isReceipt.value));

const bankAccountOptions = computed(() =>
    props.bankAccounts.filter(
        (account) => account.currency_id === form.currency_id,
    ),
);

const chequeBookOptions = computed(() =>
    props.chequeBooks.filter(
        (book) => book.bank_account_id === form.bank_account_id,
    ),
);

const chequeBook = computed(() =>
    props.chequeBooks.find((book) => book.value === form.cheque_book_id),
);

watch(
    () => form.currency_id,
    () => {
        if (
            !bankAccountOptions.value.some(
                (account) => account.value === form.bank_account_id,
            )
        ) {
            form.bank_account_id =
                bankAccountOptions.value.length === 1
                    ? bankAccountOptions.value[0].value
                    : null;
        }
    },
    { immediate: true },
);

watch(
    () => form.bank_account_id,
    () => {
        if (
            !chequeBookOptions.value.some(
                (book) => book.value === form.cheque_book_id,
            )
        ) {
            form.cheque_book_id = null;
        }
    },
);

/**
 * The currency whose rate is looked up: set once the page is shown, so the
 * rate of a foreign default currency is fetched too.
 */
const rateCurrencyId = ref<number | null>(null);

const { error: rateError } = useExchangeRate(
    rateCurrencyId,
    toRef(form, 'date'),
    toRef(form, 'exchange_rate'),
);

watch(
    () => form.currency_id,
    (id) => {
        rateCurrencyId.value = id === null || id === '' ? null : Number(id);
    },
);

function round(value: number): number {
    const factor = 10 ** decimals.value;

    return Math.round((value + Number.EPSILON) * factor) / factor;
}

const money = (value: string | number): string =>
    formatDecimal(value, locale.value, decimals.value);

/**
 * The open invoices that can be settled: those in the payment's currency.
 */
const invoices = computed(() =>
    props.openInvoices.filter(
        (invoice) => invoice.currency_id === form.currency_id,
    ),
);

/**
 * The amount to allocate to each open invoice, by invoice id.
 */
const allocations = reactive<Record<number, string>>({});

function clearAllocations(): void {
    for (const id of Object.keys(allocations)) {
        delete allocations[Number(id)];
    }
}

const allocated = computed(() =>
    round(
        invoices.value.reduce(
            (sum, invoice) => sum + Number(allocations[invoice.id] || 0),
            0,
        ),
    ),
);

const unallocated = computed(() =>
    round(Number(form.amount || 0) - allocated.value),
);

/**
 * Settle the oldest invoices first, up to the amount. Without an amount,
 * every invoice is settled in full and the amount is their total.
 */
function allocateAutomatically(): void {
    clearAllocations();

    if (!(Number(form.amount) > 0)) {
        for (const invoice of invoices.value) {
            allocations[invoice.id] = invoice.amount_due;
        }

        form.amount = allocated.value
            ? allocated.value.toFixed(decimals.value)
            : '';

        return;
    }

    let remaining = Number(form.amount);

    for (const invoice of invoices.value) {
        if (remaining <= 0) {
            break;
        }

        const amount = round(Math.min(Number(invoice.amount_due), remaining));
        allocations[invoice.id] = amount.toFixed(decimals.value);
        remaining = round(remaining - amount);
    }
}

/**
 * Whether the allocations were chosen by hand (or for the invoice the page
 * was opened for). Until then they follow the amount by themselves, oldest
 * invoices first.
 */
const allocatedByHand = ref(false);

function allocateUnlessChosenByHand(): void {
    if (
        !allocatedByHand.value &&
        invoices.value.length > 0 &&
        Number(form.amount) > 0
    ) {
        allocateAutomatically();
    }
}

function allocateOnRequest(): void {
    allocatedByHand.value = false;
    allocateAutomatically();
}

function clearOnRequest(): void {
    allocatedByHand.value = true;
    clearAllocations();
}

/**
 * Settle the invoice the page was opened for, e.g. from its "Record
 * payment" button.
 */
function preselectInvoice(): void {
    const invoice = props.openInvoices.find(
        (open) => open.id === props.defaults.invoice_id,
    );

    if (!invoice) {
        return;
    }

    form.currency_id = invoice.currency_id;
    allocations[invoice.id] = invoice.amount_due;
    allocatedByHand.value = true;

    if (!form.amount) {
        form.amount = invoice.amount_due;
    }
}

onMounted(() => {
    rateCurrencyId.value = form.currency_id ? Number(form.currency_id) : null;
    preselectInvoice();
    allocateUnlessChosenByHand();
});

watch(
    () => props.openInvoices,
    () => {
        clearAllocations();
        allocatedByHand.value = false;
        allocateUnlessChosenByHand();
    },
);

watch([invoices, () => form.amount], allocateUnlessChosenByHand);

function chooseContact(option: ComboboxOption | null): void {
    contactOption.value = option as ContactOption | null;

    if (contactOption.value?.currency_id) {
        form.currency_id = contactOption.value.currency_id;
    }

    router.reload({
        data: { contact_id: option?.value ?? undefined },
        only: ['openInvoices', 'contact'],
    });
}

const allocationErrors = computed(() =>
    Object.entries(form.errors as Record<string, string | undefined>)
        .filter(([key]) => key.startsWith('allocations'))
        .map(([, message]) => message)
        .filter((message): message is string => Boolean(message)),
);

function submit(): void {
    form.transform((data) => ({
        contact_id: data.contact_id,
        date: data.date,
        method: data.method,
        amount: data.amount,
        currency_id: data.currency_id,
        exchange_rate: isForeign.value ? data.exchange_rate || null : null,
        bank_account_id: needsBankAccount.value ? data.bank_account_id : null,
        reference: data.reference || null,
        description: data.description || null,
        ...(isCheque.value
            ? {
                  cheque_number: data.cheque_number || null,
                  cheque_due_date: data.cheque_due_date || null,
                  ...(isReceipt.value
                      ? { drawee_bank: data.drawee_bank || null }
                      : { cheque_book_id: data.cheque_book_id }),
              }
            : {}),
        allocations: invoices.value
            .filter((invoice) => Number(allocations[invoice.id] || 0) > 0)
            .map((invoice) => ({
                invoice_id: invoice.id,
                amount: allocations[invoice.id],
            })),
    })).submit(routes.value.store(), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('New :document', { document: type.label })"
            :breadcrumbs="[
                {
                    title: isReceipt
                        ? t('Customer Receipts')
                        : t('Vendor Payments'),
                    href: routes.index(),
                },
                { title: t('New') },
            ]"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <BaseCard
                :title="isReceipt ? t('Receipt details') : t('Payment details')"
            >
                <div
                    class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <BaseField
                        :label="isReceipt ? t('Customer') : t('Vendor')"
                        required
                        :error="form.errors.contact_id"
                    >
                        <BaseCombobox
                            v-model="form.contact_id"
                            :options="contactOption ? [contactOption] : []"
                            :search="searchContacts"
                            :placeholder="
                                isReceipt
                                    ? t('Choose a customer')
                                    : t('Choose a vendor')
                            "
                            :invalid="Boolean(form.errors.contact_id)"
                            @select="chooseContact"
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
                        :label="t('Method')"
                        for="method"
                        required
                        :error="form.errors.method"
                    >
                        <BaseSelect
                            id="method"
                            v-model="form.method"
                            :invalid="Boolean(form.errors.method)"
                        >
                            <option
                                v-for="method in methods"
                                :key="method.value"
                                :value="method.value"
                            >
                                {{ method.label }}
                            </option>
                        </BaseSelect>
                    </BaseField>
                    <BaseField
                        :label="t('Amount')"
                        for="amount"
                        required
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
                        :error="form.errors.currency_id"
                    >
                        <BaseCombobox
                            v-model="form.currency_id"
                            :options="currencies"
                            :invalid="Boolean(form.errors.currency_id)"
                        />
                    </BaseField>
                    <BaseField
                        v-if="isForeign"
                        :label="t('Exchange rate')"
                        for="exchange_rate"
                        :hint="
                            currency
                                ? t('Base currency value of 1 :currency.', {
                                      currency: currency.code,
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
                        v-if="needsBankAccount"
                        :label="
                            isCheque
                                ? t('Drawn on')
                                : isReceipt
                                  ? t('Received into')
                                  : t('Paid from')
                        "
                        required
                        :hint="
                            bankAccountOptions.length
                                ? undefined
                                : t(
                                      'There is no active bank account in this currency.',
                                  )
                        "
                        :error="form.errors.bank_account_id"
                    >
                        <BaseCombobox
                            v-model="form.bank_account_id"
                            :options="bankAccountOptions"
                            :placeholder="t('Choose an account')"
                            :invalid="Boolean(form.errors.bank_account_id)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Reference')"
                        for="reference"
                        :error="form.errors.reference"
                    >
                        <BaseInput
                            id="reference"
                            v-model="form.reference"
                            :invalid="Boolean(form.errors.reference)"
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
                            :invalid="Boolean(form.errors.description)"
                        />
                    </BaseField>
                </div>
            </BaseCard>

            <BaseCard
                v-if="isCheque"
                :title="t('Cheque')"
                :description="
                    isReceipt
                        ? t(
                              'The cheque is kept in hand until it is deposited or endorsed.',
                          )
                        : t(
                              'The cheque is recorded as issued until the bank pays it.',
                          )
                "
            >
                <div
                    class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <BaseField
                        v-if="!isReceipt"
                        :label="t('Cheque book')"
                        :hint="
                            form.bank_account_id && !chequeBookOptions.length
                                ? t('This account has no cheque book.')
                                : undefined
                        "
                        :error="form.errors.cheque_book_id"
                    >
                        <BaseSelect
                            v-model="form.cheque_book_id"
                            :invalid="Boolean(form.errors.cheque_book_id)"
                        >
                            <option :value="null">
                                {{ t('No cheque book') }}
                            </option>
                            <option
                                v-for="book in chequeBookOptions"
                                :key="book.value"
                                :value="book.value"
                            >
                                {{ book.label }}
                            </option>
                        </BaseSelect>
                    </BaseField>
                    <BaseField
                        :label="t('Cheque number')"
                        for="cheque_number"
                        :required="!chequeBook"
                        :hint="
                            chequeBook
                                ? t(
                                      'Leave empty to use the next number of the book (:number).',
                                      { number: chequeBook.next_number },
                                  )
                                : undefined
                        "
                        :error="form.errors.cheque_number"
                    >
                        <BaseInput
                            id="cheque_number"
                            v-model="form.cheque_number"
                            dir="ltr"
                            class="font-mono"
                            :invalid="Boolean(form.errors.cheque_number)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Due date')"
                        for="cheque_due_date"
                        required
                        :error="form.errors.cheque_due_date"
                    >
                        <BaseInput
                            id="cheque_due_date"
                            v-model="form.cheque_due_date"
                            type="date"
                            :invalid="Boolean(form.errors.cheque_due_date)"
                        />
                    </BaseField>
                    <BaseField
                        v-if="isReceipt"
                        :label="t('Drawee bank')"
                        for="drawee_bank"
                        :error="form.errors.drawee_bank"
                    >
                        <BaseInput
                            id="drawee_bank"
                            v-model="form.drawee_bank"
                            :invalid="Boolean(form.errors.drawee_bank)"
                        />
                    </BaseField>
                </div>
            </BaseCard>

            <BaseCard
                :title="isReceipt ? t('Invoices settled') : t('Bills settled')"
                :description="
                    currency
                        ? t('Open documents in :currency, oldest first.', {
                              currency: currency.code,
                          })
                        : undefined
                "
            >
                <template v-if="invoices.length" #actions>
                    <div class="flex flex-wrap gap-2">
                        <BaseButton
                            variant="outline"
                            size="sm"
                            @click="allocateOnRequest"
                        >
                            <MagicStick class="size-4" />
                            {{ t('Allocate automatically') }}
                        </BaseButton>
                        <BaseButton
                            variant="ghost"
                            size="sm"
                            @click="clearOnRequest"
                        >
                            <Eraser class="size-4" />
                            {{ t('Clear') }}
                        </BaseButton>
                    </div>
                </template>

                <EmptyState
                    v-if="!form.contact_id"
                    :message="
                        isReceipt
                            ? t('Choose a customer to see their open invoices.')
                            : t('Choose a vendor to see their open bills.')
                    "
                />
                <EmptyState
                    v-else-if="!invoices.length"
                    :message="
                        t(
                            'Nothing open in this currency. The whole amount stays unallocated.',
                        )
                    "
                />
                <div
                    v-else
                    class="-mx-4 custom-scrollbar overflow-x-auto sm:-mx-6"
                >
                    <table class="min-w-full">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="th-cell">{{ t('Number') }}</th>
                                <th class="th-cell">{{ t('Date') }}</th>
                                <th class="th-cell">{{ t('Due date') }}</th>
                                <th class="th-cell text-end">
                                    {{ t('Total') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Amount due') }}
                                </th>
                                <th class="w-44 th-cell text-end">
                                    {{ t('Amount to allocate') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr v-for="invoice in invoices" :key="invoice.id">
                                <td class="td-cell font-mono" dir="ltr">
                                    {{ invoice.number }}
                                </td>
                                <td class="td-cell whitespace-nowrap">
                                    {{ formatDate(invoice.date, locale) }}
                                </td>
                                <td
                                    :class="[
                                        'td-cell whitespace-nowrap',
                                        invoice.is_overdue
                                            ? 'font-medium text-error-600 dark:text-error-500'
                                            : '',
                                    ]"
                                >
                                    {{ formatDate(invoice.due_date, locale) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ money(invoice.total) }}
                                </td>
                                <td
                                    class="td-cell text-end font-mono"
                                    dir="ltr"
                                >
                                    {{ money(invoice.amount_due) }}
                                </td>
                                <td class="px-4 py-2">
                                    <BaseInput
                                        v-model="allocations[invoice.id]"
                                        type="number"
                                        @update:model-value="
                                            allocatedByHand = true
                                        "
                                        step="any"
                                        min="0"
                                        :max="invoice.amount_due"
                                        dir="ltr"
                                        class="text-end font-mono"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <dl
                    class="ms-auto mt-4 w-full space-y-2 border-t border-gray-100 pt-4 text-sm sm:w-80 dark:border-gray-800"
                >
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Amount') }}
                        </dt>
                        <dd
                            class="font-mono text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ money(Number(form.amount || 0)) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Allocated') }}
                        </dt>
                        <dd
                            class="font-mono text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ money(allocated) }}
                        </dd>
                    </div>
                    <div
                        :class="[
                            'flex justify-between gap-4 font-semibold',
                            unallocated < 0
                                ? 'text-error-600 dark:text-error-500'
                                : 'text-gray-800 dark:text-white/90',
                        ]"
                    >
                        <dt>{{ t('Unallocated') }}</dt>
                        <dd class="font-mono" dir="ltr">
                            {{ money(unallocated) }}
                            <span
                                class="text-theme-xs font-normal text-gray-400"
                            >
                                {{ currency?.code }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <BaseAlert
                    v-if="unallocated < 0 || allocationErrors.length"
                    variant="error"
                    class="mt-4"
                    :messages="
                        allocationErrors.length
                            ? allocationErrors
                            : [t('The allocations are more than the amount.')]
                    "
                />
            </BaseCard>

            <div class="flex justify-end gap-3">
                <BaseButton variant="outline" :href="routes.index()">
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    :loading="form.processing"
                    :disabled="unallocated < 0"
                >
                    {{ isReceipt ? t('Post receipt') : t('Post payment') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
