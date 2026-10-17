<script setup lang="ts">
import Eye from '~icons/solar/eye-linear';
import EyeOff from '~icons/solar/eye-closed-linear';
import { ref, useTemplateRef } from 'vue';
import BaseInput from '@/components/base/BaseInput.vue';
import { useTranslations } from '@/composables/useTranslations';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    autofocus?: boolean;
    invalid?: boolean;
}>();

const { t } = useTranslations();
const showPassword = ref(false);
const inputRef = useTemplateRef('inputRef');

defineExpose({
    $el: inputRef,
    focus: () => inputRef.value?.$el?.focus(),
});
</script>

<template>
    <div class="relative">
        <BaseInput
            ref="inputRef"
            v-focus="props.autofocus"
            v-bind="$attrs"
            :type="showPassword ? 'text' : 'password'"
            :invalid="invalid"
            class="pe-11"
        />
        <button
            type="button"
            class="absolute end-4 top-1/2 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400"
            :aria-label="showPassword ? t('Hide password') : t('Show password')"
            :tabindex="-1"
            @click="showPassword = !showPassword"
        >
            <EyeOff v-if="showPassword" class="size-5" />
            <Eye v-else class="size-5" />
        </button>
    </div>
</template>
