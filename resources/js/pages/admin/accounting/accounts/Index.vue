<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AddCircle from '~icons/solar/add-circle-linear';
import AltArrowDown from '~icons/solar/alt-arrow-down-linear';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { computed, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import BaseTranslatableInput from '@/components/base/BaseTranslatableInput.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { destroy, store, update } from '@/routes/admin/accounting/accounts';
import type {
    AccountRow,
    ComboboxOption,
    SelectOption,
    SubtypeOption,
    Translations,
} from '@/types';

const props = defineProps<{
    accounts: AccountRow[];
    parents: ComboboxOption[];
    subtypes: SubtypeOption[];
    types: SelectOption[];
    currencies: ComboboxOption[];
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

/*
 * Tree: groups start expanded; a search shows every matching account with
 * its ancestors.
 */
const collapsed = ref(new Set<number>());
const search = ref('');

const byId = computed(
    () => new Map(props.accounts.map((account) => [account.id, account])),
);

function isHiddenByCollapse(account: AccountRow): boolean {
    let parentId = account.parent_id;

    while (parentId !== null) {
        if (collapsed.value.has(parentId)) {
            return true;
        }

        parentId = byId.value.get(parentId)?.parent_id ?? null;
    }

    return false;
}

const visibleAccounts = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return props.accounts.filter((account) => !isHiddenByCollapse(account));
    }

    const visible = new Set<number>();

    for (const account of props.accounts) {
        if (`${account.code} ${account.name}`.toLowerCase().includes(term)) {
            let current: AccountRow | undefined = account;

            while (current) {
                visible.add(current.id);
                current =
                    current.parent_id !== null
                        ? byId.value.get(current.parent_id)
                        : undefined;
            }
        }
    }

    return props.accounts.filter((account) => visible.has(account.id));
});

function toggle(account: AccountRow): void {
    const next = new Set(collapsed.value);

    if (next.has(account.id)) {
        next.delete(account.id);
    } else {
        next.add(account.id);
    }

    collapsed.value = next;
}

function collapseAll(): void {
    collapsed.value = new Set(
        props.accounts
            .filter((account) => account.has_children)
            .map((account) => account.id),
    );
}

/**
 * Balances are shown on the account's normal side: a credit balance of a
 * liability or revenue account is shown as a positive amount.
 */
function displayBalance(account: AccountRow): string {
    const value = Number(account.balance);
    const debitNormal = account.type === 'asset' || account.type === 'expense';

    return formatDecimal(
        debitNormal ? value : -value,
        locale.value,
        props.baseCurrency.decimal_places,
    );
}

/*
 * Create and edit dialog.
 */
const editing = ref<AccountRow | null>(null);
const isFormOpen = ref(false);

const form = useForm({
    parent_id: null as number | null,
    code: '',
    name: {} as Translations,
    subtype: '',
    is_group: false,
    is_active: true,
    currency_id: null as number | null,
    description: '',
});

const parentType = computed(
    () =>
        (form.parent_id !== null
            ? byId.value.get(form.parent_id)?.type
            : null) ?? null,
);

const subtypeOptions = computed<ComboboxOption[]>(() =>
    props.subtypes
        .filter(
            (subtype) =>
                parentType.value === null || subtype.type === parentType.value,
        )
        .map((subtype) => ({
            value: subtype.value,
            label: subtype.label,
            hint: props.types.find((type) => type.value === subtype.type)
                ?.label,
        })),
);

function openCreate(parent: AccountRow | null = null): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.parent_id = parent?.id ?? null;
    form.subtype = parent?.subtype ?? '';
    isFormOpen.value = true;
}

function openEdit(account: AccountRow): void {
    editing.value = account;
    form.clearErrors();
    form.parent_id = account.parent_id;
    form.code = account.code;
    form.name = { ...account.name_translations };
    form.subtype = account.subtype;
    form.is_group = account.is_group;
    form.is_active = account.is_active;
    form.currency_id = account.currency_id;
    form.description = account.description ?? '';
    isFormOpen.value = true;
}

function submit(): void {
    form.submit(
        editing.value ? update({ account: editing.value.id }) : store(),
        {
            preserveScroll: true,
            onSuccess: () => (isFormOpen.value = false),
        },
    );
}

const deletion = useConfirmDelete((account: AccountRow) =>
    destroy({ account: account.id }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Chart of Accounts')"
            :description="
                t(
                    'The accounts of the general ledger, with their current balances.',
                )
            "
            :breadcrumbs="[{ title: t('Chart of Accounts') }]"
        >
            <template #actions>
                <BaseButton v-if="can('Account Create')" @click="openCreate()">
                    <Plus class="size-5" />
                    {{ t('Add account') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <template #search>
                <SearchInput
                    v-model="search"
                    :placeholder="t('Search by code or name...')"
                />
            </template>
            <template #filters>
                <BaseButton
                    variant="outline"
                    size="sm"
                    @click="collapsed = new Set()"
                >
                    {{ t('Expand all') }}
                </BaseButton>
                <BaseButton variant="outline" size="sm" @click="collapseAll">
                    {{ t('Collapse all') }}
                </BaseButton>
            </template>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="visibleAccounts.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Account') }}</th>
                            <th class="th-cell">{{ t('Account type') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Balance') }} ({{ baseCurrency.code }})
                            </th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="account in visibleAccounts"
                            :key="account.id"
                            :class="{ 'opacity-60': !account.is_active }"
                        >
                            <td class="td-cell">
                                <div
                                    class="flex items-center gap-2"
                                    :style="{
                                        paddingInlineStart: `${account.depth * 1.5}rem`,
                                    }"
                                >
                                    <button
                                        v-if="account.has_children"
                                        type="button"
                                        class="flex size-6 items-center justify-center rounded text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5"
                                        :aria-label="t('Toggle')"
                                        @click="toggle(account)"
                                    >
                                        <AltArrowDown
                                            :class="[
                                                'size-4 transition-transform',
                                                {
                                                    '-rotate-90 rtl:rotate-90':
                                                        collapsed.has(
                                                            account.id,
                                                        ),
                                                },
                                            ]"
                                        />
                                    </button>
                                    <span v-else class="size-6" />
                                    <span
                                        class="font-mono text-theme-xs text-gray-500"
                                        dir="ltr"
                                        >{{ account.code }}</span
                                    >
                                    <span
                                        :class="[
                                            'text-gray-800 dark:text-white/90',
                                            {
                                                'font-semibold':
                                                    account.is_group,
                                            },
                                        ]"
                                    >
                                        {{ account.name }}
                                    </span>
                                    <BaseBadge
                                        v-if="!account.is_active"
                                        color="light"
                                    >
                                        {{ t('Inactive') }}
                                    </BaseBadge>
                                </div>
                            </td>
                            <td class="td-cell">{{ account.subtype_label }}</td>
                            <td
                                :class="[
                                    'td-cell text-end font-mono',
                                    {
                                        'font-semibold text-gray-800 dark:text-white/90':
                                            account.is_group,
                                    },
                                ]"
                                dir="ltr"
                            >
                                {{ displayBalance(account) }}
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="
                                            account.is_group &&
                                            can('Account Create')
                                        "
                                        :label="t('Add sub-account')"
                                        @click="openCreate(account)"
                                    >
                                        <AddCircle />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="account.can.update"
                                        :label="t('Edit')"
                                        @click="openEdit(account)"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="account.can.delete"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(account)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No accounts found.')" />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isFormOpen"
            :title="editing ? t('Edit account') : t('Add account')"
            max-width="lg"
        >
            <form id="account-form" class="space-y-5" @submit.prevent="submit">
                <BaseField
                    :label="t('Parent account')"
                    :error="form.errors.parent_id"
                    :hint="t('Leave empty for a top-level account.')"
                >
                    <BaseCombobox
                        v-model="form.parent_id"
                        :options="parents"
                        :invalid="Boolean(form.errors.parent_id)"
                        clearable
                    />
                </BaseField>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
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
                            :invalid="Boolean(form.errors.code)"
                        />
                    </BaseField>
                    <BaseField
                        :label="t('Account type')"
                        required
                        :error="form.errors.subtype"
                    >
                        <BaseCombobox
                            v-model="form.subtype"
                            :options="subtypeOptions"
                            :invalid="Boolean(form.errors.subtype)"
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
                <BaseField
                    :label="t('Currency')"
                    :error="form.errors.currency_id"
                    :hint="
                        t(
                            'Only for accounts kept in a foreign currency, such as a USD bank account.',
                        )
                    "
                >
                    <BaseCombobox
                        v-model="form.currency_id"
                        :options="currencies"
                        clearable
                    />
                </BaseField>
                <BaseField
                    :label="t('Description')"
                    for="description"
                    :error="form.errors.description"
                >
                    <BaseTextarea id="description" v-model="form.description" />
                </BaseField>
                <div class="flex flex-wrap gap-6">
                    <BaseCheckbox v-model="form.is_group">
                        {{ t('Group account (holds other accounts)') }}
                    </BaseCheckbox>
                    <BaseCheckbox v-model="form.is_active">
                        {{ t('Active') }}
                    </BaseCheckbox>
                </div>
                <p
                    v-if="form.errors.is_group"
                    class="text-theme-xs text-error-500"
                >
                    {{ form.errors.is_group }}
                </p>
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
                    form="account-form"
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
                    { name: deletion.target?.label ?? '' },
                )
            "
            :processing="deletion.processing"
            @update:open="(open) => !open && deletion.cancel()"
            @confirm="deletion.confirm"
        />
    </div>
</template>
