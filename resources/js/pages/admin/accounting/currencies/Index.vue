<script setup lang="ts">
import { router, useForm, useHttp } from '@inertiajs/vue3';
import Chart from '~icons/solar/chart-2-linear';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import BaseTranslatableInput from '@/components/base/BaseTranslatableInput.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/format';
import { destroy, store, update } from '@/routes/admin/accounting/currencies';
import * as ratesRoutes from '@/routes/admin/accounting/currencies/rates';
import type { Translations } from '@/types';

type CurrencyRow = {
    id: number;
    code: string;
    name: string;
    name_translations: Translations;
    symbol: string | null;
    decimal_places: number;
    is_active: boolean;
    is_base: boolean;
    latest_rate: { rate: string; effective_date: string } | null;
};

type Rate = { id: number; effective_date: string; rate: string };

defineProps<{
    currencies: CurrencyRow[];
    baseCode: string;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

/*
 * Currency dialog.
 */
const editing = ref<CurrencyRow | null>(null);
const isFormOpen = ref(false);
const form = useForm({
    code: '',
    name: {} as Translations,
    symbol: '',
    decimal_places: 2,
    is_active: true,
});

function openForm(currency: CurrencyRow | null = null): void {
    editing.value = currency;
    form.clearErrors();
    form.code = currency?.code ?? '';
    form.name = { ...currency?.name_translations };
    form.symbol = currency?.symbol ?? '';
    form.decimal_places = currency?.decimal_places ?? 2;
    form.is_active = currency?.is_active ?? true;
    isFormOpen.value = true;
}

function submit(): void {
    form.submit(
        editing.value ? update({ currency: editing.value.id }) : store(),
        {
            preserveScroll: true,
            onSuccess: () => (isFormOpen.value = false),
        },
    );
}

const deletion = useConfirmDelete((currency: CurrencyRow) =>
    destroy({ currency: currency.id }),
);

/*
 * Exchange rates dialog.
 */
const ratesFor = ref<CurrencyRow | null>(null);
const rateHistory = ref<Rate[]>([]);
const historyRequest = useHttp<Record<string, never>, Rate[]>({});
const rateForm = useForm({
    effective_date: new Date().toISOString().slice(0, 10),
    rate: '',
});

async function openRates(currency: CurrencyRow): Promise<void> {
    ratesFor.value = currency;
    rateForm.reset();
    rateForm.clearErrors();
    rateHistory.value = await historyRequest.get(
        ratesRoutes.index.url({ currency: currency.id }),
    );
}

function saveRate(): void {
    if (!ratesFor.value) {
        return;
    }

    const currency = ratesFor.value;

    rateForm.submit(ratesRoutes.store({ currency: currency.id }), {
        preserveScroll: true,
        onSuccess: () => {
            rateForm.reset('rate');
            void openRates(currency);
        },
    });
}

function deleteRate(rate: Rate): void {
    if (!ratesFor.value) {
        return;
    }

    const currency = ratesFor.value;

    router.visit(
        ratesRoutes.destroy({ currency: currency.id, rate: rate.id }),
        {
            preserveScroll: true,
            onSuccess: () => void openRates(currency),
        },
    );
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Currencies')"
            :description="
                t('Exchange rates say how much one unit is worth in :base.', {
                    base: baseCode,
                })
            "
            :breadcrumbs="[{ title: t('Currencies') }]"
        >
            <template #actions>
                <BaseButton v-if="can('Currency Create')" @click="openForm()">
                    <Plus class="size-5" />
                    {{ t('Add currency') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Currency') }}</th>
                            <th class="th-cell">{{ t('Symbol') }}</th>
                            <th class="th-cell">{{ t('Decimals') }}</th>
                            <th class="th-cell">{{ t('Latest rate') }}</th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="currency in currencies"
                            :key="currency.id"
                            :class="{ 'opacity-60': !currency.is_active }"
                        >
                            <td class="td-cell">
                                <span
                                    class="font-mono font-medium text-gray-800 dark:text-white/90"
                                    >{{ currency.code }}</span
                                >
                                {{ currency.name }}
                                <BaseBadge
                                    v-if="currency.is_base"
                                    color="primary"
                                    class="ms-2"
                                    >{{ t('Base currency') }}</BaseBadge
                                >
                                <BaseBadge
                                    v-else-if="!currency.is_active"
                                    color="light"
                                    class="ms-2"
                                    >{{ t('Inactive') }}</BaseBadge
                                >
                            </td>
                            <td class="td-cell">
                                {{ currency.symbol ?? '—' }}
                            </td>
                            <td class="td-cell">
                                {{ currency.decimal_places }}
                            </td>
                            <td class="td-cell font-mono" dir="ltr">
                                <template v-if="currency.is_base">1</template>
                                <template v-else-if="currency.latest_rate">
                                    {{ currency.latest_rate.rate }}
                                    <span class="text-theme-xs text-gray-400"
                                        >({{
                                            formatDate(
                                                currency.latest_rate
                                                    .effective_date,
                                                locale,
                                            )
                                        }})</span
                                    >
                                </template>
                                <span v-else class="text-warning-500">{{
                                    t('No rate yet')
                                }}</span>
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="
                                            !currency.is_base &&
                                            can('Currency Update')
                                        "
                                        :label="t('Exchange rates')"
                                        @click="openRates(currency)"
                                    >
                                        <Chart />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="can('Currency Update')"
                                        :label="t('Edit')"
                                        @click="openForm(currency)"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="
                                            !currency.is_base &&
                                            can('Currency Delete')
                                        "
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(currency)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isFormOpen"
            :title="editing ? t('Edit currency') : t('Add currency')"
            max-width="lg"
        >
            <form id="currency-form" class="space-y-5" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <BaseField
                        :label="t('Code')"
                        for="code"
                        required
                        :error="form.errors.code"
                        :hint="t('ISO code, e.g. USD')"
                    >
                        <BaseInput
                            id="code"
                            v-model="form.code"
                            maxlength="3"
                            dir="ltr"
                            class="uppercase"
                            :invalid="Boolean(form.errors.code)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Symbol')"
                        for="symbol"
                        :error="form.errors.symbol"
                    >
                        <BaseInput id="symbol" v-model="form.symbol" />
                    </BaseField>
                    <BaseField
                        :label="t('Decimals')"
                        for="decimal_places"
                        required
                        :error="form.errors.decimal_places"
                    >
                        <BaseInput
                            id="decimal_places"
                            v-model="form.decimal_places"
                            type="number"
                            min="0"
                            max="4"
                        />
                    </BaseField>
                </div>
                <BaseTranslatableInput
                    v-model="form.name"
                    :label="t('Name')"
                    field="name"
                    :errors="form.errors"
                    required
                />
                <BaseCheckbox
                    v-if="!editing?.is_base"
                    v-model="form.is_active"
                    >{{ t('Active') }}</BaseCheckbox
                >
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isFormOpen = false"
                    >{{ t('Cancel') }}</BaseButton
                >
                <BaseButton
                    type="submit"
                    form="currency-form"
                    size="sm"
                    :loading="form.processing"
                    >{{ t('Save') }}</BaseButton
                >
            </template>
        </BaseModal>

        <BaseModal
            :open="ratesFor !== null"
            :title="
                t('Exchange rates of :code', { code: ratesFor?.code ?? '' })
            "
            max-width="md"
            @update:open="(open) => !open && (ratesFor = null)"
        >
            <form
                class="mb-5 grid grid-cols-1 items-end gap-3 sm:grid-cols-[1fr_1fr_auto]"
                @submit.prevent="saveRate"
            >
                <BaseField
                    :label="t('Date')"
                    for="rate-date"
                    :error="rateForm.errors.effective_date"
                >
                    <BaseInput
                        id="rate-date"
                        v-model="rateForm.effective_date"
                        type="date"
                    />
                </BaseField>
                <BaseField
                    :label="
                        t('1 :code = ? :base', {
                            code: ratesFor?.code ?? '',
                            base: baseCode,
                        })
                    "
                    for="rate"
                    :error="rateForm.errors.rate"
                >
                    <BaseInput
                        id="rate"
                        v-model="rateForm.rate"
                        type="number"
                        step="any"
                        min="0"
                        dir="ltr"
                    />
                </BaseField>
                <BaseButton type="submit" :loading="rateForm.processing">{{
                    t('Save')
                }}</BaseButton>
            </form>

            <ul
                class="custom-scrollbar max-h-72 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800"
            >
                <li
                    v-for="rate in rateHistory"
                    :key="rate.id"
                    class="flex items-center justify-between py-2 text-sm"
                >
                    <span class="text-gray-600 dark:text-gray-400">{{
                        formatDate(rate.effective_date, locale)
                    }}</span>
                    <span class="flex items-center gap-2">
                        <span
                            class="font-mono text-gray-800 dark:text-white/90"
                            dir="ltr"
                            >{{ rate.rate }}</span
                        >
                        <BaseIconButton
                            color="danger"
                            :label="t('Delete')"
                            @click="deleteRate(rate)"
                        >
                            <Trash2 />
                        </BaseIconButton>
                    </span>
                </li>
                <li
                    v-if="!rateHistory.length"
                    class="py-4 text-center text-sm text-gray-500"
                >
                    {{ t('No rates yet.') }}
                </li>
            </ul>
        </BaseModal>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete currency')"
            :message="
                t(
                    'Are you sure you want to delete :name? This action cannot be undone.',
                    { name: deletion.target?.code ?? '' },
                )
            "
            :processing="deletion.processing"
            @update:open="(open) => !open && deletion.cancel()"
            @confirm="deletion.confirm"
        />
    </div>
</template>
