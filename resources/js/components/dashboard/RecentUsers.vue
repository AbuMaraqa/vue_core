<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BaseAvatar from '@/components/base/BaseAvatar.vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useLocale } from '@/composables/useLocale';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/format';
import { index as usersIndex } from '@/routes/admin/users';
import type { ManagedUser } from '@/types';

defineProps<{
    users: ManagedUser[];
}>();

const { t } = useTranslations();
const { locale } = useLocale();
const { can } = usePermissions();
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-4 pt-4 pb-3 sm:px-6 dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div class="mb-4 flex items-center justify-between gap-2">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                {{ t('Latest users') }}
            </h3>
            <Link
                v-if="can('users.view-any')"
                :href="usersIndex()"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
            >
                {{ t('See all') }}
            </Link>
        </div>

        <div
            v-if="users.length"
            class="custom-scrollbar max-w-full overflow-x-auto"
        >
            <table class="min-w-full">
                <thead>
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <th class="th-cell">{{ t('User') }}</th>
                        <th class="th-cell">{{ t('Roles') }}</th>
                        <th class="th-cell">{{ t('Joined') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users"
                        :key="user.id"
                        class="border-t border-gray-100 dark:border-gray-800"
                    >
                        <td class="px-5 py-3 whitespace-nowrap sm:px-6">
                            <div class="flex items-center gap-3">
                                <BaseAvatar :name="user.name" />
                                <div>
                                    <p
                                        class="text-theme-sm font-medium text-gray-800 dark:text-white/90"
                                    >
                                        {{ user.name }}
                                    </p>
                                    <span
                                        class="text-theme-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ user.email }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="td-cell">
                            <div class="flex flex-wrap gap-1">
                                <BaseBadge
                                    v-for="role in user.roles"
                                    :key="role"
                                >
                                    {{ t(role) }}
                                </BaseBadge>
                                <span v-if="!user.roles.length">—</span>
                            </div>
                        </td>
                        <td class="td-cell">
                            {{ formatDate(user.created_at, locale) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else :message="t('No users yet.')" />
    </div>
</template>
