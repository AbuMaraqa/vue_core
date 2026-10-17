<script setup lang="ts">
import Check from '~icons/solar/check-linear';
import Copy from '~icons/solar/copy-linear';
import ExternalLink from '~icons/solar/square-arrow-right-up-linear';
import FileText from '~icons/solar/document-text-linear';
import Trash2 from '~icons/solar/trash-bin-minimalistic-linear';
import { useClipboard } from '@vueuse/core';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/lib/format';
import type { MediaItem } from '@/types';

defineProps<{
    item: MediaItem | null;
}>();

const emit = defineEmits<{
    delete: [item: MediaItem];
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useTranslations();
const { locale } = useLocale();
const { copy, copied } = useClipboard();
</script>

<template>
    <BaseModal v-model:open="open" :title="item?.name" max-width="lg">
        <div v-if="item" class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
            <div
                class="flex aspect-square items-center justify-center overflow-hidden rounded-2xl bg-gray-100 dark:bg-gray-800"
            >
                <img
                    v-if="item.is_image"
                    :src="item.url"
                    :alt="item.name"
                    class="max-h-full max-w-full object-contain"
                />
                <FileText v-else class="size-16 text-gray-400" />
            </div>

            <div class="space-y-4">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('File name') }}
                        </dt>
                        <dd
                            class="truncate font-medium text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ item.file_name }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Type') }}
                        </dt>
                        <dd
                            class="font-medium text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ item.mime_type }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Size') }}
                        </dt>
                        <dd
                            class="font-medium text-gray-800 dark:text-white/90"
                            dir="ltr"
                        >
                            {{ item.human_size }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Used in') }}
                        </dt>
                        <dd class="flex items-center gap-2">
                            <BaseBadge color="light">{{
                                t(item.owner.type)
                            }}</BaseBadge>
                            <BaseBadge>{{ t(item.collection) }}</BaseBadge>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ t('Uploaded') }}
                        </dt>
                        <dd
                            class="font-medium text-gray-800 dark:text-white/90"
                        >
                            {{
                                formatDate(item.created_at, locale, {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                })
                            }}
                        </dd>
                    </div>
                </dl>

                <div class="flex gap-2">
                    <input
                        :value="item.url"
                        readonly
                        dir="ltr"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <BaseButton
                        variant="outline"
                        size="sm"
                        :title="t('Copy link')"
                        @click="copy(item.url)"
                    >
                        <Check v-if="copied" class="size-4 text-success-500" />
                        <Copy v-else class="size-4" />
                    </BaseButton>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <a
                        :href="item.url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
                    >
                        <ExternalLink class="size-4" />
                        {{ t('Open') }}
                    </a>
                    <BaseButton
                        v-if="item.can.delete"
                        variant="danger"
                        size="sm"
                        @click="emit('delete', item)"
                    >
                        <Trash2 class="size-4" />
                        {{ t('Delete') }}
                    </BaseButton>
                </div>
            </div>
        </div>
    </BaseModal>
</template>
