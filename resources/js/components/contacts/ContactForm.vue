<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { index, show, store, update } from '@/routes/admin/contacts';
import type {
    ComboboxOption,
    Contact,
    CurrencyOption,
    SelectOption,
} from '@/types';

const props = defineProps<{
    contact?: Contact;
    type?: 'customer' | 'vendor';
    kinds: SelectOption[];
    currencies: CurrencyOption[];
    receivableAccounts: ComboboxOption[];
    payableAccounts: ComboboxOption[];
}>();

const { t } = useTranslations();

const form = useForm({
    code: props.contact?.code ?? '',
    kind: props.contact?.kind ?? 'company',
    name: props.contact?.name ?? '',
    contact_person: props.contact?.contact_person ?? '',
    is_customer: props.contact?.is_customer ?? props.type !== 'vendor',
    is_vendor: props.contact?.is_vendor ?? props.type === 'vendor',
    email: props.contact?.email ?? '',
    phone: props.contact?.phone ?? '',
    mobile: props.contact?.mobile ?? '',
    tax_number: props.contact?.tax_number ?? '',
    currency_id: props.contact?.currency_id ?? null,
    receivable_account_id: props.contact?.receivable_account_id ?? null,
    payable_account_id: props.contact?.payable_account_id ?? null,
    payment_terms_days: props.contact?.payment_terms_days ?? 0,
    credit_limit: props.contact?.credit_limit ?? '',
    address: props.contact?.address ?? '',
    city: props.contact?.city ?? '',
    country: props.contact?.country ?? '',
    notes: props.contact?.notes ?? '',
    is_active: props.contact?.is_active ?? true,
});

const simpleMode = useSimpleMode();
const showAllFields = ref(false);

/**
 * Fields an everyday user rarely needs. Simple mode hides them until asked
 * for, and shows them anyway when one of them has a validation error.
 */
const ADVANCED_FIELDS = [
    'code',
    'kind',
    'contact_person',
    'email',
    'country',
    'tax_number',
    'currency_id',
    'payment_terms_days',
    'credit_limit',
    'receivable_account_id',
    'payable_account_id',
] as const;

const showsAllFields = computed(
    () =>
        !simpleMode.value ||
        showAllFields.value ||
        ADVANCED_FIELDS.some((field) => Boolean(form.errors[field])),
);

function submit(): void {
    form.submit(
        props.contact ? update({ contact: props.contact.id }) : store(),
        { preserveScroll: true },
    );
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <BaseCard :title="t('Details')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
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
                    v-if="showsAllFields"
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
                    v-if="showsAllFields"
                    :label="t('Kind')"
                    for="kind"
                    :error="form.errors.kind"
                >
                    <BaseSelect id="kind" v-model="form.kind">
                        <option
                            v-for="kind in kinds"
                            :key="kind.value"
                            :value="kind.value"
                        >
                            {{ kind.label }}
                        </option>
                    </BaseSelect>
                </BaseField>
                <BaseField
                    v-if="showsAllFields"
                    :label="t('Contact person')"
                    for="contact_person"
                    :error="form.errors.contact_person"
                >
                    <BaseInput
                        id="contact_person"
                        v-model="form.contact_person"
                    />
                </BaseField>
                <div class="sm:col-span-2">
                    <div class="flex flex-wrap gap-6">
                        <BaseCheckbox v-model="form.is_customer">{{
                            t('Customer')
                        }}</BaseCheckbox>
                        <BaseCheckbox v-model="form.is_vendor">{{
                            t('Vendor')
                        }}</BaseCheckbox>
                        <BaseCheckbox v-model="form.is_active">{{
                            t('Active')
                        }}</BaseCheckbox>
                    </div>
                    <BaseInputError
                        :message="form.errors.is_customer"
                        class="mt-1.5"
                    />
                </div>
            </div>
        </BaseCard>

        <BaseCard :title="t('Contact information')">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <BaseField
                    v-if="showsAllFields"
                    :label="t('Email')"
                    for="email"
                    :error="form.errors.email"
                >
                    <BaseInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        dir="ltr"
                    />
                </BaseField>
                <BaseField
                    :label="t('Phone')"
                    for="phone"
                    :error="form.errors.phone"
                >
                    <BaseInput id="phone" v-model="form.phone" dir="ltr" />
                </BaseField>
                <BaseField
                    :label="t('Mobile')"
                    for="mobile"
                    :error="form.errors.mobile"
                >
                    <BaseInput id="mobile" v-model="form.mobile" dir="ltr" />
                </BaseField>
                <BaseField
                    :label="t('City')"
                    for="city"
                    :error="form.errors.city"
                >
                    <BaseInput id="city" v-model="form.city" />
                </BaseField>
                <BaseField
                    v-if="showsAllFields"
                    :label="t('Country')"
                    for="country"
                    :error="form.errors.country"
                >
                    <BaseInput id="country" v-model="form.country" />
                </BaseField>
                <BaseField
                    :label="t('Address')"
                    for="address"
                    :error="form.errors.address"
                >
                    <BaseInput id="address" v-model="form.address" />
                </BaseField>
            </div>
        </BaseCard>

        <BaseCard v-if="!showsAllFields" :title="t('Notes')">
            <BaseTextarea id="notes" v-model="form.notes" rows="3" />
            <BaseButton
                variant="ghost"
                size="sm"
                class="mt-4"
                @click="showAllFields = true"
            >
                {{ t('Show more fields') }}
            </BaseButton>
        </BaseCard>

        <BaseCard
            v-else
            :title="t('Accounting')"
            :description="
                t(
                    'Leave the accounts empty to use the defaults of the accounting settings.',
                )
            "
        >
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <BaseField
                    :label="t('Tax number')"
                    for="tax_number"
                    :error="form.errors.tax_number"
                >
                    <BaseInput
                        id="tax_number"
                        v-model="form.tax_number"
                        dir="ltr"
                    />
                </BaseField>
                <BaseField
                    :label="t('Currency')"
                    for="currency_id"
                    :error="form.errors.currency_id"
                >
                    <BaseCombobox
                        id="currency_id"
                        v-model="form.currency_id"
                        :options="currencies"
                        clearable
                        :placeholder="t('Base currency')"
                    />
                </BaseField>
                <BaseField
                    :label="t('Payment terms')"
                    for="payment_terms_days"
                    :hint="
                        t(
                            'Days until invoices are due. 0 means due on receipt.',
                        )
                    "
                    :error="form.errors.payment_terms_days"
                >
                    <BaseInput
                        id="payment_terms_days"
                        v-model="form.payment_terms_days"
                        type="number"
                        min="0"
                        max="365"
                        dir="ltr"
                    />
                </BaseField>
                <BaseField
                    :label="t('Credit limit')"
                    for="credit_limit"
                    :hint="t('Leave empty for no limit.')"
                    :error="form.errors.credit_limit"
                >
                    <BaseInput
                        id="credit_limit"
                        v-model="form.credit_limit"
                        type="number"
                        min="0"
                        step="0.0001"
                        dir="ltr"
                    />
                </BaseField>
                <BaseField
                    v-if="form.is_customer"
                    :label="t('Receivable account')"
                    for="receivable_account_id"
                    :error="form.errors.receivable_account_id"
                >
                    <BaseCombobox
                        id="receivable_account_id"
                        v-model="form.receivable_account_id"
                        :options="receivableAccounts"
                        clearable
                        :placeholder="t('Default account')"
                    />
                </BaseField>
                <BaseField
                    v-if="form.is_vendor"
                    :label="t('Payable account')"
                    for="payable_account_id"
                    :error="form.errors.payable_account_id"
                >
                    <BaseCombobox
                        id="payable_account_id"
                        v-model="form.payable_account_id"
                        :options="payableAccounts"
                        clearable
                        :placeholder="t('Default account')"
                    />
                </BaseField>
                <BaseField
                    :label="t('Notes')"
                    for="notes"
                    class="sm:col-span-2"
                    :error="form.errors.notes"
                >
                    <BaseTextarea id="notes" v-model="form.notes" rows="3" />
                </BaseField>
            </div>
        </BaseCard>

        <div class="flex justify-end gap-3">
            <BaseButton
                variant="outline"
                :href="contact ? show({ contact: contact.id }) : index()"
            >
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton type="submit" :loading="form.processing">
                {{ contact ? t('Save changes') : t('Create') }}
            </BaseButton>
        </div>
    </form>
</template>
