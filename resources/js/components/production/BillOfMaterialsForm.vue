<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import ProductionCostLines from '@/components/production/ProductionCostLines.vue';
import { useItemSearch } from '@/composables/useItemSearch';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { formatQuantity } from '@/lib/production';
import { rowKey } from '@/lib/rowKey';
import { index, store, update } from '@/routes/admin/production/boms';
import type {
    BillOfMaterials,
    BillOfMaterialsEstimate,
    ComboboxOption,
    ItemOption,
    ProductionCost,
} from '@/types';

const props = defineProps<{
    bom?: BillOfMaterials;
    estimate?: BillOfMaterialsEstimate;
    accounts: ComboboxOption[];
    defaultCostAccountId: number | null;
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const searchItems = useItemSearch('stock');

type ComponentInput = {
    item_id: number | null;
    quantity: string;
    waste_percent: string;
    /**
     * The chosen item, kept for its label and unit; not sent.
     */
    item: ItemOption | null;
};

function emptyComponent(): ComponentInput {
    return { item_id: null, quantity: '', waste_percent: '', item: null };
}

const form = useForm({
    code: props.bom?.code ?? '',
    name: props.bom?.name ?? '',
    item_id: props.bom?.item_id ?? (null as number | null),
    quantity: props.bom?.quantity ?? '1',
    notes: props.bom?.notes ?? '',
    is_active: props.bom?.is_active ?? true,
    components: (props.bom?.components?.map((component) => ({
        item_id: component.item_id,
        quantity: component.quantity,
        waste_percent:
            Number(component.waste_percent) > 0 ? component.waste_percent : '',
        item: component.item,
    })) ?? [emptyComponent()]) as ComponentInput[],
    costs: (props.bom?.costs?.map((cost) => ({
        description: cost.description,
        account_id: cost.account_id,
        amount: cost.amount,
    })) ?? []) as ProductionCost[],
});

const product = props.bom?.item_option ?? null;

function grossQuantity(component: ComponentInput): string {
    const quantity = Number(component.quantity) || 0;

    return formatQuantity(
        quantity + (quantity * (Number(component.waste_percent) || 0)) / 100,
        locale.value,
    );
}

/**
 * Name a new recipe after its product.
 */
function chooseProduct(option: ComboboxOption | null): void {
    if (option && form.name === '') {
        form.name = (option as ItemOption).label.replace(/^\S+\s-\s/, '');
    }
}

function error(path: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[path];
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        components: data.components.map((component) => ({
            item_id: component.item_id,
            quantity: component.quantity,
            waste_percent: component.waste_percent || null,
        })),
    })).submit(props.bom ? update({ bom: props.bom.id }) : store(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="grid grid-cols-12 gap-6" @submit.prevent="submit">
        <div class="col-span-12 lg:col-span-8">
            <BaseCard :title="t('Recipe details')">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <BaseField
                        class="sm:col-span-2"
                        :label="t('Product')"
                        required
                        :hint="t('The finished item this recipe makes.')"
                        :error="form.errors.item_id"
                    >
                        <BaseCombobox
                            v-model="form.item_id"
                            :options="product ? [product] : []"
                            :search="searchItems"
                            :placeholder="t('Choose an item')"
                            :invalid="Boolean(form.errors.item_id)"
                            @select="chooseProduct"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Code')"
                        for="code"
                        required
                        :error="form.errors.code"
                    >
                        <BaseInput
                            id="code"
                            v-model="form.code"
                            dir="ltr"
                            placeholder="BOM-001"
                            :invalid="Boolean(form.errors.code)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Name')"
                        for="name"
                        required
                        :error="form.errors.name"
                    >
                        <BaseInput
                            id="name"
                            v-model="form.name"
                            :invalid="Boolean(form.errors.name)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Batch quantity')"
                        for="quantity"
                        required
                        :hint="
                            t(
                                'How many units of the product the materials below make.',
                            )
                        "
                        :error="form.errors.quantity"
                    >
                        <BaseInput
                            id="quantity"
                            v-model="form.quantity"
                            type="number"
                            step="any"
                            min="0"
                            dir="ltr"
                            :invalid="Boolean(form.errors.quantity)"
                        />
                    </BaseField>
                    <BaseField
                        class="sm:col-span-2"
                        :label="t('Notes')"
                        for="notes"
                        :error="form.errors.notes"
                    >
                        <BaseTextarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            :placeholder="
                                t('e.g. Dimensions, finish, assembly steps')
                            "
                        />
                    </BaseField>
                </div>
            </BaseCard>
        </div>

        <div class="col-span-12 space-y-6 lg:col-span-4">
            <BaseCard :title="t('Status')">
                <BaseCheckbox v-model="form.is_active">
                    {{ t('Active') }}
                </BaseCheckbox>
                <p class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400">
                    {{
                        t(
                            'Only active recipes can be chosen in new production orders.',
                        )
                    }}
                </p>
            </BaseCard>

            <BaseCard
                v-if="estimate"
                :title="t('Estimated cost')"
                :description="
                    t('At the current average cost of the materials, as saved.')
                "
            >
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Materials') }}
                        </dt>
                        <dd class="font-mono" dir="ltr">
                            {{
                                formatDecimal(
                                    estimate.materials,
                                    locale,
                                    baseCurrency.decimal_places,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Other costs') }}
                        </dt>
                        <dd class="font-mono" dir="ltr">
                            {{
                                formatDecimal(
                                    estimate.other,
                                    locale,
                                    baseCurrency.decimal_places,
                                )
                            }}
                        </dd>
                    </div>
                    <div
                        class="flex justify-between gap-4 border-t border-gray-100 pt-3 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90"
                    >
                        <dt>{{ t('Unit cost') }} ({{ baseCurrency.code }})</dt>
                        <dd class="font-mono" dir="ltr">
                            {{
                                formatDecimal(
                                    estimate.unit,
                                    locale,
                                    baseCurrency.decimal_places,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </BaseCard>
        </div>

        <BaseCard
            class="col-span-12"
            :title="t('Materials')"
            :description="
                t(
                    'The raw materials of one batch. The waste percentage is issued on top of the quantity, e.g. offcuts.',
                )
            "
        >
            <div class="-mx-2 custom-scrollbar overflow-x-auto px-2">
                <table class="w-full min-w-[720px]">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="w-[45%] th-cell">
                                {{ t('Material') }}
                            </th>
                            <th class="w-36 th-cell text-end">
                                {{ t('Quantity') }}
                            </th>
                            <th class="w-32 th-cell text-end">
                                {{ t('Waste %') }}
                            </th>
                            <th class="w-36 th-cell text-end">
                                {{ t('Issued quantity') }}
                            </th>
                            <th class="w-12 th-cell" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(component, position) in form.components"
                            :key="rowKey(component)"
                            class="align-top"
                        >
                            <td class="px-1 py-2">
                                <BaseCombobox
                                    v-model="component.item_id"
                                    :options="
                                        component.item ? [component.item] : []
                                    "
                                    :search="searchItems"
                                    :placeholder="t('Choose a material')"
                                    :invalid="
                                        Boolean(
                                            error(
                                                `components.${position}.item_id`,
                                            ),
                                        )
                                    "
                                    @select="
                                        (option) =>
                                            (component.item =
                                                option as ItemOption | null)
                                    "
                                />
                                <BaseInputError
                                    :message="
                                        error(`components.${position}.item_id`)
                                    "
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseInput
                                    v-model="component.quantity"
                                    type="number"
                                    step="any"
                                    min="0"
                                    dir="ltr"
                                    class="text-end"
                                    :invalid="
                                        Boolean(
                                            error(
                                                `components.${position}.quantity`,
                                            ),
                                        )
                                    "
                                />
                                <BaseInputError
                                    :message="
                                        error(`components.${position}.quantity`)
                                    "
                                />
                            </td>
                            <td class="px-1 py-2">
                                <BaseInput
                                    v-model="component.waste_percent"
                                    type="number"
                                    step="any"
                                    min="0"
                                    max="100"
                                    dir="ltr"
                                    class="text-end"
                                    placeholder="0"
                                    :invalid="
                                        Boolean(
                                            error(
                                                `components.${position}.waste_percent`,
                                            ),
                                        )
                                    "
                                />
                                <BaseInputError
                                    :message="
                                        error(
                                            `components.${position}.waste_percent`,
                                        )
                                    "
                                />
                            </td>
                            <td
                                class="px-3 py-4 text-end font-mono text-theme-sm text-gray-700 dark:text-gray-300"
                                dir="ltr"
                            >
                                {{ grossQuantity(component) }}
                                <span
                                    v-if="component.item?.unit"
                                    class="text-theme-xs text-gray-400"
                                >
                                    {{ component.item.unit }}
                                </span>
                            </td>
                            <td class="px-1 py-2">
                                <BaseIconButton
                                    v-if="form.components.length > 1"
                                    color="danger"
                                    :label="t('Remove line')"
                                    @click="form.components.splice(position, 1)"
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
                            <td class="px-1 py-3" colspan="5">
                                <BaseButton
                                    variant="ghost"
                                    size="sm"
                                    @click="
                                        form.components.push(emptyComponent())
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
            <BaseInputError :message="form.errors.components" />
        </BaseCard>

        <BaseCard
            class="col-span-12"
            :title="t('Other costs')"
            :description="
                t(
                    'Costs of one batch other than materials, e.g. labour, painting or power. They are added to the cost of the product.',
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
            <BaseButton variant="outline" :href="index()">
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton type="submit" :loading="form.processing">
                {{ bom ? t('Save changes') : t('Create recipe') }}
            </BaseButton>
        </div>
    </form>
</template>
