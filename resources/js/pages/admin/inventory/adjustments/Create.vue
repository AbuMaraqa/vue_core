<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useItemSearch } from '@/composables/useItemSearch';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { rowKey } from '@/lib/rowKey';
import { index, store } from '@/routes/admin/inventory/adjustments';
import type { ComboboxOption, ItemOption } from '@/types';

const props = defineProps<{
    warehouses: ComboboxOption[];
    accounts: ComboboxOption[];
    defaults: {
        date: string;
        warehouse_id: number | null;
        account_id: number | null;
        opening_account_id: number | null;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const searchItems = useItemSearch('stock');

type LineInput = {
    item_id: number | null;
    quantity: string;
    unit_cost: string;
    /**
     * The chosen item, kept for its label and stock; not sent.
     */
    item: ItemOption | null;
};

function emptyLine(): LineInput {
    return { item_id: null, quantity: '', unit_cost: '', item: null };
}

const form = useForm({
    date: props.defaults.date,
    warehouse_id: props.defaults.warehouse_id,
    account_id: props.defaults.account_id,
    description: '',
    lines: [emptyLine()] as LineInput[],
});

function chooseItem(line: LineInput, option: ComboboxOption | null): void {
    line.item = option as ItemOption | null;
}

function setQuantity(
    line: LineInput,
    value: string | number | null | undefined,
): void {
    line.quantity = String(value ?? '');

    if (Number(line.quantity) < 0) {
        line.unit_cost = '';
    }
}

function lineError(position: number, field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `lines.${position}.${field}`
    ];
}

const quantity = (value: string): string =>
    formatDecimal(value, locale.value, /\.\d{2}\d*[1-9]/.test(value) ? 4 : 2);

function submit(): void {
    form.transform((data) => ({
        ...data,
        lines: data.lines.map((line) => ({
            item_id: line.item_id,
            quantity: line.quantity,
            unit_cost:
                Number(line.quantity) > 0 ? line.unit_cost || null : null,
        })),
    })).submit(store(), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('New Stock Adjustment')"
            :breadcrumbs="[
                { title: t('Stock Adjustments'), href: index() },
                { title: t('New adjustment') },
            ]"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <BaseCard :title="t('Adjustment details')">
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
                        :label="t('Adjustment account')"
                        :hint="
                            t(
                                'The other side of the entry, e.g. inventory differences or opening balances.',
                            )
                        "
                        :error="form.errors.account_id"
                    >
                        <BaseCombobox
                            v-model="form.account_id"
                            :options="accounts"
                            clearable
                            :placeholder="t('Default account')"
                            :invalid="Boolean(form.errors.account_id)"
                        />
                        <div
                            v-if="defaults.opening_account_id"
                            class="mt-2 flex flex-wrap gap-2"
                        >
                            <BaseButton
                                size="sm"
                                :variant="
                                    form.account_id ===
                                    defaults.opening_account_id
                                        ? 'primary'
                                        : 'outline'
                                "
                                @click="
                                    form.account_id =
                                        defaults.opening_account_id
                                "
                            >
                                {{ t('Opening stock') }}
                            </BaseButton>
                            <BaseButton
                                v-if="
                                    defaults.account_id &&
                                    form.account_id !== defaults.account_id
                                "
                                size="sm"
                                variant="ghost"
                                @click="form.account_id = defaults.account_id"
                            >
                                {{ t('Inventory adjustment') }}
                            </BaseButton>
                        </div>
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
                            :placeholder="t('e.g. Year-end stock count')"
                        />
                    </BaseField>
                </div>
            </BaseCard>

            <BaseCard
                :title="t('Items')"
                :description="
                    t(
                        'Enter a positive quantity to add stock and a negative one to remove it. Removed stock is valued at its average cost.',
                    )
                "
            >
                <div class="-mx-2 custom-scrollbar overflow-x-auto px-2">
                    <table class="w-full min-w-[720px]">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="w-[45%] th-cell">{{ t('Item') }}</th>
                                <th class="w-40 th-cell text-end">
                                    {{ t('Quantity') }}
                                </th>
                                <th class="w-40 th-cell text-end">
                                    {{ t('Unit cost') }}
                                </th>
                                <th class="w-12 th-cell" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(line, position) in form.lines"
                                :key="rowKey(line)"
                                class="align-top"
                            >
                                <td class="px-1 py-2">
                                    <BaseCombobox
                                        v-model="line.item_id"
                                        :options="line.item ? [line.item] : []"
                                        :search="searchItems"
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
                                    <p
                                        v-if="line.item?.on_hand != null"
                                        class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ t('On hand') }}:
                                        <span class="font-mono" dir="ltr">
                                            {{ quantity(line.item.on_hand) }}
                                        </span>
                                        {{ line.item.unit ?? '' }}
                                    </p>
                                    <BaseInputError
                                        :message="
                                            lineError(position, 'item_id')
                                        "
                                    />
                                </td>
                                <td class="px-1 py-2">
                                    <BaseInput
                                        :model-value="line.quantity"
                                        type="number"
                                        step="any"
                                        dir="ltr"
                                        class="text-end"
                                        placeholder="+ / −"
                                        :invalid="
                                            Boolean(
                                                lineError(position, 'quantity'),
                                            )
                                        "
                                        @update:model-value="
                                            (value) => setQuantity(line, value)
                                        "
                                    />
                                    <BaseInputError
                                        :message="
                                            lineError(position, 'quantity')
                                        "
                                    />
                                </td>
                                <td class="px-1 py-2">
                                    <BaseInput
                                        v-model="line.unit_cost"
                                        type="number"
                                        step="any"
                                        min="0"
                                        dir="ltr"
                                        class="text-end"
                                        :placeholder="t('Average cost')"
                                        :disabled="Number(line.quantity) < 0"
                                        :invalid="
                                            Boolean(
                                                lineError(
                                                    position,
                                                    'unit_cost',
                                                ),
                                            )
                                        "
                                    />
                                    <BaseInputError
                                        :message="
                                            lineError(position, 'unit_cost')
                                        "
                                    />
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
                        <tfoot>
                            <tr
                                class="border-t border-gray-100 dark:border-gray-800"
                            >
                                <td class="px-1 py-3" colspan="4">
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        @click="form.lines.push(emptyLine())"
                                    >
                                        <Plus class="size-4" />
                                        {{ t('Add line') }}
                                    </BaseButton>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400">
                    {{
                        t(
                            'Leave the unit cost empty to use the current average cost, or the purchase price when there is no stock.',
                        )
                    }}
                </p>
                <BaseInputError :message="form.errors.lines" />
            </BaseCard>

            <div class="flex justify-end gap-3">
                <BaseButton variant="outline" :href="index()">
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton type="submit" :loading="form.processing">
                    {{ t('Post adjustment') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
