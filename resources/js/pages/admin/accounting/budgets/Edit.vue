<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed, ref } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { rowKey } from '@/lib/rowKey';
import { index, store, update } from '@/routes/admin/accounting/budgets';
import type { ComboboxOption } from '@/types';

type Line = { account_id: number | null; months: string[] };

const props = defineProps<{
    budget?: {
        id: number;
        name: string;
        fiscal_year_id: number;
        cost_center_id: number | null;
        notes: string | null;
        lines: Line[];
    };
    accounts: ComboboxOption[];
    fiscalYears: ComboboxOption[];
    costCenters: ComboboxOption[];
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const emptyLine = (): Line => ({
    account_id: null,
    months: Array.from({ length: 12 }, () => ''),
});

const form = useForm({
    name: props.budget?.name ?? '',
    fiscal_year_id:
        props.budget?.fiscal_year_id ?? props.fiscalYears[0]?.value ?? null,
    cost_center_id: props.budget?.cost_center_id ?? null,
    notes: props.budget?.notes ?? '',
    lines: props.budget?.lines.map((line) => ({
        ...line,
        months: [...line.months],
    })) ?? [emptyLine()],
});

const annual = ref<Record<number, string>>({});

/**
 * Split an annual amount evenly over the 12 months; cents go to the last month.
 */
function spread(position: number): void {
    const total = Number(annual.value[position] ?? 0);
    const monthly = Math.floor((total / 12) * 100) / 100;
    const months = Array.from({ length: 12 }, () => monthly.toFixed(2));

    months[11] = (total - monthly * 11).toFixed(2);
    form.lines[position].months = months;
}

const lineTotal = (line: Line): number =>
    line.months.reduce((sum, value) => sum + Number(value || 0), 0);
const grandTotal = computed(() =>
    form.lines.reduce((sum, line) => sum + lineTotal(line), 0),
);

function submit(): void {
    form.submit(props.budget ? update({ budget: props.budget.id }) : store(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="budget ? t('Edit budget') : t('New budget')"
            :breadcrumbs="[
                { title: t('Budgets'), href: index() },
                { title: budget?.name ?? t('New budget') },
            ]"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <BaseCard>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
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
                        :label="t('Fiscal year')"
                        for="fiscal_year_id"
                        required
                        :error="form.errors.fiscal_year_id"
                    >
                        <BaseSelect
                            id="fiscal_year_id"
                            v-model="form.fiscal_year_id"
                        >
                            <option
                                v-for="year in fiscalYears"
                                :key="year.value"
                                :value="year.value"
                            >
                                {{ year.label }}
                            </option>
                        </BaseSelect>
                    </BaseField>
                    <BaseField
                        :label="t('Cost center')"
                        :error="form.errors.cost_center_id"
                        :hint="t('Leave empty for the whole company.')"
                    >
                        <BaseCombobox
                            v-model="form.cost_center_id"
                            :options="costCenters"
                            clearable
                        />
                    </BaseField>
                </div>
            </BaseCard>

            <BaseCard
                :title="t('Planned amounts')"
                :description="
                    t('Revenue and expense accounts, month by month.')
                "
            >
                <div class="custom-scrollbar overflow-x-auto">
                    <table class="w-full min-w-[1400px]">
                        <thead>
                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <th class="w-72 th-cell">{{ t('Account') }}</th>
                                <th class="w-40 th-cell">
                                    {{ t('Annual amount') }}
                                </th>
                                <th
                                    v-for="month in 12"
                                    :key="month"
                                    class="th-cell text-end"
                                >
                                    {{ month }}
                                </th>
                                <th class="th-cell text-end">
                                    {{ t('Total') }}
                                </th>
                                <th class="th-cell" />
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
                                        v-model="line.account_id"
                                        :options="accounts"
                                        :placeholder="t('Choose an account')"
                                    />
                                    <BaseInputError
                                        :message="
                                            (
                                                form.errors as Record<
                                                    string,
                                                    string
                                                >
                                            )[`lines.${position}.account_id`]
                                        "
                                    />
                                </td>
                                <td class="px-1 py-2">
                                    <div class="flex gap-1">
                                        <BaseInput
                                            v-model="annual[position]"
                                            type="number"
                                            min="0"
                                            step="any"
                                            dir="ltr"
                                        />
                                        <BaseButton
                                            variant="outline"
                                            size="sm"
                                            :title="
                                                t(
                                                    'Spread evenly over the months',
                                                )
                                            "
                                            @click="spread(position)"
                                            >÷</BaseButton
                                        >
                                    </div>
                                </td>
                                <td
                                    v-for="(_, month) in line.months"
                                    :key="month"
                                    class="px-1 py-2"
                                >
                                    <BaseInput
                                        v-model="line.months[month]"
                                        type="number"
                                        min="0"
                                        step="any"
                                        dir="ltr"
                                        class="px-2 text-end"
                                    />
                                </td>
                                <td
                                    class="px-3 py-4 text-end font-mono text-theme-sm"
                                    dir="ltr"
                                >
                                    {{ formatDecimal(lineTotal(line), locale) }}
                                </td>
                                <td class="px-1 py-2">
                                    <BaseIconButton
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
                                <td class="px-1 py-3" colspan="14">
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        @click="form.lines.push(emptyLine())"
                                    >
                                        <Plus class="size-4" />
                                        {{ t('Add account') }}
                                    </BaseButton>
                                </td>
                                <td
                                    class="px-3 py-3 text-end font-mono font-semibold"
                                    dir="ltr"
                                >
                                    {{ formatDecimal(grandTotal, locale) }}
                                </td>
                                <td />
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <BaseInputError :message="form.errors.lines" />
            </BaseCard>

            <div class="flex justify-end gap-3">
                <BaseButton variant="outline" :href="index()">{{
                    t('Cancel')
                }}</BaseButton>
                <BaseButton type="submit" :loading="form.processing">{{
                    t('Save')
                }}</BaseButton>
            </div>
        </form>
    </div>
</template>
