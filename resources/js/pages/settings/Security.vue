<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();

const { t } = useTranslations();
</script>

<template>
    <div>
        <PageHeader
            :title="t('Security settings')"
            :breadcrumbs="[{ title: t('Security settings') }]"
        />

        <div class="space-y-6">
            <BaseCard
                :title="t('Update password')"
                :description="
                    t(
                        'Ensure your account is using a long, random password to stay secure',
                    )
                "
            >
                <Form
                    v-bind="SecurityController.update.form()"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    :reset-on-error="[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]"
                    class="space-y-5"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                        <div>
                            <BaseLabel for="current_password" required>
                                {{ t('Current password') }}
                            </BaseLabel>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                autocomplete="current-password"
                                :invalid="Boolean(errors.current_password)"
                            />
                            <BaseInputError
                                :message="errors.current_password"
                            />
                        </div>
                        <div>
                            <BaseLabel for="password" required>
                                {{ t('New password') }}
                            </BaseLabel>
                            <PasswordInput
                                id="password"
                                name="password"
                                autocomplete="new-password"
                                :passwordrules="props.passwordRules"
                                :invalid="Boolean(errors.password)"
                            />
                            <BaseInputError :message="errors.password" />
                        </div>
                        <div>
                            <BaseLabel for="password_confirmation" required>
                                {{ t('Confirm password') }}
                            </BaseLabel>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autocomplete="new-password"
                                :passwordrules="props.passwordRules"
                            />
                            <BaseInputError
                                :message="errors.password_confirmation"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <BaseButton
                            type="submit"
                            :loading="processing"
                            data-test="update-password-button"
                        >
                            {{ t('Save changes') }}
                        </BaseButton>
                    </div>
                </Form>
            </BaseCard>

            <ManageTwoFactor
                :canManageTwoFactor="canManageTwoFactor"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />

            <ManagePasskeys
                :canManagePasskeys="canManagePasskeys"
                :passkeys="passkeys"
            />
        </div>
    </div>
</template>
