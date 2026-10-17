<script setup lang="ts">
import TriangleAlert from '~icons/solar/danger-triangle-linear';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import { useTranslations } from '@/composables/useTranslations';

withDefaults(
    defineProps<{
        title: string;
        message: string;
        confirmLabel?: string;
        processing?: boolean;
    }>(),
    { confirmLabel: undefined, processing: false },
);

const emit = defineEmits<{
    confirm: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useTranslations();
</script>

<template>
    <BaseModal v-model:open="open" max-width="sm">
        <div class="text-center">
            <div
                class="mx-auto mb-5 flex size-14 items-center justify-center rounded-full bg-error-50 text-error-500 dark:bg-error-500/15"
            >
                <TriangleAlert class="size-7" />
            </div>
            <h4
                class="mb-2 text-xl font-semibold text-gray-800 dark:text-white/90"
            >
                {{ title }}
            </h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ message }}
            </p>
        </div>

        <template #footer>
            <BaseButton variant="outline" size="sm" @click="open = false">
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton
                variant="danger"
                size="sm"
                :loading="processing"
                @click="emit('confirm')"
            >
                {{ confirmLabel ?? t('Delete') }}
            </BaseButton>
        </template>
    </BaseModal>
</template>
