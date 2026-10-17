<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import BaseModal from '@/components/base/BaseModal.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const isOpen = ref(false);
const form = useForm({ password: '' });

function close(): void {
    isOpen.value = false;
    form.reset();
    form.clearErrors();
}

function deleteAccount(): void {
    form.submit(ProfileController.destroy(), {
        preserveScroll: true,
        onSuccess: () => close(),
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <BaseCard
        :title="t('Delete account')"
        :description="t('Delete your account and all of its resources')"
    >
        <BaseAlert variant="error" :title="t('Warning')">
            {{ t('Please proceed with caution, this cannot be undone.') }}
        </BaseAlert>

        <div class="mt-5">
            <BaseButton
                variant="danger"
                data-test="delete-user-button"
                @click="isOpen = true"
            >
                {{ t('Delete account') }}
            </BaseButton>
        </div>

        <BaseModal
            :open="isOpen"
            :title="t('Are you sure you want to delete your account?')"
            @update:open="(open) => !open && close()"
        >
            <form class="space-y-5" @submit.prevent="deleteAccount">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{
                        t(
                            'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
                        )
                    }}
                </p>

                <div>
                    <BaseLabel for="delete-password" class="sr-only">
                        {{ t('Password') }}
                    </BaseLabel>
                    <PasswordInput
                        id="delete-password"
                        v-model="form.password"
                        :placeholder="t('Password')"
                        :invalid="Boolean(form.errors.password)"
                    />
                    <BaseInputError :message="form.errors.password" />
                </div>

                <div class="flex justify-end gap-3">
                    <BaseButton variant="outline" size="sm" @click="close">
                        {{ t('Cancel') }}
                    </BaseButton>
                    <BaseButton
                        type="submit"
                        variant="danger"
                        size="sm"
                        :loading="form.processing"
                        data-test="confirm-delete-user-button"
                    >
                        {{ t('Delete account') }}
                    </BaseButton>
                </div>
            </form>
        </BaseModal>
    </BaseCard>
</template>
