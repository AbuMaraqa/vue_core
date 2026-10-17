<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Check, LoaderCircle, Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref, watch } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import { useTranslations } from '@/composables/useTranslations';
import { browse } from '@/routes/admin/media';
import type { MediaItem, Paginated } from '@/types';

const props = withDefaults(
    defineProps<{
        multiple?: boolean;
    }>(),
    { multiple: false },
);

const emit = defineEmits<{
    select: [items: MediaItem[]];
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useTranslations();

const items = ref<MediaItem[]>([]);
const selected = ref<MediaItem[]>([]);
const hasMore = ref(false);

const request = useHttp<
    { filter: { search: string }; page: number },
    Paginated<MediaItem>
>({ filter: { search: '' }, page: 1 });

async function load(page = 1): Promise<void> {
    request.page = page;

    const response = await request.get(browse.url());

    items.value =
        page === 1 ? response.data : [...items.value, ...response.data];
    hasMore.value = response.meta.current_page < response.meta.last_page;
}

function isSelected(item: MediaItem): boolean {
    return selected.value.some((selectedItem) => selectedItem.id === item.id);
}

function toggle(item: MediaItem): void {
    if (isSelected(item)) {
        selected.value = selected.value.filter(
            (selectedItem) => selectedItem.id !== item.id,
        );
    } else {
        selected.value = props.multiple ? [...selected.value, item] : [item];
    }
}

function confirm(): void {
    emit('select', selected.value);
    open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        selected.value = [];
        void load();
    }
});

watchDebounced(
    () => request.filter.search,
    () => void load(),
    { debounce: 350 },
);
</script>

<template>
    <BaseModal v-model:open="open" :title="t('Media Library')" max-width="lg">
        <div class="relative mt-4">
            <Search
                class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-gray-400"
            />
            <BaseInput
                v-model="request.filter.search"
                type="search"
                class="ps-12"
                :placeholder="t('Search...')"
            />
        </div>

        <div class="mt-4 custom-scrollbar max-h-[55vh] overflow-y-auto pe-1">
            <div
                v-if="items.length"
                class="grid grid-cols-3 gap-3 sm:grid-cols-4"
            >
                <button
                    v-for="item in items"
                    :key="item.id"
                    type="button"
                    :title="item.name"
                    :class="[
                        'relative aspect-square overflow-hidden rounded-xl border-2 bg-gray-100 transition dark:bg-gray-800',
                        isSelected(item)
                            ? 'border-brand-500 ring-3 ring-brand-500/20'
                            : 'border-transparent hover:border-gray-300 dark:hover:border-gray-700',
                    ]"
                    @click="toggle(item)"
                >
                    <img
                        :src="item.url"
                        :alt="item.name"
                        loading="lazy"
                        class="size-full object-cover"
                    />
                    <span
                        v-if="isSelected(item)"
                        class="absolute end-2 top-2 flex size-6 items-center justify-center rounded-full bg-brand-500 text-white"
                    >
                        <Check class="size-4" />
                    </span>
                </button>
            </div>

            <div
                v-else-if="request.processing"
                class="flex justify-center py-12"
            >
                <LoaderCircle class="size-6 animate-spin text-gray-400" />
            </div>

            <EmptyState v-else :message="t('No images in the library yet.')" />

            <div v-if="hasMore" class="mt-4 flex justify-center">
                <BaseButton
                    variant="outline"
                    size="sm"
                    :loading="request.processing"
                    @click="load(request.page + 1)"
                >
                    {{ t('Load more') }}
                </BaseButton>
            </div>
        </div>

        <template #footer>
            <BaseButton variant="outline" size="sm" @click="open = false">
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton size="sm" :disabled="!selected.length" @click="confirm">
                {{ t('Choose') }}
            </BaseButton>
        </template>
    </BaseModal>
</template>
