<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
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
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import BaseTranslatableInput from '@/components/base/BaseTranslatableInput.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDecimal } from '@/lib/format';
import { destroy, store, update } from '@/routes/admin/inventory/warehouses';
import type { ResourceAbilities, Translations } from '@/types';

type WarehouseRow = {
    id: number;
    code: string;
    name: string;
    name_translations: Translations;
    address: string | null;
    is_active: boolean;
    stock_value: string;
    can: ResourceAbilities;
};

defineProps<{
    warehouses: WarehouseRow[];
    baseCurrency: { code: string; decimal_places: number };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const editing = ref<WarehouseRow | null>(null);
const isFormOpen = ref(false);
const form = useForm({
    code: '',
    name: {} as Translations,
    address: '',
    is_active: true,
});

function openForm(warehouse: WarehouseRow | null = null): void {
    editing.value = warehouse;
    form.clearErrors();
    form.code = warehouse?.code ?? '';
    form.name = { ...warehouse?.name_translations };
    form.address = warehouse?.address ?? '';
    form.is_active = warehouse?.is_active ?? true;
    isFormOpen.value = true;
}

function submit(): void {
    form.submit(
        editing.value ? update({ warehouse: editing.value.id }) : store(),
        {
            preserveScroll: true,
            onSuccess: () => (isFormOpen.value = false),
        },
    );
}

const deletion = useConfirmDelete((warehouse: WarehouseRow) =>
    destroy({ warehouse: warehouse.id }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Warehouses')"
            :breadcrumbs="[{ title: t('Warehouses') }]"
        >
            <template #actions>
                <BaseButton v-if="can('Warehouse Create')" @click="openForm()">
                    <Plus class="size-5" />
                    {{ t('Add warehouse') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="warehouses.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Code') }}</th>
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell">{{ t('Address') }}</th>
                            <th class="th-cell text-end">
                                {{ t('Stock value') }}
                                <span class="text-theme-xs font-normal">
                                    ({{ baseCurrency.code }})
                                </span>
                            </th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="warehouse in warehouses"
                            :key="warehouse.id"
                            :class="{ 'opacity-60': !warehouse.is_active }"
                        >
                            <td class="td-cell font-mono" dir="ltr">
                                {{ warehouse.code }}
                            </td>
                            <td
                                class="td-cell font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ warehouse.name }}
                                <BaseBadge
                                    v-if="!warehouse.is_active"
                                    color="light"
                                    class="ms-2"
                                >
                                    {{ t('Inactive') }}
                                </BaseBadge>
                            </td>
                            <td class="max-w-xs truncate td-cell">
                                {{ warehouse.address ?? '—' }}
                            </td>
                            <td class="td-cell text-end font-mono" dir="ltr">
                                {{
                                    formatDecimal(
                                        warehouse.stock_value,
                                        locale,
                                        baseCurrency.decimal_places,
                                    )
                                }}
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="warehouse.can.update"
                                        :label="t('Edit')"
                                        @click="openForm(warehouse)"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="warehouse.can.delete"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(warehouse)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No warehouses yet.')" />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isFormOpen"
            :title="editing ? t('Edit warehouse') : t('Add warehouse')"
            max-width="lg"
        >
            <form
                id="warehouse-form"
                class="space-y-5"
                @submit.prevent="submit"
            >
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
                <BaseTranslatableInput
                    v-model="form.name"
                    :label="t('Name')"
                    field="name"
                    :errors="form.errors"
                    required
                />
                <BaseField
                    :label="t('Address')"
                    for="address"
                    :error="form.errors.address"
                >
                    <BaseTextarea
                        id="address"
                        v-model="form.address"
                        rows="2"
                    />
                </BaseField>
                <BaseCheckbox v-model="form.is_active">
                    {{ t('Active') }}
                </BaseCheckbox>
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
                    form="warehouse-form"
                    size="sm"
                    :loading="form.processing"
                >
                    {{ t('Save') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete warehouse')"
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
