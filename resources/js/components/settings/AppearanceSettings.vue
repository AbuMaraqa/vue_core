<script setup lang="ts">
import { Check } from '@lucide/vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import MediaUpload from '@/components/media/MediaUpload.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { FontOption, MediaInput, MediaItem } from '@/types';

defineProps<{
    fonts: FontOption[];
    currentLogo: MediaItem | null;
    disabled?: boolean;
    errors: {
        font?: string;
        color?: string;
        logo?: string;
    };
}>();

const font = defineModel<string>('font', { required: true });
const color = defineModel<string>('color', { required: true });
const logo = defineModel<MediaInput>('logo', { required: true });
const logoUploading = defineModel<boolean>('logoUploading', { default: false });

const { t } = useTranslations();

const presetColors = [
    '#465fff',
    '#7a5af8',
    '#ee46bc',
    '#f04438',
    '#fb6514',
    '#12b76a',
    '#0ba5ec',
    '#344054',
];
</script>

<template>
    <BaseCard
        :title="t('Appearance')"
        :description="t('The font and primary color of the whole interface.')"
    >
        <div class="space-y-6">
            <div class="max-w-md">
                <BaseLabel>{{ t('Logo') }}</BaseLabel>
                <MediaUpload
                    v-model="logo"
                    v-model:busy="logoUploading"
                    :media="currentLogo ? [currentLogo] : []"
                    max-file-size="2MB"
                    :disabled="disabled"
                />
                <p
                    class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400"
                >
                    {{ t('PNG, JPG, WEBP or GIF, up to 2 MB.') }}
                </p>
                <BaseInputError :message="errors.logo" />
            </div>

            <div>
                <BaseLabel>{{ t('Font') }}</BaseLabel>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <label
                        v-for="option in fonts"
                        :key="option.value"
                        :class="[
                            'relative flex cursor-pointer flex-col gap-2 rounded-xl border p-4 transition',
                            font === option.value
                                ? 'border-brand-500 bg-brand-50 ring-3 ring-brand-500/10 dark:bg-brand-500/10'
                                : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700',
                        ]"
                    >
                        <input
                            v-model="font"
                            type="radio"
                            name="font_family"
                            :value="option.value"
                            class="sr-only"
                        />
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            {{ option.label }}
                        </span>
                        <span
                            class="text-xl text-gray-800 dark:text-white/90"
                            :style="{ fontFamily: `var(${option.variable})` }"
                        >
                            أبجد هوز · Aa 123
                        </span>
                        <Check
                            v-if="font === option.value"
                            class="absolute end-3 top-3 size-4 text-brand-500"
                        />
                    </label>
                </div>
                <BaseInputError :message="errors.font" />
            </div>

            <div>
                <BaseLabel for="primary_color">{{
                    t('Primary color')
                }}</BaseLabel>
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        v-for="preset in presetColors"
                        :key="preset"
                        type="button"
                        :title="preset"
                        :style="{ backgroundColor: preset }"
                        :class="[
                            'flex size-9 items-center justify-center rounded-full text-white transition hover:scale-110',
                            {
                                'ring-2 ring-gray-300 ring-offset-2 dark:ring-gray-600 dark:ring-offset-gray-900':
                                    color.toLowerCase() === preset,
                            },
                        ]"
                        @click="color = preset"
                    >
                        <Check
                            v-if="color.toLowerCase() === preset"
                            class="size-4"
                        />
                    </button>

                    <div class="flex items-center gap-2">
                        <input
                            v-model="color"
                            type="color"
                            :title="t('Custom color')"
                            class="size-11 cursor-pointer rounded-lg border border-gray-300 bg-transparent p-1 dark:border-gray-700"
                        />
                        <BaseInput
                            id="primary_color"
                            v-model="color"
                            class="w-32 font-mono uppercase"
                            dir="ltr"
                            maxlength="7"
                            :invalid="Boolean(errors.color)"
                        />
                    </div>
                </div>
                <BaseInputError :message="errors.color" />
            </div>
        </div>
    </BaseCard>
</template>
