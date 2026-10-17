<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import BaseCard from '@/components/base/BaseCard.vue';
import PasskeyItem from '@/components/PasskeyItem.vue';
import PasskeyRegister from '@/components/PasskeyRegister.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

withDefaults(defineProps<Props>(), {
    canManagePasskeys: false,
    passkeys: () => [],
});

const { t } = useTranslations();

const handleDelete = (id: number, onError: () => void) => {
    router.delete(destroy.url(id), {
        preserveScroll: true,
        onError,
    });
};

const handleRegisterSuccess = () => {
    router.reload();
};
</script>

<template>
    <BaseCard
        v-if="canManagePasskeys"
        :title="t('Passkeys')"
        :description="t('Manage your passkeys for passwordless sign-in')"
    >
        <div class="space-y-5">
            <div
                class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"
            >
                <template v-if="passkeys.length">
                    <PasskeyItem
                        v-for="passkey in passkeys"
                        :key="passkey.id"
                        :passkey="passkey"
                        @remove="handleDelete"
                    />
                </template>

                <div v-else class="p-8 text-center">
                    <div
                        class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                    >
                        <KeyRound class="size-7" />
                    </div>
                    <p class="font-medium text-gray-800 dark:text-white/90">
                        {{ t('No passkeys yet') }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Add a passkey to sign in without a password') }}
                    </p>
                </div>
            </div>

            <PasskeyRegister @success="handleRegisterSuccess" />
        </div>
    </BaseCard>
</template>
