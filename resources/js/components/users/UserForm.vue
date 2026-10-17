<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import BaseButton from '@/components/base/BaseButton.vue';
import BaseCard from '@/components/base/BaseCard.vue';
import BaseCheckbox from '@/components/base/BaseCheckbox.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import BaseInputError from '@/components/base/BaseInputError.vue';
import BaseLabel from '@/components/base/BaseLabel.vue';
import { useTranslations } from '@/composables/useTranslations';
import { index, store, update } from '@/routes/admin/users';
import type { ManagedUser } from '@/types';

const props = defineProps<{
    user?: ManagedUser;
    roles: string[];
}>();

const { t } = useTranslations();

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    roles: props.user?.roles ?? [],
});

function submit(): void {
    form.submit(props.user ? update(props.user.id) : store(), {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <form class="grid grid-cols-12 gap-6" @submit.prevent="submit">
        <div class="col-span-12 lg:col-span-8">
            <BaseCard
                :title="t('Account details')"
                :description="
                    t('The name, email and password used to sign in.')
                "
            >
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <BaseLabel for="name" required>{{
                            t('Name')
                        }}</BaseLabel>
                        <BaseInput
                            id="name"
                            v-model="form.name"
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
                            autocomplete="email"
                            :invalid="Boolean(form.errors.email)"
                        />
                        <BaseInputError :message="form.errors.email" />
                    </div>
                    <div>
                        <BaseLabel for="password" :required="!user">
                            {{ t('Password') }}
                        </BaseLabel>
                        <BaseInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                user
                                    ? t(
                                          'Leave blank to keep the current password',
                                      )
                                    : ''
                            "
                            :invalid="Boolean(form.errors.password)"
                        />
                        <BaseInputError :message="form.errors.password" />
                    </div>
                    <div>
                        <BaseLabel
                            for="password_confirmation"
                            :required="!user"
                        >
                            {{ t('Confirm password') }}
                        </BaseLabel>
                        <BaseInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                        />
                    </div>
                </div>
            </BaseCard>
        </div>

        <div class="col-span-12 lg:col-span-4">
            <BaseCard
                :title="t('Roles')"
                :description="t('Roles decide what the user can access.')"
            >
                <div class="space-y-3">
                    <BaseCheckbox
                        v-for="role in roles"
                        :key="role"
                        v-model="form.roles"
                        :value="role"
                    >
                        {{ t(role) }}
                    </BaseCheckbox>
                    <p
                        v-if="!roles.length"
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ t('No roles available.') }}
                    </p>
                </div>
                <BaseInputError :message="form.errors.roles" />
            </BaseCard>
        </div>

        <div class="col-span-12 flex justify-end gap-3">
            <BaseButton variant="outline" :href="index()">
                {{ t('Cancel') }}
            </BaseButton>
            <BaseButton type="submit" :loading="form.processing">
                {{ user ? t('Save changes') : t('Create user') }}
            </BaseButton>
        </div>
    </form>
</template>
