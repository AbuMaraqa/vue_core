<script setup lang="ts">
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { ref } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import { useTranslations } from '@/composables/useTranslations';

const emit = defineEmits<{
    success: [];
}>();

const { t } = useTranslations();

const getDefaultPasskeyName = () => {
    const ua = navigator.userAgent;

    const browser = [
        { pattern: /Edg|Edge/, name: 'Edge' },
        { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
        { pattern: /Firefox|FxiOS/, name: 'Firefox' },
        { pattern: /Chrome|CriOS/, name: 'Chrome' },
        { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    const os = [
        { pattern: /iPhone/, name: 'iPhone' },
        { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
        { pattern: /Android/, name: 'Android' },
        { pattern: /Mac/, name: 'Mac' },
        { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    return [browser, os].filter(Boolean).join(' on ') || '';
};

const name = ref(getDefaultPasskeyName());
const showForm = ref(false);

const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
        name.value = '';
        showForm.value = false;
        emit('success');
    },
});

const handleSubmit = async () => {
    if (!name.value.trim()) {
        return;
    }

    await register(name.value);
};

const handleCancel = () => {
    showForm.value = false;
    name.value = '';
};
</script>

<template>
    <p v-if="!isSupported" class="text-sm text-gray-500 dark:text-gray-400">
        {{ t('Passkeys are not supported in this browser.') }}
    </p>

    <BaseButton
        v-else-if="!showForm"
        variant="outline"
        @click="showForm = true"
    >
        {{ t('Add passkey') }}
    </BaseButton>

    <form
        v-else
        class="space-y-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]"
        @submit.prevent="handleSubmit"
    >
        <div>
            <BaseLabel for="passkey-name">{{ t('Passkey name') }}</BaseLabel>
            <BaseInput
                id="passkey-name"
                v-model="name"
                v-focus
                type="text"
                :placeholder="t('e.g., MacBook Pro, iPhone')"
            />
            <p class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400">
                {{ t('A name helps you identify this passkey later.') }}
            </p>
        </div>

        <BaseInputError v-if="error" :message="error" />

        <div class="flex gap-3">
            <BaseButton
                type="submit"
                size="sm"
                :loading="isLoading"
                :disabled="!name.trim()"
            >
                {{ t('Register passkey') }}
            </BaseButton>
            <BaseButton variant="ghost" size="sm" @click="handleCancel">
                {{ t('Cancel') }}
            </BaseButton>
        </div>
    </form>
</template>
