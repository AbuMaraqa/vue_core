<script setup lang="ts">
import { KeyRound, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Passkey } from '@/types/auth';

const props = defineProps<{
    passkey: Passkey;
}>();

const emit = defineEmits<{
    remove: [id: number, onError: () => void];
}>();

const { t } = useTranslations();

const isConfirming = ref(false);
const isDeleting = ref(false);

const handleDelete = () => {
    isDeleting.value = true;
    emit('remove', props.passkey.id, () => {
        isDeleting.value = false;
    });
};
</script>

<template>
    <div
        class="flex items-center justify-between border-b border-gray-200 p-4 last:border-b-0 dark:border-gray-800"
    >
        <div class="flex items-center gap-4">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
            >
                <KeyRound class="size-5" />
            </div>
            <div class="space-y-1">
                <div class="flex items-center gap-2.5">
                    <p class="font-medium text-gray-800 dark:text-white/90">
                        {{ passkey.name }}
                    </p>
                    <span
                        v-if="passkey.authenticator"
                        class="rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium tracking-wide text-gray-500 uppercase dark:bg-gray-800 dark:text-gray-400"
                    >
                        {{ passkey.authenticator }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Added :time', { time: passkey.created_at_diff }) }}
                    <template v-if="passkey.last_used_at_diff">
                        <span class="mx-1 opacity-50">/</span>
                        {{
                            t('Last used :time', {
                                time: passkey.last_used_at_diff,
                            })
                        }}
                    </template>
                </p>
            </div>
        </div>

        <button
            type="button"
            :title="t('Remove')"
            class="flex size-9 items-center justify-center rounded-lg text-error-500 hover:bg-error-50 dark:hover:bg-error-500/15"
            @click="isConfirming = true"
        >
            <Trash2 class="size-4" />
        </button>

        <ConfirmDialog
            v-model:open="isConfirming"
            :title="t('Remove passkey')"
            :message="
                t(
                    'Are you sure you want to remove the \':name\' passkey? You will no longer be able to use it to sign in.',
                    { name: passkey.name },
                )
            "
            :confirm-label="t('Remove passkey')"
            :processing="isDeleting"
            @confirm="handleDelete"
        />
    </div>
</template>
