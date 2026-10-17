<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import Download from '~icons/solar/download-minimalistic-linear';
import Plus from '~icons/solar/add-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { ref } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseIconButton from '@/components/base/BaseIconButton.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatBytes, formatDate, formatRelative } from '@/lib/format';
import { destroy, download, store } from '@/routes/admin/backups';
import type { BackupFile } from '@/types';

defineProps<{
    backups: BackupFile[];
    can: {
        create: boolean;
        download: boolean;
        delete: boolean;
    };
}>();

const { t } = useTranslations();
const { locale } = useLocale();

const deletion = useConfirmDelete((backup: BackupFile) =>
    destroy({ backup: backup.id }),
);

const choosingType = ref(false);
const starting = ref(false);

function createBackup(type: 'database' | 'full'): void {
    router.visit(store(), {
        data: { type },
        onStart: () => (starting.value = true),
        onFinish: () => {
            starting.value = false;
            choosingType.value = false;
        },
    });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Backups')"
            :description="
                t(
                    'Copies of the database and files, to restore the system after a failure.',
                )
            "
            :breadcrumbs="[{ title: t('Backups') }]"
        >
            <template #actions>
                <BaseButton v-if="can.create" @click="choosingType = true">
                    <Plus class="size-5" />
                    {{ t('Create backup') }}
                </BaseButton>
            </template>
        </PageHeader>

        <DataCard>
            <div class="custom-scrollbar max-w-full overflow-x-auto">
                <table v-if="backups.length" class="min-w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-100 dark:border-gray-800"
                        >
                            <th class="th-cell">{{ t('File') }}</th>
                            <th class="th-cell">{{ t('Disk') }}</th>
                            <th class="th-cell">{{ t('Size') }}</th>
                            <th class="th-cell">{{ t('Date') }}</th>
                            <th class="th-cell text-end">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr v-for="backup in backups" :key="backup.id">
                            <td
                                class="td-cell font-mono text-gray-800 dark:text-white/90"
                                dir="ltr"
                            >
                                {{ backup.name }}
                            </td>
                            <td class="td-cell">{{ backup.disk }}</td>
                            <td class="td-cell">
                                {{ formatBytes(backup.size, locale) }}
                            </td>
                            <td
                                class="td-cell"
                                :title="
                                    formatDate(backup.created_at, locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })
                                "
                            >
                                {{ formatRelative(backup.created_at, locale) }}
                            </td>
                            <td class="td-cell">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <a
                                        v-if="can.download"
                                        :href="
                                            download.url({ backup: backup.id })
                                        "
                                        :title="t('Download')"
                                        :aria-label="t('Download')"
                                        class="flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-brand-500 dark:text-gray-400 dark:hover:bg-white/5"
                                    >
                                        <Download class="size-4" />
                                    </a>
                                    <BaseIconButton
                                        v-if="can.delete"
                                        color="danger"
                                        :label="t('Delete')"
                                        @click="deletion.ask(backup)"
                                    >
                                        <Trash2 />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState v-else :message="t('No backups yet.')" />
            </div>
        </DataCard>

        <BaseModal
            v-model:open="choosingType"
            :title="t('Create backup')"
            max-width="sm"
        >
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{
                    t(
                        'The backup runs in the background. You will be notified when it is ready.',
                    )
                }}
            </p>

            <template #footer>
                <BaseButton
                    variant="outline"
                    size="sm"
                    :disabled="starting"
                    @click="createBackup('full')"
                >
                    {{ t('Database and files') }}
                </BaseButton>
                <BaseButton
                    size="sm"
                    :loading="starting"
                    @click="createBackup('database')"
                >
                    {{ t('Database only') }}
                </BaseButton>
            </template>
        </BaseModal>

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete backup')"
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
