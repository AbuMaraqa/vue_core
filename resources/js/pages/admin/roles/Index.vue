<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    KeyRound,
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { create, destroy, edit } from '@/routes/admin/roles';
import type { Role } from '@/types';

defineProps<{
    roles: { data: Role[] };
}>();

const { t } = useTranslations();
const { can } = usePermissions();

const roleToDelete = ref<Role | null>(null);
const isDeleting = ref(false);

function deleteRole(): void {
    if (!roleToDelete.value) {
        return;
    }

    router.visit(destroy({ role: roleToDelete.value.id }), {
        preserveScroll: true,
        onStart: () => (isDeleting.value = true),
        onFinish: () => {
            isDeleting.value = false;
            roleToDelete.value = null;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Roles & Permissions')"
            :breadcrumbs="[{ title: t('Roles & Permissions') }]"
        >
            <template #actions>
                <BaseButton v-if="can('roles.create')" :href="create()">
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
                        <Link
                            v-if="role.can.update"
                            :href="edit({ role: role.id })"
                            :title="t('Edit')"
                            class="flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-brand-500 dark:text-gray-400 dark:hover:bg-white/5"
                        >
                            <Pencil class="size-4" />
                        </Link>
                        <button
                            v-if="role.can.delete"
                            type="button"
                            :title="t('Delete')"
                            class="flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-error-50 hover:text-error-500 dark:text-gray-400 dark:hover:bg-error-500/15"
                            @click="roleToDelete = role"
                        >
                            <Trash2 class="size-4" />
                        </button>
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
            :open="roleToDelete !== null"
            :title="t('Delete role')"
            :message="
                t(
                    'Are you sure you want to delete :name? This action cannot be undone.',
                    { name: roleToDelete?.name ?? '' },
                )
            "
            :processing="isDeleting"
            @update:open="(open) => !open && (roleToDelete = null)"
            @confirm="deleteRole"
        />
    </div>
</template>
