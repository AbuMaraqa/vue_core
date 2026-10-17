<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { onUnmounted, ref } from 'vue';
import BaseBadge from '@/components/base/BaseBadge.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { useTranslations } from '@/composables/useTranslations';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const { t } = useTranslations();
const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <BaseCard
        v-if="canManageTwoFactor"
        :title="t('Two-factor authentication')"
        :description="t('Manage your two-factor authentication settings')"
    >
        <template #actions>
            <BaseBadge :color="twoFactorEnabled ? 'success' : 'light'">
                {{ twoFactorEnabled ? t('Enabled') : t('Disabled') }}
            </BaseBadge>
        </template>

        <div v-if="!twoFactorEnabled" class="space-y-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{
                    t(
                        'When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.',
                    )
                }}
            </p>

            <BaseButton v-if="hasSetupData" @click="showSetupModal = true">
                <ShieldCheck class="size-5" />
                {{ t('Continue setup') }}
            </BaseButton>
            <Form
                v-else
                v-bind="enable.form()"
                @success="showSetupModal = true"
                #default="{ processing }"
            >
                <BaseButton type="submit" :loading="processing">
                    {{ t('Enable 2FA') }}
                </BaseButton>
            </Form>
        </div>

        <div v-else class="space-y-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{
                    t(
                        'You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.',
                    )
                }}
            </p>

            <Form v-bind="disable.form()" #default="{ processing }">
                <BaseButton
                    type="submit"
                    variant="danger"
                    :loading="processing"
                >
                    {{ t('Disable 2FA') }}
                </BaseButton>
            </Form>

            <TwoFactorRecoveryCodes />
        </div>

        <TwoFactorSetupModal
            v-model:isOpen="showSetupModal"
            :requiresConfirmation="requiresConfirmation"
            :twoFactorEnabled="twoFactorEnabled"
        />
    </BaseCard>
</template>
