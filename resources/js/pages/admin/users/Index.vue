<script setup lang="ts">
import ArrowDownUp from '~icons/solar/sort-vertical-linear';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import BaseAvatar from '@/components/base/BaseAvatar.vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import ExportMenu from '@/components/common/ExportMenu.vue';
import ImportDialog from '@/components/common/ImportDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/format';
import {
    create,
    destroy,
    edit,
    exportMethod,
    importMethod,
    importTemplate,
    index,
} from '@/routes/admin/users';
import type { ManagedUser, Paginated } from '@/types';

const props = defineProps<{
    users: Paginated<ManagedUser>;
    roles: string[];
    filters: {
        search: string | null;
        role: string | null;
        sort: string;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();

const { filters, sortBy } = useIndexFilters(
    index,
    { search: props.filters.search, role: props.filters.role },
    { sort: props.filters.sort },
);

const deletion = useConfirmDelete((user: ManagedUser) =>
    destroy({ user: user.id }),
);
</script>

<template>
    <div>
        <PageHeader :title="t('Users')" :breadcrumbs="[{ title: t('Users') }]">
            <template #actions>
                <ImportDialog
                    v-if="can('User Import')"
                    :title="t('Import users')"
                    :import-route="importMethod"
                    :template-route="importTemplate"
                    :columns="['name', 'email', 'password', 'roles']"
                />
                <ExportMenu v-if="can('User Export')" :route="exportMethod" />
                <BaseButton v-if="can('User Create')" :href="create()">
                    <Plus class="size-5" />
                    {{ t('Add User') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard :pagination="users">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search by name or email...')"
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.role" class="sm:w-52">
                    <option value="">{{ t('All roles') }}</option>
                    <option v-for="item in roles" :key="item" :value="item">
                        {{ t(item) }}
                    </option>
                </BaseSelect>
            </template>

            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="users.data.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-y border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5"
                                    @click="sortBy('name')"
                                >
                                    {{ t('User') }}
                                    <ArrowDownUp class="size-3.5" />
                                </button>
                            </th>
                            <th class="th-cell">{{ t('Roles') }}</th>
                            <th class="th-cell">{{ t('Status') }}</th>
                            <th class="th-cell">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5"
                                    @click="sortBy('created_at')"
                                >
                                    {{ t('Joined') }}
                                    <ArrowDownUp class="size-3.5" />
                                </button>
                            </th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="user in users.data" :key="user.id">
                            <td class="td-cell">
                                <div class="flex items-center gap-3">
                                    <BaseAvatar :name="user.name" />
                                    <div>
                                        <p
                                            class="font-medium text-gray-800 dark:text-white/90"
                                        >
                                            {{ user.name }}
                                        </p>
                                        <span class="text-theme-xs">
                                            {{ user.email }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="td-cell">
                                <div class="flex flex-wrap gap-1">
                                    <BaseBadge
                                        v-for="item in user.roles"
                                        :key="item"
                                    >
                                        {{ t(item) }}
                                    </BaseBadge>
                                    <span v-if="!user.roles?.length">—</span>
                                </div>
                            </td>
                            <td class="td-cell">
                                <BaseBadge
                                    :color="
                                        user.email_verified_at
                                            ? 'success'
                                            : 'warning'
                                    "
                                >
                                    {{
                                        user.email_verified_at
                                            ? t('Verified')
                                            : t('Unverified')
                                    }}
                                </BaseBadge>
                            </td>
                            <td class="td-cell">
                                {{ formatDate(user.created_at, locale) }}
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <BaseIconButton
                                        v-if="user.can.update"
                                        :href="edit({ user: user.id })"
                                        :label="t('Edit')"
                                    >
                                        <Pencil />
                                    </BaseIconButton>
                                    <BaseIconButton
                                        v-if="user.can.delete"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(user)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No users found.')" />
            </div>
        </DataCard>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete user')"
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
