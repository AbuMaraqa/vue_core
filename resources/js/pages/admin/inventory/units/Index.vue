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
import BaseTranslatableInput from '@/components/base/BaseTranslatableInput.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { destroy, store, update } from '@/routes/admin/inventory/units';
import type { Translations } from '@/types';

type UnitRow = {
    id: number;
    name: string;
    name_translations: Translations;
    symbol: string | null;
    is_active: boolean;
};

defineProps<{
    units: UnitRow[];
}>();

const { t } = useTranslations();
const { can } = usePermissions();

const editing = ref<UnitRow | null>(null);
const isFormOpen = ref(false);
const form = useForm({
    name: {} as Translations,
    symbol: '',
    is_active: true,
});

function openForm(unit: UnitRow | null = null): void {
    editing.value = unit;
    form.clearErrors();
    form.name = { ...unit?.name_translations };
    form.symbol = unit?.symbol ?? '';
    form.is_active = unit?.is_active ?? true;
    isFormOpen.value = true;
}

function submit(): void {
    form.submit(editing.value ? update({ unit: editing.value.id }) : store(), {
        preserveScroll: true,
        onSuccess: () => (isFormOpen.value = false),
    });
}

const deletion = useConfirmDelete((unit: UnitRow) =>
    destroy({ unit: unit.id }),
);
</script>

<template>
    <div>
        <PageHeader :title="t('Units')" :breadcrumbs="[{ title: t('Units') }]">
            <template #actions>
                <BaseButton v-if="can('Unit Create')" @click="openForm()">
                    <Plus class="size-5" />
                    {{ t('Add unit') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="units.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Name') }}</th>
                            <th class="th-cell">{{ t('Symbol') }}</th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="unit in units"
                            :key="unit.id"
                            :class="{ 'opacity-60': !unit.is_active }"
                        >
                            <td
                                class="td-cell font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ unit.name }}
                                <BaseBadge
                                    v-if="!unit.is_active"
                                    color="light"
                                    class="ms-2"
                                >
                                    {{ t('Inactive') }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell" dir="ltr">
                                {{ unit.symbol ?? '—' }}
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="can('Unit Update')"
                                        :label="t('Edit')"
                                        @click="openForm(unit)"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="can('Unit Delete')"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(unit)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No units yet.')" />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isFormOpen"
            :title="editing ? t('Edit unit') : t('Add unit')"
            max-width="lg"
        >
            <form id="unit-form" class="space-y-5" @submit.prevent="submit">
                <BaseTranslatableInput
                    v-model="form.name"
                    :label="t('Name')"
                    field="name"
                    :errors="form.errors"
                    required
                />
                <BaseField
                    :label="t('Symbol')"
                    for="symbol"
                    :hint="t('A short form, e.g. kg or pcs.')"
                    :error="form.errors.symbol"
                >
                    <BaseInput
                        id="symbol"
                        v-model="form.symbol"
                        :invalid="Boolean(form.errors.symbol)"
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
                    form="unit-form"
                    size="sm"
                    :loading="form.processing"
                >
                    {{ t('Save') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete unit')"
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
