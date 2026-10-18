<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useTranslations } from '@/composables/useTranslations';
import { baseCurrency, update } from '@/routes/admin/accounting/settings';
import type { ComboboxOption, SelectOption } from '@/types';

type CurrencyOption = {
    value: number;
    label: string;
    code: string;
    suggested_rate: string | null;
};

const props = defineProps<{
    settings: {
        base_currency: string;
        lock_date: string | null;
        default_accounts: Record<string, number>;
        number_prefixes: Record<string, string>;
        company_tax_number: string | null;
        company_address: string | null;
        prices_include_tax: boolean;
        allow_negative_stock: boolean;
    };
    roles: SelectOption[];
    documentTypes: Array<{
        value: string;
        label: string;
        default_prefix: string;
    }>;
    accounts: ComboboxOption[];
    currencies: CurrencyOption[];
    hasEntries: boolean;
    canUpdate: boolean;
}>();

const { t } = useTranslations();

const form = useForm({
    lock_date: props.settings.lock_date ?? '',
    default_accounts: Object.fromEntries(
        props.roles.map((role) => [
            role.value,
            props.settings.default_accounts[String(role.value)] ?? null,
        ]),
    ) as Record<string, number | null>,
    number_prefixes: Object.fromEntries(
        props.documentTypes.map((type) => [
            type.value,
            props.settings.number_prefixes[type.value] ?? '',
        ]),
    ) as Record<string, string>,
    company_tax_number: props.settings.company_tax_number ?? '',
    company_address: props.settings.company_address ?? '',
    prices_include_tax: props.settings.prices_include_tax,
    allow_negative_stock: props.settings.allow_negative_stock,
});

function submit(): void {
    form.submit(update(), { preserveScroll: true });
}

function error(field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[field];
}

/*
 * Change base currency dialog.
 */
const isChangingBase = ref(false);
const baseForm = useForm({
    currency_id: null as number | null,
    rate: '',
});

const baseFormError = computed(
    () =>
        (baseForm.errors as Record<string, string | undefined>).accounting,
);

const newBase = computed(() =>
    props.currencies.find((currency) => currency.value === baseForm.currency_id),
);

function openChangeBase(): void {
    baseForm.reset();
    baseForm.clearErrors();
    isChangingBase.value = true;
}

function pickNewBase(): void {
    baseForm.rate = newBase.value?.suggested_rate ?? '';
}

function changeBase(): void {
    baseForm.submit(baseCurrency(), {
        preserveScroll: true,
        onSuccess: () => (isChangingBase.value = false),
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Accounting Settings')"
            :breadcrumbs="[{ title: t('Accounting Settings') }]"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <fieldset :disabled="!canUpdate" class="space-y-6">
                <BaseCard :title="t('General')">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <BaseField
                            :label="t('Base currency')"
                            for="base_currency"
                            :hint="t('The currency the books are kept in.')"
                        >
                            <div class="flex gap-3">
                                <BaseInput
                                    id="base_currency"
                                    :model-value="settings.base_currency"
                                    dir="ltr"
                                    readonly
                                />
                                <BaseButton
                                    v-if="canUpdate && currencies.length > 0"
                                    type="button"
                                    variant="outline"
                                    class="shrink-0"
                                    @click="openChangeBase"
                                    >{{ t('Change') }}</BaseButton
                                >
                            </div>
                        </BaseField>
                        <BaseField
                            :label="t('Lock date')"
                            for="lock_date"
                            :error="form.errors.lock_date"
                            :hint="
                                t(
                                    'Nothing dated on or before this day can be posted or changed.',
                                )
                            "
                        >
                            <BaseInput
                                id="lock_date"
                                v-model="form.lock_date"
                                type="date"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Company tax number')"
                            for="company_tax_number"
                            :error="form.errors.company_tax_number"
                        >
                            <BaseInput
                                id="company_tax_number"
                                v-model="form.company_tax_number"
                                dir="ltr"
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Company address')"
                            for="company_address"
                            :error="form.errors.company_address"
                        >
                            <BaseTextarea
                                id="company_address"
                                v-model="form.company_address"
                                rows="2"
                            />
                        </BaseField>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-6">
                        <BaseCheckbox v-model="form.prices_include_tax">{{
                            t('Prices include tax by default')
                        }}</BaseCheckbox>
                        <BaseCheckbox v-model="form.allow_negative_stock">{{
                            t('Allow selling more than the quantity in stock')
                        }}</BaseCheckbox>
                    </div>
                </BaseCard>

                <BaseCard
                    :title="t('Default accounts')"
                    :description="
                        t(
                            'The accounts documents post to automatically. Every one must be set before documents can be posted.',
                        )
                    "
                >
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <BaseField
                            v-for="role in roles"
                            :key="role.value"
                            :label="role.label"
                            :error="error(`default_accounts.${role.value}`)"
                        >
                            <BaseCombobox
                                v-model="form.default_accounts[role.value]"
                                :options="accounts"
                                clearable
                            />
                        </BaseField>
                    </div>
                </BaseCard>

                <BaseCard
                    :title="t('Document numbers')"
                    :description="
                        t(
                            'Numbers look like PREFIX-2026-00001 and restart every year.',
                        )
                    "
                >
                    <div
                        class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <BaseField
                            v-for="type in documentTypes"
                            :key="type.value"
                            :label="type.label"
                            :error="error(`number_prefixes.${type.value}`)"
                        >
                            <BaseInput
                                v-model="form.number_prefixes[type.value]"
                                :placeholder="type.default_prefix"
                                dir="ltr"
                                class="uppercase"
                            />
                        </BaseField>
                    </div>
                </BaseCard>
            </fieldset>

            <div v-if="canUpdate" class="flex justify-end">
                <BaseButton type="submit" :loading="form.processing">{{
                    t('Save changes')
                }}</BaseButton>
            </div>
        </form>

        <BaseModal
            v-model:open="isChangingBase"
            :title="t('Change base currency')"
            max-width="lg"
        >
            <form
                id="base-currency-form"
                class="space-y-5"
                @submit.prevent="changeBase"
            >
                <BaseAlert
                    variant="warning"
                    :title="
                        hasEntries
                            ? t(
                                  'Every amount already recorded will be converted to the new currency.',
                              )
                            : t('Prices and salaries will be converted too.')
                    "
                    :messages="[
                        t(
                            ':code becomes a foreign currency at this rate, and the rates of the other currencies are worked out again.',
                            { code: settings.base_currency },
                        ),
                        t('Take a backup first. This cannot be undone.'),
                    ]"
                />
                <BaseAlert
                    v-if="baseFormError"
                    variant="error"
                    :title="baseFormError"
                />
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <BaseField
                        :label="t('New base currency')"
                        for="new_base_currency"
                        required
                        :error="baseForm.errors.currency_id"
                    >
                        <BaseSelect
                            id="new_base_currency"
                            v-model="baseForm.currency_id"
                            :invalid="Boolean(baseForm.errors.currency_id)"
                            @change="pickNewBase"
                        >
                            <option :value="null" disabled>
                                {{ t('Select...') }}
                            </option>
                            <option
                                v-for="currency in currencies"
                                :key="currency.value"
                                :value="currency.value"
                            >
                                {{ currency.label }}
                            </option>
                        </BaseSelect>
                    </BaseField>
                    <BaseField
                        :label="
                            t('1 :from equals', {
                                from: settings.base_currency,
                            })
                        "
                        for="base_rate"
                        required
                        :error="baseForm.errors.rate"
                        :hint="newBase?.code"
                    >
                        <BaseInput
                            id="base_rate"
                            v-model="baseForm.rate"
                            type="number"
                            step="0.00000001"
                            min="0"
                            dir="ltr"
                            :invalid="Boolean(baseForm.errors.rate)"
                        />
                    </BaseField>
                </div>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isChangingBase = false"
                    >{{ t('Cancel') }}</BaseButton
                >
                <BaseButton
                    type="submit"
                    form="base-currency-form"
                    size="sm"
                    :loading="baseForm.processing"
                    >{{ t('Change base currency') }}</BaseButton
                >
            </template>
        </BaseModal>
    </div>
</template>
