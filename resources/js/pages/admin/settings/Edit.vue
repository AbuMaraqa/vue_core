<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseField from '@/components/base/BaseField.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseSelect from '@/components/base/BaseSelect.vue';
import BaseTextarea from '@/components/base/BaseTextarea.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import AppearanceSettings from '@/components/settings/AppearanceSettings.vue';
import InterfaceModeSettings from '@/components/settings/InterfaceModeSettings.vue';
import { applyTheme } from '@/composables/useBrandTheme';
import { useTranslations } from '@/composables/useTranslations';
import { emptyMediaInput } from '@/lib/media';
import { update } from '@/routes/admin/settings';
import type { FontOption, Locale, MediaItem, Resource } from '@/types';

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
            simple_mode: boolean;
        };
    };
    locales: Locale[];
    fonts: FontOption[];
    logo: Resource<MediaItem> | null;
    canUpdate: boolean;
}>();

const page = usePage();
const { t } = useTranslations();

const form = useForm({
    general: { ...props.settings.general },
    localization: { ...props.settings.localization },
    appearance: { ...props.settings.appearance },
    logo: emptyMediaInput(),
});

const logoUploading = ref(false);
const logoFieldKey = ref(0);

const logoError = computed(
    () =>
        Object.entries(form.errors).find(([field]) =>
            field.startsWith('logo'),
        )?.[1],
);

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
    form.submit(update(), {
        preserveScroll: true,
        onSuccess: () => {
            // The saved logo is now the current one, so start the field afresh.
            form.logo = emptyMediaInput();
            logoFieldKey.value++;
        },
    });
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
                    :description="
                        t('Basic information about your organization.')
                    "
                >
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <BaseField
                            :label="t('Site name')"
                            for="site_name"
                            required
                            :error="form.errors['general.site_name']"
                        >
                            <BaseInput
                                id="site_name"
                                v-model="form.general.site_name"
                                :invalid="
                                    Boolean(form.errors['general.site_name'])
                                "
                            />
                        </BaseField>
                        <BaseField
                            :label="t('Contact email')"
                            for="contact_email"
                            :error="form.errors['general.contact_email']"
                        >
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
                        </BaseField>
                        <BaseField
                            :label="t('Contact phone')"
                            for="contact_phone"
                            :error="form.errors['general.contact_phone']"
                        >
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
                        </BaseField>
                        <BaseField
                            class="sm:col-span-2"
                            :label="t('Site description')"
                            for="site_description"
                            :error="form.errors['general.site_description']"
                        >
                            <BaseTextarea
                                id="site_description"
                                v-model="form.general.site_description"
                                :invalid="
                                    Boolean(
                                        form.errors['general.site_description'],
                                    )
                                "
                            />
                        </BaseField>
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
                        <BaseField
                            :label="t('Default language')"
                            for="default_locale"
                            required
                            :error="form.errors['localization.default_locale']"
                        >
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
                        </BaseField>
                    </div>
                </BaseCard>

                <InterfaceModeSettings
                    v-model="form.appearance.simple_mode"
                    :error="form.errors['appearance.simple_mode']"
                />

                <AppearanceSettings
                    v-model:font="form.appearance.font_family"
                    v-model:color="form.appearance.primary_color"
                    :key="logoFieldKey"
                    v-model:logo="form.logo"
                    v-model:logo-uploading="logoUploading"
                    :current-logo="logo?.data ?? null"
                    :disabled="!canUpdate"
                    :fonts="fonts"
                    :errors="{
                        font: form.errors['appearance.font_family'],
                        color: form.errors['appearance.primary_color'],
                        logo: logoError,
                    }"
                />
            </fieldset>

            <div v-if="canUpdate" class="flex justify-end">
                <BaseButton
                    type="submit"
                    :loading="form.processing"
                    :disabled="logoUploading"
                >
                    {{ t('Save changes') }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>
