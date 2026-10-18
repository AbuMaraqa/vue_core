<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import ChevronDown from '~icons/solar/alt-arrow-down-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed, nextTick, ref, useTemplateRef } from 'vue';
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
import { useContactSearch } from '@/composables/useContactSearch';
import { useItemSearch } from '@/composables/useItemSearch';
import { useLocale } from '@/composables/useLocale';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, formatDecimal } from '@/lib/format';
import { rowKey } from '@/lib/rowKey';
import { index, show, store, update } from '@/routes/admin/sales/quotations';
import type {
    ComboboxOption,
    ContactOption,
    CurrencyOption,
    ItemOption,
    Quotation,
    QuotationDefaults,
    TaxOption,
} from '@/types';

const props = withDefaults(
    defineProps<{
        currencies: CurrencyOption[];
        taxes: TaxOption[];
        hasItems: boolean;
        /**
         * The starting values of a new quotation.
         */
        defaults?: QuotationDefaults;
        /**
         * The quotation being edited.
         */
        quotation?: Quotation;
        /**
         * The customer of the quotation being edited, for the picker.
         */
        contact?: ContactOption | null;
        /**
         * The item of each line of the quotation being edited, by line id.
         */
        lineItems?: Record<string, ItemOption | null>;
    }>(),
    {
        defaults: undefined,
        quotation: undefined,
        contact: null,
        lineItems: () => ({}),
    },
);

const { t } = useTranslations();
const { locale } = useLocale();
const simpleMode = useSimpleMode();

const searchContacts = useContactSearch('customers');
const searchItems = useItemSearch();

type LineInput = {
    item_id: number | string | null;
    /**
     * The chosen item, kept for its label, type and prices; not sent.
     */
    item: ItemOption | null;
    description: string;
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
    if (props.quotation?.lines?.length) {
        return props.quotation.lines.map((line) => ({
            item_id: line.item_id,
            item: props.lineItems[String(line.id)] ?? null,
            description: line.description ?? '',
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
    contact_id: (props.quotation?.contact_id ??
        props.defaults?.contact?.value ??
        null) as number | string | null,
    date: props.quotation?.date ?? props.defaults?.date ?? '',
    valid_until: props.quotation?.valid_until ?? '',
    reference: props.quotation?.reference ?? '',
    currency_id: (props.quotation?.currency_id ??
        props.defaults?.currency_id ??
        null) as number | string | null,
    prices_include_tax:
        props.quotation?.prices_include_tax ??
        props.defaults?.prices_include_tax ??
        false,
    notes: props.quotation?.notes ?? '',
    lines: initialLines(),
});

const currency = computed(() =>
    props.currencies.find((option) => option.value === form.currency_id),
);

const decimals = computed(() => currency.value?.decimal_places ?? 2);

const isForeign = computed(
    () => currency.value !== undefined && !currency.value.is_base,
);

function chooseContact(option: ComboboxOption | null): void {
    contactOption.value = option as ContactOption | null;

    if (contactOption.value?.currency_id && !props.quotation) {
        form.currency_id = contactOption.value.currency_id;
    }
}

/**
 * The validity date the server sets when the field is left empty.
 */
const defaultValidUntil = computed((): string | null => {
    const days = props.defaults?.validity_days;
    const date = new Date(`${form.date}T00:00:00Z`);

    if (!days || Number.isNaN(date.getTime())) {
        return null;
    }

    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
});

const validUntilHint = computed((): string | undefined =>
    !form.valid_until && defaultValidUntil.value
        ? t('Leave empty to keep it valid until :date.', {
              date: formatDate(defaultValidUntil.value, locale.value),
          })
        : undefined,
);

function chooseItem(line: LineInput, option: ComboboxOption | null): void {
    const item = option as ItemOption | null;
    line.item = item;

    if (!item) {
        return;
    }

    line.description = item.label;
    line.unit_price = trimDecimal(item.sale_price);
    line.tax_id = item.sales_tax_id;

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
    const gross = Number(line.quantity || 0) * Number(line.unit_price || 0);
    const discount = round((gross * Number(line.discount_percent || 0)) / 100);
    const amount = round(gross) - discount;
    const tax =
        line.tax_id === null ? undefined : taxesById.value.get(line.tax_id);
    const rate = Number(tax?.rate ?? 0);

    if (form.prices_include_tax) {
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

function linesHaveError(field: string): boolean {
    return form.lines.some((_, position) =>
        Boolean(lineError(position, field)),
    );
}

/**
 * Simple mode hides what only an accountant needs; a hidden field
 * reappears when it has a validation error.
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

const showLineOptions = ref(
    form.lines.some((line) => Number(line.discount_percent) > 0),
);

const showsLineOptions = computed(
    () =>
        !simpleMode.value ||
        showLineOptions.value ||
        linesHaveError('discount_percent') ||
        linesHaveError('tax_id'),
);

const linesBody = useTemplateRef<HTMLTableSectionElement>('linesBody');

function addLine(): void {
    form.lines.push(emptyLine());
}

async function addLineAndFocus(): Promise<void> {
    addLine();
    await nextTick();

    linesBody.value?.rows[form.lines.length - 1]
        ?.querySelector<HTMLElement>('button, input')
        ?.focus();
}

/**
 * Enter in the last field of the last line adds a new line instead of
 * saving halfway through; Ctrl/Cmd+Enter saves.
 */
function handleEnter(event: KeyboardEvent): void {
    if (event.isComposing) {
        return;
    }

    if (event.ctrlKey || event.metaKey) {
        event.preventDefault();

        if (!form.processing) {
            submit();
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

function submit(): void {
    form.transform((data) => ({
        contact_id: data.contact_id,
        date: data.date,
        valid_until: data.valid_until || null,
        reference: data.reference || null,
        currency_id: data.currency_id,
        prices_include_tax: data.prices_include_tax,
        notes: data.notes || null,
        lines: data.lines.map((line) => ({
            item_id: line.item_id || null,
            description: line.description || null,
            quantity: line.quantity,
            unit_price: line.unit_price,
            discount_percent: line.discount_percent || null,
            tax_id: line.tax_id || null,
        })),
    })).submit(
        props.quotation ? update({ quotation: props.quotation.id }) : store(),
        { preserveScroll: true },
    );
}
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit"
        @keydown.enter="handleEnter"
    >
        <BaseCard :title="t('Quotation details')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <BaseField
                    :label="t('Customer')"
                    required
                    :error="form.errors.contact_id"
                >
                    <BaseCombobox
                        v-model="form.contact_id"
                        :options="contactOption ? [contactOption] : []"
                        :search="searchContacts"
                        :placeholder="t('Choose a customer')"
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
                    :label="t('Valid until')"
                    for="valid_until"
                    :hint="validUntilHint"
                    :error="form.errors.valid_until"
                >
                    <BaseInput
                        id="valid_until"
                        v-model="form.valid_until"
                        type="date"
                        :invalid="Boolean(form.errors.valid_until)"
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
                <div v-if="!simpleMode" class="flex items-end pb-3">
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
                <table
                    :class="[
                        'w-full',
                        showsLineOptions ? 'min-w-[880px]' : 'min-w-[680px]',
                    ]"
                >
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">
                                {{
                                    hasItems
                                        ? t('Item / description')
                                        : t('Description')
                                }}
                            </th>
                            <th class="w-24 th-cell text-end">
                                {{ t('Quantity') }}
                            </th>
                            <th class="w-32 th-cell text-end">
                                {{ t('Price') }}
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
                            <th class="w-32 th-cell text-end">
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
                                <template v-if="hasItems">
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
                                    />
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
                            <td class="px-1 py-2">
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
                        v-if="simpleMode"
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
                    :placeholder="
                        t(
                            'Delivery time, payment terms... shown on the printed quotation.',
                        )
                    "
                    :invalid="Boolean(form.errors.notes)"
                />
            </BaseField>
        </BaseCard>

        <div class="flex flex-wrap justify-end gap-3">
            <BaseButton
                variant="outline"
                :href="quotation ? show({ quotation: quotation.id }) : index()"
            >
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton type="submit" :loading="form.processing">
                {{ t('Save quotation') }}
            </BaseButton>
        </div>
    </form>
</template>
