<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseCombobox from '@/components/base/BaseCombobox.vue';
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
import { destroy, store, update } from '@/routes/admin/accounting/cost-centers';
import type { ComboboxOption, Translations } from '@/types';

type CenterRow = {
    id: number;
    parent_id: number | null;
    code: string;
    name: string;
    name_translations: Translations;
    is_active: boolean;
    depth: number;
    has_children: boolean;
};

defineProps<{
    centers: CenterRow[];
    parents: ComboboxOption[];
}>();

const { t } = useTranslations();
const { can } = usePermissions();

const editing = ref<CenterRow | null>(null);
const isFormOpen = ref(false);
const form = useForm({
    parent_id: null as number | null,
    code: '',
    name: {} as Translations,
    is_active: true,
});

function openForm(
    center: CenterRow | null = null,
    parentId: number | null = null,
): void {
    editing.value = center;
    form.clearErrors();
    form.parent_id = center?.parent_id ?? parentId;
    form.code = center?.code ?? '';
    form.name = { ...center?.name_translations };
    form.is_active = center?.is_active ?? true;
    isFormOpen.value = true;
}

function submit(): void {
    form.submit(
        editing.value ? update({ costCenter: editing.value.id }) : store(),
        {
            preserveScroll: true,
            onSuccess: () => (isFormOpen.value = false),
        },
    );
}

const deletion = useConfirmDelete((center: CenterRow) =>
    destroy({ costCenter: center.id }),
);
</script>

<template>
    <div>
        <PageHeader
            :title="t('Cost Centers')"
            :description="
                t(
                    'Departments, branches or projects to track revenue and expenses by.',
                )
            "
            :breadcrumbs="[{ title: t('Cost Centers') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Cost Center Create')"
                    @click="openForm()"
                >
                    <Plus class="size-5" />
                    {{ t('Add cost center') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="centers.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('Cost center') }}</th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="center in centers"
                            :key="center.id"
                            :class="{ 'opacity-60': !center.is_active }"
                        >
                            <td class="td-cell">
                                <div
                                    class="flex items-center gap-2"
                                    :style="{
                                        paddingInlineStart: `${center.depth * 1.5}rem`,
                                    }"
                                >
                                    <span
                                        class="font-mono text-theme-xs text-gray-500"
                                        dir="ltr"
                                        >{{ center.code }}</span
                                    >
                                    <span
                                        :class="[
                                            'text-gray-800 dark:text-white/90',
                                            {
                                                'font-semibold':
                                                    center.has_children,
                                            },
                                        ]"
                                        >{{ center.name }}</span
                                    >
                                    <BaseBadge
                                        v-if="!center.is_active"
                                        color="light"
                                        >{{ t('Inactive') }}</BaseBadge
                                    >
                                </div>
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <BaseIconButton
                                        v-if="can('Cost Center Create')"
                                        :label="t('Add sub-center')"
                                        @click="openForm(null, center.id)"
                                    >
                                        <Plus />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="can('Cost Center Update')"
                                        :label="t('Edit')"
                                        @click="openForm(center)"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="
                                            can('Cost Center Delete') &&
                                            !center.has_children
                                        "
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(center)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No cost centers yet.')" />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="isFormOpen"
            :title="editing ? t('Edit cost center') : t('Add cost center')"
            max-width="lg"
        >
            <form id="center-form" class="space-y-5" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <BaseField
                        :label="t('Parent')"
                        :error="form.errors.parent_id"
                    >
                        <BaseCombobox
                            v-model="form.parent_id"
                            :options="
                                parents.filter(
                                    (option) => option.value !== editing?.id,
                                )
                            "
                            clearable
                        />
                    </BaseField>
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
                </div>
                <BaseTranslatableInput
                    v-model="form.name"
                    :label="t('Name')"
                    field="name"
                    :errors="form.errors"
                    required
                />
                <BaseCheckbox v-model="form.is_active">{{
                    t('Active')
                }}</BaseCheckbox>
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
                    form="center-form"
                    size="sm"
                    :loading="form.processing"
                    >{{ t('Save') }}</BaseButton
                >
            </template>
        </BaseModal>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete cost center')"
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
