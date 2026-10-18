<script setup lang="ts">
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { sum } from '@/lib/production';
import { rowKey } from '@/lib/rowKey';
import type { ComboboxOption, ProductionCost } from '@/types';

/**
 * The other costs of a recipe or an order (labour, painting, power...): a
 * description, the account credited and an amount in the base currency.
 */
const props = defineProps<{
    accounts: ComboboxOption[];
    defaultAccountId: number | null;
    currency: { code: string; decimal_places: number };
    errors: Record<string, string | undefined>;
}>();

const costs = defineModel<ProductionCost[]>({ required: true });

const { t } = useTranslations();
const { locale } = useLocale();

const total = computed(() => sum(costs.value.map((cost) => cost.amount)));

function add(): void {
    costs.value.push({
        description: '',
        account_id: props.defaultAccountId,
        amount: '',
    });
}

function error(position: number, field: string): string | undefined {
    return props.errors[`costs.${position}.${field}`];
}
</script>

<template>
    <div>
        <div class="-mx-2 custom-scrollbar overflow-x-auto px-2">
            <table class="w-full min-w-[640px]">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="th-cell">{{ t('Description') }}</th>
                        <th class="w-[35%] th-cell">{{ t('Account') }}</th>
                        <th class="w-40 th-cell text-end">
                            {{ t('Amount') }}
                            <span class="text-theme-xs font-normal">
                                ({{ currency.code }})
                            </span>
                        </th>
                        <th class="w-12 th-cell" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(cost, position) in costs"
                        :key="rowKey(cost)"
                        class="align-top"
                    >
                        <td class="px-1 py-2">
                            <BaseInput
                                v-model="cost.description"
                                :placeholder="t('e.g. Carpentry labour')"
                                :invalid="
                                    Boolean(error(position, 'description'))
                                "
                            />
                            <BaseInputError
                                :message="error(position, 'description')"
                            />
                        </td>
                        <td class="px-1 py-2">
                            <BaseCombobox
                                v-model="cost.account_id"
                                :options="accounts"
                                :placeholder="t('Choose an account')"
                                :invalid="
                                    Boolean(error(position, 'account_id'))
                                "
                            />
                            <BaseInputError
                                :message="error(position, 'account_id')"
                            />
                        </td>
                        <td class="px-1 py-2">
                            <BaseInput
                                v-model="cost.amount"
                                type="number"
                                step="any"
                                min="0"
                                dir="ltr"
                                class="text-end"
                                :invalid="Boolean(error(position, 'amount'))"
                            />
                            <BaseInputError
                                :message="error(position, 'amount')"
                            />
                        </td>
                        <td class="px-1 py-2">
                            <BaseIconButton
                                color="danger"
                                :label="t('Remove line')"
                                @click="costs.splice(position, 1)"
                            >
                                <Trash2 />
                            </BaseIconButton>
                        </td>
                    </tr>
                    <tr v-if="!costs.length">
                        <td
                            colspan="4"
                            class="px-1 py-3 text-theme-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ t('No other costs.') }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-1 py-3" colspan="2">
                            <BaseButton variant="ghost" size="sm" @click="add">
                                <Plus class="size-4" />
                                {{ t('Add cost') }}
                            </BaseButton>
                        </td>
                        <td
                            class="px-3 py-3 text-end font-mono font-semibold text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{
                                formatDecimal(
                                    total,
                                    locale,
                                    currency.decimal_places,
                                )
                            }}
                        </td>
                        <td />
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
