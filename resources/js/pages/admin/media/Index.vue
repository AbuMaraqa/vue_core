<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import FileText from '~icons/solar/document-text-linear';
import Upload from '~icons/solar/upload-linear';
import X from '~icons/solar/close-linear';
import { ref } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import DataCard from '@/components/common/DataCard.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import MediaDetails from '@/components/media/MediaDetails.vue';
import MediaUpload from '@/components/media/MediaUpload.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { useIndexFilters } from '@/composables/useIndexFilters';
import { useTranslations } from '@/composables/useTranslations';
import { destroy, index, store } from '@/routes/admin/media';
import type { MediaInput, MediaItem, Paginated } from '@/types';
import { emptyMediaInput } from '@/lib/media';

const props = defineProps<{
    media: Paginated<MediaItem>;
    collections: string[];
    filters: {
        search: string | null;
        type: string | null;
        collection: string | null;
    };
    canUpload: boolean;
}>();

const { t } = useTranslations();

const { filters } = useIndexFilters(index, {
    search: props.filters.search,
    type: props.filters.type,
    collection: props.filters.collection,
});

/*
 * Uploading: files are sent to a temporary location by the MediaUpload
 * component, then saved into the library once every file is uploaded.
 */
const isUploading = ref(false);
const uploaderKey = ref(0);
const uploads = ref<MediaInput>(emptyMediaInput());

function saveUploads(): void {
    if (!uploads.value.uploads.length) {
        return;
    }

    router.post(
        store.url(),
        { uploads: uploads.value.uploads },
        {
            preserveScroll: true,
            onSuccess: () => {
                uploads.value = emptyMediaInput();
                uploaderKey.value++;
            },
        },
    );
}

/*
 * Details and deletion.
 */
const selected = ref<MediaItem | null>(null);
const detailsOpen = ref(false);
const deletion = useConfirmDelete(
    (item: MediaItem) => destroy({ media: item.id }),
    { onSuccess: () => (detailsOpen.value = false) },
);

function showDetails(item: MediaItem): void {
    selected.value = item;
    detailsOpen.value = true;
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Media Library')"
            :description="t('Every image and file used across the system.')"
            :breadcrumbs="[{ title: t('Media Library') }]"
        >
            <template #actions>
                <BaseButton
                    v-if="canUpload"
                    @click="isUploading = !isUploading"
                >
                    <X v-if="isUploading" class="size-5" />
                    <Upload v-else class="size-5" />
                    {{ isUploading ? t('Close') : t('Upload files') }}
                </BaseButton>
            </template>
        </PageHeader>

        <div
            v-if="isUploading"
            class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6 dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <MediaUpload
                :key="uploaderKey"
                v-model="uploads"
                multiple
                :max-files="20"
                :allow-library="false"
                @processed="saveUploads"
            />
        </div>

        <DataCard :pagination="media">
            <template #search>
                <SearchInput
                    v-model="filters.search"
                    :placeholder="t('Search by file name...')"
                />
            </template>
            <template #filters>
                <BaseSelect v-model="filters.type" class="sm:w-44">
                    <option value="">{{ t('All types') }}</option>
                    <option value="image">{{ t('Images') }}</option>
                    <option value="document">{{ t('Documents') }}</option>
                </BaseSelect>
                <BaseSelect v-model="filters.collection" class="sm:w-44">
                    <option value="">{{ t('All collections') }}</option>
                    <option
                        v-for="name in collections"
                        :key="name"
                        :value="name"
                    >
                        {{ t(name) }}
                    </option>
                </BaseSelect>
            </template>

            <div
                v-if="media.data.length"
                class="grid grid-cols-2 gap-4 border-t border-gray-100 p-5 sm:grid-cols-3 sm:p-6 md:grid-cols-4 xl:grid-cols-6 dark:border-gray-800"
            >
                <button
                    v-for="item in media.data"
                    :key="item.id"
                    type="button"
                    class="group overflow-hidden rounded-xl border border-gray-200 text-start transition hover:border-brand-300 hover:shadow-theme-md dark:border-gray-800 dark:hover:border-brand-800"
                    @click="showDetails(item)"
                >
                    <div
                        class="flex aspect-square items-center justify-center overflow-hidden bg-gray-100 dark:bg-gray-800"
                    >
                        <img
                            v-if="item.is_image"
                            :src="item.url"
                            :alt="item.name"
                            loading="lazy"
                            class="size-full object-cover transition duration-300 group-hover:scale-105"
                        />
                        <FileText v-else class="size-10 text-gray-400" />
                    </div>
                    <div class="px-3 py-2.5">
                        <p
                            class="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90"
                        >
                            {{ item.name }}
                        </p>
                        <p
                            class="text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ item.human_size }} · {{ t(item.collection) }}
                        </p>
                    </div>
                </button>
            </div>
            <EmptyState v-else :message="t('No files found.')" />
        </DataCard>

        <MediaDetails
            v-model:open="detailsOpen"
            :item="selected"
            @delete="(item) => deletion.ask(item)"
        />

        <ConfirmDialog
            :open="deletion.target !== null"
            :title="t('Delete file')"
            :message="
                t(
                    'Are you sure you want to delete :name? It will also be removed from every place it is used.',
                    { name: deletion.target?.name ?? '' },
                )
            "
            :processing="deletion.processing"
            @update:open="(open) => !open && deletion.cancel()"
            @confirm="deletion.confirm"
        />
    </div>
</template>
