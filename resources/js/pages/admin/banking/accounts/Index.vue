<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import Plus from '~icons/solar/add-linear';
import ArrowLeftDown from '~icons/solar/arrow-left-down-linear';
import ArrowRightUp from '~icons/solar/arrow-right-up-linear';
import Banknote from '~icons/solar/banknote-linear';
import CardRecive from '~icons/solar/card-recive-linear';
import CardSend from '~icons/solar/card-send-linear';
import Buildings from '~icons/solar/buildings-linear';
import Pencil from '~icons/solar/pen-linear';
import Transfer from '~icons/solar/transfer-horizontal-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import WalletMoney from '~icons/solar/wallet-money-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useSimpleMode } from '@/composables/useSimpleMode';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { destroy, show, store, update } from '@/routes/admin/banking/accounts';
import { create as createCheque } from '@/routes/admin/banking/cheques';
import { create as createTransfer } from '@/routes/admin/banking/transfers';
import { create as createVoucher } from '@/routes/admin/banking/vouchers';
import type {
    BankAccountRow,
    BankAccountType,
    ComboboxOption,
    CurrencyFormat,
    CurrencyOption,
    SelectOption,
} from '@/types';

const props = defineProps<{
    bankAccounts: BankAccountRow[];
    totals: {
        cash: string;
        bank: string;
        cheques_receivable: string;
        cheques_payable: string;
    };
    types: SelectOption[];
    currencies: CurrencyOption[];
    linkableAccounts: ComboboxOption[];
    baseCurrency: CurrencyFormat;
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
const simpleMode = useSimpleMode();

// Simple mode uses the plain wording of the sidebar.
const title = computed(() =>
    simpleMode.value ? t('Cash boxes and bank accounts') : t('Bank & Cash'),
);

const base = (value: string): string =>
    formatDecimal(value, locale.value, props.baseCurrency.decimal_places);

const summary = computed(() => [
    { label: t('Cash on hand'), value: props.totals.cash, icon: WalletMoney },
    { label: t('Bank balances'), value: props.totals.bank, icon: Buildings },
    {
        label: t('Cheques receivable'),
        value: props.totals.cheques_receivable,
        icon: CardRecive,
    },
    {
        label: t('Cheques payable'),
        value: props.totals.cheques_payable,
        icon: CardSend,
    },
]);

const baseCurrencyId = computed(
    () => props.currencies.find((currency) => currency.is_base)?.value ?? null,
);

const editing = ref<BankAccountRow | null>(null);
const isFormOpen = ref(false);
const form = useForm({
    name: '',
    type: 'bank' as BankAccountType,
    currency_id: null as number | string | null,
    account_id: null as number | string | null,
    bank_name: '',
    branch: '',
    account_number: '',
    iban: '',
    is_active: true,
});

function openForm(bankAccount: BankAccountRow | null = null): void {
    editing.value = bankAccount;
    form.clearErrors();
    form.name = bankAccount?.name ?? '';
    form.type = bankAccount?.type ?? 'bank';
    form.currency_id = bankAccount?.currency_id ?? baseCurrencyId.value;
    form.account_id = null;
    form.bank_name = bankAccount?.bank_name ?? '';
    form.branch = bankAccount?.branch ?? '';
    form.account_number = bankAccount?.account_number ?? '';
    form.iban = bankAccount?.iban ?? '';
    form.is_active = bankAccount?.is_active ?? true;
    isFormOpen.value = true;
}

function submit(): void {
    const bankAccount = editing.value;

    form.transform(({ account_id: accountId, ...data }) =>
        bankAccount ? data : { ...data, account_id: accountId },
    ).submit(bankAccount ? update({ bankAccount: bankAccount.id }) : store(), {
        preserveScroll: true,
        onSuccess: () => (isFormOpen.value = false),
    });
}

const deletion = useConfirmDelete((bankAccount: BankAccountRow) =>
    destroy({ bankAccount: bankAccount.id }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="title"
            :description="
                t('Cash boxes and bank accounts, and the money in each.')
            "
            :breadcrumbs="[{ title }]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Bank Transfer Create')"
                    variant="outline"
                    :href="createTransfer()"
                >
                    <Transfer class="size-5" />
                    {{ t('New transfer') }}
                </BaseButton>
                <BaseButton
                    v-if="can('Bank Voucher Create')"
                    variant="outline"
                    :href="
                        createVoucher(undefined, {
                            query: { direction: 'out' },
                        })
                    "
                >
                    <ArrowRightUp class="size-5" />
                    {{ t('Payment voucher') }}
                </BaseButton>
                <BaseButton
                    v-if="can('Bank Voucher Create')"
                    variant="outline"
                    :href="
                        createVoucher(undefined, {
                            query: { direction: 'in' },
                        })
                    "
                >
                    <ArrowLeftDown class="size-5" />
                    {{ t('Receipt voucher') }}
                </BaseButton>
                <BaseButton
                    v-if="can('Cheque Create')"
                    variant="outline"
                    :href="
                        createCheque(undefined, {
                            query: { direction: 'incoming' },
                        })
                    "
                >
                    <Banknote class="size-5" />
                    {{ t('Receive cheque') }}
                </BaseButton>
                <BaseButton
                    v-if="can('Bank Account Create')"
                    @click="openForm()"
                >
                    <Plus class="size-5" />
                    {{ t('Add account') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in summary"
                :key="card.label"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-theme-sm text-gray-500 dark:text-gray-400">
                        {{ card.label }}
                    </p>
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400"
                    >
                        <component :is="card.icon" class="size-5" />
                    </span>
                </div>
                <p
                    class="mt-2 font-mono text-xl font-semibold text-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    {{ base(card.value) }}
                    <span class="text-theme-xs font-normal text-gray-400">
                        {{ baseCurrency.code }}
                    </span>
                </p>
            </div>
        </div>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="bankAccounts.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell">{{ t('Type') }}</th>
                            <th class="th-cell">{{ t('Bank') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Balance') }}
                            </th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="bankAccount in bankAccounts"
                            :key="bankAccount.id"
                            class="hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="td-cell">
                                <Link
                                    :href="
                                        show({ bankAccount: bankAccount.id })
                                    "
                                    class="font-medium text-brand-500 hover:underline"
                                >
                                    {{ bankAccount.name }}
                                </Link>
                                <span class="block text-theme-xs text-gray-400">
                                    {{ bankAccount.account }}
                                </span>
                                <BaseBadge
                                    v-if="!bankAccount.is_active"
                                    color="light"
                                    class="mt-1"
                                >
                                    {{ t('Inactive') }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">
                                <BaseBadge
                                    :color="
                                        bankAccount.type === 'cash'
                                            ? 'warning'
                                            : 'info'
                                    "
                                >
                                    {{ bankAccount.type_label }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">
                                <span
                                    class="block text-gray-800 dark:text-white/90"
                                >
                                    {{
                                        [
                                            bankAccount.bank_name,
                                            bankAccount.branch,
                                        ]
                                            .filter(Boolean)
                                            .join(' — ') || '—'
                                    }}
                                </span>
                                <span
                                    v-if="
                                        bankAccount.iban ||
                                        bankAccount.account_number
                                    "
                                    class="block font-mono text-theme-xs text-gray-400"
                                    dir="ltr"
                                >
                                    {{
                                        bankAccount.iban ??
                                        bankAccount.account_number
                                    }}
                                </span>
                            </td>
                            <td class="td-cell text-end whitespace-nowrap">
                                <span
                                    class="block font-mono font-medium text-gray-800 dark:text-white/90"
                                    dir="ltr"
                                >
                                    {{
                                        formatDecimal(
                                            bankAccount.balance,
                                            locale,
                                            bankAccount.currency.decimal_places,
                                        )
                                    }}
                                    <span
                                        class="text-theme-xs font-normal text-gray-400"
                                    >
                                        {{ bankAccount.currency.code }}
                                    </span>
                                </span>
                                <span
                                    v-if="
                                        bankAccount.currency.code !==
                                        baseCurrency.code
                                    "
                                    class="block font-mono text-theme-xs text-gray-400"
                                    dir="ltr"
                                >
                                    ≈ {{ base(bankAccount.base_balance) }}
                                    {{ baseCurrency.code }}
                                </span>
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="bankAccount.can.update"
                                        :label="t('Edit')"
                                        @click="openForm(bankAccount)"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="bankAccount.can.delete"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(bankAccount)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState
                    v-else
                    :message="t('No bank or cash accounts yet.')"
                />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isFormOpen"
            :title="editing ? t('Edit account') : t('Add account')"
            max-width="lg"
        >
            <form
                id="bank-account-form"
                class="grid grid-cols-1 gap-5 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <BaseField
                    class="sm:col-span-2"
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
                    :label="t('Type')"
                    for="type"
                    required
                    :error="form.errors.type"
                >
                    <BaseSelect
                        id="type"
                        v-model="form.type"
                        :invalid="Boolean(form.errors.type)"
                    >
                        <option
                            v-for="type in types"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </option>
                    </BaseSelect>
                </BaseField>
                <BaseField
                    :label="t('Currency')"
                    required
                    :error="form.errors.currency_id"
                >
                    <BaseCombobox
                        v-model="form.currency_id"
                        :options="currencies"
                        :invalid="Boolean(form.errors.currency_id)"
                    />
                </BaseField>
                <BaseField
                    v-if="!editing"
                    class="sm:col-span-2"
                    :label="t('Use an existing account')"
                    :hint="
                        t(
                            'Leave empty to create a new account in the chart of accounts.',
                        )
                    "
                    :error="form.errors.account_id"
                >
                    <BaseCombobox
                        v-model="form.account_id"
                        :options="linkableAccounts"
                        clearable
                        :placeholder="t('Create a new account')"
                        :invalid="Boolean(form.errors.account_id)"
                    />
                </BaseField>
                <template v-if="form.type === 'bank'">
                    <BaseField
                        :label="t('Bank')"
                        for="bank_name"
                        :error="form.errors.bank_name"
                    >
                        <BaseInput id="bank_name" v-model="form.bank_name" />
                    </BaseField>
                    <BaseField
                        :label="t('Branch')"
                        for="branch"
                        :error="form.errors.branch"
                    >
                        <BaseInput id="branch" v-model="form.branch" />
                    </BaseField>
                    <BaseField
                        :label="t('Account number')"
                        for="account_number"
                        :error="form.errors.account_number"
                    >
                        <BaseInput
                            id="account_number"
                            v-model="form.account_number"
                            dir="ltr"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('IBAN')"
                        for="iban"
                        :error="form.errors.iban"
                    >
                        <BaseInput id="iban" v-model="form.iban" dir="ltr" />
                    </BaseField>
                </template>
                <div class="sm:col-span-2">
                    <BaseCheckbox v-model="form.is_active">
                        {{ t('Active') }}
                    </BaseCheckbox>
                </div>
            </form>
            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="isFormOpen = false"
                >
                    {{ t('Cancel') }}
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="bank-account-form"
                    size="sm"
                    :loading="form.processing"
                >
                    {{ t('Save') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete account')"
            :message="
                t(
                    'Are you sure you want to delete :name? This action cannot be undone.',
                    { name: deletion.target?.name ?? '' },
                )
            "
            :processing="deletion.processing"
            @update:open="(open) => !open && deletion.cancel()"
            @confirm="deletion.confirm"
        />
    </div>
</template>
