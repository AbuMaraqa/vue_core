<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import BaseTranslatableInput from '@/components/base/BaseTranslatableInput.vue';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { index, show, store, update } from '@/routes/admin/inventory/items';
import type {
    ComboboxOption,
    Item,
    SelectOption,
    TaxOption,
    Translations,
} from '@/types';

const props = defineProps<{
    item?: Item;
    type?: 'stock' | 'service';
    types: SelectOption[];
    categories: ComboboxOption[];
    units: ComboboxOption[];
    salesTaxes: TaxOption[];
    purchaseTaxes: TaxOption[];
    revenueAccounts: ComboboxOption[];
    expenseAccounts: ComboboxOption[];
    inventoryAccounts: ComboboxOption[];
}>();

const { t } = useTranslations();

const form = useForm({
    code: props.item?.code ?? '',
    name: { ...props.item?.name_translations } as Translations,
    type: props.item?.type ?? props.type ?? 'stock',
    category_id: props.item?.category_id ?? null,
    unit_id: props.item?.unit_id ?? null,
    barcode: props.item?.barcode ?? '',
    sale_price: props.item?.sale_price ?? '',
    purchase_price: props.item?.purchase_price ?? '',
    sales_tax_id: props.item?.sales_tax_id ?? null,
    purchase_tax_id: props.item?.purchase_tax_id ?? null,
    reorder_level: props.item?.reorder_level ?? '',
    sales_account_id: props.item?.sales_account_id ?? null,
    purchase_account_id: props.item?.purchase_account_id ?? null,
    inventory_account_id: props.item?.inventory_account_id ?? null,
    cost_of_sales_account_id: props.item?.cost_of_sales_account_id ?? null,
    is_active: props.item?.is_active ?? true,
    description: props.item?.description ?? '',
});

const isStock = computed(() => form.type === 'stock');

const simpleMode = useSimpleMode();

/**
 * The code is numbered automatically and the accounts fall back to the category
 * or the defaults, so simple mode hides them unless one of them has an error.
 */
const ADVANCED_FIELDS = [
    'code',
    'sales_account_id',
    'purchase_account_id',
    'inventory_account_id',
    'cost_of_sales_account_id',
] as const;

const showsAdvancedFields = computed(
    () =>
        !simpleMode.value ||
        ADVANCED_FIELDS.some((field) => Boolean(form.errors[field])),
);

/**
 * Fields that do not apply to the chosen type are sent empty.
 */
function submit(): void {
    form.transform((data) => ({
        ...data,
        reorder_level: data.type === 'stock' ? data.reorder_level : null,
        purchase_account_id:
            data.type === 'service' ? data.purchase_account_id : null,
        inventory_account_id:
            data.type === 'stock' ? data.inventory_account_id : null,
        cost_of_sales_account_id:
            data.type === 'stock' ? data.cost_of_sales_account_id : null,
    })).submit(props.item ? update({ item: props.item.id }) : store(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <BaseCard :title="t('Details')">
            <div class="space-y-5">
                <BaseTranslatableInput
                    v-model="form.name"
                    :label="t('Name')"
                    field="name"
                    :errors="form.errors"
                    required
                />
                <div
                    class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <BaseField
                        v-if="showsAdvancedFields"
                        :label="t('Code')"
                        for="code"
                        :hint="t('Leave empty to number it automatically.')"
                        :error="form.errors.code"
                    >
                        <BaseInput
                            id="code"
                            v-model="form.code"
                            dir="ltr"
                            :invalid="Boolean(form.errors.code)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Type')"
                        for="type"
                        required
                        :hint="
                            item
                                ? t(
                                      'The type cannot change once the item has moved in stock.',
                                  )
                                : undefined
                        "
                        :error="form.errors.type"
                    >
                        <BaseSelect
                            id="type"
                            v-model="form.type"
                            :invalid="Boolean(form.errors.type)"
                        >
                            <option
                                v-for="option in types"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </BaseSelect>
                    </BaseField>
                    <BaseField
                        :label="t('Barcode')"
                        for="barcode"
                        :error="form.errors.barcode"
                    >
                        <BaseInput
                            id="barcode"
                            v-model="form.barcode"
                            dir="ltr"
                            :invalid="Boolean(form.errors.barcode)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Category')"
                        for="category_id"
                        :error="form.errors.category_id"
                    >
                        <BaseCombobox
                            id="category_id"
                            v-model="form.category_id"
                            :options="categories"
                            clearable
                            :placeholder="t('No category')"
                            :invalid="Boolean(form.errors.category_id)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Unit')"
                        for="unit_id"
                        :error="form.errors.unit_id"
                    >
                        <BaseCombobox
                            id="unit_id"
                            v-model="form.unit_id"
                            :options="units"
                            clearable
                            :placeholder="t('No unit')"
                            :invalid="Boolean(form.errors.unit_id)"
                        />
                    </BaseField>
                    <BaseField
                        v-if="isStock"
                        :label="t('Reorder level')"
                        for="reorder_level"
                        :hint="
                            t(
                                'The item is flagged when its stock falls to this quantity.',
                            )
                        "
                        :error="form.errors.reorder_level"
                    >
                        <BaseInput
                            id="reorder_level"
                            v-model="form.reorder_level"
                            type="number"
                            min="0"
                            step="any"
                            dir="ltr"
                            :invalid="Boolean(form.errors.reorder_level)"
                        />
                    </BaseField>
                    <BaseField
                        class="sm:col-span-2 lg:col-span-3"
                        :label="t('Description')"
                        for="description"
                        :error="form.errors.description"
                    >
                        <BaseTextarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                        />
                    </BaseField>
                    <BaseCheckbox v-model="form.is_active">
                        {{ t('Active') }}
                    </BaseCheckbox>
                </div>
            </div>
        </BaseCard>

        <BaseCard :title="t('Prices and taxes')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <BaseField
                    :label="t('Sale price')"
                    for="sale_price"
                    :error="form.errors.sale_price"
                >
                    <BaseInput
                        id="sale_price"
                        v-model="form.sale_price"
                        type="number"
                        min="0"
                        step="any"
                        dir="ltr"
                        :invalid="Boolean(form.errors.sale_price)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Sales tax')"
                    for="sales_tax_id"
                    :error="form.errors.sales_tax_id"
                >
                    <BaseCombobox
                        id="sales_tax_id"
                        v-model="form.sales_tax_id"
                        :options="salesTaxes"
                        clearable
                        :placeholder="t('No tax')"
                        :invalid="Boolean(form.errors.sales_tax_id)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Purchase price')"
                    for="purchase_price"
                    :error="form.errors.purchase_price"
                >
                    <BaseInput
                        id="purchase_price"
                        v-model="form.purchase_price"
                        type="number"
                        min="0"
                        step="any"
                        dir="ltr"
                        :invalid="Boolean(form.errors.purchase_price)"
                    />
                </BaseField>
                <BaseField
                    :label="t('Purchase tax')"
                    for="purchase_tax_id"
                    :error="form.errors.purchase_tax_id"
                >
                    <BaseCombobox
                        id="purchase_tax_id"
                        v-model="form.purchase_tax_id"
                        :options="purchaseTaxes"
                        clearable
                        :placeholder="t('No tax')"
                        :invalid="Boolean(form.errors.purchase_tax_id)"
                    />
                </BaseField>
            </div>
        </BaseCard>

        <BaseCard
            v-if="showsAdvancedFields"
            :title="t('Accounts')"
            :description="
                t('Leave empty to use the category or the default accounts.')
            "
        >
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <BaseField
                    :label="t('Sales account')"
                    for="sales_account_id"
                    :error="form.errors.sales_account_id"
                >
                    <BaseCombobox
                        id="sales_account_id"
                        v-model="form.sales_account_id"
                        :options="revenueAccounts"
                        clearable
                        :placeholder="t('Default account')"
                        :invalid="Boolean(form.errors.sales_account_id)"
                    />
                </BaseField>
                <BaseField
                    v-if="!isStock"
                    :label="t('Purchase account')"
                    for="purchase_account_id"
                    :error="form.errors.purchase_account_id"
                >
                    <BaseCombobox
                        id="purchase_account_id"
                        v-model="form.purchase_account_id"
                        :options="expenseAccounts"
                        clearable
                        :placeholder="t('Default account')"
                        :invalid="Boolean(form.errors.purchase_account_id)"
                    />
                </BaseField>
                <BaseField
                    v-if="isStock"
                    :label="t('Inventory account')"
                    for="inventory_account_id"
                    :error="form.errors.inventory_account_id"
                >
                    <BaseCombobox
                        id="inventory_account_id"
                        v-model="form.inventory_account_id"
                        :options="inventoryAccounts"
                        clearable
                        :placeholder="t('Default account')"
                        :invalid="Boolean(form.errors.inventory_account_id)"
                    />
                </BaseField>
                <BaseField
                    v-if="isStock"
                    :label="t('Cost of sales account')"
                    for="cost_of_sales_account_id"
                    :error="form.errors.cost_of_sales_account_id"
                >
                    <BaseCombobox
                        id="cost_of_sales_account_id"
                        v-model="form.cost_of_sales_account_id"
                        :options="expenseAccounts"
                        clearable
                        :placeholder="t('Default account')"
                        :invalid="Boolean(form.errors.cost_of_sales_account_id)"
                    />
                </BaseField>
            </div>
        </BaseCard>

        <div class="flex justify-end gap-3">
            <BaseButton
                variant="outline"
                :href="item ? show({ item: item.id }) : index()"
            >
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton type="submit" :loading="form.processing">
                {{ item ? t('Save changes') : t('Create') }}
            </BaseButton>
        </div>
    </form>
</template>
