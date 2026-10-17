<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import BaseAlert from '@/components/base/BaseAlert.vue';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import PageHeader from '@/components/common/PageHeader.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import { useTranslations } from '@/composables/useTranslations';
import { send } from '@/routes/verification';

defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
}>();

const page = usePage();
const { t } = useTranslations();

const form = useForm({
    name: page.props.auth.user?.name ?? '',
    email: page.props.auth.user?.email ?? '',
});

function submit(): void {
    form.submit(ProfileController.update(), { preserveScroll: true });
}
</script>

<template>
    <div>
        <PageHeader
            :title="t('Profile settings')"
            :breadcrumbs="[{ title: t('Profile settings') }]"
        />

        <div class="space-y-6">
            <BaseCard
                :title="t('Profile')"
                :description="t('Update your name and email address')"
            >
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <BaseLabel for="name" required>{{
                                t('Name')
                            }}</BaseLabel>
                            <BaseInput
                                id="name"
                                v-model="form.name"
                                required
                                autocomplete="name"
                                :invalid="Boolean(form.errors.name)"
                            />
                            <BaseInputError :message="form.errors.name" />
                        </div>
                        <div>
                            <BaseLabel for="email" required>{{
                                t('Email')
                            }}</BaseLabel>
                            <BaseInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="username"
                                :invalid="Boolean(form.errors.email)"
                            />
                            <BaseInputError :message="form.errors.email" />
                        </div>
                    </div>

                    <BaseAlert
                        v-if="
                            mustVerifyEmail &&
                            !page.props.auth.user?.email_verified_at
                        "
                        variant="warning"
                    >
                        {{ t('Your email address is unverified.') }}
                        <Link
                            :href="send()"
                            as="button"
                            class="font-medium text-brand-500 hover:text-brand-600"
                        >
                            {{
                                t(
                                    'Click here to re-send the verification email.',
                                )
                            }}
                        </Link>
                        <p
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 font-medium text-success-600"
                        >
                            {{
                                t(
                                    'A new verification link has been sent to your email address.',
                                )
                            }}
                        </p>
                    </BaseAlert>

                    <div class="flex justify-end">
                        <BaseButton
                            type="submit"
                            :loading="form.processing"
                            data-test="update-profile-button"
                        >
                            {{ t('Save changes') }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <DeleteUser />
        </div>
    </div>
</template>
