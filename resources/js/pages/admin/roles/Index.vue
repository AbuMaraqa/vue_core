<script setup lang="ts">
import KeyRound from '~icons/solar/key-minimalistic-linear';
import Pencil from '~icons/solar/pen-linear';
import Plus from '~icons/solar/add-linear';
import ShieldCheck from '~icons/solar/shield-check-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import Users from '~icons/solar/users-group-rounded-linear';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { create, destroy, edit } from '@/routes/admin/roles';
import type { Resource, Role } from '@/types';

defineProps<{
    roles: Resource<Role[]>;
}>();

const { t } = useTranslations();
const { can } = usePermissions();

const deletion = useConfirmDelete((role: Role) => destroy({ role: role.id }));
</script>

<template>
    <div>
        <PageHeader
            :title="t('Roles & Permissions')"
            :breadcrumbs="[{ title: t('Roles & Permissions') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="can('Roles And Permissions Create')"
                    :href="create()"
                >
                    <Plus class="size-5" />
                    {{ t('Create role') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div
            class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6 xl:grid-cols-3"
        >
            <div
                v-for="role in roles.data"
                :key="role.id"
                class="flex flex-col rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-12 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400"
                        >
                            <ShieldCheck class="size-6" />
                        </span>
                        <div>
                            <h3
                                class="text-base font-semibold text-gray-800 dark:text-white/90"
                            >
                                {{ t(role.name) }}
                            </h3>
                            <BaseBadge
                                v-if="role.is_protected"
                                color="light"
                                class="mt-1"
                            >
                                {{ t('System role') }}
                            </BaseBadge>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        <BaseIconButton
                            v-if="role.can.update"
                            :href="edit({ role: role.id })"
                            :label="t('Edit')"
                        >
                            <Pencil />
                        </BaseIconButton>
                        <BaseIconButton
                            v-if="role.can.delete"
                            color="danger"
                            :label="t('Delete')"
                            @click="deletion.ask(role)"
                        >
                            <Trash2 />
                        </BaseIconButton>
                    </div>
                </div>

                <div
                    class="mt-6 flex items-center gap-6 border-t border-gray-100 pt-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400"
                >
                    <span class="flex items-center gap-2">
                        <Users class="size-4" />
                        {{
                            t(':count users', { count: role.users_count ?? 0 })
                        }}
                    </span>
                    <span class="flex items-center gap-2">
                        <KeyRound class="size-4" />
                        {{
                            role.is_super_admin
                                ? t('All permissions')
                                : t(':count permissions', {
                                      count: role.permissions_count ?? 0,
                                  })
                        }}
                    </span>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete role')"
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
