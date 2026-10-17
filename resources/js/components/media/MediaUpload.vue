<script setup lang="ts">
/**
 * A media field built on FilePond and Spatie Media Library, similar to
 * Filament's SpatieMediaLibraryFileUpload.
 *
 * Files are uploaded to a temporary location as soon as they are dropped. The
 * v-model collects the temporary upload ids, the media picked from the media
 * library and the existing media to remove; the server applies them when the
 * form is saved.
 */
import type { FilePondInitialFile, FilePondServerConfigProps } from 'filepond';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css';
import { Images, X } from '@lucide/vue';
import { computed, ref, useTemplateRef } from 'vue';
import vueFilePond from 'vue-filepond';
import BaseButton from '@/components/base/BaseButton.vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { jsonHeaders } from '@/lib/http';
import {
    destroy as destroyUpload,
    store as storeUpload,
} from '@/routes/admin/uploads';
import type { MediaInput, MediaItem } from '@/types';

const FilePond = vueFilePond(
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize,
    FilePondPluginImagePreview,
);

const props = withDefaults(
    defineProps<{
        media?: MediaItem[];
        acceptedFileTypes?: string[];
        maxFileSize?: string;
        multiple?: boolean;
        maxFiles?: number;
        allowLibrary?: boolean;
        disabled?: boolean;
    }>(),
    {
        media: () => [],
        acceptedFileTypes: () => [
            'image/png',
            'image/jpeg',
            'image/webp',
            'image/gif',
        ],
        maxFileSize: '10MB',
        multiple: false,
        maxFiles: undefined,
        allowLibrary: true,
        disabled: false,
    },
);

const emit = defineEmits<{
    processed: [];
}>();

const model = defineModel<MediaInput>({
    default: () => ({ uploads: [], library: [], removed: [] }),
});

/**
 * True while files are still being uploaded, so the form can wait for them.
 */
const busy = defineModel<boolean>('busy', { default: false });

const { t } = useTranslations();
const { can } = usePermissions();

const pond = useTemplateRef<{ removeFiles: () => void }>('pond');
const pickerOpen = ref(false);
const picked = ref<MediaItem[]>([]);

const initialFiles: FilePondInitialFile[] = props.media.map((item) => ({
    source: String(item.id),
    options: { type: 'local' },
}));

const labels = computed(() => ({
    labelIdle: `${t('Drag & drop your files or')} <span class="filepond--label-action">${t('Browse')}</span>`,
    labelFileProcessing: t('Uploading'),
    labelFileProcessingComplete: t('Upload complete'),
    labelFileProcessingAborted: t('Upload cancelled'),
    labelFileProcessingError: t('Upload failed'),
    labelFileLoading: t('Loading'),
    labelTapToCancel: t('tap to cancel'),
    labelTapToRetry: t('tap to retry'),
    labelTapToUndo: t('tap to undo'),
    labelFileTypeNotAllowed: t('Invalid file type'),
    fileValidateTypeLabelExpectedTypes: t('Allowed: {allTypes}'),
    labelMaxFileSizeExceeded: t('File is too large'),
    labelMaxFileSize: t('Maximum file size is {filesize}'),
    labelButtonRemoveItem: t('Remove'),
    labelButtonAbortItemProcessing: t('Cancel'),
    labelButtonUndoItemProcessing: t('Undo'),
    labelButtonRetryItemProcessing: t('Retry'),
}));

function errorMessage(responseText: string): string {
    try {
        const response = JSON.parse(responseText) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        return (
            response.errors?.file?.[0] ?? response.message ?? t('Upload failed')
        );
    } catch {
        return t('Upload failed');
    }
}

const server: FilePondServerConfigProps['server'] = {
    process: (_fieldName, file, _metadata, load, error, progress, abort) => {
        const request = new XMLHttpRequest();
        const data = new FormData();

        data.append('file', file, file.name);

        request.open('POST', storeUpload.url());

        Object.entries(jsonHeaders()).forEach(([name, value]) =>
            request.setRequestHeader(name, value),
        );

        request.upload.onprogress = (event) =>
            progress(event.lengthComputable, event.loaded, event.total);

        request.onload = () => {
            if (request.status !== 201) {
                error(errorMessage(request.responseText));

                return;
            }

            const { id } = JSON.parse(request.responseText) as { id: string };

            model.value = {
                ...model.value,
                uploads: [...model.value.uploads, id],
            };
            load(id);
        };

        request.onerror = () => error(t('Upload failed'));
        request.send(data);

        return {
            abort: () => {
                request.abort();
                abort();
            },
        };
    },
    revert: (uploadId: string, load, error) => {
        model.value = {
            ...model.value,
            uploads: model.value.uploads.filter((id) => id !== uploadId),
        };

        fetch(destroyUpload.url({ temporaryUpload: uploadId }), {
            method: 'DELETE',
            headers: jsonHeaders(),
        })
            .then(() => load())
            .catch(() => error(t('Upload failed')));
    },
    load: (source: string, load, error) => {
        const item = props.media.find(
            (mediaItem) => String(mediaItem.id) === source,
        );

        if (!item) {
            error(t('Loading'));

            return;
        }

        fetch(item.url)
            .then((response) => response.blob())
            .then((blob) =>
                load(
                    new File([blob], item.file_name, { type: item.mime_type }),
                ),
            )
            .catch(() => error(t('Loading')));
    },
    remove: (source: string, load) => {
        model.value = {
            ...model.value,
            removed: [...model.value.removed, Number(source)],
        };
        load();
    },
};

let activeUploads = 0;

function trackUpload(change: 1 | -1): void {
    activeUploads = Math.max(0, activeUploads + change);
    busy.value = activeUploads > 0;
}

function selectFromLibrary(items: MediaItem[]): void {
    if (!props.multiple) {
        // A single file field keeps one file: the picked one replaces the rest.
        pond.value?.removeFiles();
        picked.value = items.slice(0, 1);
    } else {
        picked.value = [
            ...picked.value,
            ...items.filter(
                (item) =>
                    !picked.value.some(
                        (pickedItem) => pickedItem.id === item.id,
                    ),
            ),
        ];
    }

    model.value = {
        ...model.value,
        library: picked.value.map((item) => item.id),
    };
}

function removePicked(item: MediaItem): void {
    picked.value = picked.value.filter(
        (pickedItem) => pickedItem.id !== item.id,
    );
    model.value = {
        ...model.value,
        library: picked.value.map((pickedItem) => pickedItem.id),
    };
}

function handleAddFile(): void {
    // Uploading a new file to a single file field replaces a picked one.
    if (!props.multiple && picked.value.length) {
        picked.value = [];
        model.value = { ...model.value, library: [] };
    }
}
</script>

<template>
    <div class="space-y-3">
        <FilePond
            ref="pond"
            :files="initialFiles"
            :server="server"
            :allow-multiple="multiple"
            :max-files="multiple ? maxFiles : 1"
            :allow-replace="!multiple"
            :accepted-file-types="acceptedFileTypes"
            :max-file-size="maxFileSize"
            :disabled="disabled"
            :credits="false"
            image-preview-height="170"
            v-bind="labels"
            @addfile="handleAddFile"
            @processfilestart="trackUpload(1)"
            @processfile="trackUpload(-1)"
            @processfileabort="trackUpload(-1)"
            @processfiles="emit('processed')"
        />

        <div v-if="picked.length" class="flex flex-wrap gap-3">
            <div
                v-for="item in picked"
                :key="item.id"
                class="relative size-24 overflow-hidden rounded-xl border border-brand-500 bg-gray-100 dark:bg-gray-800"
            >
                <img
                    :src="item.url"
                    :alt="item.name"
                    class="size-full object-cover"
                />
                <button
                    type="button"
                    :title="t('Remove')"
                    class="absolute end-1 top-1 flex size-6 items-center justify-center rounded-full bg-gray-900/70 text-white hover:bg-gray-900"
                    @click="removePicked(item)"
                >
                    <X class="size-3.5" />
                </button>
            </div>
        </div>

        <BaseButton
            v-if="allowLibrary && can('media.view-any') && !disabled"
            variant="outline"
            size="sm"
            @click="pickerOpen = true"
        >
            <Images class="size-4" />
            {{ t('Choose from library') }}
        </BaseButton>

        <MediaPicker
            v-if="allowLibrary"
            v-model:open="pickerOpen"
            :multiple="multiple"
            @select="selectFromLibrary"
        />
    </div>
</template>
