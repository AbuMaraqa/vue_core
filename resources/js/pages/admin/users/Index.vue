<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowDownUp, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import BaseAvatar from '@/components/base/BaseAvatar.vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import TablePagination from '@/components/common/TablePagination.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/format';
import { create, destroy, edit, index } from '@/routes/admin/users';
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

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');
const sort = ref(props.filters.sort);

function applyFilters(): void {
    router.get(
        index.url(),
        {
            filter: {
                search: search.value || undefined,
                role: role.value || undefined,
            },
            sort: sort.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function sortBy(column: string): void {
    sort.value = sort.value === column ? `-${column}` : column;
    applyFilters();
}

watchDebounced(search, applyFilters, { debounce: 350 });

const userToDelete = ref<ManagedUser | null>(null);
const isDeleting = ref(false);

function deleteUser(): void {
    if (!userToDelete.value) {
        return;
    }

    router.visit(destroy({ user: userToDelete.value.id }), {
        preserveScroll: true,
        onStart: () => (isDeleting.value = true),
        onFinish: () => {
            isDeleting.value = false;
            userToDelete.value = null;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader :title="t('Users')" :breadcrumbs="[{ title: t('Users') }]">
            <template #actions>
                <BaseButton v-if="can('users.create')" :href="create()">
                    <Plus class="size-5" />
                    {{ t('Add User') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div class="relative w-full sm:max-w-sm">
                    <Search
                        class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-gray-400"
                    />
                    <BaseInput
                        v-model="search"
                        type="search"
                        class="ps-12"
                        :placeholder="t('Search by name or email...')"
                    />
                </div>
                <div class="w-full sm:w-52">
                    <BaseSelect v-model="role" @change="applyFilters">
                        <option value="">{{ t('All roles') }}</option>
                        <option v-for="item in roles" :key="item" :value="item">
                            {{ t(item) }}
                        </option>
                    </BaseSelect>
                </div>
            </div>

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
                                    <span v-if="!user.roles.length">—</span>
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
                                    <Link
                                        v-if="user.can.update"
                                        :href="edit({ user: user.id })"
                                        :title="t('Edit')"
                                        class="flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-brand-500 dark:text-gray-400 dark:hover:bg-white/5"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                    <button
                                        v-if="user.can.delete"
                                        type="button"
                                        :title="t('Delete')"
                                        class="flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-error-50 hover:text-error-500 dark:text-gray-400 dark:hover:bg-error-500/15"
                                        @click="userToDelete = user"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No users found.')" />
            </div>

            <TablePagination :meta="users.meta" :links="users.links" />
        </div>

        <ConfirmDialog
            :open="userToDelete !== null"
            :title="t('Delete user')"
            :message="
                t(
                    'Are you sure you want to delete :name? This action cannot be undone.',
                    { name: userToDelete?.name ?? '' },
                )
            "
            :processing="isDeleting"
            @update:open="(open) => !open && (userToDelete = null)"
            @confirm="deleteUser"
        />
    </div>
</template>
