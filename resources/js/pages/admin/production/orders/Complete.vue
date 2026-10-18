<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import ProductionCostLines from '@/components/production/ProductionCostLines.vue';
import { useItemSearch } from '@/composables/useItemSearch';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { formatQuantity, sum } from '@/lib/production';
import { rowKey } from '@/lib/rowKey';
import { complete, index, show } from '@/routes/admin/production/orders';
import type {
    ComboboxOption,
    ItemOption,
    ProductionCost,
    ProductionCurrency,
    ProductionOrder,
    Resource,
} from '@/types';

const props = defineProps<{
    order: Resource<ProductionOrder>;
    accounts: ComboboxOption[];
    defaultCostAccountId: number | null;
    today: string;
    baseCurrency: ProductionCurrency;
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const order = computed(() => props.order.data);
const searchItems = useItemSearch('stock');

type MaterialInput = {
    id: number | null;
    item_id: number | null;
    consumed_quantity: string;
    /**
     * Issued when the order started; null for a material added now.
     */
    issued: { quantity: string; unit_cost: string; value: string } | null;
    item: ItemOption | null;
};

const form = useForm({
    date:
        props.order.data.started_on && props.today < props.order.data.started_on
            ? props.order.data.started_on
            : props.today,
    produced_quantity: props.order.data.quantity,
    materials: (props.order.data.materials ?? []).map((material) => ({
        id: material.id,
        item_id: material.item_id,
        consumed_quantity: material.quantity,
        issued: {
            quantity: material.quantity,
            unit_cost: material.unit_cost,
            value: material.issued_value,
        },
        item: material.item,
    })) as MaterialInput[],
    costs: (props.order.data.costs ?? []).map((cost) => ({
        description: cost.description,
        account_id: cost.account_id,
        amount: cost.amount,
    })) as ProductionCost[],
});

const amount = (value: number | string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

function difference(material: MaterialInput): number {
    return (
        (Number(material.consumed_quantity) || 0) -
        Number(material.issued?.quantity ?? 0)
    );
}

/**
 * The expected cost of what a line consumed. Extra quantities are priced
 * at the issue cost here; the server uses the warehouse's average.
 */
function lineValue(material: MaterialInput): number {
    const consumed = Number(material.consumed_quantity) || 0;
    const unitCost = Number(
        material.issued?.unit_cost ?? material.item?.purchase_price ?? 0,
    );

    return material.issued && consumed === Number(material.issued.quantity)
        ? Number(material.issued.value)
        : consumed * unitCost;
}

const materialsTotal = computed(() =>
    form.materials.reduce((total, material) => total + lineValue(material), 0),
);
const costsTotal = computed(() => sum(form.costs.map((cost) => cost.amount)));
const total = computed(() => materialsTotal.value + costsTotal.value);
const unitCost = computed(() =>
    Number(form.produced_quantity) > 0
        ? total.value / Number(form.produced_quantity)
        : 0,
);

function addMaterial(): void {
    form.materials.push({
        id: null,
        item_id: null,
        consumed_quantity: '',
        issued: null,
        item: null,
    });
}

function error(path: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[path];
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        materials: data.materials.map((material) => ({
            id: material.id,
            item_id: material.item_id,
            consumed_quantity: material.consumed_quantity,
        })),
    })).submit(complete({ order: order.value.id }), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="
                t('Complete production order :number', { number: order.number })
            "
            :breadcrumbs="[
                { title: t('Production Orders'), href: index() },
                { title: order.number, href: show({ order: order.id }) },
                { title: t('Complete production') },
            ]"
        />

        <form class="grid grid-cols-12 gap-6" @submit.prevent="submit">
            <div class="col-span-12 space-y-6 lg:col-span-8">
                <BaseAlert
                    v-if="error('accounting')"
                    variant="error"
                    :messages="[error('accounting') ?? '']"
                />

                <BaseCard :title="t('Production result')">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <BaseField
                            :label="t('Completion date')"
                            for="date"
                            required
                            :error="form.errors.date"
                        >
                            <BaseInput
                                id="date"
                                v-model="form.date"
                                type="date"
                                :min="order.started_on ?? undefined"
                                :invalid="Boolean(form.errors.date)"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Produced quantity')"
                            for="produced_quantity"
                            required
                            :hint="
                                t('Planned: :quantity', {
                                    quantity: `${formatQuantity(order.quantity, locale)} ${order.unit ?? ''}`,
                                })
                            "
                            :error="form.errors.produced_quantity"
                        >
                            <BaseInput
                                id="produced_quantity"
                                v-model="form.produced_quantity"
                                type="number"
                                step="any"
                                min="0"
                                dir="ltr"
                                :invalid="
                                    Boolean(form.errors.produced_quantity)
                                "
                            />
                        </BaseField>
                    </div>
                    <p
                        class="mt-4 text-theme-sm text-gray-500 dark:text-gray-400"
                    >
                        {{
                            t(':item will be added to :warehouse.', {
                                item: order.item ?? '',
                                warehouse: order.product_warehouse ?? '',
                            })
                        }}
                    </p>
                </BaseCard>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <BaseCard :title="t('Expected cost')">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Materials') }}
                            </dt>
                            <dd class="font-mono" dir="ltr">
                                {{ amount(materialsTotal) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">
                                {{ t('Other costs') }}
                            </dt>
                            <dd class="font-mono" dir="ltr">
                                {{ amount(costsTotal) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-4 border-t border-gray-100 pt-3 font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90"
                        >
                            <dt>{{ t('Total cost') }}</dt>
                            <dd class="font-mono" dir="ltr">
                                {{ amount(total) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-4 font-semibold text-brand-500"
                        >
                            <dt>
                                {{ t('Unit cost') }} ({{ baseCurrency.code }})
                            </dt>
                            <dd class="font-mono" dir="ltr">
                                {{ amount(unitCost) }}
                            </dd>
                        </div>
                    </dl>
                </BaseCard>
            </div>

            <BaseCard
                class="col-span-12"
                :title="t('Materials used')"
                :description="
                    t(
                        'Enter what was really used. More than issued is taken from the warehouse now; less is returned to it.',
                    )
                "
            >
                <div class="-mx-2 custom-scrollbar overflow-x-auto px-2">
                    <table class="w-full min-w-[760px]">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="w-[40%] th-cell">
                                    {{ t('Material') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Issued quantity') }}
                                </th>
                                <th class="w-40 th-cell text-end">
                                    {{ t('Quantity used') }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Difference') }}
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
                                    <p
                                        v-if="material.issued"
                                        class="py-2.5 text-theme-sm text-gray-800 dark:text-white/90"
                                    >
                                        {{ material.item?.label ?? '—' }}
                                    </p>
                                    <template v-else>
                                        <BaseCombobox
                                            v-model="material.item_id"
                                            :options="
                                                material.item
                                                    ? [material.item]
                                                    : []
                                            "
                                            :search="searchItems"
                                            :placeholder="
                                                t('Choose a material')
                                            "
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
                                                error(
                                                    `materials.${position}.item_id`,
                                                )
                                            "
                                        />
                                    </template>
                                </td>
                                <td
                                    class="px-3 py-4 text-end font-mono text-theme-sm text-gray-500 dark:text-gray-400"
                                    dir="ltr"
                                >
                                    {{
                                        material.issued
                                            ? formatQuantity(
                                                  material.issued.quantity,
                                                  locale,
                                              )
                                            : t('Added')
                                    }}
                                </td>
                                <td class="px-1 py-2">
                                    <BaseInput
                                        v-model="material.consumed_quantity"
                                        type="number"
                                        step="any"
                                        min="0"
                                        dir="ltr"
                                        class="text-end"
                                        :invalid="
                                            Boolean(
                                                error(
                                                    `materials.${position}.consumed_quantity`,
                                                ),
                                            )
                                        "
                                    />
                                    <BaseInputError
                                        :message="
                                            error(
                                                `materials.${position}.consumed_quantity`,
                                            )
                                        "
                                    />
                                </td>
                                <td
                                    class="px-3 py-4 text-end font-mono text-theme-sm"
                                    :class="
                                        difference(material) > 0
                                            ? 'text-error-600 dark:text-error-500'
                                            : difference(material) < 0
                                              ? 'text-success-600 dark:text-success-500'
                                              : 'text-gray-400'
                                    "
                                    dir="ltr"
                                >
                                    <template v-if="material.issued">
                                        {{ difference(material) > 0 ? '+' : ''
                                        }}{{
                                            formatQuantity(
                                                difference(material),
                                                locale,
                                            )
                                        }}
                                    </template>
                                </td>
                                <td class="px-1 py-2">
                                    <BaseIconButton
                                        v-if="!material.issued"
                                        color="danger"
                                        :label="t('Remove line')"
                                        @click="
                                            form.materials.splice(position, 1)
                                        "
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
                                        @click="addMaterial"
                                    >
                                        <Plus class="size-4" />
                                        {{ t('Add a material that was used') }}
                                    </BaseButton>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <BaseInputError :message="form.errors.materials" />
            </BaseCard>

            <BaseCard
                class="col-span-12"
                :title="t('Other costs')"
                :description="
                    t(
                        'The final costs other than materials. Each is credited to its account and added to the cost of the product.',
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
                <BaseButton variant="outline" :href="show({ order: order.id })">
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton type="submit" :loading="form.processing">
                    {{ t('Complete production') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
