<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { onUnmounted, watch } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import AppearanceSettings from '@/components/settings/AppearanceSettings.vue';
import { applyTheme } from '@/composables/useTheme';
import { useTranslations } from '@/composables/useTranslations';
import { update } from '@/routes/admin/settings';
import type { FontOption, Locale } from '@/types';

const props = defineProps<{
    settings: {
        general: {
            site_name: string;
            site_description: string | null;
            contact_email: string | null;
            contact_phone: string | null;
        };
        localization: {
            default_locale: string;
        };
        appearance: {
            font_family: string;
            primary_color: string;
        };
    };
    locales: Locale[];
    fonts: FontOption[];
    canUpdate: boolean;
}>();

const page = usePage();
const { t } = useTranslations();

const form = useForm({
    general: { ...props.settings.general },
    localization: { ...props.settings.localization },
    appearance: { ...props.settings.appearance },
});

// Preview the chosen font and color immediately, and restore the saved
// theme when leaving the page without saving.
watch(
    () => ({ ...form.appearance }),
    (appearance) => {
        const font = props.fonts.find(
            (option) => option.value === appearance.font_family,
        );

        if (font && /^#[0-9a-f]{6}$/i.test(appearance.primary_color)) {
            applyTheme({
                fontVariable: font.variable,
                primaryColor: appearance.primary_color,
            });
        }
    },
);

onUnmounted(() => applyTheme(page.props.theme));

function submit(): void {
    form.submit(update(), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Settings')"
            :breadcrumbs="[{ title: t('Settings') }]"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <fieldset :disabled="!canUpdate" class="space-y-6">
                <BaseCard
                    :title="t('General')"
                    :description="t('Basic information about your store.')"
                >
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <BaseLabel for="site_name" required>
                                {{ t('Site name') }}
                            </BaseLabel>
                            <BaseInput
                                id="site_name"
                                v-model="form.general.site_name"
                                :invalid="
                                    Boolean(form.errors['general.site_name'])
                                "
                            />
                            <BaseInputError
                                :message="form.errors['general.site_name']"
                            />
                        </div>
                        <div>
                            <BaseLabel for="contact_email">
                                {{ t('Contact email') }}
                            </BaseLabel>
                            <BaseInput
                                id="contact_email"
                                v-model="form.general.contact_email"
                                type="email"
                                :invalid="
                                    Boolean(
                                        form.errors['general.contact_email'],
                                    )
                                "
                            />
                            <BaseInputError
                                :message="form.errors['general.contact_email']"
                            />
                        </div>
                        <div>
                            <BaseLabel for="contact_phone">
                                {{ t('Contact phone') }}
                            </BaseLabel>
                            <BaseInput
                                id="contact_phone"
                                v-model="form.general.contact_phone"
                                type="tel"
                                :invalid="
                                    Boolean(
                                        form.errors['general.contact_phone'],
                                    )
                                "
                            />
                            <BaseInputError
                                :message="form.errors['general.contact_phone']"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <BaseLabel for="site_description">
                                {{ t('Site description') }}
                            </BaseLabel>
                            <BaseTextarea
                                id="site_description"
                                v-model="form.general.site_description"
                                :invalid="
                                    Boolean(
                                        form.errors['general.site_description'],
                                    )
                                "
                            />
                            <BaseInputError
                                :message="
                                    form.errors['general.site_description']
                                "
                            />
                        </div>
                    </div>
                </BaseCard>

                <BaseCard
                    :title="t('Language')"
                    :description="
                        t(
                            'The language visitors see before choosing their own.',
                        )
                    "
                >
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <BaseLabel for="default_locale" required>
                                {{ t('Default language') }}
                            </BaseLabel>
                            <BaseSelect
                                id="default_locale"
                                v-model="form.localization.default_locale"
                                :invalid="
                                    Boolean(
                                        form.errors[
                                            'localization.default_locale'
                                        ],
                                    )
                                "
                            >
                                <option
                                    v-for="locale in locales"
                                    :key="locale.code"
                                    :value="locale.code"
                                >
                                    {{ locale.native }}
                                </option>
                            </BaseSelect>
                            <BaseInputError
                                :message="
                                    form.errors['localization.default_locale']
                                "
                            />
                        </div>
                    </div>
                </BaseCard>

                <AppearanceSettings
                    v-model:font="form.appearance.font_family"
                    v-model:color="form.appearance.primary_color"
                    :fonts="fonts"
                    :errors="{
                        font: form.errors['appearance.font_family'],
                        color: form.errors['appearance.primary_color'],
                    }"
                />
            </fieldset>

            <div v-if="canUpdate" class="flex justify-end">
                <BaseButton type="submit" :loading="form.processing">
                    {{ t('Save changes') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
