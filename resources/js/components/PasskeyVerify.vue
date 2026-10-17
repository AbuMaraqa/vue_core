<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { KeyRound, LoaderCircle } from '@lucide/vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
};

const props = defineProps<Props>();

const { t } = useTranslations();

const { verify, isLoading, error, isSupported } = usePasskeyVerify({
    ...(props.routes
        ? {
              routes: {
                  options: props.routes.options.url,
                  submit: props.routes.submit.url,
              },
          }
        : {}),
    onSuccess: (response) => {
        router.visit(response.redirect ?? home.url());
    },
});
</script>

<template>
    <div v-if="isSupported">
        <button
            type="button"
            class="inline-flex w-full items-center justify-center gap-3 rounded-lg bg-gray-100 px-4 py-3 text-sm font-normal text-gray-700 transition-colors hover:bg-gray-200 hover:text-gray-800 disabled:opacity-60 dark:bg-white/5 dark:text-white/90 dark:hover:bg-white/10"
            :disabled="isLoading"
            @click="verify"
        >
            <LoaderCircle v-if="isLoading" class="size-5 animate-spin" />
            <KeyRound v-else class="size-5" />
            {{
                isLoading
                    ? (props.loadingLabel ?? t('Authenticating...'))
                    : (props.label ?? t('Sign in with a passkey'))
            }}
        </button>

        <div v-if="error" class="text-center">
            <BaseInputError :message="error" />
        </div>

        <div class="relative py-3 sm:py-5">
            <div class="absolute inset-0 flex items-center">
                <div
                    class="w-full border-t border-gray-200 dark:border-gray-800"
                />
            </div>
            <div class="relative flex justify-center text-sm">
                <span
                    class="bg-white p-2 text-gray-400 sm:px-5 sm:py-2 dark:bg-gray-900"
                >
                    {{ props.separator ?? t('Or') }}
                </span>
            </div>
        </div>
    </div>
</template>
