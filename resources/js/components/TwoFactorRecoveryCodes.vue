<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from '@lucide/vue';
import { nextTick, onMounted, ref, useTemplateRef } from 'vue';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import { useTranslations } from '@/composables/useTranslations';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

const { t } = useTranslations();
const { recoveryCodesList, fetchRecoveryCodes, errors } = useTwoFactorAuth();
const isRecoveryCodesVisible = ref<boolean>(false);
const recoveryCodeSectionRef = useTemplateRef('recoveryCodeSectionRef');

const toggleRecoveryCodesVisibility = async () => {
    if (!isRecoveryCodesVisible.value && !recoveryCodesList.value.length) {
        await fetchRecoveryCodes();
    }

    isRecoveryCodesVisible.value = !isRecoveryCodesVisible.value;

    if (isRecoveryCodesVisible.value) {
        await nextTick();
        recoveryCodeSectionRef.value?.scrollIntoView({ behavior: 'smooth' });
    }
};

onMounted(async () => {
    if (!recoveryCodesList.value.length) {
        await fetchRecoveryCodes();
    }
});
</script>

<template>
    <div class="rounded-xl border border-gray-200 p-5 dark:border-gray-800">
        <h4
            class="flex items-center gap-3 font-medium text-gray-800 dark:text-white/90"
        >
            <LockKeyhole class="size-4" />
            {{ t('2FA recovery codes') }}
        </h4>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{
                t(
                    'Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.',
                )
            }}
        </p>

        <div
            class="mt-4 flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between"
        >
            <BaseButton
                variant="outline"
                size="sm"
                @click="toggleRecoveryCodesVisibility"
            >
                <component
                    :is="isRecoveryCodesVisible ? EyeOff : Eye"
                    class="size-4"
                />
                {{
                    isRecoveryCodesVisible
                        ? t('Hide recovery codes')
                        : t('View recovery codes')
                }}
            </BaseButton>

            <Form
                v-if="isRecoveryCodesVisible && recoveryCodesList.length"
                v-bind="regenerateRecoveryCodes.form()"
                method="post"
                :options="{ preserveScroll: true }"
                @success="fetchRecoveryCodes"
                #default="{ processing }"
            >
                <BaseButton
                    type="submit"
                    variant="ghost"
                    size="sm"
                    :loading="processing"
                >
                    <RefreshCw class="size-4" />
                    {{ t('Regenerate codes') }}
                </BaseButton>
            </Form>
        </div>

        <div v-if="isRecoveryCodesVisible" class="mt-4">
            <BaseAlert
                v-if="errors?.length"
                variant="error"
                :messages="errors"
            />
            <div v-else class="space-y-3">
                <div
                    ref="recoveryCodeSectionRef"
                    class="grid gap-1 rounded-lg bg-gray-100 p-4 font-mono text-sm text-gray-800 dark:bg-gray-800 dark:text-white/90"
                    dir="ltr"
                >
                    <div v-if="!recoveryCodesList.length" class="space-y-2">
                        <div
                            v-for="n in 8"
                            :key="n"
                            class="h-4 animate-pulse rounded bg-gray-200 dark:bg-gray-700"
                        />
                    </div>
                    <div
                        v-for="(code, index) in recoveryCodesList"
                        v-else
                        :key="index"
                    >
                        {{ code }}
                    </div>
                </div>
                <p class="text-theme-xs text-gray-500 dark:text-gray-400">
                    {{
                        t(
                            'Each recovery code can be used once to access your account and will be removed after use. If you need more, regenerate the codes.',
                        )
                    }}
                </p>
            </div>
        </div>
    </div>
</template>
