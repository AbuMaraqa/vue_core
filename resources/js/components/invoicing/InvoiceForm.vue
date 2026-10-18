<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import ChevronDown from '~icons/solar/alt-arrow-down-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import {
    computed,
    nextTick,
    onMounted,
    ref,
    toRef,
    useTemplateRef,
    watch,
} from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import QuickContactModal from '@/components/contacts/QuickContactModal.vue';
import QuickItemModal from '@/components/inventory/QuickItemModal.vue';
import { useContactSearch } from '@/composables/useContactSearch';
import { useExchangeRate } from '@/composables/useExchangeRate';
import { useItemSearch } from '@/composables/useItemSearch';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { documentRoutes } from '@/lib/invoicing-routes';
import { rowKey } from '@/lib/rowKey';
import type {
    BankAccountOption,
    ComboboxOption,
    ContactOption,
    CurrencyOption,
    Invoice,
    InvoiceDefaults,
    InvoiceTypeProps,
    ItemOption,
    TaxOption,
} from '@/types';

const props = withDefaults(
    defineProps<{
        type: InvoiceTypeProps;
        currencies: CurrencyOption[];
        taxes: TaxOption[];
        accounts: ComboboxOption[];
        costCenters: ComboboxOption[];
        warehouses: ComboboxOption[];
        hasItems: boolean;
        /**
         * The cash and bank accounts an invoice or bill can be paid with
         * as it is posted; empty for notes.
         */
        bankAccounts?: BankAccountOption[];
        /**
         * The units offered when an item is created from the form.
         */
        units?: ComboboxOption[];
        /**
         * The starting values of a new document.
         */
        defaults?: InvoiceDefaults;
        /**
         * The draft being edited.
         */
        invoice?: Invoice;
        /**
         * The contact of the draft being edited, for the picker.
         */
        contact?: ContactOption | null;
        /**
         * The item of each line of the draft being edited, by line id.
         */
        lineItems?: Record<string, ItemOption | null>;
    }>(),
    {
        bankAccounts: () => [],
        units: () => [],
        defaults: undefined,
        invoice: undefined,
        contact: null,
        lineItems: () => ({}),
    },
);

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
const simpleMode = useSimpleMode();

const routes = computed(() => documentRoutes(props.type.route));

const searchContacts = useContactSearch(props.type.contact_type);
const searchItems = useItemSearch();

const isOpening =
    props.invoice?.is_opening ?? props.defaults?.is_opening ?? false;

const originalInvoice =
    props.invoice?.original_invoice ?? props.defaults?.original_invoice ?? null;

type LineInput = {
    item_id: number | string | null;
    /**
     * The chosen item, kept for its label, type and prices; not sent.
     */
    item: ItemOption | null;
    description: string;
    account_id: number | string | null;
    cost_center_id: number | string | null;
    quantity: string;
    unit_price: string;
    discount_percent: string;
    tax_id: number | string | null;
};

function emptyLine(): LineInput {
    return {
        item_id: null,
        item: null,
        description: '',
        account_id: null,
        cost_center_id: null,
        quantity: '1',
        unit_price: '',
        discount_percent: '',
        tax_id: null,
    };
}

/**
 * Drop the trailing zeros the server sends, e.g. "2.0000" becomes "2".
 */
function trimDecimal(value: string): string {
    return value.includes('.') ? value.replace(/\.?0+$/, '') : value;
}

function initialLines(): LineInput[] {
    if (props.invoice?.lines?.length) {
        return props.invoice.lines.map((line) => ({
            item_id: line.item_id,
            item: props.lineItems[String(line.id)] ?? null,
            description: line.description ?? '',
            account_id: line.account_id,
            cost_center_id: line.cost_center_id,
            quantity: trimDecimal(line.quantity),
            unit_price: trimDecimal(line.unit_price),
            discount_percent: Number(line.discount_percent)
                ? trimDecimal(line.discount_percent)
                : '',
            tax_id: line.tax_id,
        }));
    }

    if (props.defaults?.lines.length) {
        return props.defaults.lines.map((line) => ({
            item_id: line.item_id,
            item: line.item,
            description: line.description ?? '',
            account_id: line.account_id,
            cost_center_id: line.cost_center_id,
            quantity: trimDecimal(line.quantity),
            unit_price: trimDecimal(line.unit_price),
            discount_percent: Number(line.discount_percent)
                ? trimDecimal(line.discount_percent)
                : '',
            tax_id: line.tax_id,
        }));
    }

    return [emptyLine()];
}

const contactOption = ref<ContactOption | null>(
    props.contact ?? props.defaults?.contact ?? null,
);

const form = useForm({
    contact_id: (props.invoice?.contact_id ??
        props.defaults?.contact?.value ??
        null) as number | string | null,
    date: props.invoice?.date ?? props.defaults?.date ?? '',
    due_date: props.invoice?.due_date ?? '',
    reference: props.invoice?.reference ?? '',
    currency_id: (props.invoice?.currency_id ??
        props.defaults?.currency_id ??
        null) as number | string | null,
    exchange_rate: props.invoice
        ? trimDecimal(props.invoice.exchange_rate)
        : '',
    warehouse_id: (props.invoice?.warehouse_id ??
        props.defaults?.warehouse_id ??
        props.warehouses[0]?.value ??
        null) as number | string | null,
    cost_center_id: (props.invoice?.cost_center_id ?? null) as
        | number
        | string
        | null,
    prices_include_tax:
        props.invoice?.prices_include_tax ??
        props.defaults?.prices_include_tax ??
        false,
    notes: props.invoice?.notes ?? '',
    lines: initialLines(),
    paid_now: false,
    payment_account_id: null as number | string | null,
});

const currency = computed(() =>
    props.currencies.find((option) => option.value === form.currency_id),
);

const decimals = computed(() => currency.value?.decimal_places ?? 2);

const isForeign = computed(
    () => currency.value !== undefined && !currency.value.is_base,
);

/**
 * The currency whose rate is looked up. A new document starts without one,
 * so the rate of its default currency is fetched once the page is shown; a
 * draft keeps its saved rate until the currency or date change.
 */
const rateCurrencyId = ref<number | null>(
    props.invoice ? props.invoice.currency_id : null,
);

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

onMounted(() => {
    if (!props.invoice && form.currency_id) {
        rateCurrencyId.value = Number(form.currency_id);
    }
});

function chooseContact(option: ComboboxOption | null): void {
    contactOption.value = option as ContactOption | null;

    if (
        contactOption.value?.currency_id &&
        !props.invoice &&
        !originalInvoice
    ) {
        form.currency_id = contactOption.value.currency_id;
    }
}

/**
 * The due date the server sets when the field is left empty: the date plus
 * the contact's payment terms.
 */
const termsDueDate = computed((): string | null => {
    if (!contactOption.value || !form.date) {
        return null;
    }

    const date = new Date(`${form.date}T00:00:00Z`);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    date.setUTCDate(date.getUTCDate() + contactOption.value.payment_terms_days);

    return date.toISOString().slice(0, 10);
});

const dueDateHint = computed((): string =>
    termsDueDate.value
        ? t("Leave empty to use the contact's payment terms (:date).", {
              date: formatDate(termsDueDate.value, locale.value),
          })
        : t("Leave empty to use the contact's payment terms."),
);

const hasStockItems = computed(() =>
    form.lines.some((line) => line.item?.type === 'stock'),
);

function chooseItem(line: LineInput, option: ComboboxOption | null): void {
    const item = option as ItemOption | null;
    line.item = item;

    if (!item) {
        return;
    }

    line.description = item.label;
    line.unit_price = trimDecimal(
        props.type.is_sales ? item.sale_price : item.purchase_price,
    );
    line.tax_id = props.type.is_sales
        ? item.sales_tax_id
        : item.purchase_tax_id;

    if (!line.quantity) {
        line.quantity = '1';
    }
}

function round(value: number): number {
    const factor = 10 ** decimals.value;

    return Math.round((value + Number.EPSILON) * factor) / factor;
}

type LineAmounts = {
    discount: number;
    subtotal: number;
    tax: number;
    total: number;
};

const taxesById = computed(
    () => new Map(props.taxes.map((tax) => [tax.value, tax])),
);

/**
 * The amounts of a line, worked out like InvoiceCalculator on the server.
 */
function lineAmounts(line: LineInput): LineAmounts {
    const quantity = isOpening ? 1 : Number(line.quantity || 0);
    const gross = quantity * Number(line.unit_price || 0);
    const discount = isOpening
        ? 0
        : round((gross * Number(line.discount_percent || 0)) / 100);
    const amount = round(gross) - discount;
    const tax =
        isOpening || line.tax_id === null
            ? undefined
            : taxesById.value.get(line.tax_id);
    const rate = Number(tax?.rate ?? 0);

    if (form.prices_include_tax && !isOpening) {
        const subtotal = round((amount * 100) / (100 + rate));

        return {
            discount,
            subtotal,
            tax: round(amount - subtotal),
            total: amount,
        };
    }

    const lineTax = round((amount * rate) / 100);

    return {
        discount,
        subtotal: amount,
        tax: lineTax,
        total: round(amount + lineTax),
    };
}

/**
 * The amounts of every line, worked out once per change and shared by the
 * line totals and the document totals.
 */
const amounts = computed(() => form.lines.map(lineAmounts));

const totals = computed(() =>
    amounts.value.reduce(
        (sum, line) => ({
            discount: sum.discount + line.discount,
            subtotal: sum.subtotal + line.subtotal,
            tax: sum.tax + line.tax,
            total: sum.total + line.total,
        }),
        { discount: 0, subtotal: 0, tax: 0, total: 0 },
    ),
);

const money = (value: number): string =>
    formatDecimal(round(value), locale.value, decimals.value);

function lineError(position: number, field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `lines.${position}.${field}`
    ];
}

/**
 * Whether any line has an error on the given field, so a column hidden in
 * simple mode is shown again when it needs fixing.
 */
function linesHaveError(field: string): boolean {
    return form.lines.some((_, position) =>
        Boolean(lineError(position, field)),
    );
}

/**
 * Simple mode hides the fields only an accountant needs; their defaults are
 * still submitted. A hidden field reappears when it has a validation error.
 */
const showsReference = computed(
    () => !simpleMode.value || Boolean(form.errors.reference),
);

const showsCurrency = computed(
    () =>
        !simpleMode.value ||
        isForeign.value ||
        Boolean(form.errors.currency_id),
);

const showsCostCenter = computed(
    () =>
        props.costCenters.length > 0 &&
        !isOpening &&
        (!simpleMode.value || Boolean(form.errors.cost_center_id)),
);

const showsPricesIncludeTax = computed(() => !isOpening && !simpleMode.value);

const showsAccountColumn = computed(
    () => !isOpening && (!simpleMode.value || linesHaveError('account_id')),
);

const showLineOptions = ref(
    form.lines.some((line) => Number(line.discount_percent) > 0),
);

const showsLineOptions = computed(
    () =>
        !isOpening &&
        (!simpleMode.value ||
            showLineOptions.value ||
            linesHaveError('discount_percent') ||
            linesHaveError('tax_id')),
);

const tableWidth = computed((): string => {
    if (isOpening) {
        return 'min-w-[560px]';
    }

    if (showsAccountColumn.value) {
        return 'min-w-[1100px]';
    }

    return showsLineOptions.value ? 'min-w-[880px]' : 'min-w-[680px]';
});

const linesBody = useTemplateRef<HTMLTableSectionElement>('linesBody');

function addLine(): void {
    form.lines.push(emptyLine());
}

/**
 * Add a line and move the cursor to its item picker (or its description
 * when there are no items), for typing several lines from the keyboard.
 */
async function addLineAndFocus(): Promise<void> {
    addLine();
    await nextTick();

    linesBody.value?.rows[form.lines.length - 1]
        ?.querySelector<HTMLElement>('button, input')
        ?.focus();
}

/**
 * Enter must not submit the form from a field, which would save a draft and
 * leave the page halfway through typing a line. Instead Enter in the last
 * field of the last line adds a new line, and Ctrl/Cmd+Enter saves.
 */
function handleEnter(event: KeyboardEvent): void {
    if (event.isComposing) {
        return;
    }

    if (event.ctrlKey || event.metaKey) {
        event.preventDefault();

        if (!form.processing) {
            submit(false);
        }

        return;
    }

    const target = event.target;

    if (
        !(target instanceof HTMLInputElement) &&
        !(target instanceof HTMLSelectElement)
    ) {
        return;
    }

    event.preventDefault();

    const lastRow = linesBody.value?.rows[form.lines.length - 1];
    const fields = lastRow?.querySelectorAll('input, select');

    if (fields?.length && fields[fields.length - 1] === target) {
        void addLineAndFocus();
    }
}

const canPost = computed(() => can(`${props.type.permission} Post`));

/**
 * An invoice or bill can be paid in full as it is posted, by whoever may
 * post it and record the receipt (or vendor payment).
 */
const canPayNow = computed(
    () =>
        !props.type.is_return &&
        !isOpening &&
        canPost.value &&
        can(
            props.type.is_sales
                ? 'Customer Receipt Create'
                : 'Vendor Payment Create',
        ),
);

const paymentAccountOptions = computed(() =>
    props.bankAccounts.filter(
        (account) => account.currency_id === Number(form.currency_id),
    ),
);

/**
 * Keep the paying account in the document's currency: the only one, else
 * the first cash box.
 */
watch(
    paymentAccountOptions,
    (options) => {
        if (
            options.some((account) => account.value === form.payment_account_id)
        ) {
            return;
        }

        const preferred =
            options.length === 1
                ? options[0]
                : (options.find((account) => account.type === 'cash') ??
                  options[0]);

        form.payment_account_id = preferred?.value ?? null;
    },
    { immediate: true },
);

const canCreateContact = computed(() => can('Contact Create'));
const canCreateItem = computed(() => can('Item Create'));

const showsItemPicker = computed(
    () => !isOpening && (props.hasItems || canCreateItem.value),
);

const isCreatingContact = ref(false);
const newContactName = ref('');

function startNewContact(query: string, close: () => void): void {
    close();
    newContactName.value = query;
    isCreatingContact.value = true;
}

function contactCreated(contact: ContactOption): void {
    form.contact_id = contact.value;
    chooseContact(contact);
}

const isCreatingItem = ref(false);
const newItemName = ref('');
const newItemLine = ref<LineInput | null>(null);

function startNewItem(line: LineInput, query: string, close: () => void): void {
    close();
    newItemLine.value = line;
    newItemName.value = query;
    isCreatingItem.value = true;
}

function itemCreated(item: ItemOption): void {
    const line = newItemLine.value;

    if (line) {
        line.item_id = item.value;
        chooseItem(line, item);
    }
}

const submitting = ref<'draft' | 'post' | 'new' | null>(null);

/**
 * Save the document, posting it with `post`. With `andNew` the server opens
 * a fresh form of the same type instead of the document, so the page is
 * replaced unless the form comes back with errors.
 */
function submit(post: boolean, andNew = false): void {
    submitting.value = andNew ? 'new' : post ? 'post' : 'draft';
    const paysNow = canPayNow.value && post && form.paid_now;

    form.transform((data) => ({
        contact_id: data.contact_id,
        date: data.date,
        due_date: data.due_date || null,
        reference: data.reference || null,
        currency_id: data.currency_id,
        exchange_rate: isForeign.value ? data.exchange_rate || null : null,
        warehouse_id: hasStockItems.value ? data.warehouse_id : null,
        cost_center_id: data.cost_center_id || null,
        prices_include_tax: isOpening ? false : data.prices_include_tax,
        notes: data.notes || null,
        ...(props.type.is_return
            ? { original_invoice_id: originalInvoice?.id ?? null }
            : { is_opening: isOpening }),
        lines: data.lines.map((line) =>
            isOpening
                ? {
                      description: line.description || null,
                      quantity: '1',
                      unit_price: line.unit_price,
                  }
                : {
                      item_id: line.item_id || null,
                      description: line.description || null,
                      account_id: line.account_id || null,
                      cost_center_id: line.cost_center_id || null,
                      quantity: line.quantity,
                      unit_price: line.unit_price,
                      discount_percent: line.discount_percent || null,
                      tax_id: line.tax_id || null,
                  },
        ),
        post,
        paid_now: paysNow,
        payment_account_id: paysNow ? data.payment_account_id : null,
        and_new: andNew,
    })).submit(
        props.invoice
            ? routes.value.update({ invoice: props.invoice.id })
            : routes.value.store(),
        {
            preserveScroll: andNew ? 'errors' : true,
            preserveState: andNew ? 'errors' : true,
            onFinish: () => (submitting.value = null),
        },
    );
}
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit(false)"
        @keydown.enter="handleEnter"
    >
        <BaseAlert
            v-if="isOpening"
            variant="info"
            :title="t('Opening balance')"
            :messages="[
                t(
                    'Balance carried over from the previous system. It is posted against the opening balances account, without items or taxes.',
                ),
            ]"
        />

        <BaseAlert
            v-if="originalInvoice && type.is_return"
            variant="info"
            :title="
                t('Reverses :document', { document: type.counterpart.label })
            "
        >
            <Link
                :href="
                    documentRoutes(type.counterpart.route).show({
                        invoice: originalInvoice.id,
                    })
                "
                class="font-mono text-brand-500 hover:underline"
                dir="ltr"
            >
                {{ originalInvoice.number }}
            </Link>
        </BaseAlert>

        <BaseCard :title="t('Document details')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <BaseField
                    :label="type.is_sales ? t('Customer') : t('Vendor')"
                    required
                    :error="form.errors.contact_id"
                >
                    <BaseCombobox
                        v-model="form.contact_id"
                        :options="contactOption ? [contactOption] : []"
                        :search="searchContacts"
                        :placeholder="
                            type.is_sales
                                ? t('Choose a customer')
                                : t('Choose a vendor')
                        "
                        :invalid="Boolean(form.errors.contact_id)"
                        @select="chooseContact"
                    >
                        <template
                            v-if="canCreateContact"
                            #footer="{ query, close }"
                        >
                            <button
                                type="button"
                                class="mt-2 flex w-full items-center gap-2 rounded-md border-t border-gray-100 px-3 pt-3 pb-2 text-sm font-medium text-brand-500 hover:bg-brand-50 dark:border-gray-800 dark:text-brand-400 dark:hover:bg-brand-500/15"
                                @click="startNewContact(query, close)"
                            >
                                <Plus class="size-4" />
                                {{
                                    type.is_sales
                                        ? t('New customer')
                                        : t('New vendor')
                                }}
                            </button>
                        </template>
                    </BaseCombobox>
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
                    :label="t('Due date')"
                    for="due_date"
                    :hint="form.due_date ? undefined : dueDateHint"
                    :error="form.errors.due_date"
                >
                    <BaseInput
                        id="due_date"
                        v-model="form.due_date"
                        type="date"
                        :invalid="Boolean(form.errors.due_date)"
                    />
                </BaseField>
                <BaseField
                    v-if="showsReference"
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
                    v-if="showsCurrency"
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
                    v-if="hasStockItems && !isOpening"
                    :label="t('Warehouse')"
                    for="warehouse_id"
                    required
                    :error="form.errors.warehouse_id"
                >
                    <BaseSelect
                        id="warehouse_id"
                        v-model="form.warehouse_id"
                        :invalid="Boolean(form.errors.warehouse_id)"
                    >
                        <option
                            v-for="warehouse in warehouses"
                            :key="warehouse.value"
                            :value="warehouse.value"
                        >
                            {{ warehouse.label }}
                        </option>
                    </BaseSelect>
                </BaseField>
                <BaseField
                    v-if="showsCostCenter"
                    :label="t('Cost center')"
                    :error="form.errors.cost_center_id"
                >
                    <BaseCombobox
                        v-model="form.cost_center_id"
                        :options="costCenters"
                        clearable
                        :placeholder="t('None')"
                    />
                </BaseField>
                <div v-if="showsPricesIncludeTax" class="flex items-end pb-3">
                    <BaseCheckbox v-model="form.prices_include_tax">
                        {{ t('Prices include tax') }}
                    </BaseCheckbox>
                </div>
            </div>
        </BaseCard>

        <BaseCard
            :title="t('Lines')"
            :description="
                currency
                    ? t('Amounts in :currency', { currency: currency.code })
                    : undefined
            "
        >
            <div class="-mx-2 custom-scrollbar overflow-x-auto px-2">
                <table :class="['w-full', tableWidth]">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">
                                {{
                                    showsItemPicker
                                        ? t('Item / description')
                                        : t('Description')
                                }}
                            </th>
                            <th
                                v-if="showsAccountColumn"
                                class="w-[20%] th-cell"
                            >
                                {{ t('Account') }}
                            </th>
                            <th v-if="!isOpening" class="w-24 th-cell text-end">
                                {{ t('Quantity') }}
                            </th>
                            <th class="w-32 th-cell text-end">
                                {{ isOpening ? t('Amount') : t('Price') }}
                            </th>
                            <th
                                v-if="showsLineOptions"
                                class="w-24 th-cell text-end"
                            >
                                {{ t('Discount %') }}
                            </th>
                            <th v-if="showsLineOptions" class="w-40 th-cell">
                                {{ t('Tax') }}
                            </th>
                            <th v-if="!isOpening" class="w-32 th-cell text-end">
                                {{ t('Total') }}
                            </th>
                            <th class="w-12 th-cell" />
                        </tr>
                    </thead>
                    <tbody ref="linesBody">
                        <tr
                            v-for="(line, position) in form.lines"
                            :key="rowKey(line)"
                            class="align-top"
                        >
                            <td class="space-y-2 px-1 py-2">
                                <template v-if="showsItemPicker">
                                    <BaseCombobox
                                        v-model="line.item_id"
                                        :options="line.item ? [line.item] : []"
                                        :search="searchItems"
                                        clearable
                                        :placeholder="t('Choose an item')"
                                        :invalid="
                                            Boolean(
                                                lineError(position, 'item_id'),
                                            )
                                        "
                                        @select="
                                            (option) => chooseItem(line, option)
                                        "
                                    >
                                        <template
                                            v-if="canCreateItem"
                                            #footer="{ query, close }"
                                        >
                                            <button
                                                type="button"
                                                class="mt-2 flex w-full items-center gap-2 rounded-md border-t border-gray-100 px-3 pt-3 pb-2 text-sm font-medium text-brand-500 hover:bg-brand-50 dark:border-gray-800 dark:text-brand-400 dark:hover:bg-brand-500/15"
                                                @click="
                                                    startNewItem(
                                                        line,
                                                        query,
                                                        close,
                                                    )
                                                "
                                            >
                                                <Plus class="size-4" />
                                                {{ t('New item') }}
                                            </button>
                                        </template>
                                    </BaseCombobox>
                                    <BaseInputError
                                        :message="
                                            lineError(position, 'item_id')
                                        "
                                    />
                                </template>
                                <BaseInput
                                    v-model="line.description"
                                    :placeholder="t('Description')"
                                    :invalid="
                                        Boolean(
                                            lineError(position, 'description'),
                                        )
                                    "
                                />
                                <BaseInputError
                                    :message="
                                        lineError(position, 'description')
                                    "
                                />
                                <p
                                    v-if="line.item?.unit"
                                    class="text-theme-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ t('Unit') }}: {{ line.item.unit }}
                                </p>
                            </td>
                            <td v-if="showsAccountColumn" class="px-1 py-2">
                                <BaseCombobox
                                    v-model="line.account_id"
                                    :options="accounts"
                                    clearable
                                    :placeholder="
                                        line.item_id
                                            ? t('Item default')
                                            : t('Choose an account')
                                    "
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
                            <td v-if="!isOpening" class="px-1 py-2">
                                <BaseInput
                                    v-model="line.quantity"
                                    type="number"
                                    step="any"
                                    min="0"
                                    dir="ltr"
                                    class="text-end font-mono"
                                    :invalid="
                                        Boolean(lineError(position, 'quantity'))
                                    "
                                />
                                <BaseInputError
                                    :message="lineError(position, 'quantity')"
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseInput
                                    v-model="line.unit_price"
                                    type="number"
                                    step="any"
                                    min="0"
                                    dir="ltr"
                                    class="text-end font-mono"
                                    :invalid="
                                        Boolean(
                                            lineError(position, 'unit_price'),
                                        )
                                    "
                                />
                                <BaseInputError
                                    :message="lineError(position, 'unit_price')"
                                />
                            </td>
                            <td v-if="showsLineOptions" class="px-1 py-2">
                                <BaseInput
                                    v-model="line.discount_percent"
                                    type="number"
                                    step="any"
                                    min="0"
                                    max="100"
                                    dir="ltr"
                                    class="text-end font-mono"
                                    :invalid="
                                        Boolean(
                                            lineError(
                                                position,
                                                'discount_percent',
                                            ),
                                        )
                                    "
                                />
                                <BaseInputError
                                    :message="
                                        lineError(position, 'discount_percent')
                                    "
                                />
                            </td>
                            <td v-if="showsLineOptions" class="px-1 py-2">
                                <BaseSelect
                                    v-model="line.tax_id"
                                    :invalid="
                                        Boolean(lineError(position, 'tax_id'))
                                    "
                                >
                                    <option :value="null">
                                        {{ t('No tax') }}
                                    </option>
                                    <option
                                        v-for="tax in taxes"
                                        :key="tax.value"
                                        :value="tax.value"
                                    >
                                        {{ tax.label }} ({{
                                            Number(tax.rate)
                                        }}%)
                                    </option>
                                </BaseSelect>
                                <BaseInputError
                                    :message="lineError(position, 'tax_id')"
                                />
                            </td>
                            <td
                                v-if="!isOpening"
                                class="px-3 py-4 text-end font-mono text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ money(amounts[position]?.total ?? 0) }}
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
                </table>
            </div>
            <BaseInputError :message="form.errors.lines" />

            <div
                class="mt-4 flex flex-col gap-6 border-t border-gray-100 pt-4 sm:flex-row sm:items-start sm:justify-between dark:border-gray-800"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <BaseButton variant="ghost" size="sm" @click="addLine">
                        <Plus class="size-4" />
                        {{ t('Add line') }}
                    </BaseButton>
                    <button
                        v-if="simpleMode && !isOpening"
                        type="button"
                        class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-brand-500 dark:text-gray-400"
                        :aria-expanded="showsLineOptions"
                        @click="showLineOptions = !showLineOptions"
                    >
                        <ChevronDown
                            :class="[
                                'size-4 transition-transform',
                                { 'rotate-180': showsLineOptions },
                            ]"
                        />
                        {{ t('More options') }}
                    </button>
                </div>

                <dl class="w-full space-y-2 text-sm sm:w-80">
                    <template v-if="!isOpening">
                        <div
                            v-if="totals.discount > 0"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Discount') }}
                            </dt>
                            <dd
                                class="font-mono text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                −{{ money(totals.discount) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Subtotal') }}
                            </dt>
                            <dd
                                class="font-mono text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ money(totals.subtotal) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Tax') }}
                            </dt>
                            <dd
                                class="font-mono text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ money(totals.tax) }}
                            </dd>
                        </div>
                    </template>
                    <div
                        class="flex justify-between gap-4 border-t border-gray-100 pt-2 text-base font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90"
                    >
                        <dt>{{ t('Total') }}</dt>
                        <dd class="font-mono" dir="ltr">
                            {{ money(totals.total) }}
                            <span
                                class="text-theme-xs font-normal text-gray-400"
                            >
                                {{ currency?.code }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </BaseCard>

        <BaseCard :title="t('Notes')">
            <BaseField for="notes" :error="form.errors.notes">
                <BaseTextarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    :placeholder="t('Shown on the printed document.')"
                    :invalid="Boolean(form.errors.notes)"
                />
            </BaseField>
        </BaseCard>

        <div
            v-if="canPayNow"
            :class="[
                'rounded-2xl border p-4 sm:p-5',
                simpleMode
                    ? 'border-brand-200 bg-brand-25 dark:border-brand-500/30 dark:bg-brand-500/10'
                    : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]',
            ]"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <BaseCheckbox v-model="form.paid_now">
                        <span
                            :class="
                                simpleMode
                                    ? 'text-base font-semibold text-gray-800 dark:text-white/90'
                                    : 'font-medium'
                            "
                        >
                            {{ t('Paid in full now') }}
                        </span>
                    </BaseCheckbox>
                    <p
                        class="ms-8 mt-1 text-theme-xs text-gray-500 dark:text-gray-400"
                    >
                        {{
                            type.is_sales
                                ? t(
                                      'A receipt for the full total is recorded when the invoice is posted.',
                                  )
                                : t(
                                      'A payment for the full total is recorded when the bill is posted.',
                                  )
                        }}
                    </p>
                </div>
                <BaseField
                    v-if="form.paid_now"
                    class="w-full sm:w-72"
                    :label="type.is_sales ? t('Received into') : t('Paid from')"
                    required
                    :hint="
                        paymentAccountOptions.length
                            ? undefined
                            : t(
                                  'There is no active bank account in this currency.',
                              )
                    "
                    :error="form.errors.payment_account_id"
                >
                    <BaseCombobox
                        v-model="form.payment_account_id"
                        :options="paymentAccountOptions"
                        :placeholder="t('Choose an account')"
                        :invalid="Boolean(form.errors.payment_account_id)"
                    />
                </BaseField>
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3">
            <BaseButton
                variant="outline"
                :href="
                    invoice
                        ? routes.show({ invoice: invoice.id })
                        : routes.index()
                "
            >
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton
                type="submit"
                :variant="canPost ? 'outline' : 'primary'"
                :loading="submitting === 'draft'"
                :disabled="form.processing"
            >
                {{ t('Save draft') }}
            </BaseButton>
            <BaseButton
                v-if="!invoice"
                variant="outline"
                :loading="submitting === 'new'"
                :disabled="form.processing"
                @click="submit(canPost, true)"
            >
                <Plus class="size-4" />
                {{ canPost ? t('Save, post and new') : t('Save and new') }}
            </BaseButton>
            <BaseButton
                v-if="canPost"
                :loading="submitting === 'post'"
                :disabled="form.processing"
                @click="submit(true)"
            >
                {{ t('Save and post') }}
            </BaseButton>
        </div>

        <QuickContactModal
            v-model:open="isCreatingContact"
            :type="type.contact_type"
            :name="newContactName"
            @created="contactCreated"
        />
        <QuickItemModal
            v-model:open="isCreatingItem"
            :units="units"
            :is-sales="type.is_sales"
            :name="newItemName"
            @created="itemCreated"
        />
    </form>
</template>
