<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import Refresh from '~icons/solar/refresh-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed, onMounted, ref } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ProductionCostLines from '@/components/production/ProductionCostLines.vue';
import { useContactSearch } from '@/composables/useContactSearch';
import { useItemSearch } from '@/composables/useItemSearch';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatQuantity, scale } from '@/lib/production';
import { rowKey } from '@/lib/rowKey';
import { index, show, store, update } from '@/routes/admin/production/orders';
import type {
    BillOfMaterialsOption,
    ComboboxOption,
    ItemOption,
    ProductionCost,
    ProductionCurrency,
    ProductionOrder,
} from '@/types';

const props = defineProps<{
    order?: ProductionOrder;
    defaults?: {
        date: string;
        materials_warehouse_id: number | null;
        product_warehouse_id: number | null;
        bill_of_materials_id: number | null;
    };
    warehouses: ComboboxOption[];
    billsOfMaterials: BillOfMaterialsOption[];
    accounts: ComboboxOption[];
    costCenters: ComboboxOption[];
    defaultCostAccountId: number | null;
    baseCurrency: ProductionCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const searchItems = useItemSearch('stock');
const searchCustomers = useContactSearch('customers');

type MaterialInput = {
    item_id: number | null;
    quantity: string;
    /**
     * The chosen item, kept for its label, unit and stock; not sent.
     */
    item: ItemOption | null;
};

function emptyMaterial(): MaterialInput {
    return { item_id: null, quantity: '', item: null };
}

const form = useForm({
    date: props.order?.date ?? props.defaults?.date ?? '',
    bill_of_materials_id: props.order?.bill_of_materials_id ?? null,
    item_id: props.order?.item_id ?? (null as number | null),
    quantity: props.order?.quantity ?? '1',
    materials_warehouse_id:
        props.order?.materials_warehouse_id ??
        props.defaults?.materials_warehouse_id ??
        null,
    product_warehouse_id:
        props.order?.product_warehouse_id ??
        props.defaults?.product_warehouse_id ??
        null,
    contact_id: props.order?.contact_id ?? null,
    cost_center_id: props.order?.cost_center_id ?? null,
    reference: props.order?.reference ?? '',
    description: props.order?.description ?? '',
    materials: (props.order?.materials?.map((material) => ({
        item_id: material.item_id,
        quantity: material.quantity,
        item: material.item,
    })) ?? [emptyMaterial()]) as MaterialInput[],
    costs: (props.order?.costs?.map((cost) => ({
        description: cost.description,
        account_id: cost.account_id,
        amount: cost.amount,
    })) ?? []) as ProductionCost[],
});

const product = ref<ItemOption | null>(props.order?.item_option ?? null);
const customer = props.order?.contact ?? null;

const recipe = computed(
    () =>
        props.billsOfMaterials.find(
            (bom) => bom.value === form.bill_of_materials_id,
        ) ?? null,
);

/**
 * Copy the chosen recipe, scaled to the quantity to produce: its product,
 * its materials (waste included) and its other costs.
 */
function applyRecipe(): void {
    const bom = recipe.value;

    if (!bom) {
        return;
    }

    const factor = (Number(form.quantity) || 1) / (Number(bom.quantity) || 1);

    product.value = bom.item;
    form.item_id = bom.item.value;
    form.materials = bom.components.map((component) => ({
        item_id: component.item.value,
        quantity: scale(component.quantity, factor),
        item: component.item,
    }));
    form.costs = bom.costs.map((cost) => ({
        description: cost.description,
        account_id: cost.account_id,
        amount: scale(cost.amount, factor, props.baseCurrency.decimal_places),
    }));

    if (form.materials.length === 0) {
        form.materials = [emptyMaterial()];
    }
}

function chooseRecipe(option: ComboboxOption | null): void {
    form.bill_of_materials_id = option ? Number(option.value) : null;
    applyRecipe();
}

onMounted(() => {
    if (!props.order && props.defaults?.bill_of_materials_id) {
        form.bill_of_materials_id = props.defaults.bill_of_materials_id;
        applyRecipe();
    }
});

function error(path: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[path];
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        materials: data.materials.map((material) => ({
            item_id: material.item_id,
            quantity: material.quantity,
        })),
    })).submit(props.order ? update({ order: props.order.id }) : store(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="grid grid-cols-12 gap-6" @submit.prevent="submit">
        <div class="col-span-12 lg:col-span-8">
            <BaseCard :title="t('What to produce')">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <BaseField
                        class="sm:col-span-2"
                        :label="t('Bill of materials')"
                        :hint="
                            t(
                                'Choose a recipe to fill in the product, its materials and costs. You can still change them.',
                            )
                        "
                        :error="form.errors.bill_of_materials_id"
                    >
                        <BaseCombobox
                            :model-value="form.bill_of_materials_id"
                            :options="billsOfMaterials"
                            clearable
                            :placeholder="t('Without a recipe')"
                            @select="chooseRecipe"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Product')"
                        required
                        :error="form.errors.item_id"
                    >
                        <BaseCombobox
                            v-model="form.item_id"
                            :options="product ? [product] : []"
                            :search="searchItems"
                            :placeholder="t('Choose an item')"
                            :invalid="Boolean(form.errors.item_id)"
                            @select="
                                (option) =>
                                    (product = option as ItemOption | null)
                            "
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Quantity to produce')"
                        for="quantity"
                        required
                        :error="form.errors.quantity"
                    >
                        <div class="flex gap-2">
                            <BaseInput
                                id="quantity"
                                v-model="form.quantity"
                                type="number"
                                step="any"
                                min="0"
                                dir="ltr"
                                class="flex-1"
                                :invalid="Boolean(form.errors.quantity)"
                            />
                            <BaseIconButton
                                v-if="recipe"
                                :label="t('Recalculate from the recipe')"
                                @click="applyRecipe"
                            >
                                <Refresh />
                            </BaseIconButton>
                        </div>
                    </BaseField>
                    <BaseField
                        :label="t('Materials warehouse')"
                        for="materials_warehouse_id"
                        required
                        :hint="t('Where the materials are taken from.')"
                        :error="form.errors.materials_warehouse_id"
                    >
                        <BaseSelect
                            id="materials_warehouse_id"
                            v-model="form.materials_warehouse_id"
                            :invalid="
                                Boolean(form.errors.materials_warehouse_id)
                            "
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
                        :label="t('Finished goods warehouse')"
                        for="product_warehouse_id"
                        required
                        :hint="t('Where the finished product is stored.')"
                        :error="form.errors.product_warehouse_id"
                    >
                        <BaseSelect
                            id="product_warehouse_id"
                            v-model="form.product_warehouse_id"
                            :invalid="Boolean(form.errors.product_warehouse_id)"
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
                </div>
            </BaseCard>
        </div>

        <div class="col-span-12 lg:col-span-4">
            <BaseCard :title="t('Order details')">
                <div class="space-y-5">
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
                        :label="t('Customer')"
                        :hint="
                            t(
                                'Optional, when the order is made for a customer.',
                            )
                        "
                        :error="form.errors.contact_id"
                    >
                        <BaseCombobox
                            v-model="form.contact_id"
                            :options="customer ? [customer] : []"
                            :search="searchCustomers"
                            clearable
                            :placeholder="t('Choose a customer')"
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
                            :placeholder="
                                t('e.g. Sales order or drawing number')
                            "
                        />
                    </BaseField>
                    <BaseField
                        v-if="costCenters.length"
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
                    <BaseField
                        :label="t('Description')"
                        for="description"
                        :error="form.errors.description"
                    >
                        <BaseInput
                            id="description"
                            v-model="form.description"
                            :placeholder="t('e.g. Kitchen cabinets, walnut')"
                        />
                    </BaseField>
                </div>
            </BaseCard>
        </div>

        <BaseCard
            class="col-span-12"
            :title="t('Materials')"
            :description="
                t(
                    'What will be issued from the materials warehouse when production starts. The real consumption is entered when it completes.',
                )
            "
        >
            <div class="-mx-2 custom-scrollbar overflow-x-auto px-2">
                <table class="w-full min-w-[640px]">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="w-[55%] th-cell">
                                {{ t('Material') }}
                            </th>
                            <th class="w-40 th-cell text-end">
                                {{ t('Quantity') }}
                            </th>
                            <th class="th-cell text-end">
                                {{ t('On hand') }}
                            </th>
                            <th class="w-12 th-cell" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(material, position) in form.materials"
                            :key="rowKey(material)"
                            class="align-top"
                        >
                            <td class="px-1 py-2">
                                <BaseCombobox
                                    v-model="material.item_id"
                                    :options="
                                        material.item ? [material.item] : []
                                    "
                                    :search="searchItems"
                                    :placeholder="t('Choose a material')"
                                    :invalid="
                                        Boolean(
                                            error(
                                                `materials.${position}.item_id`,
                                            ),
                                        )
                                    "
                                    @select="
                                        (option) =>
                                            (material.item =
                                                option as ItemOption | null)
                                    "
                                />
                                <BaseInputError
                                    :message="
                                        error(`materials.${position}.item_id`)
                                    "
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseInput
                                    v-model="material.quantity"
                                    type="number"
                                    step="any"
                                    min="0"
                                    dir="ltr"
                                    class="text-end"
                                    :invalid="
                                        Boolean(
                                            error(
                                                `materials.${position}.quantity`,
                                            ),
                                        )
                                    "
                                />
                                <BaseInputError
                                    :message="
                                        error(`materials.${position}.quantity`)
                                    "
                                />
                            </td>
                            <td
                                class="px-3 py-4 text-end font-mono text-theme-sm"
                                :class="
                                    material.item?.on_hand != null &&
                                    Number(material.item.on_hand) <
                                        Number(material.quantity)
                                        ? 'text-error-600 dark:text-error-500'
                                        : 'text-gray-500 dark:text-gray-400'
                                "
                                dir="ltr"
                            >
                                <template v-if="material.item?.on_hand != null">
                                    {{
                                        formatQuantity(
                                            material.item.on_hand,
                                            locale,
                                        )
                                    }}
                                    <span class="text-theme-xs">
                                        {{ material.item.unit ?? '' }}
                                    </span>
                                </template>
                            </td>
                            <td class="px-1 py-2">
                                <BaseIconButton
                                    v-if="form.materials.length > 1"
                                    color="danger"
                                    :label="t('Remove line')"
                                    @click="form.materials.splice(position, 1)"
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
                            <td class="px-1 py-3" colspan="4">
                                <BaseButton
                                    variant="ghost"
                                    size="sm"
                                    @click="
                                        form.materials.push(emptyMaterial())
                                    "
                                >
                                    <Plus class="size-4" />
                                    {{ t('Add material') }}
                                </BaseButton>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400">
                {{ t('On hand is the quantity in all warehouses.') }}
            </p>
            <BaseInputError :message="form.errors.materials" />
        </BaseCard>

        <BaseCard
            class="col-span-12"
            :title="t('Other costs')"
            :description="
                t(
                    'Expected costs other than materials, e.g. labour or painting. They can be corrected when production completes.',
                )
            "
        >
            <ProductionCostLines
                v-model="form.costs"
                :accounts="accounts"
                :default-account-id="defaultCostAccountId"
                :currency="baseCurrency"
                :errors="form.errors"
            />
        </BaseCard>

        <div class="col-span-12 flex justify-end gap-3">
            <BaseButton
                variant="outline"
                :href="order ? show({ order: order.id }) : index()"
            >
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton type="submit" :loading="form.processing">
                {{ order ? t('Save changes') : t('Save draft') }}
            </BaseButton>
        </div>
    </form>
</template>
